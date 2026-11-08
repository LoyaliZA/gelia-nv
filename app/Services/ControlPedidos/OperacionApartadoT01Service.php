<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\{CatalogoEstatusPedido, PedidoBma, PedidoBmaCumplimientoEvento, PedidoBmaCumplimientoFisico, PedidoBmaTareaDocumento, PedidoBmaTareaPreparacion};
use App\Models\User;
use App\Support\ControlPedidos\{ApartadoT01, InstruccionEntrega, MaquinaEstadosCumplimientoFisico};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/** Confirmación de cobro externo y entrega, o liberación explícita del apartado impago. */
final class OperacionApartadoT01Service
{
    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $actor, array $datos, bool $entregar): PedidoBmaCumplimientoFisico
    {
        abort_unless($actor->can('control_pedidos.tienda.responder') || $actor->can('control_pedidos.tienda.apartado.separar'), 403);
        $rutas = [];
        try {
            return DB::transaction(function () use ($tarea, $actor, $datos, $entregar, &$rutas) {
                $tarea = InstruccionEntrega::bloquearParaEjecutar($tarea);
                $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($tarea->pedido_bma_id);
                if (! ApartadoT01::aplica($pedido) || ($pedido->solicitud_preparacion_json['cobertura_tienda']['tipo'] ?? null) !== 'apartado') {
                    throw ValidationException::withMessages(['modalidad' => 'Esta operación corresponde a pago al recoger en tienda.']);
                }
                if ((int) $tarea->asignada_a_id !== (int) $actor->id) abort(403, 'Solo el auxiliar asignado puede entregar o liberar este apartado.');
                $apartado = $tarea->cumplimientoFisico()->lockForUpdate()->firstOrFail();
                $destino = $entregar ? PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA : PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL;
                if ($apartado->estado === $destino) return $apartado;
                if ($entregar && ! app(ConfiguracionOperativaTienda::class)->seguimientoEntregaPdvHabilitado()) {
                    throw ValidationException::withMessages(['operacion' => 'El paso de pago, remisión y entrega de Pago al recoger está deshabilitado en Configuración operativa.']);
                }
                if ((int) $apartado->version !== (int) $datos['version']) {
                    throw ValidationException::withMessages(['version' => 'El apartado cambió. Actualice antes de continuar.']);
                }
                if ($apartado->estado !== PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO) {
                    throw ValidationException::withMessages(['estado' => 'La operación requiere un apartado confirmado.']);
                }
                if ($entregar) {
                    if (! $pedido->consultaCerrada() || ($bloqueo = $pedido->motivoBloqueoEntregaTienda())) {
                        throw ValidationException::withMessages(['estado' => $bloqueo ?? 'Falta el cierre vigente de la consulta.']);
                    }
                    if (! filter_var($datos['pago_confirmado'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        throw ValidationException::withMessages(['pago_confirmado' => 'Confirme que PDV recibió el pago antes de entregar.']);
                    }
                    $numero = trim((string) ($datos['numero_remision'] ?? ''));
                    if ($numero === '' || ! ($datos['foto'] ?? null)) {
                        throw ValidationException::withMessages(['entrega' => 'Indique el número de remisión y adjunte la foto de entrega.']);
                    }
                    $estatus = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENTREGADO);
                    if (! $estatus) throw ValidationException::withMessages(['estado' => 'Falta configurar el estado Entregado.']);
                    try {
                        app(\App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService::class)->aplicarReservasPedido($pedido, $actor->id);
                    } catch (\InvalidArgumentException $e) {
                        throw ValidationException::withMessages(['saldo_a_favor' => $e->getMessage()]);
                    }
                    foreach (['foto' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_ENTREGA, 'ticket' => PedidoBmaTareaDocumento::TIPO_REMISION] as $clave => $tipo) {
                        if (! ($archivo = $datos[$clave] ?? null)) continue;
                        $ruta = $archivo->store('pedidos_bma/tareas_preparacion/'.$tarea->id, 'local');
                        $rutas[] = $ruta;
                        $tarea->documentos()->create([
                            'tipo_evidencia' => $tipo, 'ruta_interna' => $ruta,
                            'nombre_original' => $archivo->getClientOriginalName(), 'mime_type' => $archivo->getMimeType(),
                            'tamano_bytes' => $archivo->getSize(), 'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                            'subido_por_id' => $actor->id, 'subido_at' => now(), 'inmutable' => true,
                        ]);
                    }
                    MaquinaEstadosCumplimientoFisico::assertTransicion($apartado->estado, $destino);
                    $apartado->update([
                        'estado' => $destino, 'version' => $apartado->version + 1,
                        'pago_confirmado_at' => $apartado->pago_confirmado_at ?? now(), 'pago_confirmado_por_id' => $actor->id,
                        'condicion_cobro' => 'PAGADO', 'folio_operacion' => $numero,
                        'entregada_at' => now(), 'entregada_por_id' => $actor->id, 'receptor_nombre' => trim($datos['receptor']),
                    ]);
                    $pedido->update(['numero_remision' => $numero, 'catalogo_estatus_pedido_id' => $estatus->id]);
                } else {
                    if ($apartado->pago_confirmado_at || $pedido->pedidoPagadoORemisionado()
                        || $pedido->pagosExhibicion()->where('activo_para_cobertura', true)->exists()
                        || $pedido->safAplicaciones()->where('estado', 'aplicado')->exists()) {
                        throw ValidationException::withMessages(['pago' => 'El pedido tiene pago o remisión. No se libera como apartado impago.']);
                    }
                    MaquinaEstadosCumplimientoFisico::assertTransicion($apartado->estado, PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE);
                    MaquinaEstadosCumplimientoFisico::assertTransicion(PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE, $destino);
                    $cancelado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_CANCELADO);
                    if (! $cancelado) throw ValidationException::withMessages(['estado' => 'Falta configurar el estado final del apartado liberado.']);
                    $saldoAnterior = (float) $pedido->saldo_a_favor;
                    $totalAnterior = (float) $pedido->total_a_cobrar;
                    try {
                        app(\App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService::class)->liberarReservasPendientes($pedido, $actor->id);
                    } catch (\InvalidArgumentException $e) {
                        throw ValidationException::withMessages(['saldo_a_favor' => $e->getMessage()]);
                    }
                    app(CompromisoInventarioTienda::class)->liberarPedido($pedido);
                    $pedido->refresh();
                    $apartado->update(['estado' => $destino, 'devuelta_at' => now(), 'devuelta_por_id' => $actor->id, 'version' => $apartado->version + 1]);
                    $pedido->update([
                        'estatus_envio' => 'apartado_liberado', 'catalogo_estatus_pedido_id' => $cancelado->id,
                        'cancelado_at' => now(), 'cancelado_por_id' => $actor->id,
                        'saldo_a_favor' => 0, 'total_a_cobrar' => round($totalAnterior + $saldoAnterior, 2),
                    ]);
                }
                app(RegistrarEventoCumplimientoFisicoService::class)->ejecutar(
                    $apartado, $entregar ? PedidoBmaCumplimientoEvento::TIPO_ENTREGADA : PedidoBmaCumplimientoEvento::TIPO_DEVUELTA_ANAQUEL,
                    $actor, 't01:'.($entregar ? 'entrega:' : 'liberacion:').$apartado->id,
                    $entregar ? 'Entrega con cobro y remisión realizados en PDV.' : trim($datos['motivo']),
                    ['cobro_externo_pdv' => $entregar, 'numero_remision' => $entregar ? $numero : null]
                );
                app(NotificarPedidoBmaService::class)->ejecutar($pedido->fresh(), $entregar ? 'pedido_entregado_tienda' : 'pedido_apartado_liberado',
                    $entregar ? 'Tienda entregó el pedido con pago confirmado en PDV y remisión '.$numero.'.' : 'Tienda liberó el apartado: '.trim($datos['motivo']), [], $actor->id, true);
                return $apartado->fresh();
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($rutas);
            throw $e;
        }
    }
}

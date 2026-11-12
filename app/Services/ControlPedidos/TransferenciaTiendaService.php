<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use App\Support\ControlPedidos\AlmacenPdv;
use App\Support\ControlPedidos\AtencionMismoDiaTienda;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use App\Support\ControlPedidos\CustodiaDocumentalTienda;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Coordina T-02 con el empaque, traslado y resguardo existentes; no registra cobros. */
final class TransferenciaTiendaService
{
    public function puedeOrigen(PedidoBma $pedido, User $actor): bool
    {
        $json = $pedido->solicitud_preparacion_json ?? [];
        if (($json['area_revision'] ?? null) === 'control_pedidos') {
            return $actor->can('control_pedidos.cedis') && VisibilidadPedidoBma::puedeVerEnCedis($actor, $pedido);
        }
        $tarea = $pedido->instruccionEntregaVigente;

        return $actor->can('control_pedidos.tienda.ver')
            && ($actor->can('control_pedidos.tienda.responder') || $actor->can('control_pedidos.tienda.trasladar'))
            && (int) $tarea?->asignada_a_id === (int) $actor->id;
    }

    public function puedeDestino(PedidoBma $pedido, User $actor): bool
    {
        return $actor->idsSucursalesOperables()->contains((int) $pedido->sucursal_destino_id)
            && ($actor->can('control_pedidos.tienda.responder') || $actor->can('control_pedidos.tienda.trasladar')
                || $actor->can('pdv.resguardos.confirmar_llegada') || $actor->can('pdv.resguardos.confirmar_custodia'));
    }

    public function assertVisible(PedidoBma $pedido, User $actor): void
    {
        abort_unless(VisibilidadPedidoBma::puedeConsultar($actor, $pedido) || $this->puedeOrigen($pedido, $actor)
            || $this->puedeDestino($pedido, $actor)
            || ($actor->can('pdv.resguardos.entregar') && $actor->idsSucursalesOperables()->contains((int) $pedido->sucursal_destino_id)), 403);
    }

    public function resumen(PedidoBma $pedido, User $actor): array
    {
        $this->assertVisible($pedido, $actor);
        $json = $pedido->solicitud_preparacion_json ?? [];
        $operacion = $json['transferencia_tienda'] ?? [];
        $md = AtencionMismoDiaTienda::datos($pedido);
        $credito = CustodiaDocumentalTienda::credito($pedido);
        $resguardo = $this->resguardo($pedido);
        $remision = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->where('activo', true)->oldest()->first();
        $desde = isset($json['remision_tienda']['emitida_at']) ? Carbon::parse($json['remision_tienda']['emitida_at']) : $remision?->created_at;
        $limite = ($md || $credito) ? null : $desde?->copy()->addDays(app(ConfiguracionOperativaTienda::class)->diasRecoleccion($pedido));
        $estado = match ($resguardo?->estado) {
            ResguardoPdv::ESTADO_ENTREGADO => 'entregada',
            ResguardoPdv::ESTADO_EN_CUSTODIA => 'en_custodia',
            ResguardoPdv::ESTADO_RECIBIDO, ResguardoPdv::ESTADO_EN_RECEPCION => 'recibida',
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION => 'en_traslado',
            default => $pedido->empacado_at ? 'preparada' : 'pendiente_preparacion',
        };
        $segundo = $json['segundo_traslado'] ?? [];
        if (($segundo['estado'] ?? '') === 'autorizada' && ! ($segundo['recepcion_confirmada'] ?? false)) {
            $estado = ($segundo['movimiento_estado'] ?? '') === 'en_camino' ? 'en_traslado' : 'pendiente_salida_sucursal';
        }
        $custodiaDestino = app(MovimientosTiendaService::class)->tieneCustodiaDestino((int) $pedido->sucursal_destino_id);
        $derivacion = preg_match('/city[\s-]*center/iu', $pedido->sucursalDestino?->nombre ?? '') ? 'T-03' : null;
        $listo = (! $credito || ($json['credito_wizerp']['resultado'] ?? '') === 'aprobado') && ! $pedido->tieneSinExistenciaAbierta() && $pedido->consultaCerrada() && $pedido->tienePagoValidado() && $pedido->tieneRemision()
            && filled($pedido->numero_remision) && (int) $pedido->sucursal_destino_id > 0
            && ! $pedido->motivoBloqueoEntregaTienda() && ! $pedido->cancelado_at
            && ! $pedido->consulta_actualizacion_pendiente && ! $resguardo?->entrega_bloqueada
            && ($json['retiro_recoleccion']['estado'] ?? '') !== 'pendiente_traslado'
            && ! (($json['segundo_traslado']['estado'] ?? '') === 'autorizada' && ! ($json['segundo_traslado']['recepcion_confirmada'] ?? false));

        return [
            'aplica' => CustodiaDocumentalTienda::aplica($pedido),
            'flujo' => $credito ? 'T-09' : ($md ? 'T-02.1' : 'T-02'),
            'cobertura' => $json['cobertura_tienda']['tipo'] ?? null,
            'credito_wizerp' => $json['credito_wizerp'] ?? null,
            'atencion_md' => $md,
            'solicitud_preparacion' => $pedido->solicitud_preparacion,
            'derivacion' => $derivacion,
            'estado' => $estado,
            'estatus' => $pedido->estatus,
            'estatus_sucursal' => $pedido->estatus_sucursal,
            'empacado_at' => $pedido->empacado_at?->toIso8601String(),
            'version' => (int) ($operacion['version'] ?? 0),
            'consulta_cerrada' => $pedido->consultaCerrada(),
            'pago_validado' => $pedido->tienePagoValidado(),
            'numero_remision' => $pedido->numero_remision,
            'remision_adjunta' => $pedido->tieneRemision(),
            'sucursal_destino_id' => $pedido->sucursal_destino_id,
            'sucursal' => $pedido->sucursalDestino?->nombre,
            'origen' => $pedido->almacen?->nombre ?? (($json['area_revision'] ?? '') === 'control_pedidos' ? 'CEDIS' : $pedido->instruccionEntregaVigente?->almacen?->nombre),
            'requiere_corregir_disponibilidad' => $pedido->consultaCerrada() && $pedido->tieneSinExistenciaAbierta(),
            'requiere_traslado' => $pedido->esTrasladoInternoAPdv(),
            'ubicacion' => $resguardo && $estado === 'en_custodia' ? ($operacion['ubicacion'] ?? ($resguardo->snapshot_json['ubicacion_t02'] ?? null)) : null,
            'observaciones' => $operacion['observaciones'] ?? null,
            'historial' => $operacion['historial'] ?? [],
            'gestiones' => $operacion['gestiones'] ?? [],
            'puede_gestionar' => ! $pedido->tieneSinExistenciaAbierta() && (int) $pedido->vendedor_id === (int) $actor->id && $actor->can('control_pedidos.crear') && $estado !== 'entregada' && ! $pedido->cancelado_at,
            'puede_continuar_posterior' => ! $pedido->tieneSinExistenciaAbierta() && $md !== null && (int) $pedido->vendedor_id === (int) $actor->id
                && $actor->can('control_pedidos.crear') && ! $pedido->cancelado_at && ! $resguardo && in_array($estado, ['pendiente_preparacion', 'preparada'], true),
            'resguardo_id' => $resguardo?->id,
            'bultos' => $pedido->bultosEmpaque()->with('documentos')->get(),
            'plazo_recoleccion_dias' => app(ConfiguracionOperativaTienda::class)->diasRecoleccion($pedido),
            'nota_md' => app(ConfiguracionOperativaTienda::class)->notaMd(),
            'limite_recoleccion' => $limite?->toIso8601String(),
            'pendiente_gestion' => $estado !== 'entregada' && $limite && now()->gte($limite),
            'bloqueo' => $this->accionPendiente($pedido),
            'puede_preparar' => $listo && (! $md || $md['puede_preparar_md']) && $pedido->puedeMarcarEmpacado() && $estado === 'pendiente_preparacion' && $this->puedeOrigen($pedido, $actor)
                && (($json['area_revision'] ?? '') !== 'control_pedidos' || $actor->can('control_pedidos.cedis.empacar')),
            'bloqueo_destino' => ! $custodiaDestino ? 'La sucursal destino necesita un almacén PDV activo de custodia antes de confirmar la salida.' : null,
            'puede_salida' => $custodiaDestino && $listo && (! $md || $md['puede_preparar_md']) && $estado === 'preparada' && $this->puedeOrigen($pedido, $actor)
                && (($json['area_revision'] ?? '') !== 'control_pedidos' || $actor->can('control_pedidos.cedis.enviar')),
            'puede_recibir' => $listo && $estado === 'en_traslado' && $this->puedeDestino($pedido, $actor),
            'puede_custodia' => $listo && $estado === 'recibida' && $this->puedeDestino($pedido, $actor),
            'puede_entregar' => $listo && $estado === 'en_custodia' && $actor->can('pdv.resguardos.entregar')
                && $actor->idsSucursalesOperables()->contains((int) $pedido->sucursal_destino_id),
            'almacenes_destino' => Almacen::query()->where('activo', true)->where('sucursal_id', $pedido->sucursal_destino_id)->get(['id', 'nombre']),
        ];
    }

    /** La vista nombra la etapa pendiente. No cambia el pago ni fuerza el estado. */
    private function accionPendiente(PedidoBma $pedido): ?string
    {
        try {
            $partes = [];
            if ($pedido->tieneSinExistenciaAbierta()) {
                $partes[] = 'Hay piezas sin existencias pendientes. Reabra la consulta para retirarlas o sustituirlas.';
            } elseif ($entrega = $pedido->motivoBloqueoEntregaTienda()) {
                $partes[] = $entrega;
            }
            if (CustodiaDocumentalTienda::aplica($pedido) && ! $pedido->consultaCerrada() && $partes === []) {
                $partes[] = $pedido->motivoBloqueoCierreConsultaTienda()
                    ?: 'Cierre la consulta vigente antes de continuar.';
            }
            if (! CustodiaDocumentalTienda::credito($pedido) && $pedido->pagosExhibicion()->activosParaCobertura()->exists()) {
                $calculo = app(CoberturaPagoPedidoBmaService::class)->calcular($pedido);
                $faltante = collect($calculo['bloqueos'] ?? [])->first(
                    fn ($mensaje) => is_string($mensaje) && str_contains($mensaje, 'Faltan')
                );
                if (is_string($faltante) && $faltante !== '') {
                    $partes[] = $faltante;
                }
            }
            $partes = array_values(array_unique($partes));

            return $partes === [] ? null : implode(' ', $partes);
        } catch (\Throwable) {
            return 'No se pudo comprobar la etapa pendiente. Recargue el seguimiento antes de continuar.';
        }
    }

    public function ejecutar(PedidoBma $pedido, User $actor, string $accion, array $datos): array
    {
        return DB::transaction(function () use ($pedido, $actor, $accion, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $this->assertVisible($pedido, $actor);
            if (! CustodiaDocumentalTienda::aplica($pedido)) {
                throw ValidationException::withMessages(['modalidad' => 'Esta operación corresponde a pedidos de Tienda con transferencia o crédito.']);
            }
            $json = $pedido->solicitud_preparacion_json ?? [];
            $operacion = $json['transferencia_tienda'] ?? [];
            // A repeated submission may return its result, but must still belong to its actor.
            if ($accion !== 'gestionar' && ($operacion['historial'][$accion]['por_id'] ?? null) === $actor->id) {
                return $this->resumen($pedido, $actor);
            }
            if ((int) ($datos['version'] ?? -1) !== (int) ($operacion['version'] ?? 0)) {
                throw ValidationException::withMessages(['version' => 'El pedido cambió. Actualice antes de continuar.']);
            }
            $resumen = $this->resumen($pedido, $actor);
            $clave = ['preparar' => 'puede_preparar', 'salida' => 'puede_salida', 'recibir' => 'puede_recibir', 'custodia' => 'puede_custodia', 'entregar' => 'puede_entregar', 'gestionar' => 'puede_gestionar', 'continuar_posterior' => 'puede_continuar_posterior'][$accion] ?? '';
            if (! ($resumen[$clave] ?? false)) {
                throw ValidationException::withMessages(['operacion' => 'La acción espera la etapa anterior, el pago verificado, la remisión y el responsable autorizado de origen o destino.']);
            }
            AssertPedidoNoBloqueadoFase7::assert($pedido);
            $pedido->assertSinExistenciaAtendida();
            if (in_array($accion, ['preparar', 'salida'], true) && ($json['area_revision'] ?? '') === 'preparacion_tienda') {
                InstruccionEntrega::assertEjecutable($pedido->instruccionEntregaVigente);
            }
            app(ValidarSucursalDestinoPedidoBma::class)->ejecutar($pedido, (int) $pedido->sucursal_destino_id, exigirSiAplica: true);
            if ($accion === 'continuar_posterior') {
                $motivo = trim($datos['observaciones'] ?? '');
                if ($motivo === '') {
                    throw ValidationException::withMessages(['observaciones' => 'Registre el acuerdo con el cliente para continuar con recolección posterior.']);
                }
                $json['tienda_md_continuacion'] = ['destino' => 'T-02', 'acuerdo' => $motivo, 'por_id' => $actor->id, 'por' => $actor->name,
                    'fecha' => now()->toIso8601String(), 'reporte_original' => $json['tienda_md_reporte'] ?? null];
                $json['atencion_tienda'] = 'no';
                $json['caso_tienda'] = ($json['area_revision'] ?? '') === 'control_pedidos' ? 'consultar_cedis' : 'revisar_apartar';
                $pedido->update(['solicitud_preparacion_json' => $json, 'prioridad_md' => false]);
            } elseif ($accion === 'gestionar') {
                if (trim($datos['observaciones'] ?? '') === '') {
                    throw ValidationException::withMessages(['observaciones' => 'Describa el seguimiento con el cliente.']);
                }
                $operacion['gestiones'][] = ['nota' => trim($datos['observaciones']), 'por_id' => $actor->id, 'por' => $actor->name, 'fecha' => now()->toIso8601String()];
            } elseif ($accion === 'preparar') {
                app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($pedido, $actor->id, [$pedido->id => $datos['bultos']]);
                $this->reflejarPreparacion($pedido, $actor);
                $operacion['observaciones'] = trim($datos['observaciones'] ?? '');
                $operacion['almacen_origen_id'] = $pedido->almacen_id;
                $operacion['area_origen'] = $json['area_revision'] ?? null;
            } elseif ($accion === 'salida') {
                // Local custody still needs an explicit handoff; it is never a customer delivery.
                app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido, $actor->id);
            } elseif ($accion === 'recibir') {
                $resguardo = $this->resguardo($pedido, true);
                if (empty($datos['bolsas_verificadas']) || ! $resguardo || $pedido->bultosEmpaque()->count() !== (int) $resguardo->cantidad_bultos_esperada) {
                    throw ValidationException::withMessages(['bolsas' => 'Verifique todas las bolsas identificadas antes de recibir.']);
                }
                $anterior = $resguardo->estado;
                foreach ($pedido->bultosEmpaque as $bulto) {
                    $resguardo->bultos()->firstOrCreate(['folio' => 'BOLSA-'.$pedido->id.'-'.$bulto->numero], [
                        'pedido_bma_id' => $pedido->id, 'tipo' => ResguardoPdvBulto::TIPO_BOLSA,
                        'codigo_etiqueta' => GeneradorCodigoEtiquetaResguardoPdv::generar(),
                        'piezas' => 0, 'condicion' => 'bueno', 'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE,
                        'recepcion_at' => now(), 'recepcion_por_id' => $actor->id, 'version' => 1,
                    ]);
                }
                $resguardo->update(['estado' => ResguardoPdv::ESTADO_RECIBIDO, 'recepcion_fisica_at' => now(), 'version' => $resguardo->version + 1]);
                $this->evento($resguardo, $actor, ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA, $anterior, 'recibir');
                $pedido->update(['estatus_sucursal' => EstatusSucursalPedidoBma::RECIBIDO]);
                $operacion['recepcion_observaciones'] = trim($datos['observaciones'] ?? '');
            } elseif ($accion === 'custodia') {
                $almacen = Almacen::query()->where('activo', true)->where('sucursal_id', $pedido->sucursal_destino_id)->find($datos['almacen_id']);
                if (! $almacen || ! AlmacenPdv::esAlmacen($almacen)) {
                    throw ValidationException::withMessages(['almacen' => 'Elija un almacén PDV activo de la sucursal destino.']);
                }
                $resguardo = $this->resguardo($pedido, true);
                $anterior = $resguardo->estado;
                $ubicacion = trim($datos['ubicacion']);
                if ($ubicacion === '') {
                    throw ValidationException::withMessages(['ubicacion' => 'Identifique la ubicación de custodia.']);
                }
                $snapshot = array_merge($resguardo->snapshot_json ?? [], ['ubicacion_t02' => $ubicacion, 'almacen_origen_id' => $pedido->almacen_id]);
                $resguardo->bultos()->update(['estado' => ResguardoPdvBulto::ESTADO_EN_CUSTODIA, 'custodia_at' => now(), 'custodia_por_id' => $actor->id]);
                $resguardo->update(['estado' => ResguardoPdv::ESTADO_EN_CUSTODIA, 'almacen_id' => $almacen->id,
                    'custodia_confirmada_at' => now(), 'snapshot_json' => $snapshot, 'version' => $resguardo->version + 1]);
                $this->evento($resguardo, $actor, ResguardoPdvEvento::TIPO_CUSTODIA_COMPLETA, $anterior, 'custodia');
                $this->reflejarPreparacion($pedido, $actor, $resguardo);
                $pedido->update(['estatus_sucursal' => EstatusSucursalPedidoBma::EN_CUSTODIA]);
                $operacion['ubicacion'] = $ubicacion;
                $operacion['almacen_custodia_id'] = $almacen->id;
            } else {
                $resguardo = $this->resguardo($pedido, true);
                $entregado = app(RegistrarEntregaResguardoPdvService::class)->ejecutar($resguardo, $actor, $resguardo->version,
                    'entrega-t02:'.$resguardo->id, ResguardoPdvEntrega::RELACION_TITULAR, trim($datos['receptor']),
                    RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FOTOGRAFIA_T02, $datos['foto'],
                    'Entrega en tienda con fotografía.');
                if (! app(InformarEntregaResguardoPdvService::class)->ejecutar($entregado, $entregado->entregas()->latest('id')->firstOrFail(), $actor->id)) {
                    throw ValidationException::withMessages(['entrega' => 'No se pudo reflejar la entrega. No se confirmó la operación.']);
                }
                app(CompromisoInventarioTienda::class)->entregarPedido($pedido);
                $pedido->update(['estatus_sucursal' => null]);
                $operacion['receptor'] = trim($datos['receptor']);
            }
            // Nested services may have persisted inventory/financial metadata. Preserve it.
            $pedido->refresh();
            $json = $pedido->solicitud_preparacion_json ?? [];
            if ($accion !== 'gestionar') {
                $operacion['historial'][$accion] = ['por_id' => $actor->id, 'por' => $actor->name, 'fecha' => now()->toIso8601String()];
            }
            $operacion['version'] = (int) ($operacion['version'] ?? 0) + 1;
            $json['transferencia_tienda'] = $operacion;
            $pedido->update(['solicitud_preparacion_json' => $json]);
            if ($accion !== 'gestionar') {
                app(NotificarPedidoBmaService::class)->ejecutar($pedido, 'pedido_transferencia_tienda',
                    match ($accion) {
                        'preparar' => 'Bolsas preparadas. Falta confirmar su salida o entrega interna a custodia.',
                        'salida' => 'Mercancía enviada a la sucursal de recolección. Falta verificar la recepción.',
                        'recibir' => 'Bolsas verificadas en la sucursal. Falta identificar su custodia.',
                        'custodia' => 'Pedido en custodia identificada, listo para que el cliente lo recoja.',
                        'continuar_posterior' => 'Ventas confirmó la recolección posterior y el resguardo en tienda. Se conservan los pagos, la remisión y el reporte original de TIENDA MD.',
                        default => 'Pedido entregado al cliente desde custodia, con fotografía.',
                    }, [], $actor->id, true);
            }

            return $this->resumen($pedido->fresh(), $actor);
        });
    }

    private function resguardo(PedidoBma $pedido, bool $bloquear = false): ?ResguardoPdv
    {
        return ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->where('sucursal_id', $pedido->sucursal_destino_id)
            ->when($bloquear, fn ($q) => $q->lockForUpdate())->first();
    }

    private function reflejarPreparacion(PedidoBma $pedido, User $actor, ?ResguardoPdv $resguardo = null): void
    {
        $tarea = $pedido->instruccionEntregaVigente;
        if (! $tarea) {
            return;
        }
        $cumplimiento = app(AsegurarCumplimientoFisicoService::class)->ejecutar($tarea);
        $cumplimiento = $cumplimiento->newQuery()->lockForUpdate()->findOrFail($cumplimiento->id);
        if ($resguardo && $cumplimiento->estado === 'POR_SEPARAR') {
            $this->reflejarPreparacion($pedido, $actor);
            $cumplimiento->refresh();
        }
        $destino = $resguardo ? 'LISTA_PARA_SALIDA' : 'SEPARADA';
        if ($cumplimiento->estado === 'APARTADO_CONFIRMADO') {
            $destino = 'APARTADO_CONFIRMADO';
        }
        if ($cumplimiento->estado !== $destino) {
            MaquinaEstadosCumplimientoFisico::assertTransicion($cumplimiento->estado, $destino);
            $cumplimiento->update(['estado' => $destino, 'version' => $cumplimiento->version + 1]);
            app(RegistrarEventoCumplimientoFisicoService::class)->ejecutar($cumplimiento,
                $resguardo ? PedidoBmaCumplimientoEvento::TIPO_SALIDA_AUTORIZADA : PedidoBmaCumplimientoEvento::TIPO_PIEZA_SEPARADA,
                $actor, 't02:'.$tarea->id.':'.$destino, $resguardo ? 'Pedido recibido en custodia identificada; falta entrega al cliente.' : 'Conjunto preparado en bolsas conforme a la consulta cerrada.');
        }
        $cumplimiento->update(['resguardo_pdv_id' => $resguardo?->id ?? $cumplimiento->resguardo_pdv_id,
            'pago_confirmado_at' => $pedido->pago_validado_at, 'pago_confirmado_por_id' => $pedido->pago_validado_por_id,
            'condicion_cobro' => 'PAGADO']);
    }

    private function evento(ResguardoPdv $resguardo, User $actor, string $tipo, string $anterior, string $accion): void
    {
        $resguardo->eventos()->create(['tipo_evento' => $tipo, 'estado_anterior' => $anterior, 'estado_nuevo' => $resguardo->estado,
            'actor_id' => $actor->id, 'ocurrido_at' => now(), 'snapshot_json' => ['proceso' => 'T-02', 'ubicacion' => $resguardo->snapshot_json['ubicacion_t02'] ?? null],
            'idempotency_key' => 't02:'.$resguardo->id.':'.$accion]);
    }
}

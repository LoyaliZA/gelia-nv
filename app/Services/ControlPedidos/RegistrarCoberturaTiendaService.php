<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\CambioCoberturaTienda;
use App\Support\ControlPedidos\RegistrarPedidos\PoliticaAccionesRegistrarPedidos;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Elige cobertura e intención de Mismo día antes del compromiso logístico. No confirma pago ni entrega. */
final class RegistrarCoberturaTiendaService
{
    public function __construct(
        private PoliticaAccionesRegistrarPedidos $politica,
        private PreparacionTiendaConfig $config,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $usuario, string $cobertura, bool $mismoDia, ?string $autorizacionId = null, string $motivoResolucion = ''): PedidoBma
    {
        if ($autorizacionId === null && ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien registró el pedido elige la cobertura.',
            ]);
        }

        $codigo = PoliticaAccionesRegistrarPedidos::codigoDeCobertura($cobertura);
        if ($codigo === null) {
            throw ValidationException::withMessages([
                'cobertura' => 'Elija pago al recoger, transferencia o crédito.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario, $cobertura, $mismoDia, $codigo, $autorizacionId, $motivoResolucion) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            if ($autorizacionId === null) {
                abort_unless(VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido), 403);
            }
            $anterior = $json['cobertura_tienda'] ?? [];
            $cambio = ! empty($anterior['tipo']) && ($anterior['tipo'] !== $cobertura || (($json['atencion_tienda'] ?? '') === 'prevista') !== $mismoDia);
            if ($autorizacionId !== null) {
                $solicitud = $json['cambio_cobertura_tienda'] ?? [];
                abort_unless($usuario->can(CambioCoberturaTienda::PERMISO) && VisibilidadPedidoBma::puedeConsultar($usuario, $pedido), 403);
                if (! $cambio || ($solicitud['id'] ?? '') !== $autorizacionId || ($solicitud['estado'] ?? '') !== 'pendiente'
                    || (int) ($solicitud['solicitada_por_id'] ?? 0) === (int) $usuario->id
                    || ($solicitud['cobertura'] ?? '') !== $cobertura || (bool) ($solicitud['mismo_dia'] ?? false) !== $mismoDia
                    || mb_strlen(trim($motivoResolucion)) < 5) {
                    throw ValidationException::withMessages(['cobertura' => 'La solicitud no admite esta autorización.']);
                }
                if (! hash_equals($solicitud['version_pedido'], CambioCoberturaTienda::version($pedido))) {
                    throw ValidationException::withMessages(['cobertura' => 'El pedido cambió después de la solicitud. Rechácela y solicite una nueva revisión.']);
                }
                if ($bloqueo = CambioCoberturaTienda::bloqueo($pedido)) {
                    throw ValidationException::withMessages(['cobertura' => $bloqueo]);
                }
                $solicitud['estado'] = 'aplicada';
                $solicitud['resuelta_por_id'] = $usuario->id;
                $solicitud['resuelta_por'] = $usuario->name;
                $solicitud['resuelta_at'] = now()->toIso8601String();
                $solicitud['motivo_resolucion'] = trim($motivoResolucion);
                $json['cambio_cobertura_tienda'] = $solicitud;
                $json['historial_cambios_cobertura'][] = $solicitud;
            } elseif ($cambio) {
                throw ValidationException::withMessages(['cobertura' => 'La modalidad ya está guardada. Solicite un cambio con motivo y autorización.']);
            } elseif (! empty($anterior['tipo'])) {
                // Guardar lo mismo no altera confirmación, vigencia, autor ni instrucciones.
                return $pedido->fresh(['estatus']);
            }
            if ($mismoDia && $cobertura !== 'transferencia') {
                throw ValidationException::withMessages(['cobertura' => 'Mismo día corresponde a transferencia.']);
            }
            if (! $pedido->consultaCerrada()) {
                throw ValidationException::withMessages([
                    'cobertura' => 'La cobertura espera el cierre vigente de la consulta.',
                ]);
            }
            if ($pedido->esConsultaDisponibilidadTienda()) {
                $bloqueo = $pedido->motivoBloqueoCierreConsultaTienda();
                if ($bloqueo !== null) {
                    throw ValidationException::withMessages(['cobertura' => $bloqueo]);
                }
            }
            $estado = $json['revision_disponibilidad']['estado'] ?? null;
            if (! in_array($estado, ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'], true)) {
                throw ValidationException::withMessages([
                    'cobertura' => 'La cobertura espera el cierre vigente de la consulta.',
                ]);
            }
            if (filled($pedido->numero_remision) || $pedido->tieneRemision()) {
                throw ValidationException::withMessages([
                    'cobertura' => 'Ya hay remisión. La cobertura no se edita de forma informal.',
                ]);
            }
            $eraMd = ($json['atencion_tienda'] ?? '') === 'prevista';
            if ($eraMd !== $mismoDia && ($pedido->pago_validado_at || $pedido->pagosExhibicion()->activosParaCobertura()->exists())) {
                throw ValidationException::withMessages(['atencion_tienda' => 'Ya hay un reporte de pago. Registre el acuerdo de continuación; no cambie la intención ni reinicie el corte desde la cobertura.']);
            }

            $atencion = 'no';
            if ($mismoDia) {
                $zona = $this->config->zonaHoraria();
                $local = now($zona);
                $evaluada = $this->politica->evaluarAtencion([
                    'cobertura' => $cobertura,
                    'atencion' => 'prevista',
                    'facultad' => $this->config->facultadMismoDia($usuario, $pedido->departamento_operacion_id),
                    'sabado' => ! app(ConfiguracionOperativaTienda::class)->diaMd($local),
                ]);
                if ($evaluada['motivo']) {
                    throw ValidationException::withMessages(['atencion_tienda' => $evaluada['motivo']]);
                }
                $atencion = 'prevista';
            }

            $json['codigo_modalidad'] = $codigo;
            $json['atencion_tienda'] = $mismoDia ? $atencion : 'no';
            if ($mismoDia && ! isset($json['tienda_md_solicitud'])) {
                $json['tienda_md_solicitud'] = ['fecha' => now($this->config->zonaHoraria())->toDateString(), 'registrada_at' => now()->toIso8601String(), 'por_id' => $usuario->id];
            }
            $vigenciaAnterior = $anterior;
            if ($cobertura === 'apartado' && ($anterior['tipo'] ?? '') !== 'apartado') {
                foreach ($json['historial_cambios_cobertura'] ?? [] as $registro) {
                    if (($registro['anterior']['tipo'] ?? '') === 'apartado') { $vigenciaAnterior = $registro['anterior']; break; }
                }
            }
            $registradaAt = ($anterior['tipo'] ?? '') === $cobertura ? ($anterior['registrada_at'] ?? now()->toIso8601String()) : now()->toIso8601String();
            $json['cobertura_tienda'] = [
                'tipo' => $cobertura,
                'confirmada' => false,
                'registrada_at' => $registradaAt,
                'registrada_por_id' => $usuario->id,
            ];
            if (($anterior['tipo'] ?? '') === $cobertura) {
                foreach (['confirmacion_enviada_at', 'confirmacion_enviada_por_id'] as $clave) {
                    if (isset($anterior[$clave])) $json['cobertura_tienda'][$clave] = $anterior[$clave];
                }
            }
            if (($json['area_revision'] ?? null) === 'control_pedidos' && $cobertura === 'apartado') {
                $json['origen_apartado'] = 'consulta_cedis_documentada';
            }
            $pedido->solicitud_preparacion_json = $json;
            if ($cobertura === 'apartado' && \App\Support\ControlPedidos\ApartadoT01::aplica($pedido)) {
                $evaluacion = (new \App\Support\ControlPedidos\PlazoApartadoTienda($this->config))->evaluarConfirmacion(
                    now(), isset($vigenciaAnterior['vence_at']) && ($vigenciaAnterior['tipo'] ?? '') === 'apartado' ? \Carbon\Carbon::parse($vigenciaAnterior['vence_at']) : null
                );
                $json['cobertura_tienda']['requiere_confirmacion_pedido'] = true;
                $json['cobertura_tienda']['vence_at'] = $evaluacion['vence_at']?->toIso8601String();
                if (! $evaluacion['permite']) $json['cobertura_tienda']['motivo_vigencia'] = $evaluacion['mensaje'];
                $tarea = InstruccionEntrega::abiertas((int) $pedido->id)->first();
                if ($tarea) {
                    $tarea->update(['fecha_limite' => $evaluacion['vence_at']]);
                    $tarea->cumplimientoFisico()->update(['vence_at' => $evaluacion['vence_at']]);
                }
            }
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'prioridad_md' => $mismoDia && ($json['tienda_md_reporte']['oportuno'] ?? false),
            ]);

            $abierta = InstruccionEntrega::abiertas((int) $pedido->id)->first();
            if ($abierta && ! in_array($abierta->estado, [
                PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
                PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
            ], true)) {
                $modalidad = CatalogoModalidadPreparacionPedido::query()->where('codigo', $codigo)->where('activo', true)->first();
                if ($modalidad) {
                    $cambiosTarea = ['catalogo_modalidad_preparacion_id' => $modalidad->id];
                    if ($autorizacionId !== null) {
                        $cambiosTarea['version'] = (int) $abierta->version + 1;
                        $cambiosTarea['requiere_traslado_cedis'] = $modalidad->requiereTrasladoCedisPorDefecto();
                        $cambiosTarea['fecha_limite'] = $cobertura === 'apartado' ? ($json['cobertura_tienda']['vence_at'] ?? null) : null;
                        $fisico = $abierta->cumplimientoFisico;
                        $fisico?->update([
                            'version' => (int) $fisico->version + 1,
                            'vence_at' => $cambiosTarea['fecha_limite'],
                        ]);
                    }
                    $abierta->update($cambiosTarea);
                }
            }

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            if ($estatusId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    $autorizacionId !== null
                        ? 'Cambio de modalidad de pago autorizado y aplicado: '.($anterior['tipo'] ?? '').' → '.$cobertura.'. Motivo: '.$motivoResolucion.'. Se requiere confirmar el nuevo recorrido.'
                        : ($mismoDia
                        ? 'Cobertura registrada con intención de Mismo día. No confirma pago, preparación ni entrega.'
                        : 'Cobertura registrada. No confirma pago, preparación ni entrega.'),
                    AccionesHistorialPedidoBma::CORRECCION
                );
            }

            if ($autorizacionId !== null) {
                app(NotificarPedidoBmaService::class)->ejecutar($pedido, 'pedido_cambio_cobertura_aplicado', 'Cambio de modalidad de pago autorizado. Revise y confirme el nuevo recorrido antes de continuar.', ['control_pedidos.tienda.responder', CambioCoberturaTienda::PERMISO], $usuario->id, true);
            }

            return $pedido->fresh(['estatus']);
        });
    }
}

<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
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

    public function ejecutar(PedidoBma $pedido, User $usuario, string $cobertura, bool $mismoDia): PedidoBma
    {
        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
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

        return DB::transaction(function () use ($pedido, $usuario, $cobertura, $mismoDia, $codigo) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
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
                    'sabado' => $local->isSaturday() || $local->isSunday(),
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
            $anterior = $json['cobertura_tienda'] ?? [];
            $registradaAt = ($anterior['tipo'] ?? '') === $cobertura ? ($anterior['registrada_at'] ?? now()->toIso8601String()) : now()->toIso8601String();
            $json['cobertura_tienda'] = [
                'tipo' => $cobertura,
                'confirmada' => false,
                'registrada_at' => $registradaAt,
                'registrada_por_id' => $usuario->id,
            ];
            if (($json['area_revision'] ?? null) === 'control_pedidos' && $cobertura === 'apartado') {
                $json['origen_apartado'] = 'consulta_cedis_documentada';
            }
            $pedido->solicitud_preparacion_json = $json;
            if ($cobertura === 'apartado' && \App\Support\ControlPedidos\ApartadoT01::aplica($pedido)) {
                $evaluacion = (new \App\Support\ControlPedidos\PlazoApartadoTienda($this->config))->evaluarConfirmacion(
                    now(), isset($anterior['vence_at']) && ($anterior['tipo'] ?? '') === 'apartado' ? \Carbon\Carbon::parse($anterior['vence_at']) : null
                );
                if (! $evaluacion['permite']) throw ValidationException::withMessages(['cobertura' => $evaluacion['mensaje']]);
                $json['cobertura_tienda']['vence_at'] = $evaluacion['vence_at']->toIso8601String();
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
                    $abierta->update(['catalogo_modalidad_preparacion_id' => $modalidad->id]);
                }
            }

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            if ($estatusId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    $mismoDia
                        ? 'Cobertura registrada con intención de Mismo día. No confirma pago, preparación ni entrega.'
                        : 'Cobertura registrada. No confirma pago, preparación ni entrega.',
                    AccionesHistorialPedidoBma::CORRECCION
                );
            }

            return $pedido->fresh(['estatus']);
        });
    }
}

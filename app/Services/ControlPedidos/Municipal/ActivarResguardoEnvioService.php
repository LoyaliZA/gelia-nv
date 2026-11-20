<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivarResguardoEnvioService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages(['pedido' => 'No puede modificar el resguardo de este envío.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            RevisionCapturaPedido::comprobar($pedido, $datos);
            if (! ConsultaMunicipal::es($pedido)) {
                throw ValidationException::withMessages(['modalidad' => 'El resguardo de envío aplica a pedidos municipales.']);
            }
            if ($pedido->cancelado_at || ! PoliticaResguardoEnvio::puedeReconducir($pedido)) {
                throw ValidationException::withMessages(['resguardo' => 'Este envío ya no admite una intención de resguardo.']);
            }

            $activo = filter_var($datos['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (! empty(PoliticaResguardoEnvio::datos($pedido)['liberado_at'])) {
                throw ValidationException::withMessages(['resguardo' => PoliticaResguardoEnvio::MENSAJE_REACTIVAR]);
            }
            $fase = $pedido->estatus?->fase_ciclo;
            if (! $activo && $fase && ! $pedido->esEditablePorVendedora()) {
                throw ValidationException::withMessages([
                    'resguardo' => 'El resguardo ya está en revisión. Solo lo libera quien audita, no quien registró la venta.',
                ]);
            }

            if ($activo) {
                if (PoliticaResguardoEnvio::fuenteCreditoBloquea($pedido)) {
                    throw ValidationException::withMessages(['resguardo' => PoliticaResguardoEnvio::MENSAJE_CREDITO]);
                }
                $motivo = $datos['motivo'] ?? null;
                if (! in_array($motivo, PoliticaResguardoEnvio::MOTIVOS, true)) {
                    throw ValidationException::withMessages(['motivo' => 'Indique el motivo de la espera.']);
                }
                if ($motivo === PoliticaResguardoEnvio::MOTIVO_FECHA && empty($datos['fecha_solicitada'])) {
                    throw ValidationException::withMessages(['fecha_solicitada' => 'Indique la fecha solicitada por el cliente.']);
                }
            }

            $motivo = $activo ? ($datos['motivo'] ?? null) : null;
            // Una acumulación ya cerrada no se reabre al guardar de nuevo la instrucción.
            $cerrada = ! empty(PoliticaResguardoEnvio::datos($pedido)['acumulacion_cerrada_at']);
            $acumula = $activo && $motivo === PoliticaResguardoEnvio::MOTIVO_ACUMULACION;
            $json = PoliticaResguardoEnvio::fusionarJson($pedido, [
                'activo' => $activo,
                'motivo' => $motivo,
                'fecha_solicitada' => $activo && $motivo === PoliticaResguardoEnvio::MOTIVO_FECHA
                    ? $datos['fecha_solicitada']
                    : null,
                'acumulacion' => $cerrada ? 'cerrada' : ($acumula ? 'abierta' : null),
                'acumulacion_abierta' => $acumula && ! $cerrada,
                'causa_flag' => $activo ? PoliticaResguardoEnvio::CAUSA_MUNICIPAL : (PoliticaResguardoEnvio::datos($pedido)['causa_flag'] ?? null),
                'solicitado_at' => $activo ? now()->toIso8601String() : (PoliticaResguardoEnvio::datos($pedido)['solicitado_at'] ?? null),
                'solicitado_por_id' => $activo ? $usuario->id : (PoliticaResguardoEnvio::datos($pedido)['solicitado_por_id'] ?? null),
                'activado_at' => $activo ? now()->toIso8601String() : (PoliticaResguardoEnvio::datos($pedido)['activado_at'] ?? null),
                'activado_por_id' => $activo ? $usuario->id : (PoliticaResguardoEnvio::datos($pedido)['activado_por_id'] ?? null),
            ]);
            if (! $activo) {
                $json['resguardo_envio']['causa_flag'] = null;
            }

            $pedido->fill(['solicitud_preparacion_json' => $json]);
            $pedido->es_resguardo = PoliticaResguardoEnvio::aplicarFlagCompartido($pedido, $activo);
            $pedido->save();

            $this->historial->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                $activo
                    ? 'Intención de resguardo de envío registrada. No despacha hasta la liberación de la persona auxiliar.'
                    : 'Se retiró la intención de resguardo de envío.',
                AccionesHistorialPedidoBma::RESGUARDO
            );

            return $pedido->fresh();
        });
    }
}

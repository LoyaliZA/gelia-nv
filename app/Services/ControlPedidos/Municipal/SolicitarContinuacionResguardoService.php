<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitarContinuacionResguardoService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages(['pedido' => 'No puede solicitar la continuación de este envío.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            RevisionCapturaPedido::comprobar($pedido, $datos);
            if (! PoliticaResguardoEnvio::intencionActiva($pedido)) {
                throw ValidationException::withMessages(['resguardo' => 'Este envío no está en resguardo municipal.']);
            }
            $caps = PoliticaResguardoEnvio::evaluar($pedido);
            if (! ($caps['capacidades']['puede_solicitar_continuacion'] ?? false)) {
                $motivo = PoliticaResguardoEnvio::motivo($pedido) === PoliticaResguardoEnvio::MOTIVO_FECHA
                    ? PoliticaResguardoEnvio::MENSAJE_M09
                    : (PoliticaResguardoEnvio::m10EnAcumulacion($pedido)
                        ? PoliticaResguardoEnvio::MENSAJE_ACUMULACION
                        : PoliticaResguardoEnvio::MENSAJE_CONJUNTO);
                throw ValidationException::withMessages(['resguardo' => $motivo]);
            }

            $json = PoliticaResguardoEnvio::fusionarJson($pedido, [
                'continuacion_solicitada_at' => now()->toIso8601String(),
                'continuacion_solicitada_por_id' => $usuario->id,
                'continuacion_huella' => PoliticaResguardoEnvio::huellaConjunto($pedido),
                'continuacion_revision' => (int) $pedido->revision_registro,
            ]);
            $pedido->update(['solicitud_preparacion_json' => $json]);

            $this->historial->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                'Se solicitó continuar el envío resguardado. No se libera ni se despacha en automático.',
                AccionesHistorialPedidoBma::CONTINUACION_RESGUARDO
            );

            return $pedido->fresh();
        });
    }
}

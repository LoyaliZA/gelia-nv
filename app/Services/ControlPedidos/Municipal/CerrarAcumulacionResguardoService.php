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

class CerrarAcumulacionResguardoService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos = []): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages(['pedido' => 'No puede cerrar la acumulación de este envío.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            RevisionCapturaPedido::comprobar($pedido, $datos);
            if (! PoliticaResguardoEnvio::intencionActiva($pedido)
                || PoliticaResguardoEnvio::motivo($pedido) !== PoliticaResguardoEnvio::MOTIVO_ACUMULACION) {
                throw ValidationException::withMessages(['resguardo' => 'Este envío no está acumulando mercancía.']);
            }
            if (! (PoliticaResguardoEnvio::datos($pedido)['acumulacion_abierta'] ?? false)) {
                // Cierre repetido: idempotente, sin nuevo historial ni cambios.
                return $pedido->fresh();
            }
            try {
                PoliticaResguardoEnvio::assertIntegracionPermitida($pedido);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['conjunto' => $e->getMessage()]);
            }

            $json = PoliticaResguardoEnvio::fusionarJson($pedido, [
                'acumulacion' => 'cerrada',
                'acumulacion_abierta' => false,
                'acumulacion_cerrada_at' => now()->toIso8601String(),
                'acumulacion_cerrada_por_id' => $usuario->id,
            ]);
            $pedido->update(['solicitud_preparacion_json' => $json]);
            // El conjunto cambió: la continuación solicitada antes ya no es válida.
            if (PoliticaResguardoEnvio::datos($pedido)['continuacion_solicitada_at'] ?? null) {
                $pedido->update(['solicitud_preparacion_json' => PoliticaResguardoEnvio::invalidarContinuacion($pedido)]);
            }

            $this->historial->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                'Se cerró la acumulación. Las cajas se indican cuando se conozcan; no se registra cero.',
                AccionesHistorialPedidoBma::CERRAR_ACUMULACION_RESGUARDO
            );

            return $pedido->fresh();
        });
    }
}

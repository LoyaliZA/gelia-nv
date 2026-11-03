<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saca de la espera de recolección un pedido pagado en custodia para incorporarlo a un envío.
 * No confirma la recepción en CEDIS ni vuelve a cobrar la mercancía.
 */
final class RetirarCustodiaParaEnvioService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $custodia, PedidoBma $principal, User $usuario): PedidoBma
    {
        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $custodia)
            && ! $usuario->can('control_pedidos.preparacion.solicitar')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para retirar esta custodia de la recolección.',
            ]);
        }

        return DB::transaction(function () use ($custodia, $principal, $usuario) {
            $primero = $custodia->id < $principal->id ? $custodia->id : $principal->id;
            $segundo = $custodia->id < $principal->id ? $principal->id : $custodia->id;
            $bloqueados = PedidoBma::query()->whereIn('id', [$primero, $segundo])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $custodia = $bloqueados->get($custodia->id);
            $principal = $bloqueados->get($principal->id);
            if (! $custodia || ! $principal) {
                throw ValidationException::withMessages(['pedido' => 'No se encontraron los pedidos.']);
            }

            if ((int) $custodia->cliente_id !== (int) $principal->cliente_id) {
                throw ValidationException::withMessages(['cliente' => 'El complemento y el envío deben ser del mismo cliente.']);
            }
            if ($principal->esComplemento()) {
                throw ValidationException::withMessages(['principal' => 'Elija el pedido de envío principal, no otro complemento.']);
            }
            if (! $custodia->tieneRemision() && ! filled($custodia->numero_remision)) {
                throw ValidationException::withMessages(['remision' => 'Solo se retira de recolección un pedido con remisión.']);
            }
            if ($custodia->pago_validado_at === null) {
                throw ValidationException::withMessages(['pago' => 'La mercancía en custodia debe tener la cobertura verificada. No se cobra otra vez.']);
            }
            if (! in_array($custodia->estatus_sucursal, [
                EstatusSucursalPedidoBma::EN_CUSTODIA,
                EstatusSucursalPedidoBma::CUSTODIA_PARCIAL,
                EstatusSucursalPedidoBma::RECIBIDO,
            ], true)) {
                throw ValidationException::withMessages([
                    'ubicacion' => 'El pedido no está en custodia de sucursal. La salida del origen no se da por hecha.',
                ]);
            }

            $json = is_array($custodia->solicitud_preparacion_json) ? $custodia->solicitud_preparacion_json : [];
            if (($json['retiro_recoleccion']['estado'] ?? null) === 'pendiente_traslado' && (int) $custodia->pedido_principal_id === (int) $principal->id) {
                return $custodia->fresh(['principal']);
            }

            $json['retiro_recoleccion'] = [
                'estado' => 'pendiente_traslado',
                'pedido_principal_id' => $principal->id,
                'integrado_cedis' => false,
                'registrado_at' => now()->toIso8601String(),
                'registrado_por_id' => $usuario->id,
            ];
            $custodia->update([
                'pedido_principal_id' => $principal->id,
                'solicitud_preparacion_json' => $json,
            ]);

            ResguardoPdv::query()
                ->where('pedido_bma_id', $custodia->id)
                ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
                ->update(['entrega_bloqueada' => true]);

            $estatusId = $custodia->catalogo_estatus_pedido_id;
            if ($estatusId) {
                $this->historial->ejecutar(
                    $custodia->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    "Retirado de la espera de recolección para incorporarlo a {$principal->folio}. Falta el traslado PDV a CEDIS y su recepción. No se cobra de nuevo la mercancía cubierta.",
                    AccionesHistorialPedidoBma::COMPLEMENTO
                );
            }

            return $custodia->fresh(['principal', 'estatus']);
        });
    }
}

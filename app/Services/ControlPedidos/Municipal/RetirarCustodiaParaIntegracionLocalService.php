<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\ExclusividadRemisionEnvio;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Validation\ValidationException;

/**
 * Retira de custodia PDV para integrar en el mismo sucursal. No crea traslado a CEDIS.
 */
class RetirarCustodiaParaIntegracionLocalService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $custodia, PedidoBma $principal, User $usuario): PedidoBma
    {
        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $custodia)
            && ! $usuario->can('control_pedidos.preparacion.solicitar')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para retirar esta custodia hacia el envío local.',
            ]);
        }

        return ExclusividadRemisionEnvio::conLock($custodia, $principal, function ($bloqueados) use ($custodia, $principal, $usuario) {
            $custodia = $bloqueados->get($custodia->id);
            $principal = $bloqueados->get($principal->id);
            if (! $custodia || ! $principal) {
                throw ValidationException::withMessages(['pedido' => 'No se encontraron los pedidos.']);
            }
            try {
                PoliticaResguardoEnvio::assertConjuntoEditable($principal);
                PoliticaResguardoEnvio::assertUbicacionIntegracionLocal($custodia, $principal);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['ubicacion' => $e->getMessage()]);
            }
            try {
                ExclusividadRemisionEnvio::assertDisponible($custodia, $principal);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['remision' => $e->getMessage()]);
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
                    'ubicacion' => 'El pedido no está en custodia de sucursal.',
                ]);
            }

            $json = is_array($custodia->solicitud_preparacion_json) ? $custodia->solicitud_preparacion_json : [];
            $local = is_array($json['integracion_local'] ?? null) ? $json['integracion_local'] : [];
            if (($local['estado'] ?? null) === 'pendiente' && (int) ($local['pedido_principal_id'] ?? 0) === (int) $principal->id) {
                return $custodia->fresh(['principal']);
            }
            if (in_array($json['retiro_recoleccion']['estado'] ?? null, ['pendiente_traslado', 'en_traslado', 'recibido_cedis'], true)) {
                throw ValidationException::withMessages([
                    'retiro' => 'Esta remisión ya tiene un traslado hacia CEDIS. No se integra en sucursal.',
                ]);
            }

            $json['integracion_local'] = [
                'estado' => 'pendiente',
                'pedido_principal_id' => $principal->id,
                'registrado_at' => now()->toIso8601String(),
                'registrado_por_id' => $usuario->id,
            ];
            unset($json['retiro_recoleccion']);
            $huellaAntes = PoliticaResguardoEnvio::datos($principal)['continuacion_solicitada_at'] ?? null;
            $custodia->update([
                'pedido_principal_id' => $principal->id,
                'solicitud_preparacion_json' => $json,
            ]);
            // Se recuerda el bloqueo previo para restaurar la custodia exacta si el vínculo se deshace.
            $previo = ResguardoPdv::query()
                ->where('pedido_bma_id', $custodia->id)
                ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
                ->pluck('entrega_bloqueada', 'id')
                ->map(fn ($v) => (bool) $v)->all();
            ResguardoPdv::query()
                ->where('pedido_bma_id', $custodia->id)
                ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
                ->update(['entrega_bloqueada' => true]);
            $json = is_array($custodia->fresh()->solicitud_preparacion_json) ? $custodia->fresh()->solicitud_preparacion_json : [];
            $json['integracion_local']['bloqueo_previo'] = $previo;
            $custodia->update(['solicitud_preparacion_json' => $json]);

            if ($huellaAntes) {
                $principal->update(['solicitud_preparacion_json' => PoliticaResguardoEnvio::invalidarContinuacion($principal)]);
            }

            if ($custodia->catalogo_estatus_pedido_id) {
                $this->historial->ejecutar(
                    $custodia->id,
                    $usuario->id,
                    $custodia->catalogo_estatus_pedido_id,
                    $custodia->catalogo_estatus_pedido_id,
                    "Retirado de custodia para integrarlo en sucursal al envío {$principal->folio}. Sin traslado a CEDIS. No se cobra de nuevo.",
                    AccionesHistorialPedidoBma::COMPLEMENTO
                );
            }

            return $custodia->fresh(['principal', 'estatus']);
        });
    }
}

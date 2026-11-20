<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ExclusividadRemisionEnvio;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use Illuminate\Validation\ValidationException;

/**
 * Confirmación física de la integración local en la sucursal. Solo la hace quien opera esa sucursal:
 * Ventas y auditoría no confirman hechos físicos.
 */
class IntegrarCustodiaLocalMunicipalService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $custodia, User $usuario, ?PedidoBma $principalUrl = null): PedidoBma
    {
        $json0 = is_array($custodia->solicitud_preparacion_json) ? $custodia->solicitud_preparacion_json : [];
        $principalId = (int) ($json0['integracion_local']['pedido_principal_id'] ?? $custodia->pedido_principal_id);
        if ($principalId < 1) {
            throw ValidationException::withMessages(['integracion' => 'Retire primero la custodia para integración en sucursal.']);
        }
        if ($principalUrl && (int) $principalUrl->id !== $principalId) {
            throw ValidationException::withMessages(['integracion' => 'Esa remisión no pertenece a este envío.']);
        }
        if (! PoliticaResguardoEnvio::puedeConfirmarIntegracionFisica($usuario, $custodia, PoliticaResguardoEnvio::MECANISMO_LOCAL)) {
            throw ValidationException::withMessages(['permiso' => 'Solo el personal de la sucursal donde está la custodia confirma la integración.']);
        }
        $principalModelo = PedidoBma::query()->findOrFail($principalId);

        return ExclusividadRemisionEnvio::conLockEnvio($principalModelo, $custodia, function ($bloqueados) use ($custodia, $principalModelo, $usuario) {
            $custodia = $bloqueados->get($custodia->id);
            $principal = $bloqueados->get($principalModelo->id);
            $json = is_array($custodia->solicitud_preparacion_json) ? $custodia->solicitud_preparacion_json : [];
            $local = is_array($json['integracion_local'] ?? null) ? $json['integracion_local'] : [];
            if (($local['estado'] ?? null) === 'integrado') {
                return $custodia->fresh();
            }
            if (($local['estado'] ?? null) !== 'pendiente' || (int) ($local['pedido_principal_id'] ?? 0) !== (int) $principal->id) {
                throw ValidationException::withMessages(['integracion' => 'Retire primero la custodia para integración en sucursal.']);
            }
            if ((int) $custodia->cliente_id !== (int) $principal->cliente_id) {
                throw ValidationException::withMessages(['cliente' => 'La remisión no pertenece al cliente del envío.']);
            }
            try {
                PoliticaResguardoEnvio::assertIntegracionPermitida($principal);
                PoliticaResguardoEnvio::assertUbicacionIntegracionLocal($custodia, $principal);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['ubicacion' => $e->getMessage()]);
            }
            if (in_array($json['retiro_recoleccion']['estado'] ?? null, ['pendiente_traslado', 'en_traslado', 'recibido_cedis'], true)) {
                throw ValidationException::withMessages([
                    'retiro' => 'Esta remisión tiene un traslado activo a CEDIS. No se confirma como integración local.',
                ]);
            }
            if (PoliticaResguardoEnvio::incidenciaPagoMiembro($custodia)) {
                throw ValidationException::withMessages(['pago' => PoliticaResguardoEnvio::MENSAJE_INCIDENCIA_PAGO]);
            }

            $json['integracion_local'] = array_merge($local, [
                'estado' => 'integrado',
                'integrado_at' => now()->toIso8601String(),
                'integrado_por_id' => $usuario->id,
            ]);
            $custodia->update(['solicitud_preparacion_json' => $json]);

            $principalJson = is_array($principal->solicitud_preparacion_json) ? $principal->solicitud_preparacion_json : [];
            $principalJson['complemento_pdv'] = [
                'pedido_id' => $custodia->id,
                'estado' => 'integrado_local',
                'integrado' => true,
                'integrado_at' => now()->toIso8601String(),
                'mecanismo' => PoliticaResguardoEnvio::MECANISMO_LOCAL,
            ];
            $principal->update(['solicitud_preparacion_json' => $principalJson]);
            if (PoliticaResguardoEnvio::datos($principal->fresh())['continuacion_solicitada_at'] ?? null) {
                $principal->update(['solicitud_preparacion_json' => PoliticaResguardoEnvio::invalidarContinuacion($principal->fresh())]);
            }

            if ($custodia->catalogo_estatus_pedido_id) {
                $this->historial->ejecutar(
                    $custodia->id,
                    $usuario->id,
                    $custodia->catalogo_estatus_pedido_id,
                    $custodia->catalogo_estatus_pedido_id,
                    'Integración local confirmada. La mercancía queda en el envío de sucursal; no hay traspaso a CEDIS.',
                    AccionesHistorialPedidoBma::INTEGRACION_LOCAL_MUNICIPAL
                );
            }

            return $custodia->fresh();
        });
    }
}

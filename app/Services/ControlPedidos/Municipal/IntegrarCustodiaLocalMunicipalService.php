<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IntegrarCustodiaLocalMunicipalService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $custodia, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear')
            && ! $usuario->can('control_pedidos.preparacion.solicitar')
            && ! $usuario->can('control_pedidos.auditar')) {
            throw ValidationException::withMessages(['permiso' => 'No tiene permiso para confirmar la integración local.']);
        }

        return DB::transaction(function () use ($custodia, $usuario) {
            $custodia = PedidoBma::query()->lockForUpdate()->findOrFail($custodia->id);
            $json = is_array($custodia->solicitud_preparacion_json) ? $custodia->solicitud_preparacion_json : [];
            $local = is_array($json['integracion_local'] ?? null) ? $json['integracion_local'] : [];
            if (($local['estado'] ?? null) === 'integrado') {
                return $custodia->fresh();
            }
            if (($local['estado'] ?? null) !== 'pendiente') {
                throw ValidationException::withMessages([
                    'integracion' => 'Retire primero la custodia para integración en sucursal.',
                ]);
            }
            if (in_array($json['retiro_recoleccion']['estado'] ?? null, ['pendiente_traslado', 'en_traslado'], true)) {
                throw ValidationException::withMessages([
                    'retiro' => 'Esta remisión está en traslado a CEDIS. No se confirma como integración local.',
                ]);
            }

            $json['integracion_local'] = array_merge($local, [
                'estado' => 'integrado',
                'integrado_at' => now()->toIso8601String(),
                'integrado_por_id' => $usuario->id,
            ]);
            $custodia->update(['solicitud_preparacion_json' => $json]);

            $principalId = (int) ($local['pedido_principal_id'] ?? $custodia->pedido_principal_id);
            $principal = $principalId > 0 ? PedidoBma::query()->lockForUpdate()->find($principalId) : null;
            if ($principal) {
                $principalJson = is_array($principal->solicitud_preparacion_json) ? $principal->solicitud_preparacion_json : [];
                $principalJson['complemento_pdv'] = [
                    'pedido_id' => $custodia->id,
                    'estado' => 'integrado_local',
                    'integrado' => true,
                    'integrado_at' => now()->toIso8601String(),
                    'mecanismo' => PoliticaResguardoEnvio::MECANISMO_LOCAL,
                ];
                $principal->update(['solicitud_preparacion_json' => $principalJson]);
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

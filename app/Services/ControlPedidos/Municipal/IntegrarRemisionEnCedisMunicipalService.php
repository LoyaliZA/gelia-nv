<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaEnvioResguardo;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ExclusividadRemisionEnvio;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use Illuminate\Validation\ValidationException;

/**
 * Identificación y confirmación física, dentro de CEDIS, de una remisión que ya estaba resguardada allí.
 * No duplica mercancía ni remisiones: solo registra que el bulto se identificó y quedó integrado al envío.
 */
class IntegrarRemisionEnCedisMunicipalService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $envio, PedidoBma $origen, User $usuario): PedidoBma
    {
        if (! PoliticaResguardoEnvio::puedeConfirmarIntegracionFisica($usuario, $origen, PoliticaResguardoEnvio::MECANISMO_RESGUARDO)) {
            throw ValidationException::withMessages(['permiso' => 'Solo el área CEDIS confirma la integración de la mercancía.']);
        }

        return ExclusividadRemisionEnvio::conLockEnvio($envio, $origen, function ($bloqueados) use ($envio, $origen, $usuario) {
            $envio = $bloqueados->get($envio->id);
            $origen = $bloqueados->get($origen->id);
            $vinculada = PedidoBmaEnvioResguardo::query()
                ->where('pedido_envio_id', $envio->id)
                ->where('pedido_resguardo_id', $origen->id)
                ->exists();
            if (! $vinculada) {
                throw ValidationException::withMessages(['remision' => 'Esa remisión no está anexada a este envío.']);
            }
            if ((int) $origen->cliente_id !== (int) $envio->cliente_id) {
                throw ValidationException::withMessages(['cliente' => 'La remisión no pertenece al cliente del envío.']);
            }
            PoliticaResguardoEnvio::assertConjuntoMutable($envio);
            $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
            if (($json['integracion_envio']['estado'] ?? null) === 'integrado') {
                return $envio->fresh(['resguardosAnexados.resguardo', 'complementos']);
            }
            $json['integracion_envio'] = [
                'estado' => 'integrado',
                'pedido_envio_id' => $envio->id,
                'integrado_at' => now()->toIso8601String(),
                'integrado_por_id' => $usuario->id,
            ];
            $origen->update(['solicitud_preparacion_json' => $json]);
            if (PoliticaResguardoEnvio::datos($envio)['continuacion_solicitada_at'] ?? null) {
                $envio->update(['solicitud_preparacion_json' => PoliticaResguardoEnvio::invalidarContinuacion($envio)]);
            }
            if ($origen->catalogo_estatus_pedido_id) {
                $this->historial->ejecutar(
                    $origen->id,
                    $usuario->id,
                    $origen->catalogo_estatus_pedido_id,
                    $origen->catalogo_estatus_pedido_id,
                    "Integración en CEDIS confirmada para el envío {$envio->folio}. No se duplica mercancía ni remisión.",
                    AccionesHistorialPedidoBma::INTEGRACION_LOCAL_MUNICIPAL
                );
            }

            return $envio->fresh(['resguardosAnexados.resguardo', 'complementos']);
        });
    }
}

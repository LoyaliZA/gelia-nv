<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\ExclusividadRemisionEnvio;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** El vínculo no es la recepción ni la integración. */
class CustodiaComplementoNacional
{
    public function vincular(PedidoBma $envio, PedidoBma $resguardo, int $usuarioId): PedidoBma
    {
        return DB::transaction(function () use ($envio, $resguardo, $usuarioId) {
            $envio = PedidoBma::query()->lockForUpdate()->findOrFail($envio->id);
            $resguardo = PedidoBma::query()->lockForUpdate()->findOrFail($resguardo->id);
            if (! CapturaNacional::es($envio) || ! CapturaNacional::es($resguardo)) {
                throw new InvalidArgumentException('La custodia nacional solo vincula envíos de la captura actual.');
            }
            $envio->loadMissing('estatus');
            if (in_array($envio->estatus?->fase_ciclo, [
                \App\Models\ControlPedidos\CatalogoEstatusPedido::FASE_ENVIADO,
                \App\Models\ControlPedidos\CatalogoEstatusPedido::FASE_ENTREGADO,
                \App\Models\ControlPedidos\CatalogoEstatusPedido::FASE_EN_RUTA,
            ], true)) {
                throw new InvalidArgumentException('No se vincula custodia después de la salida.');
            }
            $actual = $this->vinculo($resguardo);
            if (($actual['estado'] ?? null) !== null && (int) ($resguardo->pedido_principal_id ?? 0) === (int) $envio->id) {
                return $resguardo;
            }

            ExclusividadRemisionEnvio::assertDisponible($resguardo, $envio);
            $resguardo->update([
                'pedido_principal_id' => $envio->id,
                'solicitud_preparacion_json' => $this->marcar($resguardo, [
                    'estado' => 'vinculado',
                    'integrado' => false,
                    'pedido_envio_id' => $envio->id,
                    'usuario_id' => $usuarioId,
                    'vinculado_at' => now()->toIso8601String(),
                    'salida_at' => null,
                    'recibido_at' => null,
                ]),
            ]);

            return $resguardo->fresh();
        });
    }

    public function registrarSalidaOrigen(PedidoBma $resguardo, int $usuarioId): PedidoBma
    {
        return $this->avanzar($resguardo, 'vinculado', 'en_traslado', $usuarioId, 'salida_at');
    }

    public function confirmarRecepcionCedis(PedidoBma $resguardo, int $usuarioId): PedidoBma
    {
        return $this->avanzar($resguardo, 'en_traslado', 'recibido_cedis', $usuarioId, 'recibido_at');
    }

    public function liberar(PedidoBma $resguardo, int $usuarioId): PedidoBma
    {
        return DB::transaction(function () use ($resguardo, $usuarioId) {
            $resguardo = PedidoBma::query()->lockForUpdate()->findOrFail($resguardo->id);
            $vinculo = $this->vinculo($resguardo);
            if (($vinculo['estado'] ?? null) !== 'recibido_cedis') {
                throw new InvalidArgumentException('La liberación espera la recepción física en CEDIS.');
            }
            $vinculo['integrado'] = true;
            $vinculo['liberado_at'] = now()->toIso8601String();
            $vinculo['liberado_por_id'] = $usuarioId;
            $resguardo->update([
                'solicitud_preparacion_json' => $this->marcar($resguardo, $vinculo),
            ]);

            return $resguardo->fresh();
        });
    }

    private function avanzar(PedidoBma $resguardo, string $desde, string $hacia, int $usuarioId, string $marca): PedidoBma
    {
        return DB::transaction(function () use ($resguardo, $desde, $hacia, $usuarioId, $marca) {
            $resguardo = PedidoBma::query()->lockForUpdate()->findOrFail($resguardo->id);
            $vinculo = $this->vinculo($resguardo);
            if (($vinculo['estado'] ?? null) === $hacia) {
                return $resguardo;
            }
            if (($vinculo['estado'] ?? null) !== $desde) {
                throw new InvalidArgumentException('La custodia no está en el paso anterior.');
            }
            $vinculo['estado'] = $hacia;
            $vinculo['integrado'] = false;
            $vinculo[$marca] = now()->toIso8601String();
            $vinculo['usuario_id'] = $usuarioId;
            $resguardo->update([
                'solicitud_preparacion_json' => $this->marcar($resguardo, $vinculo),
            ]);

            return $resguardo->fresh();
        });
    }

    /** @return array<string, mixed> */
    private function vinculo(PedidoBma $pedido): array
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];

        return is_array($json['vinculo_resguardo'] ?? null) ? $json['vinculo_resguardo'] : [];
    }

    /** @param  array<string, mixed>  $vinculo
     * @return array<string, mixed>
     */
    private function marcar(PedidoBma $pedido, array $vinculo): array
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $json['vinculo_resguardo'] = $vinculo;

        return $json;
    }
}

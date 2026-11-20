<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\User;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** CEDIS responde las piezas del complemento. No escribe en el pedido principal. */
class ResponderPiezasComplementoNacional
{
    /** @param  array<string, mixed>  $datos */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $usuario, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (! CapturaNacional::es($pedido)) {
                throw new InvalidArgumentException('Esta respuesta solo aplica a un envío nacional de la captura actual.');
            }
            $solicitudId = (string) ($datos['solicitud_id'] ?? '');
            if ($solicitudId !== '' && (string) ($pedido->solicitud_preparacion_json['respuesta_complemento_nacional']['solicitud_id'] ?? '') === $solicitudId) {
                return $pedido->fresh();
            }

            RevisionCapturaPedido::comprobar($pedido, $datos);
            $hijos = [];
            foreach ((array) ($pedido->solicitud_preparacion_json['complementos_nacionales'] ?? []) as $item) {
                if (is_array($item) && isset($item['pedido_id'])) {
                    $hijos[] = (int) $item['pedido_id'];
                }
            }
            if ($hijos === []) {
                throw new InvalidArgumentException('Este pedido no tiene un complemento nacional.');
            }

            $respondidas = 0;
            foreach ($datos['piezas'] ?? [] as $pieza) {
                if (! is_array($pieza)) {
                    continue;
                }
                $revision = PedidoBmaRevisionProducto::query()
                    ->whereKey((int) ($pieza['revision_id'] ?? 0))
                    ->lockForUpdate()
                    ->first();
                if (! $revision || ! in_array((int) $revision->pedido_bma_id, $hijos, true)) {
                    throw new InvalidArgumentException('La pieza no pertenece a este complemento.');
                }
                $revision->update([
                    'cantidad_encontrada' => (int) ($pieza['cantidad_encontrada'] ?? 0),
                    'estado_fisico' => (string) ($pieza['estado_fisico'] ?? PedidoBmaRevisionProducto::ESTADO_BUENO),
                ]);
                $respondidas++;
            }
            if ($respondidas === 0) {
                throw new InvalidArgumentException('Indique las piezas del complemento que responde.');
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json['respuesta_complemento_nacional'] = [
                'solicitud_id' => $solicitudId,
                'usuario_id' => $usuario->id,
                'at' => now()->toIso8601String(),
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);
            $pedido->increment('revision_registro');

            return $pedido->fresh();
        });
    }
}

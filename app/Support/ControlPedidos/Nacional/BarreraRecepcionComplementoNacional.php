<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use RuntimeException;

/** Empaque, guía y salida esperan la recepción física. El vínculo no la sustituye. */
final class BarreraRecepcionComplementoNacional
{
    public static function assert(PedidoBma $pedido): void
    {
        $ids = array_values(array_unique(array_filter([
            (int) $pedido->id,
            (int) ($pedido->pedido_principal_id ?? 0),
        ])));
        $relacionados = PedidoBma::query()
            ->where(function ($query) use ($ids) {
                $query->whereIn('id', $ids)->orWhereIn('pedido_principal_id', $ids);
            })
            ->get();

        foreach ($relacionados as $item) {
            $vinculo = $item->solicitud_preparacion_json['vinculo_resguardo'] ?? null;
            if (! is_array($vinculo) || ! isset($vinculo['estado'])) {
                continue;
            }
            if ($vinculo['estado'] !== 'recibido_cedis' || ($vinculo['integrado'] ?? false) !== true) {
                throw new RuntimeException('La recepción sin integración confirmada no habilita empaque, guía ni salida.');
            }
        }
    }
}

<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

final class EjecutarSolicitudAjusteTienda
{
    public function ejecutar(PedidoBma $pedido, ?string $clave, callable $operacion, ?string $lote = null): PedidoBma
    {
        if (! $clave) {
            return $operacion();
        }

        return DB::transaction(function () use ($pedido, $clave, $operacion, $lote) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $pedido->solicitud_preparacion_json ?? [];
            $lotes = array_map('strval', (array) ($json['solicitudes_ajuste_lotes'][$clave] ?? []));
            $repetido = isset($json['solicitudes_ajuste_ids'][$clave]) && ($lote === null || $lote === '' || in_array($lote, $lotes, true));
            if ($repetido) {
                return PedidoBma::findOrFail($json['solicitudes_ajuste_ids'][$clave]);
            }
            $resultado = $operacion();
            $pedido->refresh();
            $json = $pedido->solicitud_preparacion_json ?? [];
            $json['solicitudes_ajuste_ids'][$clave] = $resultado->id;
            if ($lote !== null && $lote !== '') {
                $lotes[] = $lote;
                $json['solicitudes_ajuste_lotes'][$clave] = array_values(array_unique($lotes));
            }
            $pedido->update(['solicitud_preparacion_json' => $json]);

            return $resultado->fresh();
        });
    }
}

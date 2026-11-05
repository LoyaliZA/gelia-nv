<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

final class EjecutarSolicitudAjusteTienda
{
    public function ejecutar(PedidoBma $pedido, ?string $clave, callable $operacion): PedidoBma
    {
        if (! $clave) {
            return $operacion();
        }

        return DB::transaction(function () use ($pedido, $clave, $operacion) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $pedido->solicitud_preparacion_json ?? [];
            if (isset($json['solicitudes_ajuste_ids'][$clave])) {
                return PedidoBma::findOrFail($json['solicitudes_ajuste_ids'][$clave]);
            }
            $resultado = $operacion();
            $pedido->refresh();
            $json = $pedido->solicitud_preparacion_json ?? [];
            $json['solicitudes_ajuste_ids'][$clave] = $resultado->id;
            $pedido->update(['solicitud_preparacion_json' => $json]);

            return $resultado->fresh();
        });
    }
}

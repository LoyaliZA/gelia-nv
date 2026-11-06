<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\AlmacenPdv;
use Illuminate\Validation\ValidationException;

/** Resuelve la ruta documental al confirmar; no registra traslado ni recepción. */
final class ResolverAlmacenesConsultaCedisService
{
    public function ejecutar(PedidoBma $pedido): void
    {
        $json = $pedido->solicitud_preparacion_json ?? [];
        if (($json['modalidad_entrega'] ?? null) !== 'tienda'
            || ($json['area_revision'] ?? null) !== 'control_pedidos') {
            return;
        }

        // Conservar la procedencia explícita de pedidos anteriores.
        if (! $pedido->almacen_id) {
            $cedis = Almacen::query()->where('activo', true)->where('permite_salida_pedidos', true)
                ->whereHas('tipoAlmacen', fn ($q) => $q->where('nombre', 'CEDIS'))->get();
            if ($cedis->count() !== 1) {
                throw ValidationException::withMessages(['almacen_id' => 'Configure un único almacén CEDIS activo con salida de pedidos para resolver el origen.']);
            }
            $pedido->almacen_id = $cedis->sole()->id;
        }

        // La sucursal define el almacén previsto; la custodia física se confirma después.
        if ($pedido->sucursal_destino_id) {
            $destinos = Almacen::query()->with('tipoAlmacen')->where('activo', true)
                ->where('sucursal_id', $pedido->sucursal_destino_id)->get()
                ->filter(fn ($almacen) => AlmacenPdv::esAlmacen($almacen));
            if ($destinos->count() !== 1) {
                throw ValidationException::withMessages(['sucursal_destino_id' => 'La sucursal destino debe tener un único almacén PDV activo para asignar el destino del pedido.']);
            }
            $json['ruta_almacenes_tienda'] = [
                'almacen_origen_id' => (int) $pedido->almacen_id,
                'sucursal_destino_id' => (int) $pedido->sucursal_destino_id,
                'almacen_destino_id' => (int) $destinos->sole()->id,
            ];
            $pedido->solicitud_preparacion_json = $json;
        }
        $pedido->save();
    }
}

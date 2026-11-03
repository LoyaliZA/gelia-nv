<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\Inventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apartado de inventario existente: disponible = existencia − apartado.
 * No crea existencias ni un saldo.
 */
final class CompromisoInventarioTienda
{
    public function comprometer(PedidoBma $pedido, int $almacenId, int $productoId, string $cantidad, string $clave, string $sku = ''): void
    {
        $cantidad = $this->cantidad($cantidad);
        if (bccomp($cantidad, '0', 3) <= 0 || $almacenId < 1 || $productoId < 1) {
            return;
        }

        DB::transaction(function () use ($pedido, $almacenId, $productoId, $cantidad, $clave, $sku) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $this->json($pedido);
            $fila = is_array($json['compromisos_inventario'][$clave] ?? null)
                ? $json['compromisos_inventario'][$clave]
                : null;
            if (($fila['activo'] ?? false) === true) {
                return;
            }

            $inventario = Inventario::query()
                ->where('almacen_id', $almacenId)
                ->where('producto_id', $productoId)
                ->lockForUpdate()
                ->first();
            if (! $inventario) {
                throw ValidationException::withMessages([
                    'inventario' => 'No hay existencia registrada de esa pieza en el almacén. No se confirma disponibilidad.',
                ]);
            }

            $disponible = bcsub((string) $inventario->existencia, (string) $inventario->apartado, 3);
            if (bccomp($disponible, $cantidad, 3) < 0) {
                throw ValidationException::withMessages([
                    'inventario' => 'La pieza sustituta o comprometida no tiene disponibilidad confirmada.',
                ]);
            }

            $inventario->apartado = bcadd((string) $inventario->apartado, $cantidad, 3);
            $inventario->save();

            $json['compromisos_inventario'][$clave] = [
                'producto_id' => $productoId,
                'almacen_id' => $almacenId,
                'cantidad' => $cantidad,
                'sku' => $sku,
                'activo' => true,
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);
        });
    }

    public function liberarClave(PedidoBma $pedido, string $clave): void
    {
        DB::transaction(function () use ($pedido, $clave) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $this->json($pedido);
            $fila = is_array($json['compromisos_inventario'][$clave] ?? null)
                ? $json['compromisos_inventario'][$clave]
                : null;
            if (($fila['activo'] ?? false) !== true) {
                return;
            }

            $this->restarApartado($fila);
            $json['compromisos_inventario'][$clave]['activo'] = false;
            $json['compromisos_inventario'][$clave]['liberado_at'] = now()->toIso8601String();
            $pedido->update(['solicitud_preparacion_json' => $json]);
        });
    }

    public function liberarPedido(PedidoBma $pedido): void
    {
        $pedido = $pedido->fresh() ?: $pedido;
        $json = $this->json($pedido);
        foreach (array_keys(is_array($json['compromisos_inventario'] ?? null) ? $json['compromisos_inventario'] : []) as $clave) {
            $this->liberarClave($pedido, (string) $clave);
        }
    }

    public function entregarPedido(PedidoBma $pedido): void
    {
        DB::transaction(function () use ($pedido) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $this->json($pedido);
            $compromisos = is_array($json['compromisos_inventario'] ?? null) ? $json['compromisos_inventario'] : [];
            foreach ($compromisos as $clave => $fila) {
                if (! is_array($fila) || ($fila['activo'] ?? false) !== true) {
                    continue;
                }
                $inventario = Inventario::query()
                    ->where('almacen_id', (int) ($fila['almacen_id'] ?? 0))
                    ->where('producto_id', (int) ($fila['producto_id'] ?? 0))
                    ->lockForUpdate()
                    ->first();
                if ($inventario) {
                    $cantidad = $this->cantidad((string) ($fila['cantidad'] ?? '0'));
                    $existencia = bcsub((string) $inventario->existencia, $cantidad, 3);
                    $apartado = bcsub((string) $inventario->apartado, $cantidad, 3);
                    $inventario->existencia = bccomp($existencia, '0', 3) < 0 ? '0.000' : $existencia;
                    $inventario->apartado = bccomp($apartado, '0', 3) < 0 ? '0.000' : $apartado;
                    $inventario->save();
                }
                $compromisos[$clave]['activo'] = false;
                $compromisos[$clave]['entregado_at'] = now()->toIso8601String();
            }
            $json['compromisos_inventario'] = $compromisos;
            $pedido->update(['solicitud_preparacion_json' => $json]);
        });
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function restarApartado(array $fila): void
    {
        $inventario = Inventario::query()
            ->where('almacen_id', (int) ($fila['almacen_id'] ?? 0))
            ->where('producto_id', (int) ($fila['producto_id'] ?? 0))
            ->lockForUpdate()
            ->first();
        if (! $inventario) {
            return;
        }

        $apartado = bcsub((string) $inventario->apartado, $this->cantidad((string) ($fila['cantidad'] ?? '0')), 3);
        $inventario->apartado = bccomp($apartado, '0', 3) < 0 ? '0.000' : $apartado;
        $inventario->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function json(PedidoBma $pedido): array
    {
        return is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
    }

    private function cantidad(string $cantidad): string
    {
        if (! is_numeric($cantidad)) {
            return '0.000';
        }

        return bcadd($cantidad, '0', 3);
    }
}

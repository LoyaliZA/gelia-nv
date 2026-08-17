<?php

namespace App\Support\SaldosAFavor;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aislamiento de créditos SAF: canal, sucursal/almacén y departamento.
 * Un crédito generado en un pedido no se usa en ese mismo pedido.
 *
 * ponytail: sello por nombre (sucursal/departamento), no FK. Si renombran el
 * almacén dejan de coincidir créditos viejos; upgrade: almacen_id en saf_creditos.
 */
final class AlcanceSaf
{
    /**
     * @return array{canal_origen:string, sucursal:?string, departamento:?string, excluir_pedido_bma_id:int}
     */
    public static function desdePedido(PedidoBma $pedido): array
    {
        $pedido->loadMissing(['almacen.sucursal', 'vendedor.departamento']);

        return [
            'canal_origen' => 'bellaroma',
            'sucursal' => self::nombreSucursalAlmacen($pedido->almacen),
            'departamento' => self::nombreDepartamento($pedido->vendedor),
            'excluir_pedido_bma_id' => (int) $pedido->id,
        ];
    }

    /**
     * Sellos al generar un crédito desde un pedido (sin exclusión).
     *
     * @return array{canal_origen:string, sucursal:?string, departamento:?string}
     */
    public static function sellosDesdePedido(PedidoBma $pedido): array
    {
        $alcance = self::desdePedido($pedido);
        unset($alcance['excluir_pedido_bma_id']);

        return $alcance;
    }

    /**
     * Pedido nuevo (aún sin id): filtra por almacén elegido y depto del usuario.
     *
     * @return array{canal_origen:string, sucursal:?string, departamento:?string}
     */
    public static function desdeAlmacenYUsuario(?int $almacenId, ?User $usuario): array
    {
        $almacen = $almacenId ? Almacen::with('sucursal')->find($almacenId) : null;

        return [
            'canal_origen' => 'bellaroma',
            'sucursal' => self::nombreSucursalAlmacen($almacen),
            'departamento' => self::nombreDepartamento($usuario),
        ];
    }

    public static function aplicarFiltro(Builder $query, array $alcance): void
    {
        if ($alcance === []) {
            return;
        }

        if (! empty($alcance['excluir_pedido_bma_id'])) {
            $id = (int) $alcance['excluir_pedido_bma_id'];
            $query->where(function (Builder $q) use ($id) {
                $q->whereNull('pedido_bma_id')->orWhere('pedido_bma_id', '!=', $id);
            });
        }

        $canal = $alcance['canal_origen'] ?? null;
        if (is_string($canal) && $canal !== '') {
            $query->where(function (Builder $q) use ($canal) {
                $q->where('canal_origen', $canal)->orWhereNull('canal_origen');
            });
        }

        if (array_key_exists('sucursal', $alcance)) {
            $sucursal = $alcance['sucursal'];
            if (is_string($sucursal) && $sucursal !== '') {
                $query->where(function (Builder $q) use ($sucursal) {
                    $q->where('sucursal', $sucursal)->orWhereNull('sucursal');
                });
            } else {
                $query->whereNull('sucursal');
            }
        }

        if (array_key_exists('departamento', $alcance)) {
            $depto = $alcance['departamento'];
            if (is_string($depto) && $depto !== '') {
                $query->where(function (Builder $q) use ($depto) {
                    $q->where('departamento', $depto)->orWhereNull('departamento');
                });
            } else {
                $query->whereNull('departamento');
            }
        }
    }

    public static function nombreSucursalAlmacen(?Almacen $almacen): ?string
    {
        if (! $almacen) {
            return null;
        }

        $nombre = $almacen->sucursal?->nombre ?: $almacen->nombre;

        return $nombre !== null && $nombre !== '' ? (string) $nombre : null;
    }

    public static function nombreDepartamento(?User $usuario): ?string
    {
        if (! $usuario) {
            return null;
        }

        $usuario->loadMissing('departamento');
        $nombre = $usuario->departamento?->nombre;

        return $nombre !== null && $nombre !== '' ? (string) $nombre : null;
    }
}

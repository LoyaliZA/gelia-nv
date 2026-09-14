<?php

namespace App\Services\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeOperacionConflictException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class TiendanubeProductoRecursoLockService
{
    public const PREFIX = 'tiendanube:recurso:';

    public const TIPO_PRODUCTO = 'product';

    public const TIPO_CATEGORIA = 'category';

    public function clave(int $storeId, string $tipo, int|string $resourceId): string
    {
        return self::PREFIX.$storeId.':'.$tipo.':'.$resourceId;
    }

    public function adquirir(int $storeId, string $tipo, int|string $resourceId, int $seconds = 120, int $esperarSegundos = 10): Lock
    {
        $lock = Cache::lock($this->clave($storeId, $tipo, $resourceId), $seconds);
        $lock->block($esperarSegundos);

        return $lock;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function conProducto(int $storeId, int $productoId, callable $callback, int $seconds = 120, int $esperarSegundos = 10): mixed
    {
        $lock = $this->adquirir($storeId, self::TIPO_PRODUCTO, $productoId, $seconds, $esperarSegundos);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    public function intentarProducto(int $storeId, int $productoId, int $seconds = 120): ?Lock
    {
        $lock = Cache::lock($this->clave($storeId, self::TIPO_PRODUCTO, $productoId), $seconds);

        return $lock->get() ? $lock : null;
    }

    public function exigirProductoLibre(int $storeId, int $productoId, int $seconds = 120): Lock
    {
        $lock = $this->intentarProducto($storeId, $productoId, $seconds);
        if ($lock === null) {
            throw new TiendanubeOperacionConflictException(
                'Ya hay una operación en curso para este producto. Reintente cuando termine.'
            );
        }

        return $lock;
    }
}

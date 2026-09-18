<?php

namespace App\Services\Mobile;

class MobileSyncSuppression
{
    private static int $nivel = 0;

    public static function begin(): void
    {
        self::$nivel++;
    }

    public static function end(): void
    {
        self::$nivel = max(0, self::$nivel - 1);
    }

    public static function activa(): bool
    {
        return self::$nivel > 0;
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function ejecutar(callable $callback)
    {
        self::begin();

        try {
            return $callback();
        } finally {
            self::end();
        }
    }
}

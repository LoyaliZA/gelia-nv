<?php

namespace App\Support\ControlPedidos;

use App\Models\Almacen;

final class AlmacenPdv
{
    public const CODIGO = 'PDV';

    /** Nombre del catálogo `catalogo_tipos_almacen` que identifica punto de venta. */
    public const TIPO = 'PDV';

    public static function esCodigo(?string $codigo): bool
    {
        return self::normalizar($codigo) === self::CODIGO;
    }

    public static function esTipo(?string $nombre): bool
    {
        return self::normalizar($nombre) === self::TIPO;
    }

    /**
     * Un almacén de punto de venta se reconoce por su tipo (PDV-1, PDV-2, …).
     * El código literal PDV se conserva para catálogos anteriores al tipo.
     */
    public static function esAlmacen(?Almacen $almacen): bool
    {
        if (! $almacen) {
            return false;
        }

        $almacen->loadMissing('tipoAlmacen');
        if (self::esTipo($almacen->tipoAlmacen?->nombre)) {
            return true;
        }

        return self::esCodigo($almacen->codigo);
    }

    private static function normalizar(?string $valor): string
    {
        return strtoupper(trim((string) $valor));
    }
}

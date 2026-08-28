<?php

namespace App\Support\Facturas;

final class LimitesAdjuntosFactura
{
    public const MAX_VOUCHERS = 5;

    public const MAX_PDFS_EMITIDOS = 5;

    /** Kilobytes (Laravel `max` rule). */
    public const MAX_KB_POR_ARCHIVO = 5120;

    public const MAX_BYTES_POR_ARCHIVO = self::MAX_KB_POR_ARCHIVO * 1024;
}

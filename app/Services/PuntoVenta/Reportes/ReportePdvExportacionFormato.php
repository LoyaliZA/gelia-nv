<?php

namespace App\Services\PuntoVenta\Reportes;

final class ReportePdvExportacionFormato
{
    public const CSV = 'csv';

    public const PDF = 'pdf';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return [self::CSV, self::PDF];
    }
}

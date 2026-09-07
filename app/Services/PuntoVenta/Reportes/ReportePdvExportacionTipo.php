<?php

namespace App\Services\PuntoVenta\Reportes;

final class ReportePdvExportacionTipo
{
    public const RESGUARDOS = 'resguardos';

    public const TURNOS_OPERACION = 'turnos_operacion';

    public const CONJUNTO = 'conjunto';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return [
            self::RESGUARDOS,
            self::TURNOS_OPERACION,
            self::CONJUNTO,
        ];
    }

    public static function desdeFiltros(array $filtros): string
    {
        return (string) ($filtros['tipo_reporte'] ?? self::CONJUNTO);
    }
}

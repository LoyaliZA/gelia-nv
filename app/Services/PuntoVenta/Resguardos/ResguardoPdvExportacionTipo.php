<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdvExportacion;

final class ResguardoPdvExportacionTipo
{
    public const LISTADO = 'listado';

    public const AUDITORIA = 'auditoria';

    /** @return list<string> */
    public static function valores(): array
    {
        return [self::LISTADO, self::AUDITORIA];
    }

    public static function desdeFiltros(array $filtros): string
    {
        $tipo = (string) ($filtros['tipo'] ?? self::LISTADO);

        return in_array($tipo, self::valores(), true) ? $tipo : self::LISTADO;
    }

    public static function etiquetaModelo(string $tipo): string
    {
        return match ($tipo) {
            self::AUDITORIA => ResguardoPdvExportacion::TIPO_AUDITORIA,
            default => ResguardoPdvExportacion::TIPO_LISTADO,
        };
    }
}

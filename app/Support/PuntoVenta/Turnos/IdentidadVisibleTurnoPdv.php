<?php

namespace App\Support\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;

final class IdentidadVisibleTurnoPdv
{
    public const TIPO_REPRESENTANTE = 'representante';

    public static function esTercero(TurnoPdv $turno): bool
    {
        $tipo = $turno->snapshot_json['tipo_persona'] ?? null;
        if ($tipo === self::TIPO_REPRESENTANTE) {
            return true;
        }

        $presente = self::texto($turno->snapshot_nombre_llamado);
        $titular = self::texto($turno->snapshot_cliente_nombre);

        return $presente !== '' && $titular !== '' && strcasecmp($presente, $titular) !== 0;
    }

    public static function nombreVisible(TurnoPdv $turno): string
    {
        $presente = self::texto($turno->snapshot_nombre_llamado);
        if ($presente !== '') {
            return $presente;
        }

        return self::texto($turno->snapshot_cliente_nombre);
    }

    public static function nombreTitular(TurnoPdv $turno): ?string
    {
        if (! self::esTercero($turno)) {
            return null;
        }

        $titular = self::texto($turno->snapshot_cliente_nombre);

        return $titular !== '' ? $titular : null;
    }

    private static function texto(mixed $valor): string
    {
        return trim((string) $valor);
    }
}

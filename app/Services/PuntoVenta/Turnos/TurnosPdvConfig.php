<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;
use InvalidArgumentException;

class TurnosPdvConfig
{
    public const SEPARADOR_FOLIO = '-';

    public const PADDING_FOLIO = 4;

    /**
     * Zona horaria operativa para fecha de contador.
     * ponytail: sobrescribir por sucursal cuando exista configuración persistida de Operación.
     */
    public function zonaHorariaOperativa(?int $sucursalId = null): string
    {
        return (string) config('app.timezone', 'America/Mexico_City');
    }

    public function prefijoFolio(string $servicio): string
    {
        return match ($servicio) {
            TurnoPdv::SERVICIO_VENTAS => 'V',
            default => throw new InvalidArgumentException("Servicio de turno no soportado: {$servicio}"),
        };
    }

    public function formatearFolio(string $servicio, int $secuencia): string
    {
        $prefijo = $this->prefijoFolio($servicio);

        return $prefijo
            .self::SEPARADOR_FOLIO
            .str_pad((string) $secuencia, self::PADDING_FOLIO, '0', STR_PAD_LEFT);
    }
}

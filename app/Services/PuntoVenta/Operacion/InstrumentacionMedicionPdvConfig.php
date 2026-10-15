<?php

namespace App\Services\PuntoVenta\Operacion;

use InvalidArgumentException;

class InstrumentacionMedicionPdvConfig
{
    public function estado(): string
    {
        $estado = config('punto_venta.medicion.estado', 'desactivada');
        if (! in_array($estado, ['desactivada', 'activa', 'suspendida'], true)) {
            throw new InvalidArgumentException('Estado de instrumentación PDV no válido.');
        }

        return $estado;
    }

    public function activaParaSucursal(int $sucursalId): bool
    {
        return $this->estado() === 'activa'
            && in_array((string) $sucursalId, array_map('strval', config('punto_venta.medicion.sucursales', [])), true);
    }

    public function cobertura(): array
    {
        return ['transiciones' => ['jornada.abierta'], 'completa' => false, 'metricas_v2' => false];
    }
}

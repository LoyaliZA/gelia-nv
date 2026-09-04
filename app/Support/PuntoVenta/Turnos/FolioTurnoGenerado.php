<?php

namespace App\Support\PuntoVenta\Turnos;

/**
 * Contrato de salida del generador atómico de folios de turno (tarea 4B).
 *
 * @property-read string $folio        Formato público determinista (p. ej. V-0001 para Ventas).
 * @property-read int    $secuencia    Número asignado dentro de sucursal + fecha operativa + servicio.
 * @property-read string $fechaOperativa Fecha operativa Y-m-d resuelta en servidor.
 * @property-read string $servicio     Clave de servicio validada en servidor.
 * @property-read int    $sucursalId   Sucursal dueña del contador.
 */
readonly class FolioTurnoGenerado
{
    public function __construct(
        public string $folio,
        public int $secuencia,
        public string $fechaOperativa,
        public string $servicio,
        public int $sucursalId,
    ) {}
}

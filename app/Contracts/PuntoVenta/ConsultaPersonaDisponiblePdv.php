<?php

namespace App\Contracts\PuntoVenta;

use App\Models\User;

/**
 * Consulta de persona disponible para asignación automática (Operación §5).
 *
 * Implementación completa: tarea 5B. Turnos solo consume este contrato;
 * no debe calcular jornada, pausa ni atención en curso de forma ad hoc.
 */
interface ConsultaPersonaDisponiblePdv
{
    /**
     * Siguiente persona de la fila automática.
     * La primera ronda del día, entre quienes aún no cierran una atención, es aleatoria.
     * Después, gana quien lleva más tiempo libre desde su último cierre.
     */
    public function primeraDisponible(int $sucursalId, string $servicio): ?User;

    /**
     * Evalúa las condiciones de Operación §5.
     * Cuando $paraAltaNueva es true, exige además que la sucursal acepte altas (§5.6).
     */
    public function esDisponible(User $user, int $sucursalId, bool $paraAltaNueva = false): bool;

    /**
     * Cantidad de personas disponibles para asignación en la sucursal.
     */
    public function contarDisponibles(int $sucursalId, string $servicio): int;
}

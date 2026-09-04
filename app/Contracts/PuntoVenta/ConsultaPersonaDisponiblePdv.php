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
     * Primera persona disponible en la sucursal para el servicio indicado.
     * El desempate debe ser determinista (contrato Turnos §7).
     */
    public function primeraDisponible(int $sucursalId, string $servicio): ?User;

    /**
     * Evalúa las condiciones de Operación §5.
     * Cuando $paraAltaNueva es true, exige además que la sucursal acepte altas (§5.6).
     */
    public function esDisponible(User $user, int $sucursalId, bool $paraAltaNueva = false): bool;
}

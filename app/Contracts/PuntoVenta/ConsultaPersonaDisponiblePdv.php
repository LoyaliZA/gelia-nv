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
}

<?php

namespace App\Services\PuntoVenta\Turnos;

use Illuminate\Support\Facades\DB;

class MatchmakerTurnosPdvService
{
    /**
     * Límite de seguridad por ejecución; la cola real suele ser pequeña.
     */
    private const MAX_ASIGNACIONES_POR_EJECUCION = 50;

    public function __construct(
        private readonly SeleccionarTurnoColaPdvService $seleccionarCola,
        private readonly AsignarTurnoPdvService $asignarTurno,
    ) {}

    /**
     * Asigna turnos pendientes a personas disponibles de forma idempotente.
     *
     * @return int Cantidad de asignaciones realizadas en esta ejecución.
     */
    public function ejecutar(int $sucursalId, string $origenDisparador): int
    {
        $asignaciones = 0;
        $eventosPendientes = [];

        DB::transaction(function () use ($sucursalId, $origenDisparador, &$asignaciones, &$eventosPendientes): void {
            for ($i = 0; $i < self::MAX_ASIGNACIONES_POR_EJECUCION; $i++) {
                $turno = $this->seleccionarCola->siguiente($sucursalId);
                if ($turno === null) {
                    break;
                }

                $resultado = $this->asignarTurno->ejecutar($turno, now(), $origenDisparador);
                if ($resultado === null) {
                    break;
                }

                $eventosPendientes[] = $resultado;
                $asignaciones++;
            }
        });

        foreach ($eventosPendientes as $resultado) {
            $this->asignarTurno->publicarEventoDominio($resultado);
        }

        return $asignaciones;
    }
}

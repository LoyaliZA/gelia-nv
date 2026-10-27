<?php

namespace App\Services\Tiendanube\Gobernador;

/**
 * El gobernador ya guardó fase, motivo y reanudación. Esta implementación no encola.
 * Activar el recorrido exige el reprogramador de E2b y consumidores comprobados.
 */
class TiendanubeReprogramadorInactivo implements TiendanubeReprogramador
{
    public function reprogramar(TiendanubeTurnoResultado $turno): void
    {
    }

    public function programarEjecucion(string $ejecucionId, \DateTimeInterface $cuando): void
    {
    }
}

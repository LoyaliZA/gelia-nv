<?php

namespace App\Services\Tiendanube\Gobernador;

/**
 * E2b sustituye la implementación inactiva por la espera durable según la clase de error.
 * Hasta esa integración el recorrido nuevo permanece apagado: esta interfaz no autoriza
 * despachar trabajos a tiendanube_interactiva, tiendanube_recuperacion,
 * tiendanube_publicacion ni tiendanube_catalogo.
 */
interface TiendanubeReprogramador
{
    public function reprogramar(TiendanubeTurnoResultado $turno): void;

    public function programarEjecucion(string $ejecucionId, \DateTimeInterface $cuando): void;
}

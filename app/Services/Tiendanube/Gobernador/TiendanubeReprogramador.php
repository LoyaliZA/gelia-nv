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
    public function reprogramar(TiendanubeTurnoResultado $turno, ?string $leaseToken = null): void;

    public function programarEjecucion(string $ejecucionId, \DateTimeInterface $cuando, ?string $leaseToken = null): void;

    /**
     * Encola un solo trabajo para esa marca. Una marca futura no sale de inmediato.
     * Si la continuación ya estaba persistida y el despacho no ocurrió, lo repara.
     */
    public function despacharUnaVez(string $ejecucionId, ?\DateTimeInterface $cuando = null, ?string $leaseToken = null): bool;
}

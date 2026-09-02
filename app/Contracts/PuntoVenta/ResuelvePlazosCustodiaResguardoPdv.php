<?php

namespace App\Contracts\PuntoVenta;

/**
 * Resolución centralizada de plazos operativos de custodia PDV (global → sucursal).
 *
 * @phpstan-type PlazosCustodiaResguardo array{
 *   activo: bool,
 *   zona_horaria: string,
 *   tipo_dias: string,
 *   dias_habiles: list<int>,
 *   custodia_dias: int,
 *   aviso_previo_dias: int,
 *   rezago_dias: int
 * }
 */
interface ResuelvePlazosCustodiaResguardoPdv
{
    public function estaConfigurado(): bool;

    /**
     * @return PlazosCustodiaResguardo|null
     */
    public function obtenerGlobal(): ?array;

    /**
     * @return PlazosCustodiaResguardo|null
     */
    public function resolverParaSucursal(?int $sucursalId): ?array;

    /**
     * @param  array<string, mixed>  $configuracion
     * @return PlazosCustodiaResguardo
     */
    public function normalizar(array $configuracion): array;
}

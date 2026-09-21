<?php

namespace App\Support\PuntoVenta\Turnos;

final class ResolverCandidatosReatencionTurnoPdv
{
    /**
     * Prioriza vendedores distintos al anterior; si no hay otro disponible,
     * permite al anterior como único candidato viable.
     *
     * @param  list<array{id: int, nombre: string}>  $candidatosDisponibles
     * @return list<array{id: int, nombre: string}>
     */
    public static function filtrar(?int $vendedorAnteriorId, array $candidatosDisponibles): array
    {
        if ($vendedorAnteriorId === null) {
            return $candidatosDisponibles;
        }

        $otros = array_values(array_filter(
            $candidatosDisponibles,
            static fn (array $candidato): bool => $candidato['id'] !== $vendedorAnteriorId,
        ));

        if ($otros !== []) {
            return $otros;
        }

        return array_values(array_filter(
            $candidatosDisponibles,
            static fn (array $candidato): bool => $candidato['id'] === $vendedorAnteriorId,
        ));
    }

    /**
     * @param  list<array{id: int, nombre: string}>  $candidatosDisponibles
     */
    public static function hayOtroDisponibleDistinto(?int $vendedorAnteriorId, array $candidatosDisponibles): bool
    {
        if ($vendedorAnteriorId === null) {
            return $candidatosDisponibles !== [];
        }

        foreach ($candidatosDisponibles as $candidato) {
            if ($candidato['id'] !== $vendedorAnteriorId) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Contracts\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Contrato de alcance PDV (0B): permiso de acción y sucursal son controles separados.
 *
 * Uso desde Services/Commands (sin controlador):
 * - Piso consulta: asegurarConsultaPiso / aplicarConsultaPiso
 * - Piso mutación: asegurarMutacionPiso (sucursal del registro = activa)
 * - Global consulta: asegurarConsultaGlobal / aplicarConsultaGlobal
 *
 * HTTP: middleware `pdv.piso` + PdvOperacionPisoRequest / PdvConsultaGlobalRequest.
 * Policies: trait AutorizaAlcancePdv.
 *
 * 403: sin permiso, sin sucursal activa, sucursal no asignada o cruzada, global no muta.
 * 404: la sucursal del registro no existe.
 * No usar $user->can(): Gate::before de Super Admin no aplica a PDV (0B).
 */
interface ResuelveAlcancePdv
{
    /**
     * @return Collection<int, int>
     */
    public function idsSucursalesOperables(User $user): Collection;

    /**
     * @return Collection<int, int>
     */
    public function idsSucursalesElegibles(): Collection;

    public function sucursalActivaId(User $user): ?int;

    public function establecerSucursalActiva(User $user, int $sucursalId): void;

    public function tieneAlcanceGlobal(User $user): bool;

    public function tienePermisoPdv(User $user, string $permiso): bool;

    public function permiteConsultaPiso(User $user, string $permiso): bool;

    public function permiteMutacionPiso(User $user, string $permiso, ?int $sucursalIdRegistro = null): bool;

    public function asegurarConsultaPiso(User $user, string $permiso): void;

    public function asegurarMutacionPiso(User $user, string $permiso, ?int $sucursalIdRegistro = null): void;

    public function asegurarConsultaGlobal(User $user): void;

    public function sucursalParaMutacion(User $user, int $sucursalId, string $permiso): Sucursal;

    /**
     * Un sucursal_id de request no define la sucursal activa. Solo confirma si ya es operable.
     */
    public function sucursalIdReclamadaSiOperable(User $user, mixed $reclamado): ?int;

    public function aplicarConsultaPiso(Builder $query, User $user, string $permiso, string $columna = 'sucursal_id'): Builder;

    public function aplicarConsultaGlobal(Builder $query, User $user, string $columna = 'sucursal_id'): Builder;
}

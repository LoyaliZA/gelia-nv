<?php

namespace App\Services\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

final class AlcancePdv implements ResuelveAlcancePdv
{
    public const PERMISO_ALCANCE_GLOBAL = 'pdv.alcance.global';

    public const SESSION_SUCURSAL_ACTIVA = 'pdv.sucursal_activa_id';

    /**
     * @return Collection<int, int>
     */
    public function idsSucursalesOperables(User $user): Collection
    {
        return $user->idsSucursalesOperables();
    }

    /**
     * @return Collection<int, int>
     */
    public function idsSucursalesElegibles(): Collection
    {
        return Sucursal::query()
            ->where('activo', true)
            ->orderBy('id')
            ->pluck('id')
            ->values();
    }

    public function sucursalActivaId(User $user): ?int
    {
        $operables = $this->idsSucursalesOperables($user);
        $sesionId = session(self::SESSION_SUCURSAL_ACTIVA);

        if (is_numeric($sesionId) && $operables->contains((int) $sesionId)) {
            return (int) $sesionId;
        }

        $principal = $user->sucursalPrincipal();

        return $principal?->id;
    }

    public function establecerSucursalActiva(User $user, int $sucursalId): void
    {
        if (! $this->idsSucursalesOperables($user)->contains($sucursalId)) {
            throw new AuthorizationException('No tiene acceso operable a esa sucursal.');
        }

        session([self::SESSION_SUCURSAL_ACTIVA => $sucursalId]);
    }

    public function tieneAlcanceGlobal(User $user): bool
    {
        return $this->tienePermisoPdv($user, self::PERMISO_ALCANCE_GLOBAL);
    }

    public function tienePermisoPdv(User $user, string $permiso): bool
    {
        try {
            return $user->hasPermissionTo($permiso);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function permiteConsultaPiso(User $user, string $permiso): bool
    {
        return $this->tienePermisoPdv($user, $permiso)
            && $this->sucursalActivaId($user) !== null;
    }

    public function permiteMutacionPiso(User $user, string $permiso, ?int $sucursalIdRegistro = null): bool
    {
        if (! $this->permiteConsultaPiso($user, $permiso)) {
            return false;
        }

        if ($sucursalIdRegistro === null) {
            return true;
        }

        return $this->sucursalActivaId($user) === $sucursalIdRegistro;
    }

    public function asegurarConsultaPiso(User $user, string $permiso): void
    {
        if (! $this->permiteConsultaPiso($user, $permiso)) {
            throw new AuthorizationException('No autorizado para consultar operación de piso.');
        }
    }

    public function asegurarMutacionPiso(User $user, string $permiso, ?int $sucursalIdRegistro = null): void
    {
        if (! $this->permiteMutacionPiso($user, $permiso, $sucursalIdRegistro)) {
            throw new AuthorizationException('No autorizado para mutar en la sucursal activa.');
        }
    }

    public function asegurarConsultaGlobal(User $user): void
    {
        if (! $this->tieneAlcanceGlobal($user)) {
            throw new AuthorizationException('No autorizado para consulta global de sucursales.');
        }
    }

    public function sucursalParaMutacion(User $user, int $sucursalId, string $permiso): Sucursal
    {
        $sucursal = Sucursal::query()->find($sucursalId);
        if (! $sucursal instanceof Sucursal) {
            throw (new ModelNotFoundException)->setModel(Sucursal::class, [$sucursalId]);
        }

        $this->asegurarMutacionPiso($user, $permiso, $sucursal->id);

        return $sucursal;
    }

    public function sucursalIdReclamadaSiOperable(User $user, mixed $reclamado): ?int
    {
        if (! is_numeric($reclamado)) {
            return null;
        }

        $id = (int) $reclamado;

        return $this->idsSucursalesOperables($user)->contains($id) ? $id : null;
    }

    public function aplicarConsultaPiso(Builder $query, User $user, string $permiso, string $columna = 'sucursal_id'): Builder
    {
        $this->asegurarConsultaPiso($user, $permiso);

        $activaId = $this->sucursalActivaId($user);

        return $query->where($columna, $activaId);
    }

    public function aplicarConsultaGlobal(Builder $query, User $user, string $columna = 'sucursal_id'): Builder
    {
        $this->asegurarConsultaGlobal($user);

        return $query->whereIn($columna, $this->idsSucursalesElegibles());
    }
}

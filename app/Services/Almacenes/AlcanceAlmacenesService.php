<?php

namespace App\Services\Almacenes;

use App\Models\Almacen;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AlcanceAlmacenesService
{
    public const SESSION_SUCURSAL_ACTIVA = 'almacenes.sucursal_activa_id';

    /**
     * @return Collection<int, Sucursal>
     */
    public function sucursalesOperables(User $user): Collection
    {
        $ids = $user->idsSucursalesOperables();
        if ($ids->isEmpty()) {
            return collect();
        }

        return Sucursal::query()
            ->where('activo', true)
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    public function idsSucursalesOperables(User $user): Collection
    {
        return $user->idsSucursalesOperables();
    }

    /**
     * @return Collection<int, Almacen>
     */
    public function almacenesOperables(User $user, ?int $sucursalId = null): Collection
    {
        $idsSucursal = $this->idsSucursalesOperables($user);
        if ($idsSucursal->isEmpty()) {
            return collect();
        }

        if ($sucursalId !== null) {
            if (! $idsSucursal->contains($sucursalId)) {
                return collect();
            }
            $idsSucursal = collect([$sucursalId]);
        }

        return Almacen::query()
            ->with(['sucursal', 'tipoAlmacen'])
            ->where('activo', true)
            ->whereIn('sucursal_id', $idsSucursal)
            ->orderBy('nombre')
            ->get();
    }

    public function sucursalActivaId(User $user): ?int
    {
        $operables = $this->idsSucursalesOperables($user);
        if ($operables->isEmpty()) {
            return null;
        }

        $sesionId = session(self::SESSION_SUCURSAL_ACTIVA);
        if (is_numeric($sesionId) && $operables->contains((int) $sesionId)) {
            return (int) $sesionId;
        }

        $principal = $user->sucursalPrincipal();
        if ($principal instanceof Sucursal && $operables->contains($principal->id)) {
            return $principal->id;
        }

        if ($operables->count() === 1) {
            return (int) $operables->first();
        }

        return null;
    }

    public function establecerSucursalActiva(User $user, int $sucursalId): void
    {
        if (! $this->idsSucursalesOperables($user)->contains($sucursalId)) {
            throw new AuthorizationException('No tiene acceso operable a esa sucursal.');
        }

        session([self::SESSION_SUCURSAL_ACTIVA => $sucursalId]);
    }

    public function asegurarSucursalOperable(User $user, int $sucursalId): void
    {
        if (! $this->idsSucursalesOperables($user)->contains($sucursalId)) {
            throw new AuthorizationException('No autorizado para operar en esa sucursal.');
        }
    }

    public function asegurarAlmacenOperable(User $user, int $almacenId): Almacen
    {
        $almacen = Almacen::query()->find($almacenId);
        if (! $almacen instanceof Almacen) {
            throw ValidationException::withMessages([
                'almacen_id' => 'Almacén no encontrado.',
            ]);
        }

        if ($almacen->sucursal_id === null) {
            throw ValidationException::withMessages([
                'almacen_id' => 'El almacén no está asignado a una sucursal operable.',
            ]);
        }

        $this->asegurarSucursalOperable($user, (int) $almacen->sucursal_id);

        return $almacen;
    }

    /**
     * @param  Builder<Almacen>  $query
     * @return Builder<Almacen>
     */
    public function restringirQueryAlmacenes(Builder $query, User $user): Builder
    {
        $ids = $this->idsSucursalesOperables($user);
        if ($ids->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('sucursal_id', $ids);
    }

    /**
     * @param  Builder<Inventario>|Builder<ProductoCosto>  $query
     */
    public function restringirPorAlmacenSucursalOperable(Builder $query, User $user, string $almacenRelation = 'almacen'): Builder
    {
        $ids = $this->idsSucursalesOperables($user);
        if ($ids->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas($almacenRelation, fn (Builder $q) => $q->whereIn('sucursal_id', $ids));
    }
}

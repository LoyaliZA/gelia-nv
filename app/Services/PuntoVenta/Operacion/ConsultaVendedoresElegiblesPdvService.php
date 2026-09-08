<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Database\Eloquent\Builder;

class ConsultaVendedoresElegiblesPdvService
{
    /**
     * Usuarios que pueden figurar como vendedores en una sucursal:
     * activos, con acceso al módulo, permiso atender y asignación vigente.
     *
     * @return Builder<User>
     */
    public function query(int $sucursalId): Builder
    {
        return User::query()
            ->permission(PuntoVentaModulo::PERMISO_ACCEDER)
            ->permission(PuntoVentaModulo::PERMISO_TURNOS_ATENDER)
            ->whereHas('sucursales', function (Builder $query) use ($sucursalId): void {
                $query->where('sucursales.id', $sucursalId)
                    ->where('sucursales.activo', true)
                    ->where('sucursal_user.activo', true);
            })
            ->orderBy('users.id');
    }

    public function esElegible(User $user, int $sucursalId): bool
    {
        return $this->query($sucursalId)->whereKey($user->id)->exists();
    }
}

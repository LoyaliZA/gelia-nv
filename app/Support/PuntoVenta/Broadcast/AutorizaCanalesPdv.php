<?php

namespace App\Support\PuntoVenta\Broadcast;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;

final class AutorizaCanalesPdv
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    public function puedeSucursal(User $user, int $sucursalId): bool
    {
        if (! $user->hasPermissionTo(PuntoVentaModulo::PERMISO_ACCEDER)) {
            return false;
        }

        return $this->alcance->idsSucursalesOperables($user)->contains($sucursalId);
    }

    public function puedeUsuario(User $user, int $userId): bool
    {
        if ((int) $user->id !== $userId) {
            return false;
        }

        return $user->hasPermissionTo(PuntoVentaModulo::PERMISO_ACCEDER);
    }
}

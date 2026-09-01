<?php

namespace App\Policies\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;

trait AutorizaAlcancePdv
{
    protected function alcancePdv(): ResuelveAlcancePdv
    {
        return app(ResuelveAlcancePdv::class);
    }

    protected function permiteConsultaPisoPdv(User $user, string $permiso): bool
    {
        return $this->alcancePdv()->permiteConsultaPiso($user, $permiso);
    }

    protected function permiteMutacionPisoPdv(User $user, string $permiso, ?int $sucursalIdRegistro = null): bool
    {
        return $this->alcancePdv()->permiteMutacionPiso($user, $permiso, $sucursalIdRegistro);
    }

    protected function permiteConsultaGlobalPdv(User $user): bool
    {
        return $this->alcancePdv()->tieneAlcanceGlobal($user);
    }
}

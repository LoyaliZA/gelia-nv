<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class AutorizacionConsultaResguardoPdv
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    public function permiteHistorialEntregados(User $user): bool
    {
        return $this->alcance->permiteConsultaPiso(
            $user,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER_HISTORIAL_ENTREGAS
        );
    }

    public function asegurarHistorialEntregados(User $user): void
    {
        if (! $this->permiteHistorialEntregados($user)) {
            throw new AuthorizationException('No autorizado para consultar el historial de entregas.');
        }
    }

    public function permiteDetalleResguardo(User $user, ResguardoPdv $resguardo): bool
    {
        if ($this->alcance->permiteConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER)) {
            return true;
        }

        if (! $this->permiteHistorialEntregados($user)) {
            return false;
        }

        return $resguardo->estado === ResguardoPdv::ESTADO_ENTREGADO;
    }

    public function asegurarDetalleResguardo(User $user, ResguardoPdv $resguardo): void
    {
        if ($this->permiteDetalleResguardo($user, $resguardo)) {
            return;
        }

        if ($this->permiteHistorialEntregados($user)
            && $resguardo->estado !== ResguardoPdv::ESTADO_ENTREGADO) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }

        throw new AuthorizationException('No autorizado para consultar este resguardo.');
    }

    public function soloHistorialEntregados(User $user): bool
    {
        return $this->permiteHistorialEntregados($user)
            && ! $this->alcance->permiteConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER);
    }
}

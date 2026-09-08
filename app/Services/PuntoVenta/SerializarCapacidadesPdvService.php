<?php

namespace App\Services\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;

class SerializarCapacidadesPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{
     *     atender: bool,
     *     equipo_ver: bool,
     *     equipo_gestionar: bool,
     *     reatencion_asignar: bool,
     *     alertas_sucursal: bool,
     *     pantalla_sala_abrir: bool,
     *     aparece_como_vendedor: bool,
     * }
     */
    public function serializar(User $user): array
    {
        $puedeAtender = $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_ATENDER);
        $tieneSucursalOperable = $this->alcance->idsSucursalesOperables($user)->isNotEmpty();

        return [
            'atender' => $puedeAtender,
            'equipo_ver' => $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER),
            'equipo_gestionar' => $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR),
            'reatencion_asignar' => $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_REATENCION_ASIGNAR),
            'alertas_sucursal' => $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL),
            'pantalla_sala_abrir' => $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR),
            'aparece_como_vendedor' => $puedeAtender && $tieneSucursalOperable,
        ];
    }
}

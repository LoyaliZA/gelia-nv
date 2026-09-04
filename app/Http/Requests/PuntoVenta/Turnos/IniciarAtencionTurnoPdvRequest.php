<?php

namespace App\Http\Requests\PuntoVenta\Turnos;

use App\Services\PuntoVenta\PuntoVentaModulo;

class IniciarAtencionTurnoPdvRequest extends TurnoPdvMutacionRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reglasComunes();
    }

    public function versionEsperada(): int
    {
        return (int) $this->validated('version');
    }
}

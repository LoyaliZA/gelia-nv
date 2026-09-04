<?php

namespace App\Http\Requests\PuntoVenta\Turnos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\PuntoVenta\TurnoPdv;

abstract class TurnoPdvMutacionRequest extends PdvOperacionPisoRequest
{
    protected function sucursalIdRegistro(): ?int
    {
        $turno = $this->route('turno');

        return $turno instanceof TurnoPdv ? (int) $turno->sucursal_id : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglasComunes(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Requests\PuntoVenta\Operacion;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Services\PuntoVenta\PuntoVentaModulo;

class IniciarPausaEquipoPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo_pausa_id' => ['required', 'integer', 'exists:pdv_motivos_pausa,id'],
            'motivo_detalle' => ['nullable', 'string', 'max:500'],
        ];
    }
}

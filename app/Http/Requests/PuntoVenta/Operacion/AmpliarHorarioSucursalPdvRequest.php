<?php

namespace App\Http\Requests\PuntoVenta\Operacion;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Services\PuntoVenta\PuntoVentaModulo;

class AmpliarHorarioSucursalPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'ampliacion_hasta_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array{version: int, ampliacion_hasta_at: string}
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();

        return [
            'version' => (int) $datos['version'],
            'ampliacion_hasta_at' => (string) $datos['ampliacion_hasta_at'],
        ];
    }
}

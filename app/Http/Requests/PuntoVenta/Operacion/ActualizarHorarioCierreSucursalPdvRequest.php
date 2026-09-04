<?php

namespace App\Http\Requests\PuntoVenta\Operacion;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Services\PuntoVenta\PuntoVentaModulo;

class ActualizarHorarioCierreSucursalPdvRequest extends PdvOperacionPisoRequest
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
            'hora_cierre' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            'zona_horaria' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array{hora_cierre: string, zona_horaria: string|null}
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();

        return [
            'hora_cierre' => (string) $datos['hora_cierre'],
            'zona_horaria' => isset($datos['zona_horaria']) ? (string) $datos['zona_horaria'] : null,
        ];
    }
}

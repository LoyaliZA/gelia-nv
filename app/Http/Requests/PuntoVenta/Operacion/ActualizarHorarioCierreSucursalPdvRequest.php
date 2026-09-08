<?php

namespace App\Http\Requests\PuntoVenta\Operacion;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Validation\Validator;

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
            'hora_apertura' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            'hora_cierre' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            'zona_horaria' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $apertura = $this->input('hora_apertura');
            $cierre = $this->input('hora_cierre');

            if (is_string($apertura) && $apertura !== '' && is_string($cierre) && $cierre !== '' && $apertura >= $cierre) {
                $validator->errors()->add('hora_apertura', 'La hora de apertura debe ser anterior a la hora de cierre.');
            }
        });
    }

    /**
     * @return array{hora_apertura: string|null, hora_cierre: string, zona_horaria: string|null}
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();
        $apertura = isset($datos['hora_apertura']) ? trim((string) $datos['hora_apertura']) : '';

        return [
            'hora_apertura' => $apertura !== '' ? $apertura : null,
            'hora_cierre' => (string) $datos['hora_cierre'],
            'zona_horaria' => isset($datos['zona_horaria']) ? (string) $datos['zona_horaria'] : null,
        ];
    }
}

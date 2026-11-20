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
            'zona_horaria' => ['nullable', 'string', 'in:'.config('app.timezone', 'America/Mexico_City')],
            'dias_operacion' => ['nullable', 'array', 'min:1'],
            'dias_operacion.*' => ['integer', 'between:1,7'],
            'hora_apertura_sabado' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            'hora_cierre_sabado' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarParHoras($validator, 'hora_apertura', 'hora_cierre');
            $this->validarParHoras($validator, 'hora_apertura_sabado', 'hora_cierre_sabado');
        });
    }

    /**
     * @return array{
     *   hora_apertura: string|null,
     *   hora_cierre: string,
     *   zona_horaria: string|null,
     *   dias_operacion: list<int>|null,
     *   hora_apertura_sabado: string|null,
     *   hora_cierre_sabado: string|null
     * }
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();
        $apertura = isset($datos['hora_apertura']) ? trim((string) $datos['hora_apertura']) : '';
        $aperturaSabado = isset($datos['hora_apertura_sabado']) ? trim((string) $datos['hora_apertura_sabado']) : '';
        $cierreSabado = isset($datos['hora_cierre_sabado']) ? trim((string) $datos['hora_cierre_sabado']) : '';
        $dias = $datos['dias_operacion'] ?? null;

        return [
            'hora_apertura' => $apertura !== '' ? $apertura : null,
            'hora_cierre' => (string) $datos['hora_cierre'],
            'zona_horaria' => isset($datos['zona_horaria']) ? (string) $datos['zona_horaria'] : null,
            'dias_operacion' => is_array($dias) ? array_values(array_map('intval', $dias)) : null,
            'hora_apertura_sabado' => $aperturaSabado !== '' ? $aperturaSabado : null,
            'hora_cierre_sabado' => $cierreSabado !== '' ? $cierreSabado : null,
        ];
    }

    private function validarParHoras(Validator $validator, string $campoApertura, string $campoCierre): void
    {
        $apertura = $this->input($campoApertura);
        $cierre = $this->input($campoCierre);

        if (is_string($apertura) && $apertura !== '' && is_string($cierre) && $cierre !== '' && $apertura >= $cierre) {
            $validator->errors()->add($campoApertura, 'La hora de apertura debe ser anterior a la hora de cierre.');
        }
    }
}

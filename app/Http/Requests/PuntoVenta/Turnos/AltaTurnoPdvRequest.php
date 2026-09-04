<?php

namespace App\Http\Requests\PuntoVenta\Turnos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Validation\Validator;

class AltaTurnoPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_TURNOS_ALTA;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id', 'required_without:nombre_llamado'],
            'nombre_llamado' => ['nullable', 'string', 'max:255', 'required_without:cliente_id'],
            'prioridad_adulto_mayor' => ['sometimes', 'boolean'],
            'prioridad_discapacidad' => ['sometimes', 'boolean'],
            'prioridad_diamante' => ['prohibited'],
            'prioridad_vip' => ['prohibited'],
            'servicio' => ['prohibited'],
            'origen' => ['prohibited'],
            'sucursal_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            if ($user === null) {
                return;
            }

            foreach (['prioridad_adulto_mayor', 'prioridad_discapacidad'] as $campo) {
                if (! $this->boolean($campo)) {
                    continue;
                }

                if (! $user->can(PuntoVentaModulo::PERMISO_TURNOS_MARCAR_PRIORIDAD)) {
                    $validator->errors()->add(
                        $campo,
                        'No tiene permiso para marcar esta prioridad.'
                    );
                }
            }
        });
    }

    /**
     * @return array{
     *     idempotency_key: string,
     *     cliente_id: int|null,
     *     nombre_llamado: string|null,
     *     prioridad_adulto_mayor: bool,
     *     prioridad_discapacidad: bool
     * }
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();

        return [
            'idempotency_key' => (string) $datos['idempotency_key'],
            'cliente_id' => isset($datos['cliente_id']) ? (int) $datos['cliente_id'] : null,
            'nombre_llamado' => isset($datos['nombre_llamado']) ? (string) $datos['nombre_llamado'] : null,
            'prioridad_adulto_mayor' => (bool) ($datos['prioridad_adulto_mayor'] ?? false),
            'prioridad_discapacidad' => (bool) ($datos['prioridad_discapacidad'] ?? false),
        ];
    }
}

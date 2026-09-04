<?php

namespace App\Http\Requests\PuntoVenta\Turnos;

use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Turnos\MotivosBajaColaTurnoPdv;
use Illuminate\Validation\Rule;

class BajaColaTurnoPdvRequest extends TurnoPdvMutacionRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_TURNOS_BAJA_COLA;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->reglasComunes(), [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'motivo' => ['required', 'string', Rule::in(MotivosBajaColaTurnoPdv::valores())],
            'motivo_detalle' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @return array{
     *     version: int,
     *     idempotency_key: string,
     *     motivo: string,
     *     motivo_detalle: string|null
     * }
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();

        return [
            'version' => (int) $datos['version'],
            'idempotency_key' => (string) $datos['idempotency_key'],
            'motivo' => (string) $datos['motivo'],
            'motivo_detalle' => isset($datos['motivo_detalle']) ? (string) $datos['motivo_detalle'] : null,
        ];
    }
}

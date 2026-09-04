<?php

namespace App\Http\Requests\PuntoVenta\Turnos;

use App\Services\PuntoVenta\PuntoVentaModulo;

class TransferirTurnoPdvRequest extends TurnoPdvMutacionRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_TURNOS_TRANSFERIR;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->reglasComunes(), [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'destino_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
    }

    /**
     * @return array{
     *     version: int,
     *     idempotency_key: string,
     *     destino_user_id: int
     * }
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();

        return [
            'version' => (int) $datos['version'],
            'idempotency_key' => (string) $datos['idempotency_key'],
            'destino_user_id' => (int) $datos['destino_user_id'],
        ];
    }
}

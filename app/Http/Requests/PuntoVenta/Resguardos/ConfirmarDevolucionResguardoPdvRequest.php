<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Services\PuntoVenta\PuntoVentaModulo;

class ConfirmarDevolucionResguardoPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION;
    }

    protected function sucursalIdRegistro(): ?int
    {
        $resguardo = $this->route('resguardo');

        return $resguardo instanceof ResguardoPdv ? (int) $resguardo->sucursal_id : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'motivo' => ['required', 'string', 'max:1000'],
            'evidencias' => ['sometimes', 'array'],
            'evidencias.*' => ['file', 'image', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadOperacion(): array
    {
        return $this->validated();
    }
}

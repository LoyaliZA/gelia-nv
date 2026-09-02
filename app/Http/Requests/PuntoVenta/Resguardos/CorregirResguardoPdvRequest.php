<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\CorreccionResguardoPdv;
use Illuminate\Validation\Rule;

class CorregirResguardoPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR;
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
            'tipo_correccion' => ['required', 'string', Rule::in(CorreccionResguardoPdv::valores())],
            'motivo' => ['required', 'string', 'max:1000'],
            'snapshot_folio' => ['sometimes', 'nullable', 'string', 'max:64'],
            'snapshot_cliente_nombre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'evento_referencia_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
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

    /**
     * @return array<string, mixed>
     */
    public function datosCorreccion(): array
    {
        $datos = $this->validated();

        return array_filter([
            'snapshot_folio' => $datos['snapshot_folio'] ?? null,
            'snapshot_cliente_nombre' => $datos['snapshot_cliente_nombre'] ?? null,
            'evento_referencia_id' => $datos['evento_referencia_id'] ?? null,
        ], fn ($valor) => $valor !== null);
    }
}

<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Validation\Rule;

class RegistrarRecepcionFisicaPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR;
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
            'almacen_id' => ['required', 'integer', 'exists:almacenes,id'],
            'bultos' => ['required', 'array', 'min:1'],
            'bultos.*.folio' => ['required', 'string', 'max:64'],
            'bultos.*.tipo' => ['required', 'string', Rule::in([
                ResguardoPdvBulto::TIPO_CAJA,
                ResguardoPdvBulto::TIPO_BOLSA,
            ])],
            'bultos.*.condicion' => ['required', 'string', 'max:64'],
            'bultos.*.piezas' => ['sometimes', 'integer', 'min:1'],
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

<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use Illuminate\Validation\Rule;

class RegistrarEntregaResguardoPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR;
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
            'relacion' => ['required', 'string', Rule::in([
                ResguardoPdvEntrega::RELACION_TITULAR,
                ResguardoPdvEntrega::RELACION_TERCERO,
            ])],
            'nombre_quien_retira' => ['required', 'string', 'max:255'],
            'metodo_validacion' => ['required', 'string', Rule::in([
                RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
            ])],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'firma' => ['required', 'file', 'image', 'max:10240'],
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

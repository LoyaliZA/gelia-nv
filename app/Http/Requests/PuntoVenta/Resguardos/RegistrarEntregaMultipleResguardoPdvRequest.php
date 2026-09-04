<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use Illuminate\Validation\Rule;

class RegistrarEntregaMultipleResguardoPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entregas' => ['required', 'array', 'min:2'],
            'entregas.*.resguardo_id' => ['required', 'integer', 'distinct', 'exists:pdv_resguardos,id'],
            'entregas.*.version' => ['required', 'integer', 'min:1'],
            'entregas.*.idempotency_key' => ['required', 'string', 'max:64', 'distinct'],
            'entregas.*.relacion' => ['required', 'string', Rule::in([
                ResguardoPdvEntrega::RELACION_TITULAR,
                ResguardoPdvEntrega::RELACION_TERCERO,
            ])],
            'entregas.*.nombre_quien_retira' => ['required', 'string', 'max:255'],
            'entregas.*.metodo_validacion' => ['required', 'string', Rule::in([
                RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
            ])],
            'entregas.*.observaciones' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'entregas.*.firma' => ['required', 'file', 'image', 'max:10240'],
            'entregas.*.evidencias' => ['sometimes', 'array'],
            'entregas.*.evidencias.*' => ['file', 'image', 'max:10240'],
            'entregas.*.bulto_ids' => ['sometimes', 'array', 'min:1'],
            'entregas.*.bulto_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloadOperacion(): array
    {
        return $this->validated()['entregas'];
    }
}

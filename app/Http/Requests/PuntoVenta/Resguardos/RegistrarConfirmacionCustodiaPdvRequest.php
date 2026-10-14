<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\NormalizadorPiezasRegistroManualPdv;
use App\Support\PuntoVenta\Resguardos\OmisionRequisitoResguardoPdv;
use Illuminate\Validation\Rule;

class RegistrarConfirmacionCustodiaPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA;
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
            'bultos.*.tipo' => ['sometimes', 'string', Rule::in([
                ResguardoPdvBulto::TIPO_CAJA,
                ResguardoPdvBulto::TIPO_BOLSA,
            ])],
            'bultos.*.condicion' => ['required', 'string', 'max:64'],
            'bultos.*.piezas' => ['sometimes', 'integer', 'min:1'],
            'evidencias' => ['sometimes', 'array'],
            'evidencias.*' => ['file', 'image', 'max:10240'],
            'piezas' => ['sometimes', 'array', 'max:200'],
            'piezas.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'piezas.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'piezas.*.nota' => ['nullable', 'string', 'max:500'],
            'piezas.*.estado_fisico' => ['nullable', 'string', 'in:'.implode(',', NormalizadorPiezasRegistroManualPdv::ESTADOS)],
            'piezas.*.unica_pieza' => ['sometimes', 'boolean'],
            'piezas.*.mejor_ejemplar' => ['sometimes', 'boolean'],
            'piezas.*.evidencia_ids' => ['sometimes', 'array', 'max:8'],
            'piezas.*.evidencia_ids.*' => ['integer', 'min:1'],
            ...OmisionRequisitoResguardoPdv::reglas(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();
        if ($this->exists('piezas')) {
            $datos['piezas'] = NormalizadorPiezasRegistroManualPdv::normalizar($datos['piezas'] ?? []);
        }

        return $datos;
    }
}

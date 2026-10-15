<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\NormalizadorPiezasRegistroManualPdv;
use App\Support\PuntoVenta\Resguardos\OmisionRequisitoResguardoPdv;

class RegistrarRecepcionFisicaPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA;
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
            'condicion' => ['required', 'in:bueno,regular,malo,danado'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'folios' => ['required', 'array', 'min:1', 'max:200'],
            'folios.*' => ['required', 'string', 'max:100', 'distinct'],
            'version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'piezas' => ['sometimes', 'array', 'max:200'],
            'piezas.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'piezas.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'piezas.*.nota' => ['nullable', 'string', 'max:500'],
            'piezas.*.estado_fisico' => ['nullable', 'string', 'in:'.implode(',', NormalizadorPiezasRegistroManualPdv::ESTADOS)],
            'piezas.*.unica_pieza' => ['sometimes', 'boolean'],
            'piezas.*.mejor_ejemplar' => ['sometimes', 'boolean'],
            'piezas.*.evidencia_ids' => ['sometimes', 'array', 'max:8'],
            'piezas.*.evidencia_ids.*' => ['integer', 'min:1'],
            'archivo_ticket' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'foto_paquete' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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

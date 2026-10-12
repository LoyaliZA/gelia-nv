<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class GuardarAvancePreparacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.tienda.responder') ?? false;
    }

    public function rules(): array
    {
        return array_merge(ReglasLineasPreparacion::reglas(! $this->filled('motivo_incidencia')), [
            'version' => ['required', 'integer', 'min:1'],
            'motivo_incidencia' => ['nullable', 'string', 'max:2000'],
            'observaciones_respuesta' => ['nullable', 'string', 'max:4000'],
            'peso_real_kg' => ['nullable', 'numeric', 'min:0'],
            'peso_volumetrico_kg' => ['nullable', 'numeric', 'min:0'],
            'catalogo_tipo_caja_id' => ['nullable', 'integer', 'exists:catalogo_tipos_caja_pedido,id'],
            'observaciones_fisicas' => ['nullable', 'string', 'max:4000'],
            'evidencias' => ['nullable', 'array', 'max:10'],
            'evidencias.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);
    }
}

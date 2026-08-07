<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ResponderPesajePedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.cedis') ?? false;
    }

    public function rules(): array
    {
        return [
            'cajas' => ['required', 'array', 'min:1'],
            'cajas.*.catalogo_tipo_caja_id' => ['required', 'integer', 'exists:catalogo_tipos_caja_pedido,id'],
            'cajas.*.largo' => ['required', 'numeric', 'min:0'],
            'cajas.*.ancho' => ['required', 'numeric', 'min:0'],
            'cajas.*.alto' => ['required', 'numeric', 'min:0'],
            'cajas.*.peso_real_kg' => ['required', 'numeric', 'min:0'],
            'cajas.*.peso_volumetrico_kg' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'cajas.required' => 'Indique al menos un envío.',
            'cajas.min' => 'Indique al menos un envío.',
            'cajas.*.catalogo_tipo_caja_id.required' => 'Cada envío requiere tipo de caja.',
            'cajas.*.peso_real_kg.required' => 'Cada envío requiere peso real.',
            'cajas.*.peso_volumetrico_kg.required' => 'Cada envío requiere peso volumétrico.',
        ];
    }
}

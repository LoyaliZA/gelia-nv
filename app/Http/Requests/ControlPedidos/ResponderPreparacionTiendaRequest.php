<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResponderPreparacionTiendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.tienda.responder') ?? false;
    }

    public function rules(): array
    {
        return [
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.id' => ['required', 'integer'],
            'productos.*.cantidad_encontrada' => ['required', 'integer', 'min:0'],
            'productos.*.estado_fisico' => ['required', 'string', Rule::in(PedidoBmaRevisionProducto::ESTADOS)],
            'productos.*.observacion' => ['nullable', 'string', 'max:2000'],
            'observaciones_respuesta' => ['nullable', 'string', 'max:4000'],
            'peso_real_kg' => ['nullable', 'numeric', 'min:0'],
            'peso_volumetrico_kg' => ['nullable', 'numeric', 'min:0'],
            'catalogo_tipo_caja_id' => ['nullable', 'integer'],
            'observaciones_fisicas' => ['nullable', 'string', 'max:4000'],
            'version' => ['nullable', 'integer'],
            'evidencias' => ['nullable', 'array'],
            'evidencias.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}

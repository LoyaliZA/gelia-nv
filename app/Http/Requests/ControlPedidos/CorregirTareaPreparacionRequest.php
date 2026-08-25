<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class CorregirTareaPreparacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.preparacion.corregir') ?? false;
    }

    public function rules(): array
    {
        return [
            'almacen_id' => ['required', 'integer', 'exists:almacenes,id'],
            'observaciones' => ['nullable', 'string', 'max:4000'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.descripcion_snapshot' => ['required', 'string', 'max:255'],
            'productos.*.sku' => ['nullable', 'string', 'max:64'],
            'productos.*.producto_id' => ['nullable', 'integer'],
            'productos.*.cantidad_solicitada' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

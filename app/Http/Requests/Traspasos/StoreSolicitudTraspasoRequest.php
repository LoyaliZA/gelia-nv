<?php

namespace App\Http\Requests\Traspasos;

use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudTraspasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('traspasos.crear');
    }

    public function rules(): array
    {
        return [
            'numero_cliente' => ['required', 'string', 'max:255'],
            'almacen_origen_id' => ['required', 'exists:almacenes,id'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'exists:productos,id'],
            'productos.*.piezas' => ['required', 'integer', 'min:1', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_cliente.required' => 'Debe indicar el número de cliente.',
            'almacen_origen_id.required' => 'Debe seleccionar el almacén origen.',
            'productos.required' => 'Debe agregar al menos un producto.',
            'productos.min' => 'Debe agregar al menos un producto.',
        ];
    }
}

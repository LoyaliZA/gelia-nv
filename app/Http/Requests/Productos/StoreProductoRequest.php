<?php

namespace App\Http\Requests\Productos;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalogos.gestionar');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('sku')) {
            $this->merge(['sku' => Producto::normalizarSku($this->input('sku'))]);
        }
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|string|max:100|unique:productos,sku',
            'descripcion' => 'required|string|max:255',
            'existencia' => 'nullable|integer|min:0',
            'costo' => 'nullable|numeric|min:0',
            'precio_venta' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
        ];
    }
}

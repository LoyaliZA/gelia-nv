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
            'sku' => 'required|string|max:30|unique:productos,sku',
            'descripcion' => 'required|string|max:255',
            'activo' => 'nullable|boolean',
        ];
    }
}

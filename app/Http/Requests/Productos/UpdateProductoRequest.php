<?php

namespace App\Http\Requests\Productos;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
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
        $producto = $this->route('producto');

        return [
            'sku' => ['required', 'string', 'max:30', Rule::unique('productos', 'sku')->ignore($producto?->id)],
            'descripcion' => 'required|string|max:255',
            'activo' => 'nullable|boolean',
        ];
    }
}

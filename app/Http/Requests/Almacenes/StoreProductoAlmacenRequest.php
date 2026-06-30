<?php

namespace App\Http\Requests\Almacenes;

use App\Models\Producto;
use App\Services\Almacenes\NormalizarTextoImportacionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductoAlmacenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('almacenes.productos.gestionar');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('sku')) {
            $merge['sku'] = Producto::normalizarSku($this->input('sku'));
        }

        if ($this->filled('descripcion')) {
            $merge['descripcion'] = app(NormalizarTextoImportacionService::class)->texto($this->input('descripcion'));
        }

        $sku = $merge['sku'] ?? ($this->filled('sku') ? Producto::normalizarSku($this->input('sku')) : null);
        $codigoBarras = trim((string) $this->input('codigo_barras', ''));
        if ($codigoBarras === '' && $sku) {
            $merge['codigo_barras'] = $sku;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|string|max:30|unique:productos,sku',
            'descripcion' => 'required|string|max:255',
            'marca_id' => 'nullable|exists:catalogo_marcas_producto,id',
            'categoria_id' => 'nullable|exists:catalogo_categoria_productos,id',
            'codigo_barras' => 'nullable|string|max:30',
            'peso' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
        ];
    }
}

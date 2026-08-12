<?php

namespace App\Http\Requests\GestionInterna;

use App\Models\Producto;
use App\Services\Almacenes\NormalizarTextoImportacionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestion_interna.productos.gestionar')
            || $this->user()->can('catalogos.gestionar');
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
            'tipo_producto_id' => 'nullable|exists:tipos_producto,id',
            'descripcion_corta' => 'nullable|string|max:500',
            'codigo_barras' => 'nullable|string|max:30',
            'peso' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
            'atributos' => 'nullable|array',
            'extensiones' => 'nullable|array',
            'extensiones.perfumeria' => 'nullable|array',
            'extensiones.perfumeria.salida' => 'nullable|array',
            'extensiones.perfumeria.corazon' => 'nullable|array',
            'extensiones.perfumeria.fondo' => 'nullable|array',
            'relacionados' => 'nullable|array',
            'relacionados.*.producto_id' => 'required_with:relacionados|integer|exists:productos,id',
            'relacionados.*.tipo' => 'nullable|string|max:40',
            'contenido' => 'nullable|array',
            'contenido.pitch_venta' => 'nullable|string|max:2000',
            'contenido.descripcion_larga' => 'nullable|string',
            'contenido.seo_titulo' => 'nullable|string|max:255',
            'contenido.seo_descripcion' => 'nullable|string|max:500',
        ];
    }
}

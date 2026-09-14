<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTiendanubePrecioSeleccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.ver') ?? false;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'accion' => ['required', 'in:agregar,quitar,seleccionar_todos,reemplazar,vaciar'],
            'modo' => ['nullable', 'in:pagina,todos_resultados'],
            'variante_ids' => ['nullable', 'array', 'max:5000'],
            'variante_ids.*' => ['integer', 'min:1'],
            'page_variante_ids' => ['nullable', 'array', 'max:50'],
            'page_variante_ids.*' => ['integer', 'min:1'],
            'filtros' => ['nullable', 'array'],
            'filtros.q' => ['nullable', 'string', 'max:120'],
            'filtros.categoria_ids' => ['nullable', 'array', 'max:50'],
            'filtros.categoria_ids.*' => ['integer', 'min:1'],
            'filtros.incluir_subcategorias' => ['nullable', 'boolean'],
            'filtros.sin_categoria' => ['nullable', 'boolean'],
            'filtros.precio_min' => ['nullable', 'numeric', 'min:0'],
            'filtros.precio_max' => ['nullable', 'numeric', 'min:0'],
            'filtros.promocion' => ['nullable', 'in:cualquiera,con,sin'],
            'filtros.costo' => ['nullable', 'in:cualquiera,con,sin'],
        ];
    }
}

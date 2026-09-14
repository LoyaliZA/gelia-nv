<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListarTiendanubePrecioCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.ver') ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'categoria_ids' => ['nullable', 'array', 'max:50'],
            'categoria_ids.*' => ['integer', 'min:1'],
            'incluir_subcategorias' => ['nullable', 'boolean'],
            'sin_categoria' => ['nullable', 'boolean'],
            'precio_min' => ['nullable', 'numeric', 'min:0'],
            'precio_max' => ['nullable', 'numeric', 'min:0'],
            'promocion' => ['nullable', 'in:cualquiera,con,sin'],
            'costo' => ['nullable', 'in:cualquiera,con,sin'],
            'sort' => ['nullable', 'in:id,sku,precio_normal'],
            'dir' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'selection_id' => ['nullable', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $min = $this->input('precio_min');
            $max = $this->input('precio_max');
            if ($min !== null && $min !== '' && $max !== null && $max !== '' && (float) $max < (float) $min) {
                $validator->errors()->add('precio_max', 'El máximo debe ser mayor o igual que el mínimo.');
            }

            $costo = $this->input('costo');
            if (in_array($costo, ['con', 'sin'], true) && ! $this->user()?->can('tiendanube.precios.ver')) {
                $validator->errors()->add('costo', 'No hay permiso para filtrar por costo.');
            }
        });
    }
}

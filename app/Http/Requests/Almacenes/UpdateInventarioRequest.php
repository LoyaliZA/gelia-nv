<?php

namespace App\Http\Requests\Almacenes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('almacenes.inventarios.gestionar');
    }

    public function rules(): array
    {
        $inventario = $this->route('inventario');

        return [
            'producto_id' => [
                'required',
                'exists:productos,id',
                Rule::unique('inventarios')
                    ->where(fn ($q) => $q->where('almacen_id', $this->input('almacen_id')))
                    ->ignore($inventario?->id),
            ],
            'almacen_id' => 'required|exists:almacenes,id',
            'ubicacion' => 'nullable|string|max:50',
            'existencia' => 'nullable|numeric|min:0',
            'apartado' => 'nullable|numeric|min:0',
            'transito_oc' => 'nullable|numeric|min:0',
            'transito_ot' => 'nullable|numeric|min:0',
            'minimo' => 'nullable|numeric|min:0',
            'maximo' => 'nullable|numeric|min:0',
        ];
    }
}

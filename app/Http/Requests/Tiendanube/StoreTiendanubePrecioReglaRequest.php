<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class StoreTiendanubePrecioReglaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.reglas.administrar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'habilitada' => ['nullable', 'boolean'],
            'definicion' => ['required', 'array'],
            'definicion.id' => ['nullable', 'string', 'max:64'],
            'definicion.condiciones' => ['nullable', 'array'],
            'definicion.condiciones.*.campo' => ['required', 'string'],
            'definicion.condiciones.*.operador' => ['required', 'string'],
            'definicion.condiciones.*.valor' => ['nullable', 'string'],
            'definicion.condiciones.*.valor_hasta' => ['nullable', 'string'],
            'definicion.condiciones.*.inclusivo_desde' => ['nullable', 'boolean'],
            'definicion.condiciones.*.inclusivo_hasta' => ['nullable', 'boolean'],
            'definicion.condiciones.*.lista_id' => ['nullable', 'integer', 'min:1'],
            'definicion.base' => ['required', 'string'],
            'definicion.base_lista_id' => ['nullable', 'integer', 'min:1'],
            'definicion.operacion' => ['required', 'string'],
            'definicion.parametro' => ['nullable', 'string'],
            'definicion.destino' => ['required', 'string'],
            'definicion.redondeo' => ['nullable', 'string'],
            'definicion.redondeo_direccion' => ['nullable', 'string'],
        ];
    }
}

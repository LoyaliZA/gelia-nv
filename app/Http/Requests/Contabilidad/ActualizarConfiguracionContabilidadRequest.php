<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarConfiguracionContabilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contabilidad.plataformas.configurar') ?? false;
    }

    public function rules(): array
    {
        return [
            'mapeo_precios' => ['required', 'array'],
            'mapeo_precios.sku' => ['required', 'string', 'max:100'],
            'mapeo_precios.precio_base' => ['required', 'string', 'max:100'],
            'mapeo_precios.descripcion' => ['nullable', 'string', 'max:100'],
        ];
    }
}

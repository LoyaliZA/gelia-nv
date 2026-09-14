<?php

namespace App\Http\Requests\Tiendanube;

use App\Support\Tiendanube\Precios\TiendanubePrecioCsvColumnaCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerarTiendanubePrecioCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.precios.exportar') ?? false;
    }

    public function rules(): array
    {
        $presets = array_merge(
            array_keys(TiendanubePrecioCsvColumnaCatalogo::presets()),
            [TiendanubePrecioCsvColumnaCatalogo::PRESET_PERSONALIZADO]
        );

        return [
            'preset' => ['nullable', 'string', Rule::in($presets)],
            'columnas' => ['nullable', 'array'],
            'columnas.*' => ['string', Rule::in(TiendanubePrecioCsvColumnaCatalogo::encabezados())],
            'variante_ids' => ['nullable', 'array'],
            'variante_ids.*' => ['integer', 'min:1'],
            'selection_id' => ['nullable', 'uuid'],
        ];
    }
}

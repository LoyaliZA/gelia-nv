<?php

namespace App\Http\Requests\Traspasos;

use App\Support\RevisionFisicaProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarTraspasoCedisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('traspasos.cedis') ?? false;
    }

    public function rules(): array
    {
        $estados = RevisionFisicaProducto::ESTADOS;

        return [
            'revisiones' => ['required', 'array', 'min:1'],
            'revisiones.*.solicitud_traspaso_producto_id' => ['required', 'integer'],
            'revisiones.*.producto_id' => ['nullable', 'integer'],
            'revisiones.*.descripcion_producto' => ['nullable', 'string', 'max:255'],
            'revisiones.*.sku' => ['nullable', 'string', 'max:64'],
            'revisiones.*.estado_fisico' => ['required', 'string', Rule::in($estados)],
            'revisiones.*.comentario' => ['nullable', 'string', 'max:2000'],
            'revisiones.*.unica_pieza' => ['nullable', 'boolean'],
            'revisiones.*.mejor_ejemplar' => ['nullable', 'boolean'],
            'revisiones.*.evidencias' => ['nullable', 'array'],
            'revisiones.*.evidencias.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $revisiones = $this->input('revisiones');
        if (! is_array($revisiones)) {
            return;
        }

        $normalizadas = [];
        foreach ($revisiones as $rev) {
            if (! is_array($rev)) {
                continue;
            }
            $normalizadas[] = [
                ...$rev,
                'unica_pieza' => filter_var($rev['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'mejor_ejemplar' => filter_var($rev['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        $this->merge(['revisiones' => $normalizadas]);
    }

    public function messages(): array
    {
        return [
            'revisiones.required' => 'Debe registrar la revisión física de las piezas recibidas.',
            'revisiones.min' => 'Debe registrar la revisión física de las piezas recibidas.',
        ];
    }
}

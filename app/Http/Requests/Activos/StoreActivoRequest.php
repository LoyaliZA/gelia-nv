<?php

namespace App\Http\Requests\Activos;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('activos.crear');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->atributos)) {
            $this->merge(['atributos' => json_decode($this->atributos, true) ?? []]);
        }
    }

    public function rules(): array
    {
        return [
            'catalogo_tipo_activo_id' => 'required|exists:catalogo_tipos_activo,id',
            'departamento_id' => 'required|exists:departamentos,id',
            'area_id' => 'nullable|exists:areas,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'atributos' => 'nullable|array',
            'fecha_adquisicion' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date',
            'valor' => 'nullable|numeric|min:0',
            'fotos' => 'nullable|array|max:5',
            'fotos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
            'registro_continuo' => 'nullable|boolean',
        ];
    }
}

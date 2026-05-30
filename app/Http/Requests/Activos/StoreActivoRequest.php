<?php

namespace App\Http\Requests\Activos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

        if ($this->has('user_id') && $this->input('user_id') === '') {
            $this->merge(['user_id' => null]);
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
            'user_id' => 'nullable|exists:users,id',
            'notas' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('user_id') && !$this->user()->can('activos.asignar')) {
                $validator->errors()->add('user_id', 'No tienes permiso para asignar activos.');
            }
        });
    }
}

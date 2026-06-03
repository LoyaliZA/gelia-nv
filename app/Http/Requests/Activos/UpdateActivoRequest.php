<?php

namespace App\Http\Requests\Activos;

use App\Services\Activos\BuscarActivosService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('activos.editar');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->atributos)) {
            $this->merge(['atributos' => json_decode($this->atributos, true) ?? []]);
        }

        if ($this->has('catalogo_categoria_activo_id') && $this->input('catalogo_categoria_activo_id') === '') {
            $this->merge(['catalogo_categoria_activo_id' => null]);
        }

        if ($this->has('activo_padre_id') && $this->input('activo_padre_id') === '') {
            $this->merge(['activo_padre_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'catalogo_tipo_activo_id' => 'sometimes|exists:catalogo_tipos_activo,id',
            'catalogo_categoria_activo_id' => 'nullable|exists:catalogo_categorias_activo,id',
            'activo_padre_id' => 'nullable|exists:activos,id',
            'departamento_id' => 'sometimes|exists:departamentos,id',
            'area_id' => 'nullable|exists:areas,id',
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'atributos' => 'nullable|array',
            'fecha_adquisicion' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date',
            'valor' => 'nullable|numeric|min:0',
            'fotos' => 'nullable|array|max:5',
            'fotos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $activo = $this->route('activo');
            $tipoId = (int) ($this->input('catalogo_tipo_activo_id') ?? $activo?->catalogo_tipo_activo_id);

            if (!$tipoId) {
                return;
            }

            try {
                app(BuscarActivosService::class)->validarActivoPadre(
                    $this->has('activo_padre_id')
                        ? ($this->input('activo_padre_id') ? (int) $this->input('activo_padre_id') : null)
                        : $activo?->activo_padre_id,
                    $tipoId,
                    $activo?->id,
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }
}

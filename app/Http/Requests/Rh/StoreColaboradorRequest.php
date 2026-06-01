<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreColaboradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.colaboradores.crear');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('user_id') && $this->input('user_id') === '') {
            $this->merge(['user_id' => null]);
        }

        if (!$this->user()->can('rh.colaboradores.vincular_usuario')) {
            $this->offsetUnset('user_id');
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'nullable',
                'exists:users,id',
                Rule::unique('rh_colaboradores', 'user_id'),
            ],
            'departamento_id' => 'required|exists:departamentos,id',
            'area_id' => 'nullable|exists:areas,id',
            'nombre' => 'required|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'catalogo_puesto_id' => 'required|exists:catalogo_puestos,id',
            'salario_base' => 'required|numeric|min:0',
            'bono_productividad' => 'nullable|numeric|min:0',
            'bono_puntualidad' => 'nullable|numeric|min:0',
            'horas_laboradas_oficiales' => 'required|numeric|min:0.5|max:24',
            'activo' => 'nullable|boolean',
            'bonos' => 'nullable|array',
            'bonos.*.catalogo_bono_id' => 'required|exists:catalogo_bonos,id',
            'bonos.*.monto' => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('user_id') && !$this->user()->can('rh.colaboradores.vincular_usuario')) {
                $validator->errors()->add('user_id', 'No tienes permiso para vincular cuentas de usuario.');
            }
        });
    }
}

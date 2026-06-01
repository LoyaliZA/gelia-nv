<?php

namespace App\Http\Requests\Rh;

use App\Models\RhPrestamoPagoFijo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrestamoPagoFijoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.prestamos.editar');
    }

    public function rules(): array
    {
        return [
            'rh_colaborador_id' => 'sometimes|required|exists:rh_colaboradores,id',
            'concepto' => 'sometimes|required|string|max:2000',
            'monto_cuota' => 'sometimes|required|numeric|min:0.01',
            'num_pagos_total' => 'nullable|integer|min:1|max:9999',
            'modalidad' => ['sometimes', 'required', Rule::in([
                RhPrestamoPagoFijo::MODALIDAD_RECURRENTE,
                RhPrestamoPagoFijo::MODALIDAD_UNICA_VEZ,
            ])],
            'observaciones' => 'nullable|string|max:5000',
            'fecha_ejecucion_programada' => 'nullable|date',
            'fecha_inicio' => 'nullable|date',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('modalidad') === RhPrestamoPagoFijo::MODALIDAD_UNICA_VEZ) {
            $this->merge(['num_pagos_total' => 1]);
        }

        if ($this->input('modalidad') === RhPrestamoPagoFijo::MODALIDAD_RECURRENTE) {
            $this->merge(['fecha_ejecucion_programada' => null]);
        }
    }
}

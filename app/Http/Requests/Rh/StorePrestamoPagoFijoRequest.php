<?php

namespace App\Http\Requests\Rh;

use App\Models\RhPrestamoPagoFijo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrestamoPagoFijoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.prestamos.crear');
    }

    public function rules(): array
    {
        return [
            'rh_colaborador_id' => 'required|exists:rh_colaboradores,id',
            'concepto' => 'required|string|max:2000',
            'monto_cuota' => 'required|numeric|min:0.01',
            'num_pagos_total' => 'nullable|integer|min:1|max:9999',
            'modalidad' => ['required', Rule::in([
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

<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class StoreBancoTiempoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.banco_tiempo.crear');
    }

    public function rules(): array
    {
        return [
            'rh_colaborador_id' => 'required|exists:rh_colaboradores,id',
            'horas_pendientes'  => 'required|numeric|min:0.25|max:999.99',
            'origen_deuda'      => 'required|string|min:10|max:5000',
            'fecha_acuerdo'     => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'horas_pendientes.min' => 'El mínimo son 0.25 horas (15 minutos).',
            'origen_deuda.min'     => 'El origen de la deuda debe describirse con al menos 10 caracteres.',
        ];
    }
}

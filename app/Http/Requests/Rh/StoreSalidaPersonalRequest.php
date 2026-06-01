<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalidaPersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.salidas_personales.crear');
    }

    public function rules(): array
    {
        return [
            'rh_colaborador_id' => 'required|exists:rh_colaboradores,id',
            'fecha_evento' => 'required|date',
            'motivo' => 'required|string|min:5|max:1000',
            'hora_salida' => 'required', // can be time string or date format
            'evidencia_foto_salida' => 'required|image|max:10240',
            'hora_regreso' => 'nullable',
            'evidencia_foto_regreso' => 'nullable|image|max:10240',
            'fecha_deduccion_nomina' => 'nullable|date',
        ];
    }
}

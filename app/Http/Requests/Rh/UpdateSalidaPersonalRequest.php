<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalidaPersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.salidas_personales.editar');
    }

    public function rules(): array
    {
        return [
            'fecha_evento' => 'required|date',
            'motivo' => 'required|string|min:5|max:1000',
            'hora_salida' => 'required',
            'evidencia_foto_salida' => 'nullable|image|max:10240',
            'hora_regreso' => 'nullable',
            'evidencia_foto_regreso' => 'nullable|image|max:10240',
            'fecha_deduccion_nomina' => 'nullable|date',
        ];
    }
}

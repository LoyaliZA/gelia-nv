<?php

namespace App\Http\Requests\Activos;

use Illuminate\Foundation\Http\FormRequest;

class ProgramarMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('activos.cambiar_estado');
    }

    public function rules(): array
    {
        return [
            'tipo' => 'required|in:preventivo,correctivo,garantia',
            'fecha_programada' => 'nullable|date',
            'proveedor' => 'nullable|string|max:255',
            'costo' => 'nullable|numeric|min:0',
            'descripcion' => 'nullable|string|max:2000',
            'notas' => 'nullable|string|max:1000',
            'proximo_mantenimiento' => 'nullable|date',
        ];
    }
}

<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class SubirRemisionPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.auditar') ?? false;
    }

    public function rules(): array
    {
        return [
            'remision' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'fecha_emision' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'remision.required' => 'Debe seleccionar un archivo PDF.',
            'remision.mimes' => 'La remisión debe ser un archivo PDF.',
        ];
    }
}

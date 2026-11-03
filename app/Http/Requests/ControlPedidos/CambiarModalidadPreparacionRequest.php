<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class CambiarModalidadPreparacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('control_pedidos.preparacion.solicitar') ?? false)
            || ($this->user()?->can('control_pedidos.preparacion.corregir') ?? false);
    }

    public function rules(): array
    {
        return [
            'codigo_modalidad' => ['required', 'string', 'max:64'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'version' => ['nullable', 'integer', 'min:1'],
            'evidencia' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }
}

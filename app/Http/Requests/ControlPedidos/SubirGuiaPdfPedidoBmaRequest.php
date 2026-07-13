<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class SubirGuiaPdfPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.delegado') ?? false;
    }

    public function rules(): array
    {
        return [
            'guia_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'guia_pdf.required' => 'Debe seleccionar un archivo PDF.',
            'guia_pdf.mimes' => 'La guía debe ser un archivo PDF.',
        ];
    }
}

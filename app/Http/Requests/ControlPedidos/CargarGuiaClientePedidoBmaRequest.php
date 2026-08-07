<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class CargarGuiaClientePedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && ($user->can('control_pedidos.crear') || $user->can('control_pedidos.editar'));
    }

    public function rules(): array
    {
        return [
            'numero_rastreo' => ['required', 'string', 'min:3', 'max:100'],
            'guia_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_rastreo.required' => 'Debes capturar el número de guía.',
            'numero_rastreo.min' => 'El número de guía debe tener al menos 3 caracteres.',
            'guia_pdf.required' => 'Debes adjuntar el PDF de la guía del cliente.',
            'guia_pdf.mimes' => 'La guía debe ser un archivo PDF.',
        ];
    }
}

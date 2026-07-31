<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class SubirPdfPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'pdf_pedido' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'pdf_pedido.required' => 'Debe seleccionar el PDF del pedido.',
            'pdf_pedido.mimes' => 'El archivo debe ser un PDF.',
        ];
    }
}

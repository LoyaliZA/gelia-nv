<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class SubirAnexoPiezasPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'anexo_piezas' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'anexo_piezas.required' => 'Debe seleccionar el PDF o una foto de las piezas adicionales.',
            'anexo_piezas.mimes' => 'El archivo debe ser un PDF o una imagen (JPG, PNG o WEBP).',
        ];
    }
}

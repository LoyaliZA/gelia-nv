<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ImportarGuiasPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.delegado') ?? false;
    }

    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'max:10240',
                'mimes:csv,txt,xlsx,xls',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debes seleccionar un archivo CSV.',
            'archivo.mimes' => 'El archivo debe ser CSV o Excel.',
            'archivo.max' => 'El archivo no debe superar 10 MB.',
        ];
    }
}

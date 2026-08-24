<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class RegenerarCaratulaPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.tienda.regenerar_caratula') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo_regeneracion' => ['required', 'string', 'min:5', 'max:2000'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

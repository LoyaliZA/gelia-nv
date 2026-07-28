<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class RechazarAnexoEnvioPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.auditar') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}

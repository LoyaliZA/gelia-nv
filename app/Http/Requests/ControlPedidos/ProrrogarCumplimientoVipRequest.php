<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ProrrogarCumplimientoVipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.tienda.apartado.aprobar_prorroga') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

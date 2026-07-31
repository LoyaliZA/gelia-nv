<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarRepesajePedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->can('control_pedidos.crear') || $user->can('control_pedidos.editar'));
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', Rule::in(PedidoBma::MOTIVOS_REPESAJE)],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Indique el motivo del re-pesaje (cambio de pedido).',
            'motivo.in' => 'Motivo de re-pesaje no válido.',
        ];
    }
}

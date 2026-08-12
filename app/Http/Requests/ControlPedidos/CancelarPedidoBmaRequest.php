<?php

namespace App\Http\Requests\ControlPedidos;

use App\Services\ControlPedidos\CancelarPedidoBmaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelarPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.cancelar') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', Rule::in(array_keys(CancelarPedidoBmaService::MOTIVOS))],
            'comentario' => ['nullable', 'string', 'max:2000'],
            'resolucion_financiera' => [
                'nullable',
                'string',
                Rule::in([
                    CancelarPedidoBmaService::RESOLUCION_NINGUNA,
                    CancelarPedidoBmaService::RESOLUCION_PENDIENTE,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Seleccione el motivo de cancelación.',
            'motivo.in' => 'Motivo de cancelación no válido.',
        ];
    }
}

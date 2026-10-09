<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarNumeroRemisionPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.auditar') ?? false;
    }

    public function rules(): array
    {
        return [
            'numero_remision' => ['required', 'string', 'max:64'],
            'folio_remision' => ['prohibited'],
            'tipo_referencia' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_remision.required' => 'Escribe el número de remisión.',
            'folio_remision.prohibited' => 'El número de pedido lo captura la vendedora y no se modifica en auditoría.',
            'tipo_referencia.prohibited' => 'Esta operación registra únicamente el número de remisión.',
        ];
    }
}

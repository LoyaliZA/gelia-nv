<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarFolioRemisionPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.auditar') ?? false;
    }

    public function rules(): array
    {
        return [
            'folio_remision' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'folio_remision.required' => 'Indique el folio de pedido (Wizerp).',
        ];
    }
}

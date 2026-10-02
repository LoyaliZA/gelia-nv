<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaReferencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'tipo_referencia' => ['nullable', 'string', Rule::in([
                PedidoBmaReferencia::TIPO_COTIZACION,
                PedidoBmaReferencia::TIPO_PEDIDO,
                PedidoBmaReferencia::TIPO_REMISION,
                PedidoBmaReferencia::TIPO_OTRO,
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'folio_remision.required' => 'Indique el folio.',
        ];
    }
}

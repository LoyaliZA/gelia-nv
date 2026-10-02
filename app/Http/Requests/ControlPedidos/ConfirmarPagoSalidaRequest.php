<?php

namespace App\Http\Requests\ControlPedidos;

use App\Support\ControlPedidos\PoliticaSalidaPreparacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarPagoSalidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.salida.validar_pago') ?? false;
    }

    public function rules(): array
    {
        return [
            'condicion_cobro' => ['required', Rule::in([
                PoliticaSalidaPreparacion::COBRO_PAGADO,
                PoliticaSalidaPreparacion::COBRO_POR_COBRAR,
            ])],
            'folio_operacion' => ['nullable', 'string', 'max:80'],
            'pedido_bma_pago_id' => ['nullable', 'integer', 'min:1'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

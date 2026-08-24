<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\SaldosAFavor\PedidoBmaPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SustituirPagoPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && ($user->can('control_pedidos.pagos.sustituir') || $user->can('control_pedidos.crear'));
    }

    public function rules(): array
    {
        $forma = $this->input('forma_pago');

        return [
            'monto' => ['nullable', 'numeric', 'min:0.01'],
            'catalogo_banco_id' => [
                Rule::requiredIf(fn () => PedidoBmaPago::formaRequiereBanco($forma)),
                'nullable',
                'exists:catalogo_bancos,id',
            ],
            'forma_pago' => ['nullable', 'in:'.implode(',', PedidoBmaPago::FORMAS_PAGO)],
            'fecha_pago' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:128'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'comprobante' => ['required', 'file', 'max:10240'],
        ];
    }
}

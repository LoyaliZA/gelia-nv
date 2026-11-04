<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\SaldosAFavor\ResolverBancoCajaPdv;
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
        $pago = $this->route('pago');
        if (! $pago instanceof PedidoBmaPago && $pago) {
            $pago = PedidoBmaPago::query()->find($pago);
        }
        $pedido = $pago instanceof PedidoBmaPago ? $pago->pedido : null;
        $cobroEnTienda = $pedido && app(ResolverBancoCajaPdv::class)->esCobroEnTienda($pedido, is_string($forma) ? $forma : null);

        return [
            'monto' => ['nullable', 'numeric', 'min:0.01'],
            'catalogo_banco_id' => [
                Rule::requiredIf(fn () => ! $cobroEnTienda && PedidoBmaPago::formaRequiereBanco($forma)),
                'nullable',
                'exists:catalogo_bancos,id',
            ],
            'revision_esperada' => ['nullable', 'integer', 'min:0'],
            'total_mercancia_confirmado' => ['nullable', 'numeric', 'min:0'],
            'saldo_a_favor_confirmado' => ['nullable', 'numeric', 'min:0'],
            'forma_pago' => ['nullable', 'in:'.implode(',', PedidoBmaPago::FORMAS_PAGO)],
            'fecha_pago' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:128'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'comprobante' => ['nullable', 'file', 'max:10240'],
            'comprobantes' => [
                Rule::requiredIf(fn () => PedidoBmaPago::formaRequiereComprobante($forma) && ! $this->hasFile('comprobante')),
                'nullable',
                'array',
                'max:'.PedidoBmaPago::MAX_COMPROBANTES,
            ],
            'comprobantes.*' => ['file', 'max:10240'],
        ];
    }
}

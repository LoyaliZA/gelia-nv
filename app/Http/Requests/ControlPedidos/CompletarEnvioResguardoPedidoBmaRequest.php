<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Foundation\Http\FormRequest;

class CompletarEnvioResguardoPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && ($user->can('control_pedidos.crear') || $user->can('control_pedidos.editar'));
    }

    public function rules(): array
    {
        /** @var PedidoBma|null $pedido */
        $pedido = $this->route('pedidoBma');
        $pedido?->loadMissing('tipoOperacionEnvio');

        $requiereCaptura = $pedido
            && !$pedido->esComplemento()
            && ($pedido->esResguardoAbierto() || $pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_LIBERACION);

        if (!$requiereCaptura) {
            return [];
        }

        $pesoCajas = $pedido->tienePesajeRespondido()
            ? ['nullable', 'numeric', 'min:0']
            : ['required', 'numeric', 'min:0'];
        $numCajas = $pedido->tienePesajeRespondido()
            ? ['nullable', 'integer', 'min:0', 'max:999']
            : ['required', 'integer', 'min:0', 'max:999'];

        return [
            'peso_real_kg' => $pesoCajas,
            'numero_cajas' => $numCajas,
            'costo_envio' => ['required', 'numeric', 'gt:0'],
            'catalogo_banco_id' => ['required', 'exists:catalogo_bancos,id'],
            'comentarios' => ['nullable', 'string', 'max:2000'],
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }
}

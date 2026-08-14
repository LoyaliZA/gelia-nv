<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AtenderSinExistenciaPedidoBmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($this->input('accion') === 'cancelar') {
            return $user->can('control_pedidos.cancelar');
        }

        return $user->can('control_pedidos.crear') || $user->can('control_pedidos.editar');
    }

    public function rules(): array
    {
        $acciones = array_merge(PedidoBmaRevisionProducto::RESOLUCIONES, ['cancelar']);

        return [
            'revision_id' => ['required', 'integer'],
            'accion' => ['required', 'string', Rule::in($acciones)],
            'nota' => ['nullable', 'string', 'max:2000'],
            'comentario_cancelacion' => ['nullable', 'string', 'max:2000'],
            'total_mercancia' => ['nullable', 'numeric', 'min:0'],
            'cantidad_piezas' => ['nullable', 'integer', 'min:0'],
            'costo_envio' => ['nullable', 'numeric', 'min:0'],
            'aplica_seguro' => ['nullable', 'boolean'],
            'saldo_a_favor' => ['nullable', 'numeric', 'min:0'],
            'solicitar_repesaje' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'revision_id.required' => 'Indique la pieza sin existencias.',
            'accion.required' => 'Elija una acción.',
            'accion.in' => 'Acción no válida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'aplica_seguro' => filter_var($this->input('aplica_seguro', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'solicitar_repesaje' => filter_var($this->input('solicitar_repesaje', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}

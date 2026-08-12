<?php

namespace App\Http\Requests\ControlPedidos;

use App\Support\Clientes\Direcciones\ReglasValidacionDireccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ActualizarCamposDireccionPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.direcciones.editar') ?? false;
    }

    public function rules(): array
    {
        $irregular = $this->boolean('domicilio_irregular');
        $domicilio = ReglasValidacionDireccion::internas($irregular);

        return array_merge([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'cliente_direccion_id' => ['required', 'integer', 'exists:cliente_direcciones,id'],
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos_bma,id'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], $domicilio);
    }

    public function withValidator(Validator $validator): void
    {
        ReglasValidacionDireccion::afterIrregular($validator, $this->boolean('domicilio_irregular'));
    }
}

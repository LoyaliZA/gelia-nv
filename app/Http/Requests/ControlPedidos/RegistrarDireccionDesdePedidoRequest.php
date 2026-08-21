<?php

namespace App\Http\Requests\ControlPedidos;

use App\Support\Clientes\Direcciones\ReglasValidacionDireccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegistrarDireccionDesdePedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.direcciones.crear') ?? false;
    }

    public function rules(): array
    {
        $irregular = $this->boolean('domicilio_irregular');
        $domicilio = ReglasValidacionDireccion::internas($irregular);

        return array_merge([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos_bma,id'],
            'es_principal' => ['required', 'boolean'],
        ], $domicilio);
    }

    public function withValidator(Validator $validator): void
    {
        ReglasValidacionDireccion::afterIrregular($validator, $this->boolean('domicilio_irregular'));
    }
}

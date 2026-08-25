<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarPreparacionTiendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('control_pedidos.preparacion.solicitar') ?? false;
    }

    public function rules(): array
    {
        $esMunicipio = $this->input('codigo_modalidad') === CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO;

        return [
            'codigo_modalidad' => ['required', 'string', Rule::in(CatalogoModalidadPreparacionPedido::CODIGOS_SOLICITABLES)],
            'almacen_id' => ['required', 'integer', 'exists:almacenes,id'],
            'observaciones' => ['nullable', 'string', 'max:4000'],
            'idempotencia_clave' => ['nullable', 'string', 'max:64'],
            'destinatario_es_cliente' => [$esMunicipio ? 'required' : 'nullable', 'boolean'],
            'destinatario_nombre' => [$esMunicipio ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'destinatario_telefono' => [$esMunicipio ? 'nullable' : 'prohibited', 'string', 'max:40'],
            'municipio_destino' => [$esMunicipio ? 'required' : 'prohibited', 'string', 'max:255'],
            'direccion_referencia' => ['nullable', 'string', 'max:500'],
            'catalogo_paqueteria_id' => [$esMunicipio ? 'required' : 'prohibited', 'integer', 'exists:catalogo_paqueterias_pedido,id'],
            'modalidad_cobro' => [
                $esMunicipio ? 'required' : 'prohibited',
                'string',
                Rule::in([PedidoBmaCaratula::COBRO_PAGADO, PedidoBmaCaratula::COBRO_POR_COBRAR]),
            ],
        ];
    }
}

<?php

namespace App\Http\Requests\ControlPedidos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class PedidoBmaRequestBase extends FormRequest
{
    protected function reglasComunes(): array
    {
        return [
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'numero_cliente' => ['nullable', 'string', 'max:50'],
            'fecha' => ['nullable', 'date'],
            'catalogo_almacen_salida_id' => ['nullable', 'exists:catalogo_almacenes_salida,id'],
            'catalogo_banco_id' => ['nullable', 'exists:catalogo_bancos,id'],
            'requiere_factura' => ['nullable', 'boolean'],
            'aplica_saldo_favor' => ['nullable', 'boolean'],
            'saldo_a_favor' => ['nullable', 'numeric', 'min:0'],
            'catalogo_tipo_caja_id' => ['nullable', 'exists:catalogo_tipos_caja_pedido,id'],
            'numero_cajas' => ['nullable', 'integer', 'min:1', 'max:999'],
            'peso_real_kg' => ['nullable', 'numeric', 'min:0'],
            'peso_con_productos_kg' => ['nullable', 'numeric', 'min:0'],
            'catalogo_paqueteria_id' => ['nullable', 'exists:catalogo_paqueterias_pedido,id'],
            'catalogo_tipo_guia_id' => ['nullable', 'exists:catalogo_tipos_guia_pedido,id'],
            'catalogo_zona_id' => ['nullable', 'exists:catalogo_zonas_pedido,id'],
            'catalogo_envio_tienda_id' => ['nullable', 'exists:catalogo_envios_tienda,id'],
            'envio_tienda_otro' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $this->requiereEnvioTiendaOtro()),
            ],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'domicilio_entrega' => ['nullable', 'string'],
            'envia_a_otra_persona' => ['nullable', 'boolean'],
            'envia_otra_persona' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => filter_var($this->input('envia_a_otra_persona'), FILTER_VALIDATE_BOOLEAN)),
            ],
            'es_resguardo' => ['nullable', 'boolean'],
            'total_mercancia' => ['nullable', 'numeric', 'min:0'],
            'costo_envio' => ['nullable', 'numeric', 'min:0'],
            'aplica_seguro' => ['nullable', 'boolean'],
            'costo_seguro' => ['nullable', 'numeric', 'min:0'],
            'comentarios_drive' => ['nullable', 'string'],
            'comprobantes' => ['nullable', 'array'],
            'comprobantes.*' => ['file', 'image', 'max:10240'],
            'enviar' => ['nullable', 'boolean'],
        ];
    }

    protected function requiereEnvioTiendaOtro(): bool
    {
        $id = $this->input('catalogo_envio_tienda_id');
        if (!$id) {
            return false;
        }

        return \App\Models\ControlPedidos\CatalogoEnvioTienda::where('id', $id)
            ->where('es_otro', true)
            ->exists();
    }
}

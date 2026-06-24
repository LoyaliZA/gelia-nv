<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class StorePedidoContabilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contabilidad.pedidos.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'fecha_salida' => ['required', 'date'],
            'numero_pedido' => ['required', 'string', 'max:255', 'unique:contabilidad_pedidos,numero_pedido'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'tipo_transaccion' => ['required', 'string', 'max:50'],
            'plataforma_pago_id' => ['required', 'exists:contabilidad_plataformas_pago,id'],
            'venta_total' => ['required', 'numeric', 'min:0'],
            'costo_envio' => ['required', 'numeric', 'min:0'],
            'comision_real' => ['nullable', 'numeric', 'min:0'],
            'envio_pagado_cliente' => ['boolean'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.sku' => ['required', 'string', 'max:255'],
            'productos.*.nombre' => ['required', 'string', 'max:255'],
            'productos.*.piezas' => ['required', 'integer', 'min:1'],
            'productos.*.precio' => ['required', 'numeric', 'min:0'],
            'productos.*.tipo_devolucion' => ['sometimes', 'string', 'in:normal,devuelto,perdido_danado'],
        ];
    }
}

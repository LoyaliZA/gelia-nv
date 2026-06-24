<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePedidoContabilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contabilidad.pedidos.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo_transaccion' => ['required', 'string', 'max:50'],
            'plataforma_pago_id' => ['required', 'integer', 'exists:contabilidad_plataformas_pago,id'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'venta_total' => ['required', 'numeric', 'min:0'],
            'costo_envio' => ['required', 'numeric', 'min:0'],
            'comision_plataforma' => ['required', 'numeric', 'min:0'],
            'envio_pagado_cliente' => ['sometimes', 'boolean'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.id' => ['required', 'integer'],
            'productos.*.piezas' => ['required', 'integer', 'min:1'],
            'productos.*.tipo_devolucion' => ['sometimes', 'string', 'in:normal,devuelto,perdido_danado'],
        ];
    }
}

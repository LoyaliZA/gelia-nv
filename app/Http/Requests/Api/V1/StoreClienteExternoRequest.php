<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteExternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_cliente' => 'required|string|max:255|unique:clientes,numero_cliente',
            'nombre' => 'required|string|max:255',
            'nombre_razon_social' => 'nullable|string|max:255',
            'rfc' => 'nullable|string|max:13',
            'codigo_postal' => 'nullable|string|regex:/^\d{5}$/',
            'regimen_fiscal' => 'nullable|string|max:255',
            'correo_electronico' => 'nullable|email|max:255',
            'uso_factura' => 'nullable|string|max:255',
            'vendedor_id' => 'nullable|exists:users,id',
            'catalogo_tipo_cliente_id' => 'nullable|exists:catalogo_tipo_clientes,id',
            'monto_venta_actual' => 'nullable|numeric|min:0',
            'lista_actual_id' => 'nullable|exists:catalogo_listas_descuento,id',
            'lista_bloqueada' => 'nullable|boolean',
            'es_heredado' => 'nullable|boolean',
        ];
    }
}

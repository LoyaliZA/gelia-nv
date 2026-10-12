<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use Illuminate\Validation\Rule;

final class ReglasLineasPreparacion
{
    public static function reglas(bool $obligatorio = true): array
    {
        return [
            'lineas' => [$obligatorio ? 'required' : 'nullable', 'array', 'max:500'],
            'lineas.*.id' => ['nullable', 'integer', 'distinct'],
            'lineas.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'lineas.*.sku' => ['nullable', 'string', 'max:64'],
            'lineas.*.descripcion' => ['nullable', 'string', 'max:255'],
            'lineas.*.cantidad' => ['required', 'integer', 'min:0'],
            'lineas.*.estado_fisico' => ['required', Rule::in(PedidoBmaRevisionProducto::ESTADOS)],
            'lineas.*.observacion' => ['nullable', 'string', 'max:2000'],
            'lineas.*.evidencias' => ['nullable', 'array', 'max:10'],
            'lineas.*.evidencias.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}

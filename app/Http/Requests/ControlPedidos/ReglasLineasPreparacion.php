<?php

namespace App\Http\Requests\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use Illuminate\Validation\Rule;

final class ReglasLineasPreparacion
{
    /**
     * FormData convierte booleanos JS en "true"/"false"; la regla boolean de Laravel no los acepta.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizarMarcas(array $input): array
    {
        $lineas = $input['lineas'] ?? null;
        if (! is_array($lineas)) {
            return $input;
        }
        foreach ($lineas as $indice => $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $lineas[$indice]['unica_pieza'] = filter_var($linea['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $lineas[$indice]['mejor_ejemplar'] = filter_var($linea['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        $input['lineas'] = $lineas;

        return $input;
    }

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
            'lineas.*.unica_pieza' => ['nullable', 'boolean'],
            'lineas.*.mejor_ejemplar' => ['nullable', 'boolean'],
            'lineas.*.evidencias' => ['nullable', 'array', 'max:10'],
            'lineas.*.evidencias.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}

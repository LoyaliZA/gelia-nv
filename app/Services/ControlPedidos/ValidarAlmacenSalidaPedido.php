<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use Illuminate\Validation\ValidationException;

/** Rechaza usar como almacén de salida uno marcado solo como destino. Conservar el mismo id ya guardado sí se permite. */
class ValidarAlmacenSalidaPedido
{
    public function ejecutar(mixed $almacenId, mixed $almacenActualId = null): void
    {
        $nuevo = $this->id($almacenId);
        if ($nuevo === null) {
            return;
        }

        $actual = $this->id($almacenActualId);
        if ($actual !== null && $nuevo === $actual) {
            return;
        }

        $permite = Almacen::query()->whereKey($nuevo)->value('permite_salida_pedidos');
        if ($permite === null || filter_var($permite, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        throw ValidationException::withMessages([
            'almacen_id' => 'Este almacén no permite salida de mercancía. La sucursal puede usarse como destino.',
        ]);
    }

    private function id(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }
}

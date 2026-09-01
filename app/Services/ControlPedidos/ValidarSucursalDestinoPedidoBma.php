<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\Sucursal;
use Illuminate\Validation\ValidationException;

class ValidarSucursalDestinoPedidoBma
{
    public function ejecutar(
        PedidoBma $pedido,
        ?int $sucursalDestinoId,
        ?string $codigoModalidad = null,
        bool $exigirSiAplica = false,
    ): void {
        $id = $sucursalDestinoId !== null ? (int) $sucursalDestinoId : null;

        if ($pedido->prohibeSucursalDestino($codigoModalidad) && $id !== null) {
            throw ValidationException::withMessages([
                'sucursal_destino_id' => 'Este pedido no va a sucursal; no debe tener sucursal destino.',
            ]);
        }

        if ($exigirSiAplica && $pedido->requiereSucursalDestino($codigoModalidad) && $id === null) {
            throw ValidationException::withMessages([
                'sucursal_destino_id' => 'Indique la sucursal destino.',
            ]);
        }

        if ($id === null) {
            return;
        }

        $sucursal = Sucursal::query()->find($id);
        if (! $sucursal) {
            throw ValidationException::withMessages([
                'sucursal_destino_id' => 'La sucursal destino no existe.',
            ]);
        }

        if (! $sucursal->activo) {
            throw ValidationException::withMessages([
                'sucursal_destino_id' => 'La sucursal destino debe estar activa.',
            ]);
        }
    }
}

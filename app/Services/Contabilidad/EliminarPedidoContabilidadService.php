<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Pedido;

class EliminarPedidoContabilidadService
{
    public function ejecutar(Pedido $pedido): void
    {
        if ($pedido->bloqueado) {
            throw new \RuntimeException('El periodo está bloqueado.');
        }

        $pedido->delete();
    }
}

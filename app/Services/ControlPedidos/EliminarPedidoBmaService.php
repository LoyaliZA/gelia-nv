<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EliminarPedidoBmaService
{
    public function ejecutar(PedidoBma $pedido): void
    {
        if (!$pedido->esBorrador()) {
            throw new \RuntimeException('Solo se pueden eliminar pedidos en borrador.');
        }

        DB::transaction(function () use ($pedido) {
            foreach ($pedido->documentos as $documento) {
                Storage::disk('public')->delete($documento->ruta_archivo);
            }

            $pedido->delete();
        });
    }
}

<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EliminarPedidoBmaService
{
    public function __construct(
        private SincronizarAplicacionesPedidoSafService $safPedido,
    ) {}

    public function ejecutar(PedidoBma $pedido): void
    {
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeEliminarPreVenta()) {
            throw new \RuntimeException('Solo se pueden eliminar pedidos en borrador o pesaje pendiente.');
        }

        DB::transaction(function () use ($pedido) {
            $this->safPedido->liberarReservasPendientes($pedido, Auth::id());

            foreach ($pedido->documentos as $documento) {
                Storage::disk('public')->delete($documento->ruta_archivo);
            }

            $pedido->delete();
        });
    }
}

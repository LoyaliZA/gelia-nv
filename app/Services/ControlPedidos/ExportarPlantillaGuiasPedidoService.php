<?php

namespace App\Services\ControlPedidos;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Rap2hpoutre\FastExcel\FastExcel;

class ExportarPlantillaGuiasPedidoService
{
    public function __construct(
        private ListarPedidosDelegadoService $listarService,
    ) {}

    public function ejecutar(): StreamedResponse
    {
        $pedidos = $this->listarService->pedidosParaExportar();
        $nombreArchivo = 'plantilla_guias_' . date('Y-m-d_H-i-s') . '.csv';

        return (new FastExcel($pedidos))->download($nombreArchivo, function ($pedido) {
            return [
                'ID_Pedido' => $pedido->id,
                'Folio' => $pedido->folio_remision ?? '',
                'Paqueteria' => $pedido->paqueteria?->nombre ?? '',
                'Cliente' => $pedido->cliente?->nombre ?? '',
                'Guia_Rastreo' => '',
            ];
        });
    }
}

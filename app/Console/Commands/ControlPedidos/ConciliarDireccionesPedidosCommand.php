<?php

namespace App\Console\Commands\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDireccion;
use Illuminate\Console\Command;

class ConciliarDireccionesPedidosCommand extends Command
{
    protected $signature = 'pedidos:conciliar-direcciones';

    protected $description = 'Reporta diferencias entre domicilio_entrega y snapshot vigente';

    public function handle(): int
    {
        $diferencias = 0;
        $revisados = 0;

        PedidoBma::query()
            ->with('direccionVigente')
            ->whereHas('direccionVigente')
            ->orderBy('id')
            ->chunkById(100, function ($pedidos) use (&$diferencias, &$revisados) {
                foreach ($pedidos as $pedido) {
                    $revisados++;
                    $snap = $pedido->direccionVigente;
                    $textoSnap = trim((string) ($snap->domicilio_legacy ?? ''));
                    $textoPedido = trim((string) ($pedido->domicilio_entrega ?? ''));
                    if ($textoSnap !== '' && $textoPedido !== '' && $textoSnap !== $textoPedido) {
                        $diferencias++;
                        $this->line("Pedido {$pedido->id} ({$pedido->folio}): divergencia texto vs snapshot");
                    }
                }
            });

        $this->info("Revisados: {$revisados}. Diferencias: {$diferencias}.");

        return self::SUCCESS;
    }
}

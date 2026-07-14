<?php

namespace App\Console\Commands\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDireccion;
use Illuminate\Console\Command;

class CrearSnapshotsLegadoPedidosCommand extends Command
{
    protected $signature = 'pedidos:crear-snapshots-legado
                            {--dry-run : Simula sin escribir}
                            {--lote=100 : Tamaño de lote}';

    protected $description = 'Crea snapshots legacy de mejor esfuerzo desde domicilio_entrega sin inventar componentes';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $creados = 0;
        $omitidos = 0;

        PedidoBma::query()
            ->whereNotNull('domicilio_entrega')
            ->whereDoesntHave('direccionesSnapshot')
            ->orderBy('id')
            ->chunkById((int) $this->option('lote'), function ($pedidos) use ($dry, &$creados, &$omitidos) {
                foreach ($pedidos as $pedido) {
                    $texto = trim((string) $pedido->domicilio_entrega);
                    if ($texto === '') {
                        $omitidos++;
                        continue;
                    }
                    if ($dry) {
                        $creados++;
                        continue;
                    }
                    PedidoBmaDireccion::query()->create([
                        'pedido_bma_id' => $pedido->id,
                        'cliente_direccion_id' => $pedido->cliente_direccion_id,
                        'version_snapshot' => 1,
                        'es_vigente' => true,
                        'nombre_destinatario' => $pedido->envia_otra_persona ?: 'Histórico',
                        'codigo_postal' => $pedido->codigo_postal,
                        'domicilio_legacy' => $texto,
                        'origen' => PedidoBmaDireccion::ORIGEN_LEGACY,
                    ]);
                    $creados++;
                }
            });

        $this->table(['Resultado', 'Cantidad'], [
            ['Creados/simulados', $creados],
            ['Omitidos', $omitidos],
            ['Modo', $dry ? 'dry-run' : 'escritura'],
        ]);

        return self::SUCCESS;
    }
}

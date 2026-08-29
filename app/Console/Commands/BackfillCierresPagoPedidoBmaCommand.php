<?php

namespace App\Console\Commands;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Services\Reportes\PagosPedidos\RegistrarCierrePagoPedidoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCierresPagoPedidoBmaCommand extends Command
{
    protected $signature = 'reportes:backfill-cierres-pago
                            {--chunk=100 : Tamaño de lote}
                            {--from-id=0 : Reanudar desde pedido id}';

    protected $description = 'Crea cierres v1 (origen backfill) para pedidos con pago_validado_at';

    public function handle(RegistrarCierrePagoPedidoService $registrar): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $fromId = max(0, (int) $this->option('from-id'));

        $leidos = 0;
        $creados = 0;
        $omitidos = 0;
        $fallidos = 0;

        PedidoBma::query()
            ->whereNotNull('pago_validado_at')
            ->where('id', '>', $fromId)
            ->orderBy('id')
            ->chunkById($chunk, function ($pedidos) use ($registrar, &$leidos, &$creados, &$omitidos, &$fallidos) {
                foreach ($pedidos as $pedido) {
                    $leidos++;

                    $existe = PedidoBmaCierrePago::query()
                        ->where('pedido_bma_id', $pedido->id)
                        ->exists();

                    if ($existe) {
                        $omitidos++;
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($registrar, $pedido) {
                            $registrar->ejecutar(
                                $pedido->fresh(),
                                (int) ($pedido->pago_validado_por_id ?? 1),
                                PedidoBmaCierrePago::ORIGEN_BACKFILL
                            );
                        });
                        $creados++;
                    } catch (\Throwable $e) {
                        $fallidos++;
                        $this->warn("Pedido {$pedido->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Backfill cierres pago: leídos={$leidos}, creados={$creados}, omitidos={$omitidos}, fallidos={$fallidos}");

        logger()->info('reportes:backfill-cierres-pago', compact('leidos', 'creados', 'omitidos', 'fallidos'));

        return self::SUCCESS;
    }
}

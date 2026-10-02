<?php

namespace App\Jobs\WooCommerce;

use App\Models\Woocommerce\WoocommerceSyncLog;
use App\Services\WooCommerce\WooCommerceExportacionCsvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerarWooCommerceCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        protected int $syncLogId
    ) {}

    public function handle(WooCommerceExportacionCsvService $service): void
    {
        $log = WoocommerceSyncLog::find($this->syncLogId);
        if (! $log || $log->estado === 'cancelado') {
            return;
        }

        try {
            $service->ejecutar($log);
        } catch (Throwable $e) {
            $log->update([
                'estado' => 'error',
                'mensaje_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = WoocommerceSyncLog::find($this->syncLogId);
        if (! $log || $log->estado === 'completado') {
            return;
        }

        $log->update([
            'estado' => 'error',
            'mensaje_error' => $exception?->getMessage() ?? 'El proceso de exportación CSV fue interrumpido.',
        ]);
    }
}

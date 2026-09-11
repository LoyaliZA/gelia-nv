<?php

namespace App\Jobs\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use App\Services\Tiendanube\TiendanubeWebhookInboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncTiendanubeCatalogoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public int $syncLogId
    ) {}

    public function handle(
        TiendanubeCatalogoSyncService $service,
        TiendanubeOperacionTiendaService $operaciones,
        TiendanubeWebhookInboxService $inbox
    ): void {
        $log = TiendanubeSyncLog::find($this->syncLogId);
        if (! $log) {
            return;
        }

        try {
            if (! $this->generacionVigente($log)) {
                $log->update([
                    'estado' => 'error',
                    'fase' => 'error',
                    'mensaje_error' => 'La generación de configuración cambió; el trabajo no se ejecutó.',
                ]);

                return;
            }

            $service->sincronizar($log);
        } finally {
            $storeId = (int) ($log->store_id ?? 0);
            if ($storeId > 0) {
                $operaciones->liberar($storeId, null, $log->id);
                $inbox->liberarEntregasDiferidas($storeId);
            }
        }
    }

    public function failed(?\Throwable $e): void
    {
        $log = TiendanubeSyncLog::find($this->syncLogId);
        if (! $log) {
            return;
        }

        $log->update([
            'estado' => 'error',
            'fase' => 'error',
            'mensaje_error' => $e?->getMessage() ?? 'Error desconocido en sync Tiendanube.',
        ]);

        $storeId = (int) ($log->store_id ?? 0);
        if ($storeId > 0) {
            app(TiendanubeOperacionTiendaService::class)->liberar($storeId, null, $log->id);
            app(TiendanubeWebhookInboxService::class)->liberarEntregasDiferidas($storeId);
        }
    }

    private function generacionVigente(TiendanubeSyncLog $log): bool
    {
        $config = TiendanubeConfiguracion::obtener();

        if ($log->store_id && $config->store_id && (int) $log->store_id !== (int) $config->store_id) {
            return false;
        }

        if ($log->config_generation !== null && (int) $log->config_generation !== (int) ($config->config_generation ?: 1)) {
            return false;
        }

        return true;
    }
}

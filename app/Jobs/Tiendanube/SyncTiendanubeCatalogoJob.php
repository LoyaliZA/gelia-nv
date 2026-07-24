<?php

namespace App\Jobs\Tiendanube;

use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
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

    public function handle(TiendanubeCatalogoSyncService $service): void
    {
        $log = TiendanubeSyncLog::find($this->syncLogId);
        if (! $log) {
            return;
        }

        $service->sincronizar($log);
    }

    public function failed(?\Throwable $e): void
    {
        $log = TiendanubeSyncLog::find($this->syncLogId);
        if (! $log) {
            return;
        }

        $log->update([
            'estado' => 'error',
            'mensaje_error' => $e?->getMessage() ?? 'Error desconocido en sync Tiendanube.',
        ]);
    }
}

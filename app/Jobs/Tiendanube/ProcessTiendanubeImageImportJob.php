<?php

namespace App\Jobs\Tiendanube;

use App\Models\Tiendanube\TiendanubeImageImport;
use App\Services\Tiendanube\TiendanubeImageImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTiendanubeImageImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Por debajo del worker Sail/prod (--timeout=630); el servicio reencola por lotes. */
    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public int $importId
    ) {}

    public function handle(TiendanubeImageImportService $service): void
    {
        $import = TiendanubeImageImport::find($this->importId);
        if (! $import) {
            return;
        }

        $service->procesar($import);
    }

    public function failed(?\Throwable $e): void
    {
        $import = TiendanubeImageImport::find($this->importId);
        if (! $import) {
            return;
        }

        $import->update([
            'estado' => 'error',
            'mensaje_error' => $e?->getMessage() ?? 'Error desconocido en importación de imágenes.',
        ]);
    }
}

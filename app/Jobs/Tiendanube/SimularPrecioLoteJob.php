<?php

namespace App\Jobs\Tiendanube;

use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Models\Tiendanube\TiendanubePrecioLoteRevision;
use App\Models\Tiendanube\TiendanubePrecioLoteSimulacion;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteSimulacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SimularPrecioLoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public string $loteId,
        public int $revisionId,
        public int $userId,
    ) {}

    public function handle(
        TiendanubePrecioLoteSimulacionService $simulacion,
        TiendanubePrecioLoteService $lotes,
    ): void {
        $revision = TiendanubePrecioLoteRevision::query()->find($this->revisionId);
        if (! $revision) {
            return;
        }

        $lote = $revision->lote;
        if (! $lote || $lote->id !== $this->loteId) {
            return;
        }

        try {
            $simulacion->ejecutar($revision);
            $lotes->registrarEvento(
                $lote,
                $revision,
                TiendanubePrecioLoteEvento::TIPO_SIMULACION_COMPLETADA,
                $this->userId,
                ['total' => $revision->items()->count()]
            );
        } catch (\Throwable $e) {
            $revision->simulacion?->update([
                'estado' => TiendanubePrecioLoteSimulacion::ESTADO_ERROR,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            $lotes->registrarEvento(
                $lote,
                $revision,
                TiendanubePrecioLoteEvento::TIPO_SIMULACION_FALLIDA,
                $this->userId,
                ['error' => $e->getMessage()]
            );
            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        $revision = TiendanubePrecioLoteRevision::query()->find($this->revisionId);
        if (! $revision) {
            return;
        }
        $revision->simulacion?->update([
            'estado' => TiendanubePrecioLoteSimulacion::ESTADO_ERROR,
            'error' => $e?->getMessage() ?? 'Error desconocido en simulación de precios.',
            'completed_at' => now(),
        ]);
    }
}

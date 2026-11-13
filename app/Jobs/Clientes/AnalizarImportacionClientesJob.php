<?php

namespace App\Jobs\Clientes;

use App\Models\ImportacionCliente;
use App\Services\Clientes\RevisionImportacionClientes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AnalizarImportacionClientesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $importacionId) {}

    public function handle(RevisionImportacionClientes $revision): void
    {
        $importacion = ImportacionCliente::find($this->importacionId);
        if (! $importacion || ! in_array($importacion->estado, ['recibido', 'analizando'], true)) {
            return;
        }
        $marco = ImportacionCliente::query()
            ->whereKey($importacion->id)
            ->whereIn('estado', ['recibido', 'analizando'])
            ->update(['estado' => 'analizando', 'updated_at' => now()]);
        if ($marco === 0) {
            return;
        }
        $revision->previsualizar($importacion->fresh());
    }

    public function failed(Throwable $e): void
    {
        $importacion = ImportacionCliente::find($this->importacionId);
        if (! $importacion || in_array($importacion->estado, ['previsualizada', 'completo', 'parcial', 'cancelada'], true)) {
            return;
        }
        $importacion->update([
            'estado' => 'fallido',
            'mensaje_fatal' => mb_substr($e->getMessage(), 0, 500),
        ]);
    }
}

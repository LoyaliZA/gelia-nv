<?php

namespace App\Jobs\Clientes;

use App\Models\ImportacionCliente;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Clientes\RegistrarImportacionClienteService;
use App\Services\Clientes\RevisionImportacionClientes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcesarImportacionClientesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const LOTE = 40;

    public int $timeout = 55;

    public int $tries = 1;

    public function __construct(
        public int $importacionId,
        public string $token,
        public int $desdeFila = 0,
    ) {}

    public function handle(
        RevisionImportacionClientes $revision,
        ImportarClientesWizerpService $importador,
        RegistrarImportacionClienteService $registrar,
    ): void {
        $importacion = ImportacionCliente::find($this->importacionId);
        if (! $importacion || in_array($importacion->estado, ['completo', 'cancelada'], true)) {
            return;
        }
        $cursor = $importacion->cursorConfirmado();
        if ($cursor > $this->desdeFila) {
            return;
        }

        // shortcut: redispatch en vez de WithoutOverlapping->release, porque tries=1 marcaría el tramo como fallido.
        $candado = Cache::lock('importacion-cliente-'.$importacion->id, 70);
        if (! $candado->get()) {
            self::dispatch($importacion->id, $this->token, $this->desdeFila)->delay(now()->addSeconds(10));

            return;
        }
        $liberado = false;

        try {
            $importacion->refresh();
            $cursor = $importacion->cursorConfirmado();
            if ($cursor > $this->desdeFila || in_array($importacion->estado, ['completo', 'cancelada'], true)) {
                return;
            }
            if ($this->desdeFila === 0 && $cursor === 0) {
                try {
                    $cacheado = $revision->assertRevisionVigente($importacion, $this->token);
                } catch (ValidationException $e) {
                    $importacion->update([
                        'estado' => 'fallido',
                        'mensaje_fatal' => (string) collect($e->errors())->flatten()->first(),
                    ]);

                    return;
                }
                if ($cacheado !== null) {
                    return;
                }
            }

            $path = Storage::disk('local')->path($importacion->ruta_almacenamiento);
            $archivo = new UploadedFile($path, $importacion->nombre_archivo_original ?: 'clientes.csv', 'text/csv', null, true);
            try {
                $resultado = $importador->ejecutar($archivo, $importacion->fresh(), $this->desdeFila, self::LOTE);
            } catch (ValidationException $e) {
                $mensaje = (string) collect($e->errors())->flatten()->first();
                if (str_contains($mensaje, 'en curso')) {
                    $candado->release();
                    $liberado = true;
                    self::dispatch($importacion->id, $this->token, $this->desdeFila)->delay(now()->addSeconds(10));

                    return;
                }
                $importacion->refresh();
                if (! in_array($importacion->estado, ['parcial', 'fallido', 'completo'], true)) {
                    $importacion->update(['estado' => 'fallido', 'mensaje_fatal' => $mensaje]);
                }

                return;
            }

            if (! empty($resultado['hay_mas'])) {
                $candado->release();
                $liberado = true;
                self::dispatch($importacion->id, $this->token, (int) $resultado['cursor_fila']);

                return;
            }

            $importacion->refresh();
            $resumen = $importacion->resumen ?? [];
            if (is_array($resumen['revision'] ?? null)) {
                $resumen['revision']['aplicada'] = true;
                $resumen['revision']['resultado'] = $resultado;
            }
            $importacion->resumen = $resumen;
            $importacion->save();
            $registrar->finalizar($importacion, $resultado['stats'] ?? []);
        } finally {
            if (! $liberado) {
                $candado->release();
            }
        }
    }

    public function failed(Throwable $e): void
    {
        $importacion = ImportacionCliente::find($this->importacionId);
        if (! $importacion || in_array($importacion->estado, ['completo', 'parcial', 'previsualizada', 'cancelada'], true)) {
            return;
        }
        $importacion->update([
            'estado' => $importacion->filas_procesadas > 0 ? 'parcial' : 'fallido',
            'mensaje_fatal' => mb_substr($e->getMessage(), 0, 500),
        ]);
    }
}

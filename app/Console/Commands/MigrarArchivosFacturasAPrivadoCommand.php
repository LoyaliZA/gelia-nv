<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MigrarArchivosFacturasAPrivadoCommand extends Command
{
    protected $signature = 'facturas:migrar-archivos-privados {--dry-run : Solo listar sin mover}';

    protected $description = 'Mueve adjuntos de facturas de storage/app/public a storage/app/private (disco local).';

    public function handle(): int
    {
        $origenRoot = Storage::disk('public')->path('facturas');
        $destinoRoot = Storage::disk('local')->path('facturas');
        $dry = (bool) $this->option('dry-run');

        if (! is_dir($origenRoot)) {
            $this->info('No hay carpeta public/facturas; nada que migrar.');

            return self::SUCCESS;
        }

        $movidos = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($origenRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = 'facturas/'.ltrim(str_replace($origenRoot, '', $absolute), DIRECTORY_SEPARATOR);
            $relative = str_replace('\\', '/', $relative);
            $destino = Storage::disk('local')->path($relative);

            if ($dry) {
                $this->line("[dry-run] {$relative}");
                $movidos++;

                continue;
            }

            File::ensureDirectoryExists(dirname($destino));
            if (! is_file($destino)) {
                File::move($absolute, $destino);
            } else {
                File::delete($absolute);
            }
            $movidos++;
        }

        // Limpia dirs vacíos bajo public/facturas
        if (! $dry && is_dir($origenRoot)) {
            $this->limpiarDirsVacios($origenRoot);
        }

        $this->info(($dry ? 'Encontrados' : 'Migrados').": {$movidos} archivo(s). Destino: {$destinoRoot}");

        return self::SUCCESS;
    }

    private function limpiarDirsVacios(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->limpiarDirsVacios($path);
            }
        }

        if (count(scandir($dir) ?: []) === 2) {
            @rmdir($dir);
        }
    }
}

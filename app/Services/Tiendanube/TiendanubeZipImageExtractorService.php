<?php

namespace App\Services\Tiendanube;

use RuntimeException;
use ZipArchive;

class TiendanubeZipImageExtractorService
{
    /**
     * Inspecciona el ZIP y extrae imágenes permitidas a nombres internos.
     *
     * @return array{
     *     files: list<array{abs_path: string, relative_path: string, original_name: string}>,
     *     oversized: list<string>
     * }
     */
    public function extraer(string $zipAbs, string $extractAbs): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extensión ZipArchive no disponible.');
        }

        $maxEntries = max(1, (int) config('tiendanube.image_import_max_entries', 500));
        $maxFile = max(1, (int) config('tiendanube.image_import_max_file_bytes', 10 * 1024 * 1024));
        $maxTotal = max($maxFile, (int) config('tiendanube.image_import_max_total_uncompressed_bytes', 200 * 1024 * 1024));

        $zip = new ZipArchive;
        if ($zip->open($zipAbs) !== true) {
            throw new RuntimeException('No se pudo abrir el ZIP (archivo inválido o corrupto).');
        }

        try {
            $this->assertLimitesDeclarados($zip, $maxEntries, $maxFile, $maxTotal);

            if (! is_dir($extractAbs) && ! mkdir($extractAbs, 0755, true) && ! is_dir($extractAbs)) {
                throw new RuntimeException('No se pudo crear el directorio de extracción.');
            }

            $extractReal = realpath($extractAbs);
            if ($extractReal === false) {
                throw new RuntimeException('Directorio de extracción inválido.');
            }

            return $this->extraerSelectivo($zip, $extractReal, $maxFile, $maxTotal);
        } catch (\Throwable $e) {
            $this->limpiarDirectorio($extractAbs);
            throw $e;
        } finally {
            $zip->close();
        }
    }

    private function assertLimitesDeclarados(ZipArchive $zip, int $maxEntries, int $maxFile, int $maxTotal): void
    {
        $num = $zip->numFiles;
        if ($num > $maxEntries) {
            throw new RuntimeException("El ZIP supera el máximo de {$maxEntries} entradas.");
        }

        $total = 0;
        for ($i = 0; $i < $num; $i++) {
            $stat = $zip->statIndex($i);
            if (! is_array($stat)) {
                continue;
            }
            $name = (string) ($stat['name'] ?? '');
            if ($this->esEntradaIgnorada($name)) {
                continue;
            }
            if ($this->rutaInsegura($name)) {
                throw new RuntimeException('El ZIP contiene una ruta no permitida.');
            }
            $size = (int) ($stat['size'] ?? 0);
            $total += $size;
            if ($total > $maxTotal) {
                throw new RuntimeException('El ZIP supera el tamaño total descomprimido permitido.');
            }
        }
    }

    /**
     * @return array{
     *     files: list<array{abs_path: string, relative_path: string, original_name: string}>,
     *     oversized: list<string>
     * }
     */
    private function extraerSelectivo(ZipArchive $zip, string $extractReal, int $maxFile, int $maxTotal): array
    {
        $allowed = TiendanubeImageSkuParser::allowedExtensions();
        $files = [];
        $oversized = [];
        $totalWritten = 0;
        $seq = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! is_array($stat)) {
                continue;
            }
            $name = (string) ($stat['name'] ?? '');
            if ($this->esEntradaIgnorada($name)) {
                continue;
            }
            if ($this->rutaInsegura($name)) {
                throw new RuntimeException('El ZIP contiene una ruta no permitida.');
            }

            $base = basename(str_replace('\\', '/', $name));
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (! in_array($ext, $allowed, true)) {
                continue;
            }

            $declared = (int) ($stat['size'] ?? 0);
            if ($declared >= $maxFile) {
                $oversized[] = $base;
                continue;
            }

            $seq++;
            $stored = sprintf('%06d_%s.%s', $seq, bin2hex(random_bytes(8)), $ext);
            $absDest = $extractReal.DIRECTORY_SEPARATOR.$stored;

            $written = $this->copiarEntrada($zip, $name, $absDest, $maxFile, $maxTotal - $totalWritten);
            if ($written === null) {
                $oversized[] = $base;
                if (is_file($absDest)) {
                    @unlink($absDest);
                }
                continue;
            }

            $destReal = realpath($absDest);
            if ($destReal === false || ! str_starts_with($destReal, $extractReal.DIRECTORY_SEPARATOR)) {
                if ($destReal && is_file($destReal)) {
                    @unlink($destReal);
                }
                throw new RuntimeException('El ZIP contiene una ruta no permitida.');
            }

            $totalWritten += $written;
            $files[] = [
                'abs_path' => $destReal,
                'relative_path' => $stored,
                'original_name' => $base,
            ];
        }

        return [
            'files' => $files,
            'oversized' => $oversized,
        ];
    }

    private function copiarEntrada(ZipArchive $zip, string $name, string $absDest, int $maxFile, int $remainingTotal): ?int
    {
        $stream = $zip->getStream($name);
        if ($stream === false) {
            throw new RuntimeException('No se pudo leer una entrada del ZIP.');
        }

        $dest = fopen($absDest, 'wb');
        if ($dest === false) {
            fclose($stream);
            throw new RuntimeException('No se pudo escribir el archivo extraído.');
        }

        $written = 0;
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $written += strlen($chunk);
                if ($written >= $maxFile) {
                    return null;
                }
                if ($written > $remainingTotal) {
                    throw new RuntimeException('El ZIP supera el tamaño total descomprimido permitido.');
                }
                fwrite($dest, $chunk);
            }
        } finally {
            fclose($stream);
            fclose($dest);
        }

        return $written;
    }

    private function esEntradaIgnorada(string $name): bool
    {
        $normalized = str_replace('\\', '/', $name);
        if ($normalized === '' || str_ends_with($normalized, '/')) {
            return true;
        }
        $base = basename($normalized);
        if ($base === '' || str_starts_with($base, '.')) {
            return true;
        }
        if (str_contains($normalized, '__MACOSX')) {
            return true;
        }

        return false;
    }

    private function rutaInsegura(string $name): bool
    {
        $normalized = str_replace('\\', '/', $name);
        if ($normalized === '') {
            return true;
        }
        if (str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:/#', $normalized)) {
            return true;
        }
        foreach (explode('/', $normalized) as $part) {
            if ($part === '..') {
                return true;
            }
        }

        return false;
    }

    private function limpiarDirectorio(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
    }
}

<?php

namespace App\Services\Tiendanube;

use App\Jobs\Tiendanube\ProcessTiendanubeImageImportJob;
use App\Models\Tiendanube\TiendanubeImageImport;
use App\Models\Tiendanube\TiendanubeImageImportItem;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class TiendanubeImageImportService
{
    public const MOTIVO_NOMBRE_INVALIDO = 'nombre_invalido';

    public const MOTIVO_SKU_NO_ENCONTRADO = 'sku_no_encontrado';

    public const MOTIVO_ARCHIVO_GRANDE = 'archivo_grande';

    public const MOTIVO_ERROR_CARGA = 'error_carga';

    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private TiendanubeProductoWriteService $write
    ) {}

    public function iniciarDesdeZip(UploadedFile $zip, ?User $user = null): TiendanubeImageImport
    {
        if ($zip->getClientOriginalExtension() !== 'zip' && $zip->getMimeType() !== 'application/zip') {
            // aceptar por extensión aunque mime varíe
            if (strtolower($zip->getClientOriginalExtension()) !== 'zip') {
                throw new RuntimeException('El archivo debe ser un ZIP.');
            }
        }

        if (TiendanubeImageImport::activo()) {
            throw new RuntimeException('Ya hay una importación de imágenes en curso.');
        }

        $import = TiendanubeImageImport::create([
            'user_id' => $user?->id,
            'estado' => 'pendiente',
        ]);

        $dir = 'tiendanube/imports/'.$import->id;
        Storage::disk('local')->makeDirectory($dir);

        $zipStored = $zip->storeAs($dir, 'upload.zip', 'local');
        $extractRel = $dir.'/files';
        Storage::disk('local')->makeDirectory($extractRel);

        $zipAbs = Storage::disk('local')->path($zipStored);
        $extractAbs = Storage::disk('local')->path($extractRel);

        $this->extraerZip($zipAbs, $extractAbs);

        $archivos = $this->listarImagenes($extractAbs);
        if ($archivos === []) {
            $import->update([
                'estado' => 'error',
                'zip_path' => $zipStored,
                'extract_path' => $extractRel,
                'mensaje_error' => 'El ZIP no contiene imágenes válidas (jpg, jpeg, png, gif, webp).',
            ]);

            throw new RuntimeException($import->mensaje_error);
        }

        $items = [];
        foreach ($archivos as $absPath) {
            $relative = ltrim(str_replace($extractAbs, '', $absPath), DIRECTORY_SEPARATOR);
            $filename = basename($absPath);
            $parsed = TiendanubeImageSkuParser::parse($filename);

            if (! $parsed) {
                $items[] = [
                    'import_id' => $import->id,
                    'filename' => $filename,
                    'relative_path' => $relative,
                    'sku' => null,
                    'position' => 1,
                    'producto_id' => null,
                    'estado' => 'omitido',
                    'motivo' => self::MOTIVO_NOMBRE_INVALIDO,
                    'mensaje' => 'Nombre de archivo no válido (usa SKU.ext o SKU_n.ext).',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                continue;
            }

            if (@filesize($absPath) === false || filesize($absPath) >= self::MAX_FILE_BYTES) {
                $items[] = [
                    'import_id' => $import->id,
                    'filename' => $filename,
                    'relative_path' => $relative,
                    'sku' => $parsed['sku'],
                    'position' => $parsed['position'],
                    'producto_id' => null,
                    'estado' => 'error',
                    'motivo' => self::MOTIVO_ARCHIVO_GRANDE,
                    'mensaje' => 'Archivo >= 10 MB.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                continue;
            }

            $variante = TiendanubeProductoVariante::where('sku', $parsed['sku'])->orderBy('id')->first();
            if (! $variante) {
                $items[] = [
                    'import_id' => $import->id,
                    'filename' => $filename,
                    'relative_path' => $relative,
                    'sku' => $parsed['sku'],
                    'position' => $parsed['position'],
                    'producto_id' => null,
                    'estado' => 'error',
                    'motivo' => self::MOTIVO_SKU_NO_ENCONTRADO,
                    'mensaje' => 'SKU no encontrado en el catálogo. Sincroniza productos primero.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                continue;
            }

            $items[] = [
                'import_id' => $import->id,
                'filename' => $filename,
                'relative_path' => $relative,
                'sku' => $parsed['sku'],
                'position' => $parsed['position'],
                'producto_id' => $variante->producto_id,
                'estado' => 'pendiente',
                'motivo' => null,
                'mensaje' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($items, 200) as $chunk) {
            TiendanubeImageImportItem::insert($chunk);
        }

        $fallidosPrevios = collect($items)->whereIn('estado', ['error', 'omitido'])->count();

        $import->update([
            'estado' => 'pendiente',
            'total_archivos' => count($items),
            'procesados' => $fallidosPrevios,
            'exitosos' => 0,
            'fallidos' => $fallidosPrevios,
            'zip_path' => $zipStored,
            'extract_path' => $extractRel,
        ]);

        ProcessTiendanubeImageImportJob::dispatch($import->id);

        return $import->fresh(['items']);
    }

    public function procesar(TiendanubeImageImport $import): void
    {
        $import->update(['estado' => 'en_proceso', 'mensaje_error' => null]);

        $extractAbs = $import->extract_path
            ? Storage::disk('local')->path($import->extract_path)
            : null;

        if (! $extractAbs || ! is_dir($extractAbs)) {
            $import->update([
                'estado' => 'error',
                'mensaje_error' => 'No se encontró la carpeta extraída del ZIP.',
            ]);

            return;
        }

        $pendientes = $import->items()->where('estado', 'pendiente')->orderBy('id')->get();

        foreach ($pendientes as $item) {
            try {
                $abs = $extractAbs.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $item->relative_path);
                if (! is_file($abs)) {
                    throw new RuntimeException('Archivo no encontrado tras extracción.');
                }

                $uploaded = new UploadedFile(
                    $abs,
                    $item->filename,
                    mime_content_type($abs) ?: 'application/octet-stream',
                    null,
                    true
                );

                $imagen = $this->write->agregarImagen(
                    (int) $item->producto_id,
                    null,
                    $uploaded,
                    (int) $item->position,
                    true
                );

                $item->update([
                    'estado' => 'ok',
                    'mensaje' => null,
                    'imagen_tn_id' => $imagen->id,
                ]);
                $import->increment('exitosos');
            } catch (\Throwable $e) {
                $item->update([
                    'estado' => 'error',
                    'motivo' => self::MOTIVO_ERROR_CARGA,
                    'mensaje' => $e->getMessage(),
                ]);
                $import->increment('fallidos');
            }

            $import->increment('procesados');
            $import->touch();
        }

        $import->update([
            'estado' => 'completado',
            'mensaje_error' => null,
        ]);
    }

    private function extraerZip(string $zipAbs, string $extractAbs): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extensión ZipArchive no disponible.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipAbs) !== true) {
            throw new RuntimeException('No se pudo abrir el ZIP.');
        }

        $zip->extractTo($extractAbs);
        $zip->close();
    }

    /**
     * @return list<string> absolute paths
     */
    private function listarImagenes(string $extractAbs): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractAbs, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }
            // Ignorar metadatos macOS
            if (str_contains($file->getPathname(), '__MACOSX') || str_starts_with($file->getFilename(), '.')) {
                continue;
            }
            if (! in_array(strtolower($file->getExtension()), TiendanubeImageSkuParser::allowedExtensions(), true)) {
                continue;
            }
            $out[] = $file->getPathname();
        }

        sort($out);

        return $out;
    }
}

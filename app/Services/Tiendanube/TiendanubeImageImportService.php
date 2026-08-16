<?php

namespace App\Services\Tiendanube;

use App\Jobs\Tiendanube\ProcessTiendanubeImageImportJob;
use App\Models\Tiendanube\TiendanubeImageImport;
use App\Models\Tiendanube\TiendanubeImageImportItem;
use App\Models\Tiendanube\TiendanubeProductoImagen;
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

    /** Ítems por job: cabe cómodo bajo queue --timeout=630 (~1–2s/img). */
    public const BATCH_SIZE = 25;

    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private TiendanubeProductoWriteService $write
    ) {}

    /**
     * @param  array{convertir_webp?: bool, modo_1280?: string}  $optImagen
     */
    public function iniciarDesdeZip(UploadedFile $zip, ?User $user = null, array $optImagen = []): TiendanubeImageImport
    {
        if ($zip->getClientOriginalExtension() !== 'zip' && $zip->getMimeType() !== 'application/zip') {
            if (strtolower($zip->getClientOriginalExtension()) !== 'zip') {
                throw new RuntimeException('El archivo debe ser un ZIP.');
            }
        }

        if (TiendanubeImageImport::activo()) {
            throw new RuntimeException('Ya hay una importación de imágenes en curso.');
        }

        $opciones = OptimizarImagenTiendanubeService::normalizarOpciones($optImagen);
        $import = TiendanubeImageImport::create([
            'user_id' => $user?->id,
            'estado' => 'pendiente',
            'reemplazar_primera' => true,
            'convertir_webp' => $opciones['convertir_webp'],
            'modo_1280' => $opciones['modo_1280'],
        ]);

        $dir = 'tiendanube/imports/'.$import->id;
        Storage::disk('local')->makeDirectory($dir);

        $zipStored = $zip->storeAs($dir, 'upload.zip', 'local');
        $extractRel = $dir.'/files';
        Storage::disk('local')->makeDirectory($extractRel);

        $import->update([
            'zip_path' => $zipStored,
            'extract_path' => $extractRel,
            'estado' => 'pendiente',
            'mensaje_error' => null,
        ]);

        ProcessTiendanubeImageImportJob::dispatch($import->id);

        return $import->fresh(['items']);
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  array{convertir_webp?: bool, modo_1280?: string}  $optImagen
     */
    public function iniciarDesdeArchivos(array $files, ?User $user = null, bool $reemplazarPrimera = true, array $optImagen = []): TiendanubeImageImport
    {
        if ($files === []) {
            throw new RuntimeException('No se recibieron imágenes.');
        }

        if (TiendanubeImageImport::activo()) {
            throw new RuntimeException('Ya hay una importación de imágenes en curso.');
        }

        $opciones = OptimizarImagenTiendanubeService::normalizarOpciones($optImagen);
        $import = TiendanubeImageImport::create([
            'user_id' => $user?->id,
            'estado' => 'pendiente',
            'reemplazar_primera' => $reemplazarPrimera,
            'convertir_webp' => $opciones['convertir_webp'],
            'modo_1280' => $opciones['modo_1280'],
        ]);

        $dir = 'tiendanube/imports/'.$import->id;
        $extractRel = $dir.'/files';
        Storage::disk('local')->makeDirectory($extractRel);
        $extractAbs = Storage::disk('local')->path($extractRel);

        $paths = [];
        foreach ($files as $i => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file->getClientOriginalName()) ?: ('img_'.$i.'.bin');
            $storedName = sprintf('%04d_%s', $i + 1, $safe);
            $file->storeAs($extractRel, $storedName, 'local');
            $paths[] = $extractAbs.DIRECTORY_SEPARATOR.$storedName;
        }

        if ($paths === []) {
            $import->update([
                'estado' => 'error',
                'extract_path' => $extractRel,
                'mensaje_error' => 'No se pudieron guardar las imágenes.',
            ]);
            throw new RuntimeException($import->mensaje_error);
        }

        $import->update([
            'zip_path' => null,
            'extract_path' => $extractRel,
            'mensaje_error' => null,
        ]);

        $this->indexarArchivos($import, $paths, $extractAbs);

        ProcessTiendanubeImageImportJob::dispatch($import->id);

        return $import->fresh(['items']);
    }

    public function procesar(TiendanubeImageImport $import): void
    {
        if ($import->items()->doesntExist()) {
            $this->prepararDesdeZip($import);
            $import->refresh();
            if ($import->estado === 'error') {
                return;
            }
        }

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

        $pendientes = $import->items()
            ->where('estado', 'pendiente')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        $reemplazarPrimera = (bool) $import->reemplazar_primera;

        $productoIdsLote = [];

        foreach ($pendientes as $item) {
            try {
                $abs = $extractAbs.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $item->relative_path);
                if (! is_file($abs)) {
                    throw new RuntimeException('Archivo no encontrado tras extracción.');
                }

                $detectedMime = mime_content_type($abs) ?: 'application/octet-stream';
                $uploaded = new UploadedFile(
                    $abs,
                    $item->filename,
                    $detectedMime,
                    null,
                    true
                );

                $reemplazar = $reemplazarPrimera && ! $import->items()
                    ->where('producto_id', $item->producto_id)
                    ->where('estado', 'ok')
                    ->exists();

                $imagen = $this->write->agregarImagen(
                    (int) $item->producto_id,
                    null,
                    $uploaded,
                    (int) $item->position,
                    $reemplazar,
                    [
                        'convertir_webp' => (bool) $import->convertir_webp,
                        'modo_1280' => (string) ($import->modo_1280 ?: OptimizarImagenTiendanubeService::MODO_NONE),
                    ]
                );

                $item->update([
                    'estado' => 'ok',
                    'mensaje' => null,
                    'imagen_tn_id' => $imagen->id,
                ]);
                $import->increment('exitosos');
                if ($item->producto_id) {
                    $productoIdsLote[] = (int) $item->producto_id;
                }
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

        if ($productoIdsLote !== []) {
            $this->write->refrescarSrcsTemporalesDeProductos($productoIdsLote);
        }

        $quedan = $import->items()->where('estado', 'pendiente')->count();
        if ($quedan > 0) {
            ProcessTiendanubeImageImportJob::dispatch($import->id);

            return;
        }

        $import->update([
            'estado' => 'completado',
            'mensaje_error' => null,
        ]);
    }

    public function contarAlertasDimension(TiendanubeImageImport $import): int
    {
        $ids = $import->items()
            ->where('estado', 'ok')
            ->whereNotNull('imagen_tn_id')
            ->pluck('imagen_tn_id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return TiendanubeProductoImagen::query()
            ->whereIn('id', $ids)
            ->where('requiere_revision', true)
            ->count();
    }

    private function prepararDesdeZip(TiendanubeImageImport $import): void
    {
        $zipStored = $import->zip_path;
        $extractRel = $import->extract_path;

        if (! $zipStored || ! $extractRel) {
            $import->update([
                'estado' => 'error',
                'mensaje_error' => 'Falta la ruta del ZIP o de extracción.',
            ]);

            return;
        }

        $zipAbs = Storage::disk('local')->path($zipStored);
        $extractAbs = Storage::disk('local')->path($extractRel);

        if (! is_file($zipAbs)) {
            $import->update([
                'estado' => 'error',
                'mensaje_error' => 'No se encontró el ZIP subido.',
            ]);

            return;
        }

        Storage::disk('local')->makeDirectory($extractRel);
        $this->extraerZip($zipAbs, $extractAbs);

        $archivos = $this->listarImagenes($extractAbs);
        if ($archivos === []) {
            $import->update([
                'estado' => 'error',
                'mensaje_error' => 'El ZIP no contiene imágenes válidas (jpg, jpeg, png, gif, webp).',
            ]);

            return;
        }

        $this->indexarArchivos($import, $archivos, $extractAbs);
    }

    /**
     * @param  list<string>  $absPaths
     */
    private function indexarArchivos(TiendanubeImageImport $import, array $absPaths, string $extractAbs): void
    {
        $items = [];
        foreach ($absPaths as $absPath) {
            $relative = ltrim(str_replace($extractAbs, '', $absPath), DIRECTORY_SEPARATOR);
            $filename = basename($absPath);
            // Quitar prefijo 0001_ de cargas por archivo suelto para parsear SKU del nombre original
            $parseName = preg_replace('/^\d{4}_/', '', $filename) ?: $filename;
            $parsed = TiendanubeImageSkuParser::parse($parseName);

            if (! $parsed) {
                $items[] = [
                    'import_id' => $import->id,
                    'filename' => $parseName,
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
                    'filename' => $parseName,
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
                    'filename' => $parseName,
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
                'filename' => $parseName,
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
            'total_archivos' => count($items),
            'procesados' => $fallidosPrevios,
            'exitosos' => 0,
            'fallidos' => $fallidosPrevios,
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

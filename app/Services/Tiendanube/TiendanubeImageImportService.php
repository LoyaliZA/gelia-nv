<?php

namespace App\Services\Tiendanube;

use App\Jobs\Tiendanube\ProcessTiendanubeImageImportJob;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeImageImport;
use App\Models\Tiendanube\TiendanubeImageImportItem;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TiendanubeImageImportService
{
    public const MOTIVO_NOMBRE_INVALIDO = 'nombre_invalido';

    public const MOTIVO_SKU_NO_ENCONTRADO = 'sku_no_encontrado';

    public const MOTIVO_SKU_AMBIGUO = 'sku_ambiguo';

    public const MOTIVO_ARCHIVO_GRANDE = 'archivo_grande';

    public const MOTIVO_ERROR_CARGA = 'error_carga';

    /** Ítems por job: cabe cómodo bajo queue --timeout=630 (~1–2s/img). */
    public const BATCH_SIZE = 25;

    public function __construct(
        private TiendanubeProductoWriteService $write,
        private TiendanubeOperacionTiendaService $operaciones,
        private TiendanubeImageSkuResolverService $skuResolver,
        private TiendanubeZipImageExtractorService $zipExtractor
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

        $this->assertImportacionAdmisible();

        $opciones = OptimizarImagenTiendanubeService::normalizarOpciones($optImagen);
        $config = TiendanubeConfiguracion::obtener();
        $import = TiendanubeImageImport::create([
            'user_id' => $user?->id,
            'store_id' => $config->store_id,
            'config_generation' => (int) ($config->config_generation ?: 1),
            'estado' => TiendanubeImageImport::ESTADO_VALIDANDO,
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
            'estado' => TiendanubeImageImport::ESTADO_VALIDANDO,
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

        $this->assertImportacionAdmisible();

        $opciones = OptimizarImagenTiendanubeService::normalizarOpciones($optImagen);
        $config = TiendanubeConfiguracion::obtener();
        $import = TiendanubeImageImport::create([
            'user_id' => $user?->id,
            'store_id' => $config->store_id,
            'config_generation' => (int) ($config->config_generation ?: 1),
            'estado' => TiendanubeImageImport::ESTADO_VALIDANDO,
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
            $paths[] = [
                'abs_path' => $extractAbs.DIRECTORY_SEPARATOR.$storedName,
                'relative_path' => $storedName,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        if ($paths === []) {
            $import->update([
                'estado' => TiendanubeImageImport::ESTADO_ERROR,
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

        $this->indexarArchivos($import, $paths);
        $this->finalizarIndexado($import);

        return $import->fresh(['items']);
    }

    public function procesar(TiendanubeImageImport $import): void
    {
        if ($import->items()->doesntExist() && $import->zip_path) {
            $this->prepararDesdeZip($import);
            $import->refresh();

            return;
        }

        if (! in_array($import->estado, TiendanubeImageImport::ESTADOS_EJECUTABLES, true)) {
            return;
        }

        $legacy = in_array($import->estado, [
            TiendanubeImageImport::ESTADO_PENDIENTE,
            TiendanubeImageImport::ESTADO_EN_PROCESO,
        ], true);

        if (! $import->confirmado_at && ! $legacy) {
            return;
        }

        $this->ejecutarLote($import);
    }

    /**
     * @param  list<array{id: int, producto_id?: int|null, excluido?: bool}>  $filas
     */
    public function confirmarRevision(TiendanubeImageImport $import, array $filas): TiendanubeImageImport
    {
        if (! in_array($import->estado, [
            TiendanubeImageImport::ESTADO_REQUIERE_REVISION,
            TiendanubeImageImport::ESTADO_LISTA,
        ], true)) {
            throw new RuntimeException('La importación no está pendiente de revisión.');
        }

        $porId = [];
        foreach ($filas as $fila) {
            if (! isset($fila['id'])) {
                continue;
            }
            $porId[(int) $fila['id']] = $fila;
        }

        $items = $import->items()->whereIn('estado', [
            TiendanubeImageImportItem::ESTADO_PENDIENTE,
            TiendanubeImageImportItem::ESTADO_REQUIERE_SELECCION,
        ])->get();

        foreach ($items as $item) {
            $fila = $porId[$item->id] ?? [];
            $excluido = (bool) ($fila['excluido'] ?? false);

            if ($excluido) {
                $item->update([
                    'excluido' => true,
                    'estado' => TiendanubeImageImportItem::ESTADO_OMITIDO,
                    'mensaje' => 'Excluido en la revisión del lote.',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                ]);

                continue;
            }

            $productoId = isset($fila['producto_id']) ? (int) $fila['producto_id'] : (int) ($item->producto_id ?? 0);

            if ($item->estado === TiendanubeImageImportItem::ESTADO_REQUIERE_SELECCION) {
                $candidatos = collect($item->candidatos_json ?? [])->pluck('producto_id')->map(fn ($id) => (int) $id);
                if ($productoId <= 0 || ! $candidatos->contains($productoId)) {
                    throw new RuntimeException('Hay que elegir un destino válido para '.$item->filename.'.');
                }
            }

            if ($productoId <= 0) {
                throw new RuntimeException('Falta el producto destino para '.$item->filename.'.');
            }

            $producto = TiendanubeProducto::query()->find($productoId);
            if (! $producto) {
                throw new RuntimeException('El producto #'.$productoId.' ya no está en el catálogo. Sincroniza e intenta de nuevo.');
            }

            $item->update([
                'excluido' => false,
                'producto_id' => $productoId,
                'estado' => TiendanubeImageImportItem::ESTADO_PENDIENTE,
                'resolucion_estado' => TiendanubeImageSkuResolverService::ESTADO_ENCONTRADO,
                'motivo' => null,
                'mensaje' => null,
            ]);
        }

        $quedanAmbigua = $import->items()->where('estado', TiendanubeImageImportItem::ESTADO_REQUIERE_SELECCION)->exists();
        if ($quedanAmbigua) {
            throw new RuntimeException('Hay filas ambiguas sin destino. Elige un producto o excluye la fila.');
        }

        $import->update([
            'confirmado_at' => now(),
            'estado' => TiendanubeImageImport::ESTADO_LISTA,
            'mensaje_error' => null,
        ]);

        $this->recalcularTotales($import);
        $import->refresh();

        if ($import->items()->where('estado', TiendanubeImageImportItem::ESTADO_PENDIENTE)->where('excluido', false)->doesntExist()) {
            $this->marcarTerminal($import);

            return $import->fresh(['items']);
        }

        ProcessTiendanubeImageImportJob::dispatch($import->id);

        return $import->fresh(['items']);
    }

    public function reintentarFallidos(TiendanubeImageImport $import): TiendanubeImageImport
    {
        if (! in_array($import->estado, [
            TiendanubeImageImport::ESTADO_COMPLETADO,
            TiendanubeImageImport::ESTADO_COMPLETADO_CON_INCIDENCIAS,
            TiendanubeImageImport::ESTADO_ERROR,
            TiendanubeImageImport::ESTADO_LISTA,
            TiendanubeImageImport::ESTADO_PROCESANDO,
        ], true)) {
            throw new RuntimeException('No se pueden reintentar ítems en el estado actual.');
        }

        $actualizados = $import->items()
            ->where('estado', TiendanubeImageImportItem::ESTADO_ERROR)
            ->where('motivo', self::MOTIVO_ERROR_CARGA)
            ->where('excluido', false)
            ->update([
                'estado' => TiendanubeImageImportItem::ESTADO_PENDIENTE,
                'motivo' => null,
                'mensaje' => null,
                'claim_token' => null,
                'claim_expires_at' => null,
            ]);

        if ($actualizados === 0) {
            throw new RuntimeException('No hay errores recuperables para reintentar.');
        }

        $import->update([
            'confirmado_at' => $import->confirmado_at ?? now(),
            'estado' => TiendanubeImageImport::ESTADO_LISTA,
            'mensaje_error' => null,
        ]);
        $this->recalcularTotales($import);

        ProcessTiendanubeImageImportJob::dispatch($import->id);

        return $import->fresh(['items']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filasRevision(TiendanubeImageImport $import): array
    {
        $reemplazarPrimera = (bool) $import->reemplazar_primera;
        $vistos = [];

        return $import->items()->orderBy('id')->get()->map(function (TiendanubeImageImportItem $item) use ($reemplazarPrimera, &$vistos) {
            $modo = 'agregar';
            if ($reemplazarPrimera && $item->producto_id && empty($vistos[$item->producto_id])) {
                $modo = 'reemplazar_primera';
                $vistos[$item->producto_id] = true;
            }

            return [
                'id' => $item->id,
                'filename' => $item->filename,
                'sku' => $item->sku,
                'position' => $item->position,
                'estado' => $item->estado,
                'motivo' => $item->motivo,
                'mensaje' => $item->mensaje,
                'producto_id' => $item->producto_id,
                'resolucion_estado' => $item->resolucion_estado,
                'candidatos' => $item->candidatos_json ?? [],
                'excluido' => (bool) $item->excluido,
                'modo_reemplazo' => $modo,
            ];
        })->all();
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

    public function limpiarTemporalesVencidos(): int
    {
        $dias = max(1, (int) config('tiendanube.image_import_retention_days', 7));
        $limite = now()->subDays($dias);
        $eliminados = 0;

        TiendanubeImageImport::query()
            ->whereIn('estado', TiendanubeImageImport::ESTADOS_TERMINALES)
            ->where('updated_at', '<', $limite)
            ->orderBy('id')
            ->each(function (TiendanubeImageImport $import) use (&$eliminados) {
                $dir = 'tiendanube/imports/'.$import->id;
                if (! Storage::disk('local')->exists($dir)) {
                    return;
                }
                Storage::disk('local')->deleteDirectory($dir);
                $eliminados++;
            });

        return $eliminados;
    }

    private function prepararDesdeZip(TiendanubeImageImport $import): void
    {
        $zipStored = $import->zip_path;
        $extractRel = $import->extract_path;

        if (! $zipStored || ! $extractRel) {
            $import->update([
                'estado' => TiendanubeImageImport::ESTADO_ERROR,
                'mensaje_error' => 'Falta la ruta del ZIP o de extracción.',
            ]);

            return;
        }

        $zipAbs = Storage::disk('local')->path($zipStored);
        $extractAbs = Storage::disk('local')->path($extractRel);

        if (! is_file($zipAbs)) {
            $import->update([
                'estado' => TiendanubeImageImport::ESTADO_ERROR,
                'mensaje_error' => 'No se encontró el ZIP subido.',
            ]);

            return;
        }

        Storage::disk('local')->makeDirectory($extractRel);

        try {
            $resultado = $this->zipExtractor->extraer($zipAbs, $extractAbs);
        } catch (\Throwable $e) {
            $import->update([
                'estado' => TiendanubeImageImport::ESTADO_ERROR,
                'mensaje_error' => $e->getMessage(),
            ]);

            return;
        }

        $paths = $resultado['files'];
        foreach ($resultado['oversized'] as $name) {
            $paths[] = [
                'abs_path' => null,
                'relative_path' => null,
                'original_name' => $name,
                'oversized' => true,
            ];
        }

        if ($paths === []) {
            $import->update([
                'estado' => TiendanubeImageImport::ESTADO_ERROR,
                'mensaje_error' => 'El ZIP no contiene imágenes válidas (jpg, jpeg, png, gif, webp).',
            ]);

            return;
        }

        $this->indexarArchivos($import, $paths);
        $this->finalizarIndexado($import);
    }

    /**
     * @param  list<array{abs_path: string|null, relative_path: string|null, original_name: string, oversized?: bool}>  $archivos
     */
    private function indexarArchivos(TiendanubeImageImport $import, array $archivos): void
    {
        $maxFile = max(1, (int) config('tiendanube.image_import_max_file_bytes', 10 * 1024 * 1024));
        $items = [];
        $now = now();

        foreach ($archivos as $archivo) {
            $original = $archivo['original_name'];
            $parseName = preg_replace('/^\d{4}_/', '', $original) ?: $original;
            $relative = $archivo['relative_path'];

            if (! empty($archivo['oversized'])) {
                $parsed = TiendanubeImageSkuParser::parse($parseName);
                $items[] = $this->filaItem($import->id, $parseName, $relative, $parsed['sku'] ?? null, $parsed['position'] ?? 1, [
                    'estado' => TiendanubeImageImportItem::ESTADO_ERROR,
                    'motivo' => self::MOTIVO_ARCHIVO_GRANDE,
                    'mensaje' => 'Archivo >= 10 MB.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            $absPath = (string) $archivo['abs_path'];
            $parsed = TiendanubeImageSkuParser::parse($parseName);

            if (! $parsed) {
                $items[] = $this->filaItem($import->id, $parseName, $relative, null, 1, [
                    'estado' => TiendanubeImageImportItem::ESTADO_OMITIDO,
                    'motivo' => self::MOTIVO_NOMBRE_INVALIDO,
                    'mensaje' => 'Nombre de archivo no válido (usa SKU.ext o SKU_n.ext).',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            if (! is_file($absPath) || filesize($absPath) >= $maxFile) {
                $items[] = $this->filaItem($import->id, $parseName, $relative, $parsed['sku'], $parsed['position'], [
                    'estado' => TiendanubeImageImportItem::ESTADO_ERROR,
                    'motivo' => self::MOTIVO_ARCHIVO_GRANDE,
                    'mensaje' => 'Archivo >= 10 MB.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            $resolucion = $this->skuResolver->resolver($parsed['sku']);

            if ($resolucion['estado'] === TiendanubeImageSkuResolverService::ESTADO_NO_ENCONTRADO) {
                $items[] = $this->filaItem($import->id, $parseName, $relative, $parsed['sku'], $parsed['position'], [
                    'resolucion_estado' => $resolucion['estado'],
                    'estado' => TiendanubeImageImportItem::ESTADO_ERROR,
                    'motivo' => self::MOTIVO_SKU_NO_ENCONTRADO,
                    'mensaje' => 'SKU no encontrado en el catálogo. Sincroniza productos primero.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            if ($resolucion['estado'] === TiendanubeImageSkuResolverService::ESTADO_AMBIGUO) {
                $items[] = $this->filaItem($import->id, $parseName, $relative, $parsed['sku'], $parsed['position'], [
                    'resolucion_estado' => $resolucion['estado'],
                    'candidatos_json' => json_encode($resolucion['candidatos']),
                    'estado' => TiendanubeImageImportItem::ESTADO_REQUIERE_SELECCION,
                    'motivo' => self::MOTIVO_SKU_AMBIGUO,
                    'mensaje' => 'SKU presente en más de un producto. Elige el destino.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            $items[] = $this->filaItem($import->id, $parseName, $relative, $parsed['sku'], $parsed['position'], [
                'resolucion_estado' => $resolucion['estado'],
                'candidatos_json' => json_encode($resolucion['candidatos']),
                'producto_id' => $resolucion['producto_id'],
                'estado' => TiendanubeImageImportItem::ESTADO_PENDIENTE,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (array_chunk($items, 200) as $chunk) {
            TiendanubeImageImportItem::insert($chunk);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function filaItem(int $importId, string $filename, ?string $relative, ?string $sku, int $position, array $extra): array
    {
        return array_merge([
            'import_id' => $importId,
            'filename' => $filename,
            'relative_path' => $relative,
            'sku' => $sku,
            'resolucion_estado' => $extra['resolucion_estado'] ?? null,
            'candidatos_json' => $extra['candidatos_json'] ?? null,
            'position' => $position,
            'producto_id' => $extra['producto_id'] ?? null,
            'excluido' => false,
            'estado' => 'pendiente',
            'motivo' => null,
            'mensaje' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra);
    }

    private function finalizarIndexado(TiendanubeImageImport $import): void
    {
        $this->recalcularTotales($import);
        $import->refresh();

        $tieneAmbigua = $import->items()->where('estado', TiendanubeImageImportItem::ESTADO_REQUIERE_SELECCION)->exists();
        $tienePendiente = $import->items()->where('estado', TiendanubeImageImportItem::ESTADO_PENDIENTE)->where('excluido', false)->exists();

        if ($tieneAmbigua) {
            $import->update(['estado' => TiendanubeImageImport::ESTADO_REQUIERE_REVISION]);

            return;
        }

        if ($tienePendiente) {
            $import->update(['estado' => TiendanubeImageImport::ESTADO_LISTA]);

            return;
        }

        $this->marcarTerminal($import);
    }

    private function ejecutarLote(TiendanubeImageImport $import): void
    {
        $import->update([
            'estado' => TiendanubeImageImport::ESTADO_PROCESANDO,
            'mensaje_error' => null,
        ]);

        $extractAbs = $import->extract_path
            ? Storage::disk('local')->path($import->extract_path)
            : null;

        if (! $extractAbs || ! is_dir($extractAbs)) {
            $import->update([
                'estado' => TiendanubeImageImport::ESTADO_ERROR,
                'mensaje_error' => 'No se encontró la carpeta extraída del ZIP.',
            ]);

            return;
        }

        $candidatos = $import->items()
            ->where('estado', TiendanubeImageImportItem::ESTADO_PENDIENTE)
            ->where('excluido', false)
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        $reemplazarPrimera = (bool) $import->reemplazar_primera;
        $productoIdsLote = [];

        foreach ($candidatos as $item) {
            if (! $this->adquirirItem($item)) {
                continue;
            }
            $item->refresh();

            try {
                $abs = $extractAbs.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $item->relative_path);
                $absReal = realpath($abs);
                $extractReal = realpath($extractAbs);
                if (! $absReal || ! $extractReal || ! str_starts_with($absReal, $extractReal.DIRECTORY_SEPARATOR) || ! is_file($absReal)) {
                    throw new RuntimeException('Archivo no encontrado tras extracción.');
                }

                if (! $item->producto_id || ! TiendanubeProducto::query()->find($item->producto_id)) {
                    throw new RuntimeException('El producto destino ya no está en el catálogo.');
                }

                $detectedMime = mime_content_type($absReal) ?: 'application/octet-stream';
                $uploaded = new UploadedFile(
                    $absReal,
                    $item->filename,
                    $detectedMime,
                    null,
                    true
                );

                $reemplazar = $reemplazarPrimera && ! $import->items()
                    ->where('producto_id', $item->producto_id)
                    ->where('estado', TiendanubeImageImportItem::ESTADO_OK)
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
                    ],
                    'import:'.$import->id.':item:'.$item->id
                );

                $item->update([
                    'estado' => TiendanubeImageImportItem::ESTADO_OK,
                    'mensaje' => $imagen->operacion->esParcial()
                        ? 'Imagen cargada; retiro de anteriores pendiente de reconciliación.'
                        : null,
                    'imagen_tn_id' => $imagen->imagen->id,
                    'operacion_id' => $imagen->operacion->id,
                    'claim_token' => null,
                    'claim_expires_at' => null,
                ]);
                if ($item->producto_id) {
                    $productoIdsLote[] = (int) $item->producto_id;
                }
            } catch (\Throwable $e) {
                $item->update([
                    'estado' => TiendanubeImageImportItem::ESTADO_ERROR,
                    'motivo' => self::MOTIVO_ERROR_CARGA,
                    'mensaje' => $e->getMessage(),
                    'claim_token' => null,
                    'claim_expires_at' => null,
                ]);
            }

            $import->touch();
        }

        if ($productoIdsLote !== []) {
            $this->write->refrescarSrcsTemporalesDeProductos($productoIdsLote);
        }

        $this->recalcularTotales($import);
        $import->refresh();

        $quedan = $import->items()
            ->where('estado', TiendanubeImageImportItem::ESTADO_PENDIENTE)
            ->where('excluido', false)
            ->count();

        if ($quedan > 0) {
            ProcessTiendanubeImageImportJob::dispatch($import->id);

            return;
        }

        $this->marcarTerminal($import);
    }

    private function adquirirItem(TiendanubeImageImportItem $item): bool
    {
        $token = (string) Str::uuid();
        $seconds = max(30, (int) config('tiendanube.image_import_claim_seconds', 600));
        $now = now();

        $affected = TiendanubeImageImportItem::query()
            ->where('id', $item->id)
            ->where('estado', TiendanubeImageImportItem::ESTADO_PENDIENTE)
            ->where('excluido', false)
            ->where(function ($q) use ($now) {
                $q->whereNull('claim_expires_at')
                    ->orWhere('claim_expires_at', '<', $now);
            })
            ->update([
                'claim_token' => $token,
                'claim_expires_at' => $now->copy()->addSeconds($seconds),
                'updated_at' => $now,
            ]);

        return $affected === 1;
    }

    private function recalcularTotales(TiendanubeImageImport $import): void
    {
        $total = $import->items()->count();
        $exitosos = $import->items()->where('estado', TiendanubeImageImportItem::ESTADO_OK)->count();
        $fallidos = $import->items()->whereIn('estado', [
            TiendanubeImageImportItem::ESTADO_ERROR,
            TiendanubeImageImportItem::ESTADO_OMITIDO,
        ])->count();
        $pendientes = $import->items()->whereIn('estado', [
            TiendanubeImageImportItem::ESTADO_PENDIENTE,
            TiendanubeImageImportItem::ESTADO_REQUIERE_SELECCION,
        ])->where('excluido', false)->count();

        $import->update([
            'total_archivos' => $total,
            'exitosos' => $exitosos,
            'fallidos' => $fallidos,
            'procesados' => max(0, $total - $pendientes),
        ]);
    }

    private function marcarTerminal(TiendanubeImageImport $import): void
    {
        $this->recalcularTotales($import);
        $import->refresh();
        $incidencias = $import->items()->whereIn('estado', [
            TiendanubeImageImportItem::ESTADO_ERROR,
            TiendanubeImageImportItem::ESTADO_OMITIDO,
        ])->exists();

        $import->update([
            'estado' => $incidencias
                ? TiendanubeImageImport::ESTADO_COMPLETADO_CON_INCIDENCIAS
                : TiendanubeImageImport::ESTADO_COMPLETADO,
            'mensaje_error' => null,
        ]);
    }

    private function assertImportacionAdmisible(): void
    {
        $storeId = TiendanubeConfiguracion::obtener()->store_id;

        $this->operaciones->assertAdmisible(
            TiendanubeOperacionTiendaService::TIPO_IMAGE_IMPORT,
            $storeId ? (int) $storeId : null
        );
    }
}

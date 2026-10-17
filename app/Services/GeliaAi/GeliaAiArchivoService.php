<?php

namespace App\Services\GeliaAi;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GeliaAiArchivoService
{
    public const MAX_FILES = 10;

    public const MAX_MB = 20;

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{file_id: string, original_name: string, kind: string, headers: list<string>, rows: int, guess_mapping: array<string, string>}>
     */
    public function guardarYInspeccionar(User $user, array $files, InspeccionarArchivoGeliaAiService $inspector): array
    {
        if (count($files) > self::MAX_FILES) {
            throw new RuntimeException('Máximo '.self::MAX_FILES.' archivos por carga.');
        }

        $out = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $out[] = $this->guardarUno($user, $file, $inspector);
        }

        return $out;
    }

    /**
     * @return array{file_id: string, original_name: string, kind: string, headers: list<string>, rows: int, guess_mapping: array<string, string>}
     */
    public function guardarUno(User $user, UploadedFile $file, InspeccionarArchivoGeliaAiService $inspector): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        if (! in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            throw new RuntimeException('Formato no permitido: '.$ext);
        }

        $fileId = (string) Str::uuid();
        $dir = $this->dirUsuario($user->id);
        Storage::makeDirectory($dir);
        $relPath = $dir.'/'.$fileId.'.'.$ext;
        Storage::putFileAs($dir, $file, $fileId.'.'.$ext);

        $abs = Storage::path($relPath);
        $inspeccion = $inspector->inspeccionar($abs, $file->getClientOriginalName());

        $meta = [
            'file_id' => $fileId,
            'user_id' => $user->id,
            'path' => $relPath,
            'original_name' => $file->getClientOriginalName(),
            'kind' => $inspeccion['kind'],
            'headers' => $inspeccion['headers'],
            'rows' => $inspeccion['rows'],
            'guess_mapping' => $inspeccion['guess_mapping'],
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put($this->cacheKey($user->id, $fileId), $meta, now()->addHours(6));
        Storage::put($dir.'/'.$fileId.'.meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE));

        return $this->resumenPublico($meta);
    }

    /**
     * @return array{file_id: string, original_name: string, kind: string, headers: list<string>, rows: int, guess_mapping: array<string, string>}|null
     */
    public function metaDeUsuario(User $user, string $fileId): ?array
    {
        $meta = Cache::get($this->cacheKey($user->id, $fileId));
        if (! is_array($meta)) {
            $path = $this->dirUsuario($user->id).'/'.$fileId.'.meta.json';
            if (! Storage::exists($path)) {
                return null;
            }
            $decoded = json_decode((string) Storage::get($path), true);
            if (! is_array($decoded) || (int) ($decoded['user_id'] ?? 0) !== (int) $user->id) {
                return null;
            }
            $meta = $decoded;
            Cache::put($this->cacheKey($user->id, $fileId), $meta, now()->addHours(6));
        }

        if ((int) ($meta['user_id'] ?? 0) !== (int) $user->id) {
            return null;
        }

        return $meta;
    }

    /**
     * Metadatos de archivos del usuario que siguen existiendo (los más recientes primero en caso de exceso).
     *
     * @param  list<string>  $fileIds
     * @return list<array<string, mixed>>
     */
    public function metasDisponibles(User $user, array $fileIds): array
    {
        $out = [];
        foreach (array_slice(array_values(array_unique(array_map('strval', $fileIds))), -self::MAX_FILES) as $id) {
            if (! Str::isUuid($id)) {
                continue;
            }
            $meta = $this->metaDeUsuario($user, $id);
            if ($meta && ! empty($meta['path']) && Storage::exists($meta['path'])) {
                $out[] = $meta;
            }
        }

        return $out;
    }

    /**
     * Resumen para el modelo: estructura del archivo, nunca valores de celdas.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function resumenParaLlm(array $meta): array
    {
        return [
            'file_id' => (string) $meta['file_id'],
            'nombre' => (string) ($meta['original_name'] ?? ''),
            'kind' => (string) ($meta['kind'] ?? 'desconocido'),
            'headers' => array_values(array_slice(array_map('strval', $meta['headers'] ?? []), 0, 15)),
            'filas_aprox' => (int) ($meta['rows'] ?? 0),
            'columnas_detectadas' => is_array($meta['guess_mapping'] ?? null) ? $meta['guess_mapping'] : [],
        ];
    }

    /**
     * Resuelve un archivo por file_id, nombre original o, si no se indicó, por kind único entre los disponibles.
     *
     * @param  list<array<string, mixed>>  $disponibles
     * @return array<string, mixed>|null
     */
    public function resolverReferencia(User $user, mixed $ref, array $disponibles, ?string $kind = null): ?array
    {
        $ref = is_string($ref) ? trim($ref) : '';

        if ($ref !== '') {
            if (Str::isUuid($ref)) {
                $meta = $this->metaDeUsuario($user, $ref);

                return $meta && ! empty($meta['path']) && Storage::exists($meta['path']) ? $meta : null;
            }
            $porNombre = array_values(array_filter(
                $disponibles,
                fn ($m) => mb_strtolower((string) ($m['original_name'] ?? '')) === mb_strtolower($ref)
            ));

            return count($porNombre) === 1 ? $porNombre[0] : null;
        }

        if ($kind === null) {
            return null;
        }
        $porKind = array_values(array_filter($disponibles, fn ($m) => ($m['kind'] ?? '') === $kind));

        return count($porKind) === 1 ? $porKind[0] : null;
    }

    public function rutaAbsoluta(User $user, string $fileId): string
    {
        $meta = $this->metaDeUsuario($user, $fileId);
        if (! $meta || empty($meta['path']) || ! Storage::exists($meta['path'])) {
            throw new RuntimeException('Archivo no encontrado o expirado.');
        }

        return Storage::path($meta['path']);
    }

    public function rutaRelativa(User $user, string $fileId): string
    {
        $meta = $this->metaDeUsuario($user, $fileId);
        if (! $meta || empty($meta['path']) || ! Storage::exists($meta['path'])) {
            throw new RuntimeException('Archivo no encontrado o expirado.');
        }

        return (string) $meta['path'];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{file_id: string, original_name: string, kind: string, headers: list<string>, rows: int, guess_mapping: array<string, string>}
     */
    public function resumenPublico(array $meta): array
    {
        $headers = array_values(array_slice(array_map('strval', $meta['headers'] ?? []), 0, 12));

        return [
            'file_id' => (string) $meta['file_id'],
            'original_name' => (string) ($meta['original_name'] ?? ''),
            'kind' => (string) ($meta['kind'] ?? 'desconocido'),
            'headers' => $headers,
            'rows' => (int) ($meta['rows'] ?? 0),
            'guess_mapping' => is_array($meta['guess_mapping'] ?? null) ? $meta['guess_mapping'] : [],
        ];
    }

    private function dirUsuario(int $userId): string
    {
        return 'temp/gelia_ai/'.$userId;
    }

    private function cacheKey(int $userId, string $fileId): string
    {
        return "gelia_ai_file:{$userId}:{$fileId}";
    }
}

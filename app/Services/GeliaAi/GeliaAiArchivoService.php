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
     * @param  list<string>  $fileIds
     * @return list<array{file_id: string, original_name: string, kind: string}>
     */
    public function resúmenesParaLlm(User $user, array $fileIds): array
    {
        $out = [];
        foreach (array_slice($fileIds, 0, self::MAX_FILES) as $id) {
            $meta = $this->metaDeUsuario($user, (string) $id);
            if (! $meta) {
                continue;
            }
            // Mínimo para el LLM: sin headers/filas/mapping (el servidor ya los tiene).
            $out[] = [
                'file_id' => (string) $meta['file_id'],
                'original_name' => (string) ($meta['original_name'] ?? ''),
                'kind' => (string) ($meta['kind'] ?? 'desconocido'),
            ];
        }

        return $out;
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

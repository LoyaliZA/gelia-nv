<?php

namespace App\Services\Medios;

use App\Contracts\Medios\AlmacenObjetosMedio;
use App\Models\Medios\Medio;
use App\Models\Medios\MedioCarga;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GestionarCargaMedioService
{
    public function __construct(
        private readonly AlmacenObjetosMedio $almacen,
        private readonly AlcancePdv $alcance,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function iniciar(User $actor, array $datos): array
    {
        $proposito = (string) ($datos['proposito'] ?? '');
        $this->asegurarProposito($actor, $proposito);
        $this->validarArchivo($datos);

        $mime = strtolower((string) $datos['mime_type']);
        $nombre = $this->nombreSeguro((string) $datos['filename']);
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $tamano = (int) $datos['size'];
        $objectKey = $this->objectKey($extension);
        $umbral = (int) config('medios.umbral_multipart_bytes');
        $chunk = (int) config('medios.chunk_bytes');
        $ttl = (int) config('medios.ttl_url_seg');

        $esMultipart = $tamano > $umbral;
        $carga = MedioCarga::query()->create([
            'uuid' => (string) Str::uuid(),
            'r2_upload_id' => $esMultipart ? $this->almacen->iniciarMultipart($objectKey, $mime) : null,
            'object_key' => $objectKey,
            'nombre_original' => $nombre,
            'mime_type' => $mime,
            'tamano_bytes' => $tamano,
            'upload_type' => $esMultipart ? MedioCarga::TIPO_MULTIPART : MedioCarga::TIPO_SINGLE,
            'chunk_size' => $esMultipart ? $chunk : null,
            'estado' => MedioCarga::ESTADO_PENDING,
            'proposito' => $proposito,
            'subido_por' => $actor->id,
            'expires_at' => now()->addMinutes((int) config('medios.expires_carga_min')),
        ]);

        if ($this->almacen instanceof AlmacenObjetosMedioFake) {
            $this->almacen->registrarTamano($objectKey, $tamano);
        }

        if (! $esMultipart) {
            return [
                'upload_type' => MedioCarga::TIPO_SINGLE,
                'media_upload_id' => $carga->id,
                'uuid' => $carga->uuid,
                'upload_url' => $this->almacen->urlPutSimple($objectKey, $mime, $ttl),
            ];
        }

        $totalPartes = (int) max(1, (int) ceil($tamano / $chunk));

        return [
            'upload_type' => MedioCarga::TIPO_MULTIPART,
            'media_upload_id' => $carga->id,
            'uuid' => $carga->uuid,
            'chunk_size' => $chunk,
            'total_parts' => $totalPartes,
        ];
    }

    /**
     * @param  list<int>  $partNumbers
     * @return array<string, mixed>
     */
    public function urlsPartes(User $actor, int $cargaId, array $partNumbers): array
    {
        $carga = $this->cargaDe($actor, $cargaId);
        if ($carga->upload_type !== MedioCarga::TIPO_MULTIPART || ! $carga->vigente()) {
            throw new UnprocessableEntityHttpException('La sesión de carga no admite partes.');
        }

        $carga->update(['estado' => MedioCarga::ESTADO_UPLOADING]);
        $ttl = (int) config('medios.ttl_url_seg');
        $partes = [];
        foreach ($partNumbers as $numero) {
            $n = (int) $numero;
            if ($n < 1) {
                continue;
            }
            $partes[] = [
                'part_number' => $n,
                'upload_url' => $this->almacen->urlParte($carga->object_key, (string) $carga->r2_upload_id, $n, $ttl),
            ];
        }

        return ['parts' => $partes];
    }

    /**
     * @return array<string, mixed>
     */
    public function estado(User $actor, int $cargaId): array
    {
        $carga = $this->cargaDe($actor, $cargaId);
        $partes = [];
        if ($carga->upload_type === MedioCarga::TIPO_MULTIPART && $carga->r2_upload_id) {
            $partes = $this->almacen->listarPartes($carga->object_key, $carga->r2_upload_id);
        }

        return [
            'media_upload_id' => $carga->id,
            'uuid' => $carga->uuid,
            'upload_type' => $carga->upload_type,
            'status' => $carga->estado,
            'filename' => $carga->nombre_original,
            'size' => $carga->tamano_bytes,
            'chunk_size' => $carga->chunk_size,
            'completed_parts' => $partes,
            'expires_at' => $carga->expires_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function completar(User $actor, int $cargaId, array $datos): array
    {
        $carga = $this->cargaDe($actor, $cargaId);
        if (! $carga->vigente() && $carga->estado !== MedioCarga::ESTADO_UPLOADING) {
            throw new UnprocessableEntityHttpException('La sesión de carga no se puede completar.');
        }

        $carga->update(['estado' => MedioCarga::ESTADO_COMPLETING]);

        if ($carga->upload_type === MedioCarga::TIPO_MULTIPART) {
            $partes = $this->normalizarPartes($datos['parts'] ?? []);
            $this->almacen->completarMultipart($carga->object_key, (string) $carga->r2_upload_id, $partes);
        }

        if ($this->almacen instanceof AlmacenObjetosMedioFake) {
            $this->almacen->registrarTamano($carga->object_key, (int) $carga->tamano_bytes);
        }

        if (! $this->almacen->existe($carga->object_key)) {
            $carga->update(['estado' => MedioCarga::ESTADO_FAILED]);
            throw new UnprocessableEntityHttpException('El archivo no se encontró en el almacén.');
        }

        $tamano = $this->almacen->tamano($carga->object_key);
        if ($tamano > 0 && abs($tamano - (int) $carga->tamano_bytes) > 16) {
            $carga->update(['estado' => MedioCarga::ESTADO_FAILED]);
            throw new UnprocessableEntityHttpException('El tamaño del archivo no coincide.');
        }

        $tipo = str_starts_with($carga->mime_type, 'video/') ? Medio::TIPO_VIDEO : Medio::TIPO_IMAGEN;
        $duracion = isset($datos['duration_seconds']) ? (int) $datos['duration_seconds'] : null;
        if ($tipo === Medio::TIPO_IMAGEN) {
            $duracion = null;
        }

        $medio = Medio::query()->create([
            'uuid' => (string) Str::uuid(),
            'nombre_original' => $carga->nombre_original,
            'object_key' => $carga->object_key,
            'mime_type' => $carga->mime_type,
            'extension' => strtolower((string) pathinfo($carga->nombre_original, PATHINFO_EXTENSION)),
            'tamano_bytes' => $carga->tamano_bytes,
            'duracion_seg' => $duracion > 0 ? $duracion : null,
            'tipo' => $tipo,
            'estado' => Medio::ESTADO_READY,
            'proposito' => $carga->proposito,
            'creado_por' => $actor->id,
        ]);

        $carga->update([
            'medio_id' => $medio->id,
            'estado' => MedioCarga::ESTADO_COMPLETED,
        ]);

        return [
            'media_id' => $medio->id,
            'uuid' => $medio->uuid,
            'tipo' => $medio->tipo,
            'nombre_original' => $medio->nombre_original,
            'tamano_bytes' => $medio->tamano_bytes,
            'duracion_seg' => $medio->duracion_seg,
            'mime_type' => $medio->mime_type,
            'url' => $this->almacen->urlLectura($medio->object_key, (int) config('medios.ttl_lectura_seg')),
        ];
    }

    public function cancelar(User $actor, int $cargaId): void
    {
        $carga = $this->cargaDe($actor, $cargaId);
        if ($carga->upload_type === MedioCarga::TIPO_MULTIPART && $carga->r2_upload_id) {
            $this->almacen->abortarMultipart($carga->object_key, $carga->r2_upload_id);
        }
        $carga->update(['estado' => MedioCarga::ESTADO_CANCELLED]);
    }

    public function limpiarExpiradas(): int
    {
        $cargas = MedioCarga::query()
            ->whereIn('estado', [MedioCarga::ESTADO_PENDING, MedioCarga::ESTADO_UPLOADING, MedioCarga::ESTADO_FAILED])
            ->where(function ($query): void {
                $query->where('expires_at', '<=', now())
                    ->orWhere('estado', MedioCarga::ESTADO_FAILED);
            })
            ->where('updated_at', '<=', now()->subHour())
            ->limit(100)
            ->get();

        $n = 0;
        foreach ($cargas as $carga) {
            if ($carga->upload_type === MedioCarga::TIPO_MULTIPART && $carga->r2_upload_id) {
                try {
                    $this->almacen->abortarMultipart($carga->object_key, $carga->r2_upload_id);
                } catch (\Throwable) {
                    // ponytail: abort R2 best-effort; la sesión se marca expirada igual
                }
            }
            $carga->update(['estado' => MedioCarga::ESTADO_EXPIRED]);
            $n++;
        }

        return $n;
    }

    private function cargaDe(User $actor, int $cargaId): MedioCarga
    {
        $carga = MedioCarga::query()->whereKey($cargaId)->first();
        if (! $carga instanceof MedioCarga) {
            throw new NotFoundHttpException('Carga no encontrada.');
        }
        $this->asegurarProposito($actor, (string) $carga->proposito);
        if ((int) $carga->subido_por !== (int) $actor->id && ! $actor->hasRole('Super Admin')) {
            throw new AccessDeniedHttpException('La sesión de carga no pertenece a esta cuenta.');
        }

        return $carga;
    }

    private function asegurarProposito(User $actor, string $proposito): void
    {
        $permiso = (string) (config('medios.propositos.'.$proposito) ?? '');
        if ($permiso === '' || ! $this->alcance->tienePermisoPdv($actor, $permiso)) {
            throw new AccessDeniedHttpException('No hay permiso para cargar este tipo de archivo.');
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarArchivo(array $datos): void
    {
        $mime = strtolower((string) ($datos['mime_type'] ?? ''));
        $nombre = (string) ($datos['filename'] ?? '');
        $extension = strtolower((string) pathinfo($nombre, PATHINFO_EXTENSION));
        $tamano = (int) ($datos['size'] ?? 0);
        $permitidos = config('medios.mimes', []);
        $exts = $permitidos[$mime] ?? null;

        if (! is_array($exts) || ! in_array($extension, $exts, true)) {
            throw new UnprocessableEntityHttpException('Tipo de archivo no permitido.');
        }
        if ($tamano < 1 || $tamano > (int) config('medios.max_bytes')) {
            throw new UnprocessableEntityHttpException('El tamaño del archivo no es válido.');
        }
    }

    private function nombreSeguro(string $filename): string
    {
        $base = basename(str_replace(["\0", '\\'], '', $filename));
        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?: 'archivo';

        return substr($base, 0, 180);
    }

    private function objectKey(string $extension): string
    {
        $prefijo = trim((string) config('medios.prefijo_object_key'), '/');
        $uuid = (string) Str::uuid();

        return $prefijo.'/'.now()->format('Y/m').'/'.$uuid.'/archivo.'.$extension;
    }

    /**
     * @param  mixed  $partes
     * @return list<array{PartNumber: int, ETag: string}>
     */
    private function normalizarPartes(mixed $partes): array
    {
        if (! is_array($partes) || $partes === []) {
            throw new UnprocessableEntityHttpException('Faltan las partes de la carga.');
        }

        $normalizadas = [];
        foreach ($partes as $parte) {
            if (! is_array($parte)) {
                continue;
            }
            $numero = (int) ($parte['PartNumber'] ?? $parte['part_number'] ?? 0);
            $etag = (string) ($parte['ETag'] ?? $parte['etag'] ?? '');
            if ($numero < 1 || $etag === '') {
                continue;
            }
            $normalizadas[] = ['PartNumber' => $numero, 'ETag' => $etag];
        }

        if ($normalizadas === []) {
            throw new UnprocessableEntityHttpException('Las partes de la carga no son válidas.');
        }

        usort($normalizadas, static fn (array $a, array $b): int => $a['PartNumber'] <=> $b['PartNumber']);

        return $normalizadas;
    }
}

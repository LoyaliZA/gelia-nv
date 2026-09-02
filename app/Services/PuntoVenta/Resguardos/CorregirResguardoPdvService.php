<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\CorreccionResguardoPdvAplicada;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\CorreccionResguardoPdv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CorregirResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @param  array<string, mixed>  $datosCorreccion
     * @param  list<UploadedFile>  $evidencias
     */
    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        string $tipoCorreccion,
        string $motivo,
        array $datosCorreccion = [],
        array $evidencias = [],
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR,
            (int) $resguardo->sucursal_id
        );

        if (! in_array($tipoCorreccion, CorreccionResguardoPdv::valores(), true)) {
            throw ValidationException::withMessages([
                'tipo_correccion' => 'El tipo de corrección no es válido.',
            ]);
        }

        $pathsEscritos = [];

        try {
            return DB::transaction(function () use (
                $resguardo,
                $actor,
                $versionEsperada,
                $idempotencyKey,
                $tipoCorreccion,
                $motivo,
                $datosCorreccion,
                $evidencias,
                &$pathsEscritos,
            ) {
                $resguardo = ResguardoPdv::query()->lockForUpdate()->findOrFail($resguardo->id);

                $reintento = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                if ($reintento !== null) {
                    return $reintento;
                }

                if ((int) $resguardo->version !== $versionEsperada) {
                    throw ValidationException::withMessages([
                        'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
                    ]);
                }

                $ahora = now();
                $estadoActual = $resguardo->estado;
                $snapshotCorreccion = $this->aplicarCorreccion(
                    $resguardo,
                    $tipoCorreccion,
                    $datosCorreccion,
                    $motivo
                );

                $resguardo->update([
                    'version' => $resguardo->version + 1,
                ]);

                try {
                    $evento = ResguardoPdvEvento::query()->create([
                        'resguardo_id' => $resguardo->id,
                        'bulto_id' => $snapshotCorreccion['bulto_id'] ?? null,
                        'tipo_evento' => ResguardoPdvEvento::TIPO_CORRECCION_ADMINISTRATIVA,
                        'estado_anterior' => $estadoActual,
                        'estado_nuevo' => $estadoActual,
                        'actor_id' => $actor->id,
                        'ocurrido_at' => $ahora,
                        'snapshot_json' => array_merge($snapshotCorreccion, [
                            'tipo_correccion' => $tipoCorreccion,
                            'motivo' => $motivo,
                        ]),
                        'idempotency_key' => $idempotencyKey,
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $recuperado = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                    if ($recuperado !== null) {
                        return $recuperado;
                    }

                    throw $e;
                }

                $this->persistirEvidencias(
                    $resguardo,
                    $evento,
                    $evidencias,
                    $actor->id,
                    $ahora,
                    $pathsEscritos
                );

                $resguardo = $resguardo->fresh(['bultos']);

                CorreccionResguardoPdvAplicada::dispatch(
                    $resguardo,
                    $evento,
                    $actor->id,
                    (int) $resguardo->sucursal_id
                );

                return $resguardo;
            });
        } catch (\Throwable $e) {
            $this->eliminarArchivosHuerfanos($pathsEscritos);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $datosCorreccion
     * @return array<string, mixed>
     */
    private function aplicarCorreccion(
        ResguardoPdv $resguardo,
        string $tipoCorreccion,
        array $datosCorreccion,
        string $motivo,
    ): array {
        return match ($tipoCorreccion) {
            CorreccionResguardoPdv::TIPO_SNAPSHOT_RESGUARDO => $this->corregirSnapshotResguardo($resguardo, $datosCorreccion),
            CorreccionResguardoPdv::TIPO_ANOTACION_EVENTO => $this->anotarEvento($resguardo, $datosCorreccion, $motivo),
            default => throw ValidationException::withMessages([
                'tipo_correccion' => 'El tipo de corrección no es válido.',
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $datosCorreccion
     * @return array<string, mixed>
     */
    private function corregirSnapshotResguardo(ResguardoPdv $resguardo, array $datosCorreccion): array
    {
        $valoresAnteriores = [];
        $valoresNuevos = [];
        $actualizar = [];

        if (array_key_exists('snapshot_folio', $datosCorreccion)) {
            $nuevo = trim((string) $datosCorreccion['snapshot_folio']);
            if ($nuevo === '') {
                throw ValidationException::withMessages([
                    'snapshot_folio' => 'El folio corregido no puede estar vacío.',
                ]);
            }
            if ($nuevo !== (string) $resguardo->snapshot_folio) {
                $valoresAnteriores['snapshot_folio'] = $resguardo->snapshot_folio;
                $valoresNuevos['snapshot_folio'] = $nuevo;
                $actualizar['snapshot_folio'] = $nuevo;
            }
        }

        if (array_key_exists('snapshot_cliente_nombre', $datosCorreccion)) {
            $nuevo = trim((string) $datosCorreccion['snapshot_cliente_nombre']);
            if ($nuevo === '') {
                throw ValidationException::withMessages([
                    'snapshot_cliente_nombre' => 'El nombre de cliente corregido no puede estar vacío.',
                ]);
            }
            if ($nuevo !== (string) $resguardo->snapshot_cliente_nombre) {
                $valoresAnteriores['snapshot_cliente_nombre'] = $resguardo->snapshot_cliente_nombre;
                $valoresNuevos['snapshot_cliente_nombre'] = $nuevo;
                $actualizar['snapshot_cliente_nombre'] = $nuevo;
            }
        }

        if ($actualizar === []) {
            throw ValidationException::withMessages([
                'correccion' => 'Debe indicar al menos un campo distinto al valor actual.',
            ]);
        }

        $resguardo->update($actualizar);

        return [
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos' => $valoresNuevos,
        ];
    }

    /**
     * @param  array<string, mixed>  $datosCorreccion
     * @return array<string, mixed>
     */
    private function anotarEvento(ResguardoPdv $resguardo, array $datosCorreccion, string $motivo): array
    {
        $eventoReferenciaId = (int) ($datosCorreccion['evento_referencia_id'] ?? 0);
        if ($eventoReferenciaId < 1) {
            throw ValidationException::withMessages([
                'evento_referencia_id' => 'Debe indicar el evento de referencia.',
            ]);
        }

        $eventoReferencia = ResguardoPdvEvento::query()
            ->where('resguardo_id', $resguardo->id)
            ->find($eventoReferenciaId);

        if (! $eventoReferencia) {
            throw ValidationException::withMessages([
                'evento_referencia_id' => 'El evento de referencia no pertenece a este resguardo.',
            ]);
        }

        return [
            'evento_referencia_id' => $eventoReferencia->id,
            'evento_referencia_tipo' => $eventoReferencia->tipo_evento,
            'evento_referencia_ocurrido_at' => $eventoReferencia->ocurrido_at?->toIso8601String(),
            'evento_referencia_actor_id' => $eventoReferencia->actor_id,
            'anotacion' => $motivo,
            'bulto_id' => $eventoReferencia->bulto_id,
        ];
    }

    private function resolverReintentoIdempotente(ResguardoPdv $resguardo, string $idempotencyKey): ?ResguardoPdv
    {
        $evento = ResguardoPdvEvento::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $evento) {
            return null;
        }

        if ((int) $evento->resguardo_id !== (int) $resguardo->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        if ($evento->tipo_evento !== ResguardoPdvEvento::TIPO_CORRECCION_ADMINISTRATIVA) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        return $resguardo->fresh(['bultos']);
    }

    /**
     * @param  list<UploadedFile>  $evidencias
     * @param  list<string>  $pathsEscritos
     */
    private function persistirEvidencias(
        ResguardoPdv $resguardo,
        ResguardoPdvEvento $evento,
        array $evidencias,
        int $actorId,
        \Illuminate\Support\Carbon $capturadoAt,
        array &$pathsEscritos,
    ): void {
        $archivos = array_values(array_filter(
            $evidencias,
            fn ($archivo) => $archivo instanceof UploadedFile && $archivo->isValid()
        ));

        foreach ($archivos as $archivo) {
            $ruta = $archivo->store("pdv/resguardos/{$resguardo->id}/correcciones", 'local');
            $pathsEscritos[] = $ruta;

            ResguardoPdvEvidencia::query()->create([
                'resguardo_id' => $resguardo->id,
                'evento_id' => $evento->id,
                'tipo' => ResguardoPdvEvidencia::TIPO_FOTO,
                'ruta_interna' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                'actor_id' => $actorId,
                'capturado_at' => $capturadoAt,
                'inmutable' => true,
                'metadata_json' => ['origen' => 'correccion_administrativa'],
            ]);
        }
    }

    /**
     * @param  list<string>  $pathsEscritos
     */
    private function eliminarArchivosHuerfanos(array $pathsEscritos): void
    {
        foreach ($pathsEscritos as $ruta) {
            Storage::disk('local')->delete($ruta);
        }
    }
}

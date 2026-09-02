<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\EntregaResguardoPdvCompletada;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RegistrarEntregaResguardoPdvService
{
    public const METODO_VALIDACION_FIRMA = 'firma';

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        string $relacion,
        string $nombreQuienRetira,
        string $metodoValidacion,
        UploadedFile $firma,
        ?string $observaciones = null,
        array $evidencias = [],
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
            (int) $resguardo->sucursal_id
        );

        $pathsEscritos = [];

        try {
            return DB::transaction(function () use (
                $resguardo,
                $actor,
                $versionEsperada,
                $idempotencyKey,
                $relacion,
                $nombreQuienRetira,
                $metodoValidacion,
                $firma,
                $observaciones,
                $evidencias,
                &$pathsEscritos,
            ) {
                $resguardo = ResguardoPdv::query()
                    ->with(['bultos'])
                    ->lockForUpdate()
                    ->findOrFail($resguardo->id);

                $reintento = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                if ($reintento !== null) {
                    return $reintento;
                }

                $this->assertVersionYEstado($resguardo, $versionEsperada);
                $this->assertEntregable($resguardo);
                $this->assertMetodoValidacion($metodoValidacion);

                $bultosEntregables = $this->bultosEntregables($resguardo);
                $ahora = now();
                $tipoEvento = $relacion === ResguardoPdvEntrega::RELACION_TERCERO
                    ? ResguardoPdvEvento::TIPO_ENTREGA_TERCERO
                    : ResguardoPdvEvento::TIPO_ENTREGA_TITULAR;

                $snapshotEntrega = [
                    'receptor' => [
                        'nombre' => $nombreQuienRetira,
                        'relacion' => $relacion,
                    ],
                    'metodo_validacion' => $metodoValidacion,
                    'observaciones' => $observaciones,
                    'bultos' => $bultosEntregables->map(fn (ResguardoPdvBulto $bulto) => [
                        'id' => $bulto->id,
                        'folio' => $bulto->folio,
                        'tipo' => $bulto->tipo,
                    ])->values()->all(),
                    'integracion_cp' => [
                        'estado' => 'pendiente',
                        'idempotency_key' => $idempotencyKey,
                        'intentos' => 0,
                    ],
                ];

                try {
                    $entrega = ResguardoPdvEntrega::query()->create([
                        'resguardo_id' => $resguardo->id,
                        'pedido_bma_id' => $resguardo->pedido_bma_id,
                        'relacion' => $relacion,
                        'nombre_quien_retira' => $nombreQuienRetira,
                        'entregado_por_id' => $actor->id,
                        'entregado_at' => $ahora,
                        'snapshot_json' => $snapshotEntrega,
                        'idempotency_key' => $idempotencyKey,
                        'version' => 1,
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $recuperado = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                    if ($recuperado !== null) {
                        return $recuperado;
                    }

                    throw $e;
                }

                $entrega->bultos()->attach($bultosEntregables->pluck('id')->all());

                foreach ($bultosEntregables as $bulto) {
                    $bulto->update([
                        'estado' => ResguardoPdvBulto::ESTADO_ENTREGADO,
                        'entrega_at' => $ahora,
                        'version' => $bulto->version + 1,
                    ]);
                }

                $resguardo->update([
                    'estado' => ResguardoPdv::ESTADO_ENTREGADO,
                    'entrega_completada_at' => $ahora,
                    'version' => $resguardo->version + 1,
                ]);

                try {
                    $evento = ResguardoPdvEvento::query()->create([
                        'resguardo_id' => $resguardo->id,
                        'tipo_evento' => $tipoEvento,
                        'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
                        'estado_nuevo' => ResguardoPdv::ESTADO_ENTREGADO,
                        'actor_id' => $actor->id,
                        'ocurrido_at' => $ahora,
                        'snapshot_json' => [
                            'entrega_id' => $entrega->id,
                            'receptor' => $snapshotEntrega['receptor'],
                            'metodo_validacion' => $metodoValidacion,
                            'observaciones' => $observaciones,
                            'cantidad_entregada' => $bultosEntregables->count(),
                        ],
                        'idempotency_key' => 'evt:'.$idempotencyKey,
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $recuperado = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                    if ($recuperado !== null) {
                        return $recuperado;
                    }

                    throw $e;
                }

                $this->persistirFirma(
                    $resguardo,
                    $entrega,
                    $evento,
                    $firma,
                    $actor->id,
                    $ahora,
                    $pathsEscritos
                );

                $this->persistirEvidenciasAdicionales(
                    $resguardo,
                    $entrega,
                    $evento,
                    $evidencias,
                    $actor->id,
                    $ahora,
                    $pathsEscritos
                );

                $resguardo = $resguardo->fresh(['bultos', 'entregas']);

                EntregaResguardoPdvCompletada::dispatch(
                    $resguardo,
                    $entrega->fresh(),
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

    private function resolverReintentoIdempotente(ResguardoPdv $resguardo, string $idempotencyKey): ?ResguardoPdv
    {
        $entrega = ResguardoPdvEntrega::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $entrega) {
            return null;
        }

        if ((int) $entrega->resguardo_id !== (int) $resguardo->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        return $resguardo->fresh(['bultos', 'entregas']);
    }

    private function assertVersionYEstado(ResguardoPdv $resguardo, int $versionEsperada): void
    {
        if ((int) $resguardo->version !== $versionEsperada) {
            throw ValidationException::withMessages([
                'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
            ]);
        }

        if ($resguardo->estado === ResguardoPdv::ESTADO_ENTREGADO) {
            throw new ConflictHttpException('Este resguardo ya fue entregado.');
        }

        if ($resguardo->estado !== ResguardoPdv::ESTADO_EN_CUSTODIA) {
            throw ValidationException::withMessages([
                'estado' => 'El resguardo no admite entrega desde su estado actual.',
            ]);
        }
    }

    private function assertEntregable(ResguardoPdv $resguardo): void
    {
        if ($resguardo->entrega_bloqueada) {
            throw ValidationException::withMessages([
                'estado' => 'La entrega está bloqueada por una incidencia o cancelación pendiente.',
            ]);
        }

        $incidenciasBloqueantes = ResguardoPdvIncidencia::query()
            ->where('resguardo_id', $resguardo->id)
            ->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA)
            ->whereIn('tipo', [
                ResguardoPdvIncidencia::TIPO_DANO,
                ResguardoPdvIncidencia::TIPO_FALTANTE,
            ])
            ->exists();

        if ($incidenciasBloqueantes) {
            throw ValidationException::withMessages([
                'estado' => 'Existen incidencias abiertas que bloquean la entrega.',
            ]);
        }

        $bultos = $this->bultosEntregables($resguardo);
        if ($bultos->isEmpty()) {
            throw ValidationException::withMessages([
                'bultos' => 'No hay bultos en custodia listos para entregar.',
            ]);
        }

        $esperada = (int) $resguardo->cantidad_bultos_esperada;
        if ($bultos->count() !== $esperada) {
            throw ValidationException::withMessages([
                'bultos' => "La entrega total requiere exactamente {$esperada} bulto(s) en custodia.",
            ]);
        }
    }

    private function assertMetodoValidacion(string $metodoValidacion): void
    {
        if ($metodoValidacion !== self::METODO_VALIDACION_FIRMA) {
            throw ValidationException::withMessages([
                'metodo_validacion' => 'El método de validación no es válido.',
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, ResguardoPdvBulto>
     */
    private function bultosEntregables(ResguardoPdv $resguardo)
    {
        return $resguardo->bultos
            ->filter(fn (ResguardoPdvBulto $bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO)
            ->values();
    }

    /**
     * @param  list<string>  $pathsEscritos
     */
    private function persistirFirma(
        ResguardoPdv $resguardo,
        ResguardoPdvEntrega $entrega,
        ResguardoPdvEvento $evento,
        UploadedFile $firma,
        int $actorId,
        \Illuminate\Support\Carbon $capturadoAt,
        array &$pathsEscritos,
    ): void {
        if (! $firma->isValid()) {
            throw ValidationException::withMessages([
                'firma' => 'La firma capturada no es válida.',
            ]);
        }

        $ruta = $firma->store("pdv/resguardos/{$resguardo->id}/entregas", 'local');
        $pathsEscritos[] = $ruta;

        ResguardoPdvEvidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'evento_id' => $evento->id,
            'entrega_id' => $entrega->id,
            'tipo' => ResguardoPdvEvidencia::TIPO_FIRMA,
            'ruta_interna' => $ruta,
            'nombre_original' => $firma->getClientOriginalName(),
            'mime_type' => $firma->getMimeType(),
            'tamano_bytes' => $firma->getSize(),
            'hash_sha256' => hash_file('sha256', $firma->getRealPath()),
            'actor_id' => $actorId,
            'capturado_at' => $capturadoAt,
            'inmutable' => true,
            'metadata_json' => ['origen' => 'entrega_fisica'],
        ]);
    }

    /**
     * @param  list<UploadedFile>  $evidencias
     * @param  list<string>  $pathsEscritos
     */
    private function persistirEvidenciasAdicionales(
        ResguardoPdv $resguardo,
        ResguardoPdvEntrega $entrega,
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
            $ruta = $archivo->store("pdv/resguardos/{$resguardo->id}/entregas", 'local');
            $pathsEscritos[] = $ruta;

            ResguardoPdvEvidencia::query()->create([
                'resguardo_id' => $resguardo->id,
                'evento_id' => $evento->id,
                'entrega_id' => $entrega->id,
                'tipo' => ResguardoPdvEvidencia::TIPO_FOTO,
                'ruta_interna' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                'actor_id' => $actorId,
                'capturado_at' => $capturadoAt,
                'inmutable' => true,
                'metadata_json' => ['origen' => 'entrega_fisica'],
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

<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\IncidenciaResguardoPdvRegistrada;
use App\Models\Almacen;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Support\PuntoVenta\Resguardos\EstadoRecepcionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\IncidenciaResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RegistrarIncidenciaResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @param  list<UploadedFile>  $evidencias
     * @param  array{folio?: string, tipo?: string, condicion?: string, piezas?: int|null}|null  $bultoNuevo
     * @return array{resguardo: ResguardoPdv, incidencia: ResguardoPdvIncidencia}
     */
    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        string $tipo,
        string $descripcion,
        array $evidencias = [],
        ?int $bultoId = null,
        ?array $bultoNuevo = null,
        ?int $almacenId = null,
    ): array {
        $permiso = IncidenciaResguardoPdv::permisoRegistro($tipo);
        if ($permiso === null) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo de incidencia no es válido.',
            ]);
        }

        $this->alcance->asegurarMutacionPiso(
            $actor,
            $permiso,
            (int) $resguardo->sucursal_id
        );

        $pathsEscritos = [];

        try {
            return DB::transaction(function () use (
                $resguardo,
                $actor,
                $versionEsperada,
                $idempotencyKey,
                $tipo,
                $descripcion,
                $evidencias,
                $bultoId,
                $bultoNuevo,
                $almacenId,
                &$pathsEscritos,
            ) {
                $resguardo = ResguardoPdv::query()
                    ->with('bultos')
                    ->lockForUpdate()
                    ->findOrFail($resguardo->id);

                $reintento = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                if ($reintento !== null) {
                    return $reintento;
                }

                $estadoAnterior = $resguardo->estado;
                $this->assertVersionYEstado($resguardo, $versionEsperada);
                $this->assertDatosObligatorios($tipo, $descripcion, $evidencias, $bultoId, $bultoNuevo, $almacenId);

                $ahora = now();
                $bultoAfectado = $this->resolverBultoAfectado(
                    $resguardo,
                    $actor,
                    $tipo,
                    $bultoId,
                    $bultoNuevo,
                    $almacenId,
                    $ahora,
                );

                $incidencia = ResguardoPdvIncidencia::query()->create([
                    'resguardo_id' => $resguardo->id,
                    'bulto_id' => $bultoAfectado?->id,
                    'tipo' => $tipo,
                    'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
                    'descripcion' => $descripcion,
                    'reportado_por_id' => $actor->id,
                    'reportado_at' => $ahora,
                    'snapshot_json' => [
                        'tipo' => $tipo,
                        'bulto_id' => $bultoAfectado?->id,
                        'bulto_folio' => $bultoAfectado?->folio,
                    ],
                    'idempotency_key' => $idempotencyKey,
                    'version' => 1,
                ]);

                $estadoNuevo = $this->resolverEstadoResguardo($resguardo, $tipo, $bultoAfectado);
                $actualizacion = [
                    'estado' => $estadoNuevo,
                    'version' => $resguardo->version + 1,
                ];
                if ($almacenId !== null) {
                    $actualizacion['almacen_id'] = $almacenId;
                }
                if ($resguardo->recepcion_fisica_at === null && $bultoAfectado !== null) {
                    $actualizacion['recepcion_fisica_at'] = $ahora;
                }
                $resguardo->update($actualizacion);

                $tipoEvento = IncidenciaResguardoPdv::tipoEventoRegistro($tipo);
                try {
                    $evento = ResguardoPdvEvento::query()->create([
                        'resguardo_id' => $resguardo->id,
                        'bulto_id' => $bultoAfectado?->id,
                        'tipo_evento' => $tipoEvento,
                        'estado_anterior' => $estadoAnterior,
                        'estado_nuevo' => $estadoNuevo,
                        'actor_id' => $actor->id,
                        'ocurrido_at' => $ahora,
                        'snapshot_json' => [
                            'incidencia_id' => $incidencia->id,
                            'incidencia_tipo' => $tipo,
                            'descripcion' => $descripcion,
                            'bulto_id' => $bultoAfectado?->id,
                        ],
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
                    $incidencia,
                    $evento,
                    $bultoAfectado,
                    $evidencias,
                    $actor->id,
                    $ahora,
                    $pathsEscritos,
                );

                $resguardo = $resguardo->fresh(['bultos', 'almacen']);
                $incidencia = $incidencia->fresh(['evidencias']);

                IncidenciaResguardoPdvRegistrada::dispatch(
                    $resguardo,
                    $incidencia,
                    $evento,
                    (int) $resguardo->sucursal_id,
                    $actor->id,
                );

                return [
                    'resguardo' => $resguardo,
                    'incidencia' => $incidencia,
                ];
            });
        } catch (\Throwable $e) {
            $this->eliminarArchivosHuerfanos($pathsEscritos);
            throw $e;
        }
    }

    /**
     * @return array{resguardo: ResguardoPdv, incidencia: ResguardoPdvIncidencia}|null
     */
    private function resolverReintentoIdempotente(ResguardoPdv $resguardo, string $idempotencyKey): ?array
    {
        $incidencia = ResguardoPdvIncidencia::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $incidencia) {
            return null;
        }

        if ((int) $incidencia->resguardo_id !== (int) $resguardo->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        return [
            'resguardo' => $resguardo->fresh(['bultos', 'almacen']),
            'incidencia' => $incidencia->fresh(['evidencias']),
        ];
    }

    private function assertVersionYEstado(ResguardoPdv $resguardo, int $versionEsperada): void
    {
        if ((int) $resguardo->version !== $versionEsperada) {
            throw ValidationException::withMessages([
                'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
            ]);
        }

        if (! IncidenciaResguardoPdv::admiteRegistro($resguardo)) {
            throw ValidationException::withMessages([
                'estado' => 'El resguardo no admite registrar incidencias desde su estado actual.',
            ]);
        }
    }

    /**
     * @param  list<UploadedFile>  $evidencias
     * @param  array{folio?: string, tipo?: string, condicion?: string, piezas?: int|null}|null  $bultoNuevo
     */
    private function assertDatosObligatorios(
        string $tipo,
        string $descripcion,
        array $evidencias,
        ?int $bultoId,
        ?array $bultoNuevo,
        ?int $almacenId,
    ): void {
        if (trim($descripcion) === '') {
            throw ValidationException::withMessages([
                'descripcion' => 'La descripción de la incidencia es obligatoria.',
            ]);
        }

        if (IncidenciaResguardoPdv::exigeEvidencia($tipo) && ! $this->tieneEvidenciaValida($evidencias)) {
            throw ValidationException::withMessages([
                'evidencias' => 'Debe adjuntar al menos una foto para este tipo de incidencia.',
            ]);
        }

        if (IncidenciaResguardoPdv::exigeBultoAlRegistrar($tipo) && $bultoId === null && $bultoNuevo === null) {
            throw ValidationException::withMessages([
                'bulto_id' => 'Debe indicar el bulto afectado por el daño.',
            ]);
        }

        if ($bultoNuevo !== null && $almacenId === null) {
            throw ValidationException::withMessages([
                'almacen_id' => 'Debe indicar el almacén de ubicación al recibir el bulto dañado.',
            ]);
        }
    }

    /**
     * @param  list<UploadedFile>  $evidencias
     */
    private function tieneEvidenciaValida(array $evidencias): bool
    {
        foreach ($evidencias as $archivo) {
            if ($archivo instanceof UploadedFile && $archivo->isValid()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{folio?: string, tipo?: string, condicion?: string, piezas?: int|null}|null  $bultoNuevo
     */
    private function resolverBultoAfectado(
        ResguardoPdv $resguardo,
        User $actor,
        string $tipo,
        ?int $bultoId,
        ?array $bultoNuevo,
        ?int $almacenId,
        \Illuminate\Support\Carbon $ahora,
    ): ?ResguardoPdvBulto {
        if ($bultoId !== null) {
            $bulto = ResguardoPdvBulto::query()
                ->where('resguardo_id', $resguardo->id)
                ->whereKey($bultoId)
                ->first();

            if (! $bulto instanceof ResguardoPdvBulto) {
                throw ValidationException::withMessages([
                    'bulto_id' => 'El bulto indicado no pertenece a este resguardo.',
                ]);
            }

            return $bulto;
        }

        if ($bultoNuevo === null) {
            return null;
        }

        if ($tipo !== ResguardoPdvIncidencia::TIPO_DANO) {
            throw ValidationException::withMessages([
                'bulto' => 'Solo las incidencias de daño pueden registrar un bulto nuevo.',
            ]);
        }

        $this->resolverAlmacenUbicacion($resguardo, (int) $almacenId);
        $dato = $this->normalizarBultoNuevo($resguardo, $bultoNuevo);

        try {
            return ResguardoPdvBulto::query()->create([
                'resguardo_id' => $resguardo->id,
                'pedido_bma_id' => $resguardo->pedido_bma_id,
                'folio' => $dato['folio'],
                'codigo_etiqueta' => GeneradorCodigoEtiquetaResguardoPdv::generar(),
                'tipo' => $dato['tipo'],
                'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
                'recepcion_at' => $ahora,
                'recepcion_por_id' => $actor->id,
                'version' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'bulto.folio' => 'El folio ya fue recibido en este resguardo.',
            ]);
        }
    }

    /**
     * @param  array{folio?: string, tipo?: string, condicion?: string, piezas?: int|null}  $bulto
     * @return array{folio: string, tipo: string, condicion: string, piezas: int}
     */
    private function normalizarBultoNuevo(ResguardoPdv $resguardo, array $bulto): array
    {
        $folio = trim((string) ($bulto['folio'] ?? ''));
        if ($folio === '') {
            throw ValidationException::withMessages([
                'bulto.folio' => 'El folio del bulto es obligatorio.',
            ]);
        }

        $foliosExistentes = EstadoRecepcionResguardoPdv::bultosRecibidos($resguardo)
            ->pluck('folio')
            ->all();
        if (in_array($folio, $foliosExistentes, true)) {
            throw ValidationException::withMessages([
                'bulto.folio' => 'El folio ya fue recibido en una llegada anterior.',
            ]);
        }

        $pendientes = EstadoRecepcionResguardoPdv::cantidadPendiente($resguardo);
        if ($pendientes < 1) {
            throw ValidationException::withMessages([
                'bulto' => 'No quedan bultos pendientes por recibir en este resguardo.',
            ]);
        }

        $tipo = (string) ($bulto['tipo'] ?? '');
        if (! in_array($tipo, [ResguardoPdvBulto::TIPO_CAJA, ResguardoPdvBulto::TIPO_BOLSA], true)) {
            throw ValidationException::withMessages([
                'bulto.tipo' => 'El tipo de bulto no es válido.',
            ]);
        }

        $condicion = trim((string) ($bulto['condicion'] ?? ''));
        if ($condicion === '') {
            throw ValidationException::withMessages([
                'bulto.condicion' => 'La condición del bulto es obligatoria.',
            ]);
        }

        $piezas = isset($bulto['piezas']) ? (int) $bulto['piezas'] : 1;
        if ($piezas < 1) {
            throw ValidationException::withMessages([
                'bulto.piezas' => 'Las piezas deben ser al menos 1.',
            ]);
        }

        return [
            'folio' => $folio,
            'tipo' => $tipo,
            'condicion' => $condicion,
            'piezas' => $piezas,
        ];
    }

    private function resolverAlmacenUbicacion(ResguardoPdv $resguardo, int $almacenId): Almacen
    {
        $almacen = Almacen::query()->find($almacenId);
        if (! $almacen instanceof Almacen) {
            throw (new ModelNotFoundException)->setModel(Almacen::class, [$almacenId]);
        }

        if (! $almacen->activo) {
            throw ValidationException::withMessages([
                'almacen_id' => 'El almacén de ubicación no está activo.',
            ]);
        }

        if ((int) $almacen->sucursal_id !== (int) $resguardo->sucursal_id) {
            throw ValidationException::withMessages([
                'almacen_id' => 'El almacén no pertenece a la sucursal del resguardo.',
            ]);
        }

        return $almacen;
    }

    private function resolverEstadoResguardo(
        ResguardoPdv $resguardo,
        string $tipo,
        ?ResguardoPdvBulto $bultoAfectado,
    ): string {
        if ($tipo === ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO) {
            return EstadoRecepcionResguardoPdv::cantidadRecibida($resguardo) > 0
                ? ResguardoPdv::ESTADO_EN_CUSTODIA
                : ResguardoPdv::ESTADO_PENDIENTE_RECEPCION;
        }

        if ($bultoAfectado !== null || EstadoRecepcionResguardoPdv::cantidadRecibida($resguardo) > 0) {
            return ResguardoPdv::ESTADO_EN_CUSTODIA;
        }

        return ResguardoPdv::ESTADO_PENDIENTE_RECEPCION;
    }

    /**
     * @param  list<UploadedFile>  $evidencias
     * @param  list<string>  $pathsEscritos
     */
    private function persistirEvidencias(
        ResguardoPdv $resguardo,
        ResguardoPdvIncidencia $incidencia,
        ResguardoPdvEvento $evento,
        ?ResguardoPdvBulto $bulto,
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
            $ruta = $archivo->store("pdv/resguardos/{$resguardo->id}", 'local');
            $pathsEscritos[] = $ruta;

            ResguardoPdvEvidencia::query()->create([
                'resguardo_id' => $resguardo->id,
                'evento_id' => $evento->id,
                'bulto_id' => $bulto?->id,
                'incidencia_id' => $incidencia->id,
                'tipo' => ResguardoPdvEvidencia::TIPO_FOTO,
                'ruta_interna' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                'actor_id' => $actorId,
                'capturado_at' => $capturadoAt,
                'inmutable' => true,
                'metadata_json' => ['origen' => 'incidencia'],
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

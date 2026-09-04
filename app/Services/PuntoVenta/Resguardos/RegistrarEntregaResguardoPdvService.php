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

    /**
     * @param  list<int>|null  $bultoIds
     * @param  list<UploadedFile>  $evidencias
     */
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
        ?array $bultoIds = null,
        bool $operacionMultiple = false,
    ): ResguardoPdv {
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
                $bultoIds,
                $operacionMultiple,
                &$pathsEscritos,
            ) {
                return $this->registrar(
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
                    $bultoIds,
                    $operacionMultiple,
                    $pathsEscritos
                );
            });
        } catch (\Throwable $e) {
            $this->eliminarArchivosHuerfanos($pathsEscritos);
            throw $e;
        }
    }

    /**
     * @param  list<int>|null  $bultoIds
     * @param  list<UploadedFile>  $evidencias
     * @param  list<string>  $pathsEscritos
     */
    public function registrar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        string $relacion,
        string $nombreQuienRetira,
        string $metodoValidacion,
        UploadedFile $firma,
        ?string $observaciones,
        array $evidencias,
        ?array $bultoIds,
        bool $operacionMultiple,
        array &$pathsEscritos,
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
            (int) $resguardo->sucursal_id
        );

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

        $bultosEntregables = $this->resolverBultosSeleccionados($resguardo, $bultoIds);
        $ahora = now();
        $entregaCompleta = $this->entregaCompletaElPedido($resguardo, $bultosEntregables);
        $tipoEvento = $this->tipoEvento($relacion, $entregaCompleta, $operacionMultiple);
        $estadoNuevo = $entregaCompleta
            ? ResguardoPdv::ESTADO_ENTREGADO
            : $this->estadoTrasEntregaParcial($resguardo, $bultosEntregables);

        $snapshotEntrega = [
            'receptor' => [
                'nombre' => $nombreQuienRetira,
                'relacion' => $relacion,
            ],
            'metodo_validacion' => $metodoValidacion,
            'observaciones' => $observaciones,
            'parcial' => ! $entregaCompleta,
            'operacion_multiple' => $operacionMultiple,
            'bultos' => $bultosEntregables->map(fn (ResguardoPdvBulto $bulto) => [
                'id' => $bulto->id,
                'folio' => $bulto->folio,
                'tipo' => $bulto->tipo,
            ])->values()->all(),
            'integracion_cp' => [
                'estado' => $entregaCompleta ? 'pendiente' : 'omitida',
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
            'estado' => $estadoNuevo,
            'entrega_completada_at' => $entregaCompleta ? $ahora : $resguardo->entrega_completada_at,
            'version' => $resguardo->version + 1,
        ]);

        try {
            $evento = ResguardoPdvEvento::query()->create([
                'resguardo_id' => $resguardo->id,
                'tipo_evento' => $tipoEvento,
                'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
                'estado_nuevo' => $estadoNuevo,
                'actor_id' => $actor->id,
                'ocurrido_at' => $ahora,
                'snapshot_json' => [
                    'entrega_id' => $entrega->id,
                    'receptor' => $snapshotEntrega['receptor'],
                    'metodo_validacion' => $metodoValidacion,
                    'observaciones' => $observaciones,
                    'cantidad_entregada' => $bultosEntregables->count(),
                    'parcial' => ! $entregaCompleta,
                    'operacion_multiple' => $operacionMultiple,
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

        if ($entregaCompleta) {
            EntregaResguardoPdvCompletada::dispatch(
                $resguardo,
                $entrega->fresh(),
                $evento,
                $actor->id,
                (int) $resguardo->sucursal_id
            );
        }

        return $resguardo;
    }

    public function resolverReintentoIdempotente(ResguardoPdv $resguardo, string $idempotencyKey): ?ResguardoPdv
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

    /**
     * @param  list<string>  $pathsEscritos
     */
    public function eliminarArchivosHuerfanos(array $pathsEscritos): void
    {
        foreach ($pathsEscritos as $ruta) {
            Storage::disk('local')->delete($ruta);
        }
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

        if ($this->bultosEntregables($resguardo)->isEmpty()) {
            throw ValidationException::withMessages([
                'bultos' => 'No hay bultos en custodia listos para entregar.',
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
     * @param  list<int>|null  $bultoIds
     * @return \Illuminate\Support\Collection<int, ResguardoPdvBulto>
     */
    private function resolverBultosSeleccionados(ResguardoPdv $resguardo, ?array $bultoIds)
    {
        $entregables = $this->bultosEntregables($resguardo);

        if ($bultoIds === null) {
            return $entregables;
        }

        $solicitados = collect($bultoIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($solicitados->isEmpty()) {
            throw ValidationException::withMessages([
                'bulto_ids' => 'Seleccione al menos un bulto para entregar.',
            ]);
        }

        $porId = $entregables->keyBy('id');
        $seleccionados = $solicitados->map(function (int $id) use ($porId) {
            $bulto = $porId->get($id);
            if (! $bulto) {
                throw ValidationException::withMessages([
                    'bulto_ids' => 'Uno o más bultos no están en custodia o no pertenecen a este resguardo.',
                ]);
            }

            return $bulto;
        });

        return $seleccionados->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ResguardoPdvBulto>  $bultosEntregables
     */
    private function entregaCompletaElPedido(ResguardoPdv $resguardo, $bultosEntregables): bool
    {
        $idsEntregando = $bultosEntregables->pluck('id')->all();
        $recibidosRestantes = $resguardo->bultos
            ->filter(fn (ResguardoPdvBulto $bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO
                && ! in_array($bulto->id, $idsEntregando, true))
            ->count();

        if ($recibidosRestantes > 0) {
            return false;
        }

        $yaEntregados = $resguardo->bultos
            ->filter(fn (ResguardoPdvBulto $bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_ENTREGADO)
            ->count();

        return ($yaEntregados + $bultosEntregables->count()) === (int) $resguardo->cantidad_bultos_esperada;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ResguardoPdvBulto>  $bultosEntregables
     */
    private function estadoTrasEntregaParcial(ResguardoPdv $resguardo, $bultosEntregables): string
    {
        $idsEntregando = $bultosEntregables->pluck('id')->all();
        $quedanEnCustodia = $resguardo->bultos
            ->filter(fn (ResguardoPdvBulto $bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO
                && ! in_array($bulto->id, $idsEntregando, true))
            ->isNotEmpty();

        return $quedanEnCustodia
            ? ResguardoPdv::ESTADO_EN_CUSTODIA
            : ResguardoPdv::ESTADO_PENDIENTE_RECEPCION;
    }

    private function tipoEvento(string $relacion, bool $entregaCompleta, bool $operacionMultiple): string
    {
        if ($operacionMultiple) {
            return ResguardoPdvEvento::TIPO_ENTREGA_MULTIPLE;
        }

        if (! $entregaCompleta) {
            return ResguardoPdvEvento::TIPO_ENTREGA_PARCIAL;
        }

        return $relacion === ResguardoPdvEntrega::RELACION_TERCERO
            ? ResguardoPdvEvento::TIPO_ENTREGA_TERCERO
            : ResguardoPdvEvento::TIPO_ENTREGA_TITULAR;
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
}

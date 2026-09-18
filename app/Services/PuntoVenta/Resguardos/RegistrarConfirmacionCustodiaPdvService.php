<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\CustodiaResguardoPdvConfirmada;
use App\Models\Almacen;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RegistrarConfirmacionCustodiaPdvService
{
    /** @var list<string> */
    private const TIPOS_EVENTO_CUSTODIA = [
        ResguardoPdvEvento::TIPO_CUSTODIA_COMPLETA,
        ResguardoPdvEvento::TIPO_CUSTODIA_PARCIAL,
    ];

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly SincronizarEstatusSucursalPedidoBmaService $sincronizarEstatusSucursal,
    ) {}

    /**
     * @param  list<array{folio: string, tipo?: string, condicion: string, piezas?: int}>  $bultos
     * @param  list<UploadedFile>  $evidencias
     */
    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        int $almacenId,
        array $bultos,
        array $evidencias = [],
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA,
            (int) $resguardo->sucursal_id
        );

        $pathsEscritos = [];

        try {
            return DB::transaction(function () use (
                $resguardo,
                $actor,
                $versionEsperada,
                $idempotencyKey,
                $almacenId,
                $bultos,
                $evidencias,
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

                $this->assertVersionYEstado($resguardo, $versionEsperada);
                $almacen = $this->resolverAlmacenUbicacion($resguardo, $almacenId);
                $bultosNormalizados = $this->normalizarBultosCustodia($resguardo, $bultos);

                $estadoAnterior = $resguardo->estado;
                $esperada = (int) $resguardo->cantidad_bultos_esperada;
                $enCustodiaAntes = EstadoResguardoPdv::cantidadEnCustodia($resguardo);
                $ahora = now();

                foreach ($bultosNormalizados as $dato) {
                    $bulto = $resguardo->bultos->firstWhere('folio', $dato['folio']);
                    if (! $bulto instanceof ResguardoPdvBulto) {
                        continue;
                    }

                    $bulto->update([
                        'tipo' => $dato['tipo'],
                        'piezas' => $dato['piezas'],
                        'condicion' => $dato['condicion'],
                        'estado' => ResguardoPdvBulto::ESTADO_EN_CUSTODIA,
                        'custodia_at' => $ahora,
                        'custodia_por_id' => $actor->id,
                        'version' => $bulto->version + 1,
                    ]);
                }

                $resguardo->load('bultos');
                $totalEnCustodia = EstadoResguardoPdv::cantidadEnCustodia($resguardo);
                $tipoEvento = $totalEnCustodia >= $esperada
                    ? ResguardoPdvEvento::TIPO_CUSTODIA_COMPLETA
                    : ResguardoPdvEvento::TIPO_CUSTODIA_PARCIAL;

                $actualizacion = [
                    'estado' => EstadoResguardoPdv::resolverEstadoOperativo($resguardo),
                    'almacen_id' => $almacen->id,
                    'version' => $resguardo->version + 1,
                ];

                if ($totalEnCustodia >= $esperada && $resguardo->custodia_confirmada_at === null) {
                    $actualizacion['custodia_confirmada_at'] = $ahora;
                }

                $resguardo->update($actualizacion);

                try {
                    $evento = ResguardoPdvEvento::query()->create([
                        'resguardo_id' => $resguardo->id,
                        'tipo_evento' => $tipoEvento,
                        'estado_anterior' => $estadoAnterior,
                        'estado_nuevo' => $resguardo->estado,
                        'actor_id' => $actor->id,
                        'ocurrido_at' => $ahora,
                        'snapshot_json' => [
                            'paso' => 'recepcionista',
                            'almacen_id' => $almacen->id,
                            'almacen_codigo' => $almacen->codigo,
                            'bultos' => $bultosNormalizados,
                            'cantidad_confirmada' => count($bultosNormalizados),
                            'cantidad_en_custodia' => $totalEnCustodia,
                            'cantidad_esperada' => $esperada,
                            'cantidad_pendiente_custodia' => EstadoResguardoPdv::cantidadPendienteCustodia($resguardo),
                            'custodia_completa' => $totalEnCustodia >= $esperada,
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
                    $evento,
                    $evidencias,
                    $actor->id,
                    $ahora,
                    $pathsEscritos
                );

                $resguardo = $resguardo->fresh(['bultos', 'almacen']);
                $this->sincronizarEstatusSucursal->desdeResguardo($resguardo);

                CustodiaResguardoPdvConfirmada::dispatch(
                    $resguardo,
                    $evento,
                    (int) $resguardo->sucursal_id,
                    $enCustodiaAntes,
                    $totalEnCustodia
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

        if (! in_array($evento->tipo_evento, self::TIPOS_EVENTO_CUSTODIA, true)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia corresponde a otra transición.',
            ]);
        }

        return $resguardo->fresh(['bultos', 'almacen']);
    }

    private function assertVersionYEstado(ResguardoPdv $resguardo, int $versionEsperada): void
    {
        if ((int) $resguardo->version !== $versionEsperada) {
            throw ValidationException::withMessages([
                'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
            ]);
        }

        if (! EstadoResguardoPdv::admiteConfirmacionCustodia($resguardo)) {
            if (EstadoResguardoPdv::custodiaCompleta($resguardo)) {
                throw new ConflictHttpException('Este resguardo ya tiene todos los bultos en custodia.');
            }

            throw ValidationException::withMessages([
                'estado' => 'El resguardo no admite confirmación de custodia desde su estado actual.',
            ]);
        }
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

    /**
     * @param  list<array{folio?: string, tipo?: string, condicion?: string, piezas?: int}>  $bultos
     * @return list<array{folio: string, tipo: string, condicion: string, piezas: int}>
     */
    private function normalizarBultosCustodia(ResguardoPdv $resguardo, array $bultos): array
    {
        if ($bultos === []) {
            throw ValidationException::withMessages([
                'bultos' => 'Debe confirmar al menos un bulto en custodia.',
            ]);
        }

        $pendientes = EstadoResguardoPdv::cantidadPendienteCustodia($resguardo);
        if (count($bultos) > $pendientes) {
            throw ValidationException::withMessages([
                'bultos' => "Solo faltan {$pendientes} bulto(s) por confirmar en custodia.",
            ]);
        }

        $foliosUnicos = [];
        $normalizados = [];

        foreach ($bultos as $indice => $dato) {
            $folio = trim((string) ($dato['folio'] ?? ''));
            if ($folio === '') {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.folio" => 'El folio del bulto es obligatorio.',
                ]);
            }

            $bulto = $resguardo->bultos->firstWhere('folio', $folio);
            if (! $bulto instanceof ResguardoPdvBulto) {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.folio" => 'El folio no corresponde a un bulto recibido por gerencia.',
                ]);
            }

            if ($bulto->estado !== ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE) {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.folio" => 'El bulto no está pendiente de confirmación en custodia.',
                ]);
            }

            if (in_array($folio, $foliosUnicos, true)) {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.folio" => 'Los folios deben ser únicos.',
                ]);
            }

            $tipo = (string) ($dato['tipo'] ?? $bulto->tipo);
            if (! in_array($tipo, [ResguardoPdvBulto::TIPO_CAJA, ResguardoPdvBulto::TIPO_BOLSA], true)) {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.tipo" => 'El tipo de bulto no es válido.',
                ]);
            }

            $condicion = trim((string) ($dato['condicion'] ?? ''));
            if ($condicion === '') {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.condicion" => 'La condición del bulto es obligatoria.',
                ]);
            }

            $piezas = isset($dato['piezas']) ? (int) $dato['piezas'] : (int) $bulto->piezas;
            if ($piezas < 1) {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.piezas" => 'Las piezas deben ser al menos 1.',
                ]);
            }

            $foliosUnicos[] = $folio;
            $normalizados[] = [
                'folio' => $folio,
                'tipo' => $tipo,
                'condicion' => $condicion,
                'piezas' => $piezas,
            ];
        }

        return $normalizados;
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
            $ruta = $archivo->store("pdv/resguardos/{$resguardo->id}", 'local');
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
                'metadata_json' => ['origen' => 'confirmacion_custodia'],
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

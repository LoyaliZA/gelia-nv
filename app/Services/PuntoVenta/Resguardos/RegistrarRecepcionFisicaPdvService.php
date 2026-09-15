<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\RecepcionFisicaPdvCompletada;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EstadoRecepcionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RegistrarRecepcionFisicaPdvService
{
    /** @var list<string> */
    private const TIPOS_EVENTO_RECEPCION = [
        ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
        ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL,
    ];

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly SincronizarEstatusSucursalPedidoBmaService $sincronizarEstatusSucursal,
    ) {}

    /**
     * @param  list<array{folio: string, tipo: string, condicion: string, piezas?: int|null}>  $bultos
     * @param  list<UploadedFile>  $evidencias
     */
    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        ?int $almacenId,
        array $bultos,
        array $evidencias = [],
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE,
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

                if ((int) $resguardo->cantidad_bultos_esperada < 1) {
                    $resguardo = app(SincronizarCantidadBultosEsperadaResguardoPdvService::class)->ejecutar($resguardo);
                }

                $estadoAnterior = $resguardo->estado;
                $this->assertVersionYEstado($resguardo, $versionEsperada);
                $bultosNormalizados = $this->normalizarBultosRecepcion($resguardo, $bultos);

                $recibidosAntes = EstadoRecepcionResguardoPdv::cantidadRecibida($resguardo);
                $esperada = (int) $resguardo->cantidad_bultos_esperada;
                $ahora = now();

                foreach ($bultosNormalizados as $dato) {
                    try {
                        ResguardoPdvBulto::query()->create([
                            'resguardo_id' => $resguardo->id,
                            'pedido_bma_id' => $resguardo->pedido_bma_id,
                            'folio' => $dato['folio'],
                            'codigo_etiqueta' => GeneradorCodigoEtiquetaResguardoPdv::generar(),
                            'tipo' => $dato['tipo'],
                            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE,
                            'recepcion_at' => $ahora,
                            'recepcion_por_id' => $actor->id,
                            'version' => 1,
                        ]);
                    } catch (UniqueConstraintViolationException) {
                        throw ValidationException::withMessages([
                            'bultos' => 'Uno o más folios ya fueron recibidos en este resguardo.',
                        ]);
                    }
                }

                $totalRecibida = $recibidosAntes + count($bultosNormalizados);
                $tipoEvento = $totalRecibida >= $esperada
                    ? ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA
                    : ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL;

                $resguardo->load('bultos');
                $nuevoEstado = EstadoResguardoPdv::resolverEstadoOperativo($resguardo);
                $actualizacion = [
                    'estado' => $nuevoEstado,
                    'version' => $resguardo->version + 1,
                ];
                if ($resguardo->recepcion_fisica_at === null) {
                    $actualizacion['recepcion_fisica_at'] = $ahora;
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
                            'paso' => 'gerente',
                            'bultos' => $bultosNormalizados,
                            'cantidad_llegada' => count($bultosNormalizados),
                            'cantidad_recibida' => $totalRecibida,
                            'cantidad_esperada' => $esperada,
                            'cantidad_pendiente' => max(0, $esperada - $totalRecibida),
                            'recepcion_completa' => $totalRecibida >= $esperada,
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

                RecepcionFisicaPdvCompletada::dispatch(
                    $resguardo,
                    $evento,
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

        if (! in_array($evento->tipo_evento, self::TIPOS_EVENTO_RECEPCION, true)) {
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

        if (! EstadoRecepcionResguardoPdv::admiteRecepcion($resguardo)) {
            if (EstadoRecepcionResguardoPdv::recepcionCompleta($resguardo)) {
                throw new ConflictHttpException('Este resguardo ya recibió todos los bultos esperados.');
            }

            throw ValidationException::withMessages([
                'estado' => 'El resguardo no admite recepción física desde su estado actual.',
            ]);
        }
    }

    /**
     * @param  list<array{folio: string, tipo: string, condicion: string, piezas?: int|null}>  $bultos
     * @return list<array{folio: string, tipo: string, condicion: string, piezas: int}>
     */
    private function normalizarBultosRecepcion(ResguardoPdv $resguardo, array $bultos): array
    {
        if ($bultos === []) {
            throw ValidationException::withMessages([
                'bultos' => 'Debe registrar al menos un bulto recibido.',
            ]);
        }

        $esperada = (int) $resguardo->cantidad_bultos_esperada;
        $pendientes = EstadoRecepcionResguardoPdv::cantidadPendiente($resguardo);
        $foliosExistentes = EstadoRecepcionResguardoPdv::bultosRecibidos($resguardo)
            ->pluck('folio')
            ->all();

        if (count($bultos) > $pendientes) {
            throw ValidationException::withMessages([
                'bultos' => "Solo faltan {$pendientes} bulto(s) por recibir; no se puede exceder la cantidad esperada.",
            ]);
        }

        $folios = [];
        $normalizados = [];

        foreach ($bultos as $indice => $bulto) {
            $folio = trim((string) ($bulto['folio'] ?? ''));
            if ($folio === '') {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.folio" => 'El folio del bulto es obligatorio.',
                ]);
            }

            if (in_array($folio, $foliosExistentes, true)) {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.folio" => 'El folio ya fue recibido en una llegada anterior.',
                ]);
            }

            if (in_array($folio, $folios, true)) {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.folio" => 'Los folios de bulto deben ser únicos.',
                ]);
            }
            $folios[] = $folio;

            $tipo = (string) ($bulto['tipo'] ?? '');
            if (! in_array($tipo, [ResguardoPdvBulto::TIPO_CAJA, ResguardoPdvBulto::TIPO_BOLSA], true)) {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.tipo" => 'El tipo de bulto no es válido.',
                ]);
            }

            $condicion = trim((string) ($bulto['condicion'] ?? ''));
            if ($condicion === '') {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.condicion" => 'La condición del bulto es obligatoria.',
                ]);
            }

            $piezas = isset($bulto['piezas']) ? (int) $bulto['piezas'] : 1;
            if ($piezas < 1) {
                throw ValidationException::withMessages([
                    "bultos.{$indice}.piezas" => 'Las piezas deben ser al menos 1.',
                ]);
            }

            $normalizados[] = [
                'folio' => $folio,
                'tipo' => $tipo,
                'condicion' => $condicion,
                'piezas' => $piezas,
            ];
        }

        if ($pendientes === 0 && $esperada > 0) {
            throw ValidationException::withMessages([
                'bultos' => 'Este resguardo ya recibió todos los bultos esperados.',
            ]);
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
                'metadata_json' => ['origen' => 'recepcion_fisica'],
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

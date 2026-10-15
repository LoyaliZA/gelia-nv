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
use App\Support\PuntoVenta\Resguardos\BultosEsperadosResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EstadoRecepcionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EvidenciaMinimaRegistroManualPdv;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\NormalizadorPiezasRegistroManualPdv;
use App\Support\PuntoVenta\Resguardos\OmisionRequisitoResguardoPdv;
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
        private readonly IngresarResguardoManualPdvService $ingresarManual,
    ) {}

    /**
     * @param  list<array{producto_id: int, sku: string|null, descripcion: string|null, cantidad: int, nota: string|null}>|null  $piezas
     * @param  list<array{campo?: string, motivo?: string}>  $omisiones
     */
    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        ?array $piezas = null,
        array $omisiones = [],
        ?UploadedFile $archivoTicket = null,
        ?UploadedFile $fotoPaquete = null,
        ?array $folios = null,
        string $condicion = 'bueno',
        ?string $observacion = null,
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            (int) $resguardo->sucursal_id
        );

        $pathsEscritos = [];

        try {
            return DB::transaction(function () use (
                $resguardo,
                $actor,
                $versionEsperada,
                $idempotencyKey,
                $piezas,
                $omisiones,
                $archivoTicket,
                $fotoPaquete,
                $folios,
                $condicion,
                $observacion,
                &$pathsEscritos,
            ) {
                $resguardo = ResguardoPdv::query()
                    ->with(['bultos', 'pedido.bultosEmpaque'])
                    ->lockForUpdate()
                    ->findOrFail($resguardo->id);

                $reintento = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                if ($reintento !== null) {
                    return $reintento;
                }

                if ((int) $resguardo->cantidad_bultos_esperada < 1) {
                    $resguardo = app(SincronizarCantidadBultosEsperadaResguardoPdvService::class)->ejecutar($resguardo);
                }

                if (! in_array($condicion, ['bueno', 'regular', 'malo', 'danado'], true)) {
                    throw ValidationException::withMessages(['condicion' => 'Indique la condición física observada.']);
                }
                if (! EvidenciaMinimaRegistroManualPdv::esManual($resguardo) && ! $fotoPaquete instanceof UploadedFile) {
                    throw ValidationException::withMessages(['foto_paquete' => 'Adjunte evidencia física de esta llegada.']);
                }
                if ($condicion !== 'bueno' && mb_strlen(trim((string) $observacion)) < 8) {
                    throw ValidationException::withMessages(['observacion' => 'Describa la incidencia física (mínimo 8 caracteres).']);
                }
                $estadoAnterior = $resguardo->estado;
                $this->assertVersionYEstado($resguardo, $versionEsperada);
                if (is_array($piezas) && $piezas !== [] && $this->flujoCompleto($actor)) {
                    $this->guardarPiezas($resguardo, $piezas);
                }
                $omisionesRegistradas = $this->resolverOmisiones(
                    $resguardo,
                    $actor,
                    $omisiones,
                    $archivoTicket,
                    $fotoPaquete,
                );
                $bultosNormalizados = BultosEsperadosResguardoPdv::desdeResguardo($resguardo);
                $esperada = (int) $resguardo->cantidad_bultos_esperada;
                $recibidos = $resguardo->bultos->pluck('folio')->all();
                if ($folios === null || $folios === [] || count($folios) !== count(array_unique($folios))) {
                    throw ValidationException::withMessages(['folios' => 'Seleccione los folios de los bultos que llegaron.']);
                }
                $esperados = array_column($bultosNormalizados, 'folio');
                if (array_diff($folios, $esperados) || array_intersect($folios, $recibidos)) {
                    throw ValidationException::withMessages(['folios' => 'Hay folios ajenos o ya recibidos. Actualice la llegada.']);
                }
                $bultosNormalizados = array_values(array_filter($bultosNormalizados, fn ($b) => in_array($b['folio'], $folios, true)));
                $totalRecibido = count($recibidos) + count($bultosNormalizados);
                $completa = $totalRecibido === $esperada;
                $estadoNuevo = $completa ? ResguardoPdv::ESTADO_RECIBIDO : ResguardoPdv::ESTADO_PENDIENTE_RECEPCION;
                $ahora = now();

                foreach ($bultosNormalizados as $dato) {
                    try {
                        $bulto = ResguardoPdvBulto::query()->create([
                            'resguardo_id' => $resguardo->id,
                            'pedido_bma_id' => $resguardo->pedido_bma_id,
                            'folio' => $dato['folio'],
                            'codigo_etiqueta' => GeneradorCodigoEtiquetaResguardoPdv::generar(),
                            'tipo' => $dato['tipo'],
                            'condicion' => $condicion,
                            'piezas' => $dato['piezas'],
                            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE,
                            'recepcion_at' => $ahora,
                            'recepcion_por_id' => $actor->id,
                            'version' => 1,
                        ]);
                        if ($condicion !== 'bueno') {
                            \App\Models\PuntoVenta\ResguardoPdvIncidencia::create([
                                'resguardo_id' => $resguardo->id, 'bulto_id' => $bulto->id,
                                'tipo' => 'dano', 'estado' => 'abierta', 'descripcion' => $observacion,
                                'reportado_por_id' => $actor->id, 'reportado_at' => $ahora,
                                'idempotency_key' => hash('sha256', $idempotencyKey.':'.$bulto->folio), 'version' => 1,
                                'snapshot_json' => ['origen' => 'llegada', 'condicion' => $condicion],
                            ]);
                        }
                    } catch (UniqueConstraintViolationException) {
                        throw ValidationException::withMessages([
                            'bultos' => 'Uno o más folios ya fueron recibidos en este resguardo.',
                        ]);
                    }
                }

                $resguardo->update([
                    'estado' => $estadoNuevo,
                    'recepcion_fisica_at' => $resguardo->recepcion_fisica_at ?? $ahora,
                    'version' => $resguardo->version + 1,
                ]);

                try {
                    $evento = ResguardoPdvEvento::query()->create([
                        'resguardo_id' => $resguardo->id,
                        'tipo_evento' => $completa ? ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA : ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL,
                        'estado_anterior' => $estadoAnterior,
                        'estado_nuevo' => $estadoNuevo,
                        'actor_id' => $actor->id,
                        'ocurrido_at' => $ahora,
                        'snapshot_json' => $this->snapshotRecepcion(
                            $bultosNormalizados,
                            $esperada,
                            $omisionesRegistradas,
                            $totalRecibido,
                        ),
                        'idempotency_key' => $idempotencyKey,
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $recuperado = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                    if ($recuperado !== null) {
                        return $recuperado;
                    }

                    throw $e;
                }

                $this->adjuntarEvidenciaSiFalta(
                    $resguardo,
                    $evento,
                    $archivoTicket,
                    ResguardoPdvEvidencia::USO_TICKET,
                    $actor->id,
                    $pathsEscritos,
                );
                if ($fotoPaquete instanceof UploadedFile) {
                    $foto = $this->ingresarManual->guardar($resguardo, $evento, $fotoPaquete, ResguardoPdvEvidencia::USO_PAQUETE, $actor->id, $pathsEscritos, ['origen' => 'llegada', 'folios' => $folios, 'condicion' => $condicion, 'observacion' => $observacion]);
                    // Cada daño conserva su evidencia y se resuelve con el circuito vigente de incidencias.
                    foreach ($resguardo->bultos()->whereIn('folio', $folios)->get() as $bultoLlegada) {
                        $incidencia = \App\Models\PuntoVenta\ResguardoPdvIncidencia::where('bulto_id', $bultoLlegada->id)->where('idempotency_key', hash('sha256', $idempotencyKey.':'.$bultoLlegada->folio))->first();
                        if ($incidencia) {
                            $copia = $foto->replicate();
                            $copia->incidencia_id = $incidencia->id;
                            $copia->save();
                        }
                    }
                }

                $resguardo = $resguardo->fresh(['bultos', 'almacen', 'evidencias']);
                $ingresoManual = EvidenciaMinimaRegistroManualPdv::esManual($resguardo);
                if ($completa && $condicion === 'bueno' && $ingresoManual && ! $this->flujoCompleto($actor)
                    && ! $resguardo->incidencias()->where('estado', 'abierta')->exists()) {
                    return $this->ingresarManual->cerrarConfirmacionDoble($resguardo, $actor, $idempotencyKey);
                }

                $this->sincronizarEstatusSucursal->desdeResguardo($resguardo);

                RecepcionFisicaPdvCompletada::dispatch(
                    $resguardo,
                    $evento,
                    (int) $resguardo->sucursal_id
                );

                return $resguardo;
            });
        } catch (\Throwable $e) {
            foreach ($pathsEscritos as $ruta) {
                Storage::disk('local')->delete($ruta);
            }

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

    /**
     * @param  list<array<string, mixed>>  $bultos
     * @param  list<array{campo: string, motivo: string, actor_id: int}>  $omisiones
     * @return array<string, mixed>
     */
    private function snapshotRecepcion(array $bultos, int $esperada, array $omisiones, int $totalRecibido): array
    {
        $snapshot = [
            'paso' => 'gerente',
            'bultos' => $bultos,
            'cantidad_llegada' => count($bultos),
            'cantidad_recibida' => $totalRecibido,
            'cantidad_esperada' => $esperada,
            'cantidad_pendiente' => max(0, $esperada - $totalRecibido),
            'recepcion_completa' => $totalRecibido === $esperada,
        ];

        if ($omisiones !== []) {
            $snapshot['omisiones'] = $omisiones;
        }

        return $snapshot;
    }

    /**
     * @param  list<array{campo?: string, motivo?: string}>  $omisiones
     * @return list<array{campo: string, motivo: string, actor_id: int}>
     */
    private function resolverOmisiones(
        ResguardoPdv $resguardo,
        User $actor,
        array $omisiones,
        ?UploadedFile $archivoTicket,
        ?UploadedFile $fotoPaquete,
    ): array {
        $faltantes = OmisionRequisitoResguardoPdv::faltantes(
            $resguardo,
            $this->flujoCompleto($actor),
            true,
        );
        $faltantes = OmisionRequisitoResguardoPdv::cubrirConArchivo(
            $faltantes,
            OmisionRequisitoResguardoPdv::TICKET,
            $archivoTicket,
        );
        $faltantes = OmisionRequisitoResguardoPdv::cubrirConArchivo(
            $faltantes,
            OmisionRequisitoResguardoPdv::FOTO_PAQUETE,
            $fotoPaquete,
        );

        return OmisionRequisitoResguardoPdv::resolver($omisiones, $faltantes, $actor->id, 'recepcion');
    }

    /**
     * @param  list<string>  $pathsEscritos
     */
    private function adjuntarEvidenciaSiFalta(
        ResguardoPdv $resguardo,
        ResguardoPdvEvento $evento,
        ?UploadedFile $archivo,
        string $uso,
        int $actorId,
        array &$pathsEscritos,
    ): void {
        if (! $archivo instanceof UploadedFile || ! EvidenciaMinimaRegistroManualPdv::esManual($resguardo)) {
            return;
        }

        $resguardo->unsetRelation('evidencias');
        if (in_array($uso, EvidenciaMinimaRegistroManualPdv::usosPresentes($resguardo), true)) {
            return;
        }

        $this->ingresarManual->guardar($resguardo, $evento, $archivo, $uso, $actorId, $pathsEscritos);
        $resguardo->unsetRelation('evidencias');
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
                throw new ConflictHttpException('Este resguardo ya fue recibido por gerencia.');
            }

            throw ValidationException::withMessages([
                'estado' => 'El resguardo no admite recepción física desde su estado actual.',
            ]);
        }
    }

    private function flujoCompleto(User $actor): bool
    {
        return $this->alcance->tienePermisoPdv($actor, PuntoVentaModulo::PERMISO_RESGUARDOS_FLUJO_COMPLETO);
    }

    /**
     * @param  list<array{producto_id: int, sku: string|null, descripcion: string|null, cantidad: int, nota: string|null}>  $piezas
     */
    private function guardarPiezas(ResguardoPdv $resguardo, array $piezas): void
    {
        $snapshot = is_array($resguardo->snapshot_json) ? $resguardo->snapshot_json : [];
        $snapshot['piezas'] = $piezas;
        $snapshot['cantidad_piezas'] = NormalizadorPiezasRegistroManualPdv::cantidad($piezas);
        $resguardo->update(['snapshot_json' => $snapshot]);
        $resguardo->refresh();
    }
}

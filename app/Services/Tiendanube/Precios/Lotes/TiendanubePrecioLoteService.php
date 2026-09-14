<?php

namespace App\Services\Tiendanube\Precios\Lotes;

use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Exceptions\Tiendanube\TiendanubePrecioReglaException;
use App\Exceptions\Tiendanube\TiendanubePrecioSeleccionException;
use App\Jobs\Tiendanube\SimularPrecioLoteJob;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Models\Tiendanube\TiendanubePrecioLoteItem;
use App\Models\Tiendanube\TiendanubePrecioLoteRevision;
use App\Models\Tiendanube\TiendanubePrecioLoteSimulacion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Services\Tiendanube\Precios\Exportacion\TiendanubePrecioCsvPerfilService;
use App\Services\Tiendanube\Precios\TiendanubePrecioFuenteResolverService;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorCalculoService;
use App\Services\Tiendanube\Precios\TiendanubePrecioReglaService;
use App\Services\Tiendanube\Precios\TiendanubePrecioReglaVersionService;
use App\Services\Tiendanube\Precios\TiendanubePrecioSeleccionService;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TiendanubePrecioLoteService
{
    public function __construct(
        private readonly TiendanubePrecioSeleccionService $selecciones,
        private readonly TiendanubePrecioFuenteResolverService $fuentes,
        private readonly TiendanubePrecioReglaService $reglas,
        private readonly TiendanubePrecioReglaVersionService $versiones,
        private readonly TiendanubePrecioLoteSimulacionService $simulacion,
        private readonly TiendanubeApiClient $api,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function crear(int $storeId, int $userId, array $datos): array
    {
        $selectionId = (string) ($datos['selection_id'] ?? '');
        $selectionVersion = isset($datos['selection_version']) ? (int) $datos['selection_version'] : null;
        if ($selectionId === '' || $selectionVersion === null) {
            throw new TiendanubePrecioLoteException('Se requiere selection_id y selection_version.', 'validacion');
        }

        try {
            $seleccion = $this->selecciones->consultar($selectionId, $storeId, $userId, [], $selectionVersion);
        } catch (TiendanubePrecioSeleccionException $e) {
            throw new TiendanubePrecioLoteException($e->getMessage(), $e->codigo, $e->httpStatus, [], $e);
        }

        $definicionMeta = $this->resolverDefinicion($datos, $storeId);
        try {
            $this->versiones->validarDefinicion($definicionMeta['definicion']);
            $this->versiones->validarListasTienda($storeId, $definicionMeta['definicion']);
        } catch (TiendanubePrecioReglaException $e) {
            throw new TiendanubePrecioLoteException($e->getMessage(), $e->codigo, $e->httpStatus, $e->errores, $e);
        }

        $origen = (string) ($datos['origen'] ?? TiendanubePrecioLote::ORIGEN_CALCULO);
        if (! in_array($origen, [TiendanubePrecioLote::ORIGEN_CALCULO, TiendanubePrecioLote::ORIGEN_RESTAURACION], true)) {
            $origen = TiendanubePrecioLote::ORIGEN_CALCULO;
        }
        $loteOrigenId = $datos['lote_origen_id'] ?? null;
        $motivoRestauracion = isset($datos['motivo_restauracion']) ? (string) $datos['motivo_restauracion'] : null;

        $config = TiendanubeConfiguracion::obtener();
        $lote = DB::transaction(function () use ($storeId, $userId, $seleccion, $definicionMeta, $config, $selectionId, $selectionVersion, $origen, $loteOrigenId, $motivoRestauracion) {
            $lote = TiendanubePrecioLote::query()->create([
                'id' => (string) Str::uuid(),
                'store_id' => $storeId,
                'user_id' => $userId,
                'config_generation' => (int) ($config->config_generation ?: 1),
                'api_version' => $this->api->configuredVersion(),
                'motor_contract_version' => TiendanubePrecioMotorCalculoService::MOTOR_VERSION,
                'moneda' => 'MXN',
                'selection_id' => $selectionId,
                'selection_version' => $selectionVersion,
                'selection_generacion' => (int) ($seleccion['generacion'] ?? 1),
                'selection_modo' => (string) ($seleccion['modo'] ?? ''),
                'selection_filtros' => $seleccion['filtros'] ?? [],
                'estado' => TiendanubePrecioLote::ESTADO_BORRADOR,
                'origen' => $origen,
                'lote_origen_id' => $loteOrigenId,
                'motivo_restauracion' => $motivoRestauracion,
                'revision_actual_numero' => 1,
                'fecha_lectura' => now(),
            ]);

            $revision = TiendanubePrecioLoteRevision::query()->create([
                'lote_id' => $lote->id,
                'numero' => 1,
                'estado' => TiendanubePrecioLoteRevision::ESTADO_BORRADOR,
                'regla_id' => $definicionMeta['regla_id'],
                'regla_version_id' => $definicionMeta['regla_version_id'],
                'definicion' => $definicionMeta['definicion'],
            ]);

            $this->congelarYCapturar($lote, $revision, $storeId, $userId, $selectionId);
            $this->registrarEvento($lote, $revision, TiendanubePrecioLoteEvento::TIPO_LOTE_CREADO, $userId, [
                'selection_id' => $selectionId,
                'selection_version' => $selectionVersion,
            ]);

            return $lote->fresh();
        });

        $this->iniciarSimulacion($lote, $userId);

        return $this->payload($lote->fresh(['revisiones.simulacion']), $userId, true);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function recalcular(string $loteId, int $storeId, int $userId, array $datos): array
    {
        $lote = $this->obtenerAutorizado($loteId, $storeId, $userId);
        $this->asegurarEditable($lote);
        $actual = $lote->revisionActual();
        if (! $actual) {
            throw new TiendanubePrecioLoteException('El lote no tiene revisión.', 'no_encontrada', 404);
        }

        $nuevaDef = null;
        if (isset($datos['definicion']) || isset($datos['regla_id'])) {
            $nuevaDef = $this->resolverDefinicion($datos, $storeId);
            try {
                $this->versiones->validarDefinicion($nuevaDef['definicion']);
                $this->versiones->validarListasTienda($storeId, $nuevaDef['definicion']);
            } catch (TiendanubePrecioReglaException $e) {
                throw new TiendanubePrecioLoteException($e->getMessage(), $e->codigo, $e->httpStatus, $e->errores, $e);
            }
        }

        $defCambia = $nuevaDef !== null && json_encode($nuevaDef['definicion']) !== json_encode($actual->definicion);

        if ($defCambia) {
            $revision = $this->clonarRevision($lote, $actual, $nuevaDef, $userId, true);
        } else {
            $revision = $actual;
            $revision->items()->update([
                'ajustes_manuales' => null,
                'resultado_calculado' => null,
                'resultado_final' => null,
            ]);
            $revision->update(['estado' => TiendanubePrecioLoteRevision::ESTADO_BORRADOR]);
            $lote->update(['estado' => TiendanubePrecioLote::ESTADO_BORRADOR]);
        }

        $this->iniciarSimulacion($lote->fresh(), $userId, $revision->fresh());

        return $this->payload($lote->fresh(['revisiones.simulacion']), $userId, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function consultar(string $loteId, int $storeId, int $userId, bool $puedeVerCosto): array
    {
        return $this->payload($this->obtenerAutorizado($loteId, $storeId, $userId), $userId, $puedeVerCosto);
    }

    /**
     * @return array<string, mixed>
     */
    public function listarItems(string $loteId, int $storeId, int $userId, array $filtros, bool $puedeVerCosto): array
    {
        $lote = $this->obtenerAutorizado($loteId, $storeId, $userId);
        $revision = $lote->revisionActual();
        if (! $revision) {
            throw new TiendanubePrecioLoteException('El lote no tiene revisión.', 'no_encontrada', 404);
        }

        $query = $revision->items()->orderBy('variante_id');
        $filtro = (string) ($filtros['filtro'] ?? 'todas');
        match ($filtro) {
            'con_cambio' => $query->where('estado_fila', TiendanubePrecioLoteItem::ESTADO_CON_CAMBIO),
            'sin_cambio' => $query->where('estado_fila', TiendanubePrecioLoteItem::ESTADO_SIN_CAMBIO),
            'con_errores' => $query->where(function ($q) {
                $q->where('estado_fila', TiendanubePrecioLoteItem::ESTADO_ERROR)
                    ->orWhere('estado_fila', TiendanubePrecioLoteItem::ESTADO_BLOQUEADA);
            }),
            'sin_costo' => $query->where(function ($q) {
                $q->whereJsonContains('valores_anteriores->sin_costo_local', true)
                    ->whereJsonContains('valores_anteriores->sin_costo_remoto', true);
            }),
            default => null,
        };

        $page = max(1, (int) ($filtros['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filtros['per_page'] ?? 25)));
        $paginado = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'lote_id' => $lote->id,
            'revision_numero' => $revision->numero,
            'checksum' => $revision->checksum,
            'filtro' => $filtro,
            'data' => collect($paginado->items())->map(fn (TiendanubePrecioLoteItem $item) => $this->serializarItem($item, $puedeVerCosto))->all(),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
                'last_page' => $paginado->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function progreso(string $loteId, int $storeId, int $userId): array
    {
        $lote = $this->obtenerAutorizado($loteId, $storeId, $userId);
        $revision = $lote->revisionActual();
        $sim = $revision?->simulacion;

        return [
            'lote_id' => $lote->id,
            'estado_lote' => $lote->estado,
            'revision_numero' => $revision?->numero,
            'estado' => $sim?->estado ?? ($lote->estado === TiendanubePrecioLote::ESTADO_SIMULADO ? 'completada' : 'pendiente'),
            'total' => $sim?->total ?? 0,
            'procesados' => $sim?->procesados ?? 0,
            'porcentaje' => $sim?->porcentaje() ?? ($lote->estado === TiendanubePrecioLote::ESTADO_SIMULADO ? 100 : 0),
            'error' => $sim?->error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelar(string $loteId, int $storeId, int $userId): array
    {
        $lote = $this->obtenerAutorizado($loteId, $storeId, $userId);
        if ($lote->estado === TiendanubePrecioLote::ESTADO_APROBADO) {
            throw new TiendanubePrecioLoteException('No se puede cancelar un lote aprobado.', 'estado_invalido');
        }
        $revision = $lote->revisionActual();
        $lote->update(['estado' => TiendanubePrecioLote::ESTADO_CANCELADO]);
        $revision?->update(['estado' => TiendanubePrecioLoteRevision::ESTADO_CANCELADO]);
        $this->registrarEvento($lote, $revision, TiendanubePrecioLoteEvento::TIPO_REVISION_CANCELADA, $userId, []);

        return $this->payload($lote->fresh(), $userId, true);
    }

    public function obtenerAutorizado(string $loteId, int $storeId, int $userId): TiendanubePrecioLote
    {
        $lote = TiendanubePrecioLote::query()->where('id', $loteId)->first();
        if (! $lote || (int) $lote->store_id !== $storeId || (int) $lote->user_id !== $userId) {
            throw new TiendanubePrecioLoteException('Lote no encontrado.', 'no_encontrada', 404);
        }

        return $lote;
    }

    public function asegurarEditable(TiendanubePrecioLote $lote): void
    {
        if ($lote->estado === TiendanubePrecioLote::ESTADO_CANCELADO) {
            throw new TiendanubePrecioLoteException('El lote está cancelado.', 'estado_invalido');
        }
        if ($lote->estado === TiendanubePrecioLote::ESTADO_APROBADO) {
            throw new TiendanubePrecioLoteException(
                'La revisión está aprobada. Simule de nuevo para crear una revisión nueva.',
                'estado_invalido'
            );
        }
    }

    public function registrarEvento(
        TiendanubePrecioLote $lote,
        ?TiendanubePrecioLoteRevision $revision,
        string $tipo,
        int $actorId,
        array $payload
    ): void {
        TiendanubePrecioLoteEvento::query()->create([
            'lote_id' => $lote->id,
            'revision_id' => $revision?->id,
            'tipo' => $tipo,
            'actor_id' => $actorId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $definicionMeta
     */
    public function clonarRevision(
        TiendanubePrecioLote $lote,
        TiendanubePrecioLoteRevision $origen,
        ?array $definicionMeta,
        int $userId,
        bool $resnapshot
    ): TiendanubePrecioLoteRevision {
        return DB::transaction(function () use ($lote, $origen, $definicionMeta, $resnapshot) {
            $numero = (int) $lote->revision_actual_numero + 1;
            $revision = TiendanubePrecioLoteRevision::query()->create([
                'lote_id' => $lote->id,
                'numero' => $numero,
                'estado' => TiendanubePrecioLoteRevision::ESTADO_BORRADOR,
                'regla_id' => $definicionMeta['regla_id'] ?? $origen->regla_id,
                'regla_version_id' => $definicionMeta['regla_version_id'] ?? $origen->regla_version_id,
                'definicion' => $definicionMeta['definicion'] ?? $origen->definicion,
            ]);

            $origen->items()->orderBy('id')->chunkById(100, function ($items) use ($revision, $resnapshot) {
                foreach ($items as $item) {
                    $nuevo = $item->replicate();
                    $nuevo->revision_id = $revision->id;
                    $nuevo->ajustes_manuales = $resnapshot ? null : $item->ajustes_manuales;
                    $nuevo->resultado_calculado = $resnapshot ? null : $item->resultado_calculado;
                    $nuevo->resultado_final = $resnapshot ? null : $item->resultado_final;
                    $nuevo->save();
                }
            });

            if ($resnapshot) {
                $this->capturarFuentes($lote, $revision);
            }

            $lote->update([
                'revision_actual_numero' => $numero,
                'estado' => TiendanubePrecioLote::ESTADO_BORRADOR,
                'fecha_lectura' => $resnapshot ? now() : $lote->fecha_lectura,
            ]);

            return $revision;
        });
    }

    private function iniciarSimulacion(TiendanubePrecioLote $lote, int $userId, ?TiendanubePrecioLoteRevision $revision = null): void
    {
        $revision = $revision ?? $lote->revisionActual();
        if (! $revision) {
            throw new TiendanubePrecioLoteException('El lote no tiene revisión.', 'no_encontrada', 404);
        }

        $total = $revision->items()->count();
        TiendanubePrecioLoteSimulacion::query()->updateOrCreate(
            ['revision_id' => $revision->id],
            [
                'estado' => TiendanubePrecioLoteSimulacion::ESTADO_PENDIENTE,
                'total' => $total,
                'procesados' => 0,
                'error' => null,
                'lease_token' => (string) Str::uuid(),
                'lease_expires_at' => now()->addMinutes(10),
            ]
        );
        $this->registrarEvento($lote, $revision, TiendanubePrecioLoteEvento::TIPO_SIMULACION_INICIADA, $userId, [
            'total' => $total,
        ]);

        $maxSync = max(0, (int) config('tiendanube.precio_lote_sync_max', 200));
        if ($total <= $maxSync) {
            try {
                $this->simulacion->ejecutar($revision);
                $this->registrarEvento($lote, $revision, TiendanubePrecioLoteEvento::TIPO_SIMULACION_COMPLETADA, $userId, [
                    'total' => $total,
                ]);
            } catch (\Throwable $e) {
                $revision->simulacion?->update([
                    'estado' => TiendanubePrecioLoteSimulacion::ESTADO_ERROR,
                    'error' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
                $this->registrarEvento($lote, $revision, TiendanubePrecioLoteEvento::TIPO_SIMULACION_FALLIDA, $userId, [
                    'error' => $e->getMessage(),
                ]);
                throw new TiendanubePrecioLoteException('No se pudo simular el lote.', 'simulacion_fallida', 500, [], $e);
            }

            return;
        }

        SimularPrecioLoteJob::dispatch($lote->id, (int) $revision->id, $userId);
    }

    private function congelarYCapturar(
        TiendanubePrecioLote $lote,
        TiendanubePrecioLoteRevision $revision,
        int $storeId,
        int $userId,
        string $selectionId
    ): void {
        $ids = $this->resolverTodosLosIds($selectionId, $storeId, $userId);
        if ($ids === []) {
            throw new TiendanubePrecioLoteException('La selección no tiene variantes.', 'seleccion_vacia');
        }

        $ahora = now();
        foreach (array_chunk($ids, 100) as $chunk) {
            $varianteIds = array_map(fn ($row) => $row['id'], $chunk);
            $variantes = TiendanubeProductoVariante::query()
                ->with(['producto.imagenes'])
                ->whereIn('id', $varianteIds)
                ->get()
                ->keyBy('id');
            $fuentesIndex = collect($this->fuentes->resolver($storeId, $varianteIds))->keyBy('variante_id');

            $filas = [];
            foreach ($chunk as $row) {
                $vid = $row['id'];
                $variante = $variantes->get($vid);
                $fuenteRow = $fuentesIndex->get($vid) ?? ['fuentes' => [], 'faltantes' => [], 'existe' => false];
                $this->assertMoneda($lote, $fuenteRow['fuentes'] ?? []);
                $filas[] = $this->filaItem($revision, $variante, $vid, $fuenteRow, $ahora);
            }
            TiendanubePrecioLoteItem::query()->insert($filas);
        }
    }

    private function capturarFuentes(TiendanubePrecioLote $lote, TiendanubePrecioLoteRevision $revision): void
    {
        $revision->items()->orderBy('id')->chunkById(100, function ($items) use ($lote) {
            $ids = $items->pluck('variante_id')->map(fn ($v) => (int) $v)->all();
            $fuentesIndex = collect($this->fuentes->resolver((int) $lote->store_id, $ids))->keyBy('variante_id');
            foreach ($items as $item) {
                $fuenteRow = $fuentesIndex->get((int) $item->variante_id) ?? ['fuentes' => [], 'existe' => false];
                $this->assertMoneda($lote, $fuenteRow['fuentes'] ?? []);
                $item->fuentes_snapshot = $fuenteRow['fuentes'] ?? [];
                $item->valores_anteriores = $this->valoresAnteriores($fuenteRow['fuentes'] ?? []);
                $item->espejo_existe = (bool) ($fuenteRow['existe'] ?? false);
                $item->save();
            }
        });
    }

    /**
     * @return list<array{id: int, valido: bool}>
     */
    private function resolverTodosLosIds(string $selectionId, int $storeId, int $userId): array
    {
        $page = 1;
        $seen = [];
        $salida = [];
        do {
            try {
                $resolucion = $this->selecciones->resolver(
                    $selectionId,
                    $storeId,
                    $userId,
                    $page,
                    max(1, min(500, (int) config('tiendanube.precio_lote_resolver_per_page', 500)))
                );
            } catch (TiendanubePrecioSeleccionException $e) {
                throw new TiendanubePrecioLoteException($e->getMessage(), $e->codigo, $e->httpStatus, [], $e);
            }
            foreach ($resolucion['variante_ids'] ?? [] as $id) {
                $id = (int) $id;
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $salida[] = ['id' => $id, 'valido' => true];
                }
            }
            foreach ($resolucion['variante_ids_invalidos'] ?? [] as $id) {
                $id = (int) $id;
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $salida[] = ['id' => $id, 'valido' => false];
                }
            }
            $meta = $resolucion['resolver_meta'] ?? ['last_page' => 1];
            $page++;
        } while ($page <= (int) $meta['last_page']);

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{definicion: array<string, mixed>, regla_id: int|null, regla_version_id: int|null}
     */
    private function resolverDefinicion(array $datos, int $storeId): array
    {
        if (isset($datos['definicion']) && is_array($datos['definicion'])) {
            return [
                'definicion' => $datos['definicion'],
                'regla_id' => isset($datos['regla_id']) ? (int) $datos['regla_id'] : null,
                'regla_version_id' => null,
            ];
        }

        $reglaId = isset($datos['regla_id']) ? (int) $datos['regla_id'] : 0;
        if ($reglaId <= 0) {
            throw new TiendanubePrecioLoteException('Se requiere definición o regla_id.', 'validacion');
        }

        try {
            $regla = $this->reglas->buscar($reglaId, $storeId);
        } catch (TiendanubePrecioReglaException $e) {
            throw new TiendanubePrecioLoteException($e->getMessage(), $e->codigo, $e->httpStatus, [], $e);
        }
        $regla->loadMissing('versionActual');
        $definicion = $regla->versionActual?->definicion;
        if (! is_array($definicion)) {
            throw new TiendanubePrecioLoteException('La regla no tiene versión utilizable.', 'no_encontrada', 404);
        }

        return [
            'definicion' => $definicion,
            'regla_id' => (int) $regla->id,
            'regla_version_id' => $regla->versionActual?->id,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fuentes
     */
    private function assertMoneda(TiendanubePrecioLote $lote, array $fuentes): void
    {
        foreach ($fuentes as $fuente) {
            if (! empty($fuente['faltante']) || empty($fuente['moneda'])) {
                continue;
            }
            if ((string) $fuente['moneda'] !== (string) $lote->moneda) {
                throw new TiendanubePrecioLoteException(
                    'Hay fuentes con moneda incompatible. No se mezclan ni convierten automáticamente.',
                    'moneda_incompatible'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fuenteRow
     * @return array<string, mixed>
     */
    private function filaItem(
        TiendanubePrecioLoteRevision $revision,
        ?TiendanubeProductoVariante $variante,
        int $varianteId,
        array $fuenteRow,
        $ahora
    ): array {
        $producto = $variante?->producto;
        $fuentes = $fuenteRow['fuentes'] ?? [];

        return [
            'revision_id' => $revision->id,
            'producto_id' => $variante ? (int) $variante->producto_id : 0,
            'variante_id' => $varianteId,
            'producto_nombre' => $producto instanceof TiendanubeProducto ? $producto->nombreVisible() : null,
            'variante_sku' => $variante?->sku,
            'variante_atributos' => json_encode($this->atributos($variante)),
            'imagen_url' => $producto?->imagenes?->sortBy('position')->first()?->src,
            'espejo_existe' => $variante !== null,
            'valores_anteriores' => json_encode($this->valoresAnteriores($fuentes)),
            'fuentes_snapshot' => json_encode($fuentes),
            'resultado_calculado' => null,
            'ajustes_manuales' => null,
            'resultado_final' => null,
            'validaciones' => null,
            'errores' => json_encode([]),
            'estado_fila' => TiendanubePrecioLoteItem::ESTADO_SIN_CAMBIO,
            'excluido' => false,
            'exclusion_motivo' => null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fuentes
     * @return array<string, mixed>
     */
    private function valoresAnteriores(array $fuentes): array
    {
        $pick = function (string $tipo) use ($fuentes): ?string {
            foreach ($fuentes as $fuente) {
                if (($fuente['tipo'] ?? '') !== $tipo) {
                    continue;
                }
                if (! empty($fuente['faltante'])) {
                    return null;
                }

                return $fuente['valor_decimal'] ?? null;
            }

            return null;
        };

        $normal = $pick(TiendanubePrecioFuenteVersion::TIPO_PRECIO_NORMAL_ACTUAL);
        $promo = $pick(TiendanubePrecioFuenteVersion::TIPO_PRECIO_PROMOCIONAL_ACTUAL);
        $costoLocal = $pick(TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL);
        $costoRemoto = $pick(TiendanubePrecioFuenteVersion::TIPO_COSTO_REMOTO_ACTUAL);

        return [
            'normal' => $normal,
            'promocional' => $promo,
            'costo_local' => $costoLocal,
            'costo_remoto' => $costoRemoto,
            'sin_costo_local' => $costoLocal === null,
            'sin_costo_remoto' => $costoRemoto === null,
            'costo_remoto_cero' => $costoRemoto === '0.00' || $costoRemoto === '0',
        ];
    }

    /**
     * @return list<string>
     */
    private function atributos(?TiendanubeProductoVariante $variante): array
    {
        if (! $variante) {
            return [];
        }
        $values = $variante->values;
        if (! is_array($values)) {
            return [];
        }
        $salida = [];
        foreach ($values as $valor) {
            if (is_string($valor) || is_numeric($valor)) {
                $salida[] = (string) $valor;
            } elseif (is_array($valor)) {
                $es = $valor['es'] ?? $valor['es_MX'] ?? reset($valor);
                if ($es !== false && $es !== null) {
                    $salida[] = (string) $es;
                }
            }
        }

        return $salida;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(TiendanubePrecioLote $lote, int $userId, bool $puedeVerCosto): array
    {
        $revision = $lote->revisionActual();
        $sim = $revision?->simulacion;
        $resumen = $revision?->resumen ?? [];
        $definicion = $revision?->definicion ?? [];

        return [
            'lote_id' => $lote->id,
            'store_id' => (int) $lote->store_id,
            'estado' => $lote->estado,
            'origen' => $lote->origen ?? TiendanubePrecioLote::ORIGEN_CALCULO,
            'lote_origen_id' => $lote->lote_origen_id,
            'motivo_restauracion' => $lote->motivo_restauracion,
            'config_generation' => (int) $lote->config_generation,
            'api_version' => $lote->api_version,
            'motor_contract_version' => $lote->motor_contract_version,
            'moneda' => $lote->moneda,
            'fecha_lectura' => $lote->fecha_lectura?->toIso8601String(),
            'selection_id' => $lote->selection_id,
            'selection_version' => (int) $lote->selection_version,
            'selection_modo' => $lote->selection_modo,
            'selection_filtros' => $lote->selection_filtros,
            'revision' => $revision ? [
                'id' => $revision->id,
                'numero' => (int) $revision->numero,
                'estado' => $revision->estado,
                'checksum' => $revision->checksum,
                'regla_id' => $revision->regla_id,
                'definicion' => $definicion,
                'resumen' => $resumen,
                'aprobado_at' => $revision->aprobado_at?->toIso8601String(),
            ] : null,
            'simulacion' => [
                'estado' => $sim?->estado ?? ($lote->estado === TiendanubePrecioLote::ESTADO_SIMULADO ? 'completada' : 'pendiente'),
                'total' => $sim?->total ?? 0,
                'procesados' => $sim?->procesados ?? 0,
                'porcentaje' => $sim?->porcentaje() ?? 0,
                'error' => $sim?->error,
            ],
            'fuente' => $definicion['base'] ?? null,
            'operacion' => $definicion['operacion'] ?? null,
            'destino' => $definicion['destino'] ?? null,
            'canales_disponibles' => $lote->estado === TiendanubePrecioLote::ESTADO_APROBADO
                ? $this->canalesAprobados((int) $lote->store_id)
                : [],
            'aprobacion_habilitada' => (bool) config('tiendanube.precios_aprobacion_habilitada', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializarItem(TiendanubePrecioLoteItem $item, bool $puedeVerCosto): array
    {
        $final = $item->resultado_final ?? [];
        $antes = $item->valores_anteriores ?? [];
        $campos = [];
        foreach (TiendanubePrecioDestino::cases() as $destino) {
            $clave = $destino->value;
            $campo = $final['campos'][$clave] ?? null;
            $mapaAntes = [
                'normal' => $antes['normal'] ?? null,
                'promocional' => $antes['promocional'] ?? null,
                'costo_remoto' => $antes['costo_remoto'] ?? null,
            ];
            $intencion = $campo['intencion'] ?? 'conservar';
            $propuesto = $intencion === 'eliminar' ? null : ($campo['valor_final'] ?? $mapaAntes[$clave] ?? null);
            $actual = $mapaAntes[$clave] ?? null;
            $sinCambio = $intencion === 'conservar' || (string) $actual === (string) $propuesto;
            $filaCampo = [
                'actual' => $actual,
                'propuesto' => $sinCambio ? $actual : $propuesto,
                'sin_cambio' => $sinCambio,
                'intencion' => $intencion,
                'diferencia' => $sinCambio ? null : ($final['diferencia_absoluta'] ?? null),
                'variacion_porcentual' => $sinCambio ? null : ($final['variacion_porcentual'] ?? null),
            ];
            if (! $puedeVerCosto && $destino === TiendanubePrecioDestino::CostoRemoto) {
                $filaCampo['actual'] = null;
                $filaCampo['propuesto'] = null;
            }
            $campos[$clave] = $filaCampo;
        }

        $fila = [
            'id' => $item->id,
            'producto_id' => (int) $item->producto_id,
            'variante_id' => (int) $item->variante_id,
            'nombre' => $item->producto_nombre,
            'sku' => $item->variante_sku,
            'atributos' => $item->variante_atributos ?? [],
            'miniatura' => $item->imagen_url,
            'estado_fila' => $item->estado_fila,
            'excluido' => (bool) $item->excluido,
            'exclusion_motivo' => $item->exclusion_motivo,
            'errores' => $item->errores ?? [],
            'publicable' => (bool) (($item->validaciones['publicable'] ?? false)),
            'margen_estimado' => $puedeVerCosto ? ($final['margen_estimado'] ?? null) : null,
            'campos' => $campos,
            'ajustes_manuales' => $item->ajustes_manuales,
            'espejo_existe' => (bool) $item->espejo_existe,
        ];

        if ($puedeVerCosto) {
            $fila['costo_local'] = $antes['costo_local'] ?? null;
            $fila['costo_remoto'] = $antes['costo_remoto'] ?? null;
            $fila['sin_costo'] = (bool) (($antes['sin_costo_local'] ?? true) && ($antes['sin_costo_remoto'] ?? true));
            $fila['costo_remoto_cero'] = (bool) ($antes['costo_remoto_cero'] ?? false);
        }

        return $fila;
    }

    /**
     * @return list<array{id: string, nombre: string, disponible: bool, nota: string}>
     */
    private function canalesAprobados(int $storeId): array
    {
        $perfil = app(TiendanubePrecioCsvPerfilService::class)
            ->actual($storeId);
        $csvListo = $perfil?->estaValidado() ?? false;

        return [
            [
                'id' => 'csv',
                'nombre' => 'Exportar CSV',
                'disponible' => $csvListo,
                'nota' => $csvListo
                    ? 'Genera un archivo nativo para importar en TiendaNube'
                    : 'Configura una exportación de ejemplo de tu tienda',
            ],
            [
                'id' => 'api',
                'nombre' => 'Publicar por API',
                'disponible' => (bool) config('tiendanube.precios_aplicacion_habilitada', true),
                'nota' => 'Aplica en TiendaNube los importes aprobados de esta revisión',
            ],
        ];
    }
}

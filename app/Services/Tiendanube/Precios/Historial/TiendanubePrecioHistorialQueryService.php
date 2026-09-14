<?php

namespace App\Services\Tiendanube\Precios\Historial;

use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Models\Tiendanube\TiendanubePrecioConciliacion;
use App\Models\Tiendanube\TiendanubePrecioCsvArtefacto;
use App\Models\Tiendanube\TiendanubePrecioEjecucion;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Models\Tiendanube\TiendanubePrecioLoteItem;
use App\Models\Tiendanube\TiendanubePrecioLoteRevision;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteService;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use Illuminate\Database\Eloquent\Builder;

class TiendanubePrecioHistorialQueryService
{
    public const EVIDENCIA_BORRADOR = 'borrador';

    public const EVIDENCIA_SIMULADO = 'simulado';

    public const EVIDENCIA_APROBADO = 'aprobado';

    public const EVIDENCIA_CSV_GENERADO = 'csv_generado';

    public const EVIDENCIA_ARCHIVO_DESCARGADO = 'archivo_descargado';

    public const EVIDENCIA_IMPORTACION_DECLARADA = 'importacion_declarada';

    public const EVIDENCIA_APLICADO_API = 'aplicado_api';

    public const EVIDENCIA_VERIFICADO = 'verificado';

    public const EVIDENCIA_COINCIDENCIA_PREVIA = 'coincidencia_previa';

    public const EVIDENCIA_POR_VERIFICAR = 'por_verificar';

    public const EVIDENCIA_CONFLICTO = 'conflicto';

    public const EVIDENCIA_CANCELADO = 'cancelado';

    public function __construct(
        private readonly TiendanubePrecioLoteService $lotes,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function listarOperaciones(int $storeId, array $filtros, bool $puedeVerCosto): array
    {
        $base = TiendanubePrecioLote::query()->where('store_id', $storeId);
        $sinFiltros = $this->sinFiltros($filtros);
        $universoVacio = (clone $base)->doesntExist();

        $query = $this->aplicarFiltros(clone $base, $filtros);
        $page = max(1, (int) ($filtros['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($filtros['per_page'] ?? 25)));
        $paginado = $query->with(['user'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $ids = collect($paginado->items())->pluck('id')->all();
        $contextos = $this->contextosLotes($ids);

        $data = [];
        foreach ($paginado->items() as $lote) {
            $fila = $this->serializarListado($lote, $contextos[$lote->id] ?? []);
            if (! $puedeVerCosto) {
                unset($fila['diagnostico']);
            }
            $data[] = $fila;
        }

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
                'last_page' => $paginado->lastPage(),
            ],
            'vacio' => $universoVacio,
            'sin_resultados' => ! $universoVacio && $paginado->total() === 0,
            'filtros_activos' => ! $sinFiltros,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function consultarOperacion(string $loteId, int $storeId, bool $puedeVerCosto, bool $diagnostico = false): array
    {
        $lote = $this->obtenerDeTienda($loteId, $storeId);
        $revision = $lote->revisionActual();
        $contexto = $this->contextosLotes([$lote->id])[$lote->id] ?? [];
        $listado = $this->serializarListado($lote, $contexto);

        $items = [];
        if ($revision) {
            $ejecucionItems = $this->itemsEjecucionPorVariante($contexto['ejecucion'] ?? null);
            foreach ($revision->items()->orderBy('variante_id')->get() as $item) {
                $items[] = $this->serializarItemHistorial(
                    $item,
                    $ejecucionItems[(int) $item->variante_id] ?? null,
                    $puedeVerCosto
                );
            }
        }

        $eventos = $lote->eventos()
            ->with('actor')
            ->orderBy('id')
            ->get()
            ->map(fn (TiendanubePrecioLoteEvento $e) => [
                'tipo' => $e->tipo,
                'actor' => $e->actor?->name,
                'fecha' => $e->created_at?->toIso8601String(),
                'payload' => $this->sanearPayloadEvento($e->payload ?? [], $puedeVerCosto, $diagnostico),
            ])
            ->all();

        $csv = ($contexto['csv'] ?? collect())->map(fn (TiendanubePrecioCsvArtefacto $a) => [
            'id' => $a->id,
            'estado' => $a->estado,
            'nombre_archivo' => $a->nombre_archivo,
            'generado_at' => $a->generado_at?->toIso8601String(),
            'descargado_at' => $a->descargado_at?->toIso8601String(),
            'importacion_declarada_at' => $a->importacion_declarada_at?->toIso8601String(),
            'hash_sha256' => $diagnostico ? $a->hash_sha256 : null,
        ])->values()->all();

        $conciliaciones = $lote->conciliaciones()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (TiendanubePrecioConciliacion $c) => $this->serializarConciliacion($c, $puedeVerCosto))
            ->all();

        $payload = [
            'operacion' => $listado,
            'lote' => $this->lotes->payload($lote, (int) $lote->user_id, $puedeVerCosto),
            'items' => $items,
            'eventos' => $eventos,
            'csv' => $csv,
            'ejecucion' => $this->serializarEjecucionResumen($contexto['ejecucion'] ?? null),
            'conciliaciones' => $conciliaciones,
            'lote_origen_id' => $lote->lote_origen_id,
            'compensaciones' => $lote->compensaciones()->orderByDesc('created_at')->get(['id', 'estado', 'origen', 'created_at'])
                ->map(fn (TiendanubePrecioLote $c) => [
                    'lote_id' => $c->id,
                    'estado' => $c->estado,
                    'origen' => $c->origen,
                    'fecha' => $c->created_at?->toIso8601String(),
                ])->all(),
        ];

        if ($diagnostico) {
            $payload['diagnostico'] = [
                'config_generation' => (int) $lote->config_generation,
                'api_version' => $lote->api_version,
                'motor_contract_version' => $lote->motor_contract_version,
                'checksum' => $revision?->checksum,
                'huella_conflicto_remoto' => $revision?->huella_conflicto_remoto,
            ];
        }

        return $payload;
    }

    public function obtenerDeTienda(string $loteId, int $storeId): TiendanubePrecioLote
    {
        $lote = TiendanubePrecioLote::query()->where('id', $loteId)->first();
        if (! $lote || (int) $lote->store_id !== $storeId) {
            throw new TiendanubePrecioLoteException('Operación no encontrada.', 'no_encontrada', 404);
        }

        return $lote;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $query->whereHas('revisiones.items', function (Builder $items) use ($q) {
                $items->where(function (Builder $inner) use ($q) {
                    $inner->where('variante_sku', 'like', '%'.$q.'%')
                        ->orWhere('producto_nombre', 'like', '%'.$q.'%');
                });
            });
        }

        if (! empty($filtros['variante_id'])) {
            $vid = (int) $filtros['variante_id'];
            $query->whereHas('revisiones.items', fn (Builder $items) => $items->where('variante_id', $vid));
        }

        if (! empty($filtros['desde'])) {
            $query->where('created_at', '>=', $filtros['desde'].' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $query->where('created_at', '<=', $filtros['hasta'].' 23:59:59');
        }

        $actor = (int) ($filtros['actor_id'] ?? 0);
        if ($actor > 0) {
            $query->where('user_id', $actor);
        }

        $canal = (string) ($filtros['canal'] ?? '');
        if ($canal === 'api') {
            $query->whereHas('ejecuciones');
        } elseif ($canal === 'csv') {
            $query->whereHas('csvArtefactos');
        } elseif ($canal === 'aprobado_sin_entrega') {
            $query->where('estado', TiendanubePrecioLote::ESTADO_APROBADO)
                ->whereDoesntHave('ejecuciones')
                ->whereDoesntHave('csvArtefactos', function (Builder $csv) {
                    $csv->whereNotNull('descargado_at')->orWhereNotNull('importacion_declarada_at');
                });
        }

        $resultado = (string) ($filtros['resultado'] ?? '');
        if ($resultado !== '') {
            $query->where(function (Builder $inner) use ($resultado) {
                $this->filtrarPorEvidencia($inner, $resultado);
            });
        }

        return $query;
    }

    private function filtrarPorEvidencia(Builder $query, string $resultado): void
    {
        match ($resultado) {
            self::EVIDENCIA_APLICADO_API, self::EVIDENCIA_VERIFICADO => $query->whereHas(
                'ejecuciones',
                fn (Builder $e) => $e->where('confirmadas', '>', 0)
            ),
            self::EVIDENCIA_POR_VERIFICAR => $query->whereHas(
                'ejecuciones',
                fn (Builder $e) => $e->where('por_verificar', '>', 0)
            ),
            self::EVIDENCIA_CONFLICTO => $query->whereHas(
                'ejecuciones',
                fn (Builder $e) => $e->where('conflictos', '>', 0)
            ),
            self::EVIDENCIA_COINCIDENCIA_PREVIA => $query->whereHas(
                'ejecuciones.items',
                fn (Builder $i) => $i->where('estado', TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO)
                    ->where('error_codigo', 'coincidencia_previa')
            ),
            self::EVIDENCIA_IMPORTACION_DECLARADA => $query->whereHas(
                'csvArtefactos',
                fn (Builder $c) => $c->whereNotNull('importacion_declarada_at')
            ),
            self::EVIDENCIA_ARCHIVO_DESCARGADO => $query->whereHas(
                'csvArtefactos',
                fn (Builder $c) => $c->whereNotNull('descargado_at')->whereNull('importacion_declarada_at')
            )->whereDoesntHave('ejecuciones', fn (Builder $e) => $e->where('confirmadas', '>', 0)),
            self::EVIDENCIA_APROBADO => $query->where('estado', TiendanubePrecioLote::ESTADO_APROBADO),
            self::EVIDENCIA_CANCELADO => $query->where('estado', TiendanubePrecioLote::ESTADO_CANCELADO),
            default => $query->where('estado', $resultado),
        };
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>>
     */
    private function contextosLotes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $revisiones = TiendanubePrecioLoteRevision::query()
            ->whereIn('lote_id', $ids)
            ->get()
            ->groupBy('lote_id');

        $ejecuciones = TiendanubePrecioEjecucion::query()
            ->whereIn('lote_id', $ids)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('lote_id');

        $csv = TiendanubePrecioCsvArtefacto::query()
            ->whereIn('lote_id', $ids)
            ->orderByDesc('id')
            ->get()
            ->groupBy('lote_id');

        $conteos = TiendanubePrecioLoteItem::query()
            ->selectRaw('tiendanube_precio_lote_revisiones.lote_id, count(*) as variantes, count(distinct producto_id) as productos')
            ->join('tiendanube_precio_lote_revisiones', 'tiendanube_precio_lote_revisiones.id', '=', 'tiendanube_precio_lote_items.revision_id')
            ->join('tiendanube_precio_lotes', function ($join) {
                $join->on('tiendanube_precio_lotes.id', '=', 'tiendanube_precio_lote_revisiones.lote_id')
                    ->on('tiendanube_precio_lotes.revision_actual_numero', '=', 'tiendanube_precio_lote_revisiones.numero');
            })
            ->whereIn('tiendanube_precio_lote_revisiones.lote_id', $ids)
            ->groupBy('tiendanube_precio_lote_revisiones.lote_id')
            ->get()
            ->keyBy('lote_id');

        $salida = [];
        foreach ($ids as $id) {
            $revs = $revisiones->get($id) ?? collect();
            $loteNumero = TiendanubePrecioLote::query()->where('id', $id)->value('revision_actual_numero');
            $revision = $revs->firstWhere('numero', $loteNumero) ?? $revs->sortByDesc('numero')->first();
            $salida[$id] = [
                'revision' => $revision,
                'ejecucion' => ($ejecuciones->get($id) ?? collect())->first(),
                'csv' => $csv->get($id) ?? collect(),
                'productos' => (int) ($conteos[$id]->productos ?? 0),
                'variantes' => (int) ($conteos[$id]->variantes ?? 0),
            ];
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    private function serializarListado(TiendanubePrecioLote $lote, array $contexto): array
    {
        $evidencia = $this->derivarEvidencia($lote, $contexto);
        $revision = $contexto['revision'] ?? null;
        $definicion = is_array($revision?->definicion) ? $revision->definicion : [];
        $descripcion = $lote->origen === TiendanubePrecioLote::ORIGEN_RESTAURACION
            ? 'Restauración compensatoria'
            : (string) ($definicion['id'] ?? 'Cálculo de precios');

        return [
            'lote_id' => $lote->id,
            'fecha' => $lote->created_at?->toIso8601String(),
            'descripcion' => $descripcion,
            'actor' => $lote->user?->name,
            'actor_id' => (int) $lote->user_id,
            'canal' => $evidencia['canal'],
            'productos' => (int) ($contexto['productos'] ?? 0),
            'variantes' => (int) ($contexto['variantes'] ?? 0),
            'estado' => $lote->estado,
            'origen' => $lote->origen ?? TiendanubePrecioLote::ORIGEN_CALCULO,
            'lote_origen_id' => $lote->lote_origen_id,
            'evidencia' => $evidencia['codigo'],
            'evidencia_etiqueta' => $evidencia['etiqueta'],
            'evidencia_ayuda' => $evidencia['ayuda'],
        ];
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array{codigo: string, etiqueta: string, ayuda: string, canal: string|null}
     */
    public function derivarEvidencia(TiendanubePrecioLote $lote, array $contexto): array
    {
        /** @var TiendanubePrecioEjecucion|null $ejecucion */
        $ejecucion = $contexto['ejecucion'] ?? null;
        $csv = $contexto['csv'] ?? collect();

        if ($lote->estado === TiendanubePrecioLote::ESTADO_CANCELADO) {
            return $this->etiqueta(self::EVIDENCIA_CANCELADO, 'Cancelado', 'La revisión se canceló. No implica un cambio publicado.', null);
        }

        if ($ejecucion) {
            if ((int) $ejecucion->por_verificar > 0) {
                return $this->etiqueta(
                    self::EVIDENCIA_POR_VERIFICAR,
                    'Por verificar',
                    'La API no confirmó el resultado a tiempo. Hay que reconsultar el valor remoto.',
                    'api'
                );
            }
            if ((int) $ejecucion->conflictos > 0 && (int) $ejecucion->confirmadas === 0) {
                return $this->etiqueta(
                    self::EVIDENCIA_CONFLICTO,
                    'Conflicto',
                    'El precio remoto cambió respecto del snapshot de origen.',
                    'api'
                );
            }
            if ((int) $ejecucion->confirmadas > 0) {
                $tieneCoincidencia = TiendanubePrecioEjecucionItem::query()
                    ->where('ejecucion_id', $ejecucion->id)
                    ->where('error_codigo', 'coincidencia_previa')
                    ->exists();
                if ($tieneCoincidencia && (int) $ejecucion->confirmadas === (int) $ejecucion->total) {
                    return $this->etiqueta(
                        self::EVIDENCIA_COINCIDENCIA_PREVIA,
                        'Coincidencia previa',
                        'El valor remoto ya coincidía con el aprobado. No se volvió a escribir.',
                        'api'
                    );
                }

                return $this->etiqueta(
                    self::EVIDENCIA_APLICADO_API,
                    'Aplicado por API',
                    'La tienda confirmó por lectura los importes enviados.',
                    'api'
                );
            }

            return $this->etiqueta(
                self::EVIDENCIA_VERIFICADO,
                'Verificado',
                'Hay una ejecución de API en curso o pendiente de resultado.',
                'api'
            );
        }

        $declarado = $csv->first(fn (TiendanubePrecioCsvArtefacto $a) => $a->importacion_declarada_at !== null);
        if ($declarado) {
            return $this->etiqueta(
                self::EVIDENCIA_IMPORTACION_DECLARADA,
                'Importación declarada',
                'Alguien declaró haber importado el archivo. No equivale a un cambio confirmado por API.',
                'csv'
            );
        }

        $descargado = $csv->first(fn (TiendanubePrecioCsvArtefacto $a) => $a->descargado_at !== null);
        if ($descargado) {
            return $this->etiqueta(
                self::EVIDENCIA_ARCHIVO_DESCARGADO,
                'Archivo descargado',
                'Se descargó un CSV. Una descarga no es un cambio confirmado en la tienda.',
                'csv'
            );
        }

        $generado = $csv->isNotEmpty();
        if ($lote->estado === TiendanubePrecioLote::ESTADO_APROBADO) {
            if ($generado) {
                return $this->etiqueta(
                    self::EVIDENCIA_CSV_GENERADO,
                    'Archivo generado',
                    'Hay un CSV listo. Todavía no se descargó ni se aplicó por API.',
                    'csv'
                );
            }

            return $this->etiqueta(
                self::EVIDENCIA_APROBADO,
                'Aprobado',
                'La revisión está aprobada y aún no tiene entrega por CSV ni API.',
                null
            );
        }

        if ($lote->estado === TiendanubePrecioLote::ESTADO_SIMULADO) {
            return $this->etiqueta(self::EVIDENCIA_SIMULADO, 'Simulado', 'Hay una simulación lista para revisar.', null);
        }

        return $this->etiqueta(self::EVIDENCIA_BORRADOR, 'Borrador', 'La operación todavía no se simuló ni aprobó.', null);
    }

    /**
     * @return array{codigo: string, etiqueta: string, ayuda: string, canal: string|null}
     */
    private function etiqueta(string $codigo, string $etiqueta, string $ayuda, ?string $canal): array
    {
        return [
            'codigo' => $codigo,
            'etiqueta' => $etiqueta,
            'ayuda' => $ayuda,
            'canal' => $canal,
        ];
    }

    /**
     * @return array<int, TiendanubePrecioEjecucionItem>
     */
    private function itemsEjecucionPorVariante(?TiendanubePrecioEjecucion $ejecucion): array
    {
        if (! $ejecucion) {
            return [];
        }

        return $ejecucion->items()->get()->keyBy(fn (TiendanubePrecioEjecucionItem $i) => (int) $i->variante_id)->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarItemHistorial(
        TiendanubePrecioLoteItem $item,
        ?TiendanubePrecioEjecucionItem $ejecucionItem,
        bool $puedeVerCosto
    ): array {
        $base = $this->lotes->serializarItem($item, $puedeVerCosto);
        $confirmado = $ejecucionItem?->valor_confirmado;
        foreach (TiendanubePrecioDestino::cases() as $destino) {
            $clave = $destino->value;
            if (! isset($base['campos'][$clave])) {
                continue;
            }
            $valor = is_array($confirmado) ? ($confirmado[$clave] ?? $confirmado[$destino->value] ?? null) : null;
            if ($destino === TiendanubePrecioDestino::CostoRemoto && ! $puedeVerCosto) {
                $valor = null;
            }
            $base['campos'][$clave]['confirmado'] = $valor;
            $base['campos'][$clave]['estado_aplicacion'] = $ejecucionItem?->estado;
        }
        $base['categorias_historicas'] = $item->valores_anteriores['categorias'] ?? null;

        return $base;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializarEjecucionResumen(?TiendanubePrecioEjecucion $ejecucion): ?array
    {
        if (! $ejecucion) {
            return null;
        }

        return [
            'id' => $ejecucion->id,
            'canal' => $ejecucion->canal,
            'estado' => $ejecucion->estado,
            'confirmadas' => (int) $ejecucion->confirmadas,
            'conflictos' => (int) $ejecucion->conflictos,
            'por_verificar' => (int) $ejecucion->por_verificar,
            'fallidas' => (int) $ejecucion->fallidas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarConciliacion(TiendanubePrecioConciliacion $c, bool $puedeVerCosto): array
    {
        $fila = [
            'id' => $c->id,
            'variante_id' => (int) $c->variante_id,
            'campo' => $c->campo,
            'valor_operacion' => $c->valor_operacion,
            'valor_remoto' => $c->valor_remoto,
            'evidencia_tipo' => $c->evidencia_tipo,
            'resultado' => $c->resultado,
            'explicacion' => $c->explicacion,
            'evidencia_at' => $c->evidencia_at?->toIso8601String(),
        ];
        if (! $puedeVerCosto && $c->campo === TiendanubePrecioDestino::CostoRemoto->value) {
            $fila['valor_operacion'] = null;
            $fila['valor_remoto'] = null;
            $fila['explicacion'] = 'Costo oculto por permisos.';
        }

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanearPayloadEvento(array $payload, bool $puedeVerCosto, bool $diagnostico): array
    {
        if (! $diagnostico) {
            unset($payload['hash'], $payload['checksum'], $payload['config_generation']);
        }
        if (! $puedeVerCosto) {
            unset($payload['costo'], $payload['costo_remoto'], $payload['costo_local']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function sinFiltros(array $filtros): bool
    {
        foreach (['q', 'variante_id', 'desde', 'hasta', 'canal', 'resultado', 'actor_id'] as $clave) {
            if (! empty($filtros[$clave])) {
                return false;
            }
        }

        return true;
    }
}

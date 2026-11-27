<?php

namespace App\Services\ComisionesEmpaque;

use App\Jobs\ComisionesEmpaque\ProcesarCambiosComisionesEmpaque;
use App\Models\ComisionesEmpaque\AsignacionComisionEmpaque;
use App\Models\ComisionesEmpaque\AuditoriaComisionEmpaque;
use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\ParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\SincronizacionComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadLineaComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConsultarComisionesEmpaqueService
{
    private const CAMPOS_RELACIONADOS = ['id', 'folio_pedido', 'folio_remision', 'situacion', 'clave_origen', 'cantidad_origen', 'empacado_at'];

    public function __construct(private AlcanceAreaComisionEmpaque $alcance) {}

    /** @return array<string, mixed> */
    public function resumen(?User $usuario, ?Request $request = null, ?array $estado = null, bool $incluirSaldoConfirmado = true): array
    {
        if ($this->alcance->ids($usuario) === []) {
            return [
                'listo' => false,
                'cajas_elegibles' => null,
                'importe_por_cortar' => null,
                'importe_confirmado_por_pagar' => null,
                'por_revisar' => null,
                'mensaje' => 'No tienes áreas asignadas.',
            ];
        }

        $estado ??= $this->estadoSincronizacion($usuario, $request?->integer('area_id') ?: null);
        $listo = $estado['listo'];
        $mensaje = $estado['mensaje'];

        $contexto = new Request($request?->only(['area_id', 'desde', 'hasta', 'buscar', 'cliente', 'remision', 'pedido', 'empacador', 'empacador_id', 'empacador_nombre']) ?? []);
        $base = $this->filtrar($contexto, $usuario);

        $elegibles = (clone $base)->disponibleParaCorte();
        $cajas = UnidadLineaComisionEmpaque::query()->where('es_bolsa', false);
        $validas = (clone $base)->where(fn ($q) => $q->where('situacion', UnidadComisionEmpaque::EN_CORTE)
            ->orWhere(fn ($elegible) => $elegible->disponibleParaCorte()));
        $excluidas = [UnidadComisionEmpaque::EXCLUIDA, UnidadComisionEmpaque::RECHAZADA];
        $importeValido = (int) (clone $validas)->sum('importe_centavos');
        $porPagar = $incluirSaldoConfirmado ? AsignacionComisionEmpaque::query()->whereHas('corte', function ($q) use ($usuario, $request) {
            if ($request?->filled('area_id')) {
                $q->where('area_id', (int) $request->input('area_id'));
            }
            $q->where('estado', CorteComisionEmpaque::CONFIRMADO);
            $this->alcance->aplicar($q, $usuario);
        })->with('ajustes')->get()->sum(fn (AsignacionComisionEmpaque $a) => max(0, $a->pendienteCentavos())) : null;

        return [
            'listo' => (bool) $listo,
            'total_registros' => (clone $base)->count(),
            'revisados' => (clone $base)->whereIn('situacion', [UnidadComisionEmpaque::ELEGIBLE, UnidadComisionEmpaque::EN_CORTE, UnidadComisionEmpaque::LIQUIDADA_FUERA])->count(),
            'cajas_elegibles' => (int) (clone $validas)->sum('cantidad_comisionable'),
            'cajas_registradas' => (int) (clone $cajas)->whereIn('unidad_id', (clone $base)->select('id'))->sum('cantidad'),
            'cajas_pendientes' => (int) (clone $cajas)->whereIn('unidad_id', (clone $base)->whereIn('situacion', UnidadComisionEmpaque::POR_REVISAR)->select('id'))->sum('cantidad'),
            'cajas_excluidas' => (int) (clone $cajas)->whereIn('unidad_id', (clone $base)->whereIn('situacion', $excluidas)->select('id'))->sum('cantidad'),
            'bolsas_registradas' => (int) UnidadLineaComisionEmpaque::query()->where('es_bolsa', true)->whereIn('unidad_id', (clone $base)->select('id'))->sum('cantidad'),
            'pendientes_sin_desglose' => (int) (clone $base)->whereDoesntHave('lineas')->sum('cantidad_origen'),
            'registros_sin_cantidad' => (clone $base)->where('cantidad_origen', '<=', 0)->count(),
            'constancias_pagadas_fuera' => (clone $base)->where('liquidacion', UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA)->count(),
            'importe_valido' => DineroComisionEmpaque::texto($importeValido),
            'importe_por_cortar' => DineroComisionEmpaque::texto((int) (clone $elegibles)->sum('importe_centavos')),
            'importe_constancia' => DineroComisionEmpaque::texto((int) (clone $base)->where('liquidacion', UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA)->sum('importe_centavos')),
            'importe_confirmado_por_pagar' => $porPagar === null ? null : DineroComisionEmpaque::texto((int) $porPagar),
            'por_revisar' => (clone $base)->whereIn('situacion', UnidadComisionEmpaque::POR_REVISAR)->count(),
            'mensaje' => $mensaje,
        ];
    }

    public function estadoSincronizacion(?User $usuario, ?int $seleccionada): array
    {
        $config = ConfiguracionComisionEmpaque::actual();
        $areas = $this->alcance->areas($usuario)->filter(fn ($area) => ! $seleccionada || $area->id === $seleccionada);
        $ahora = now();
        $rangoVencido = $config->fecha_fin && $ahora->gt(DineroComisionEmpaque::finDia($config->fecha_fin->toDateString(), $config->zona_horaria));
        $latido = (int) Cache::get('comisiones-empaque:worker-activo');
        $rangoPendiente = $config->version_rango !== $config->version_reconciliada;
        $porArea = $areas->map(function ($area) use ($config, $latido, $ahora, $rangoPendiente) {
            $consulta = SincronizacionComisionEmpaque::query()->whereJsonContains('area_ids', $area->id)
                ->when($rangoPendiente, fn ($q) => $q->where('version_rango', $config->version_rango));
            $ultima = (clone $consulta)->latest('id')->first();
            $exitosa = (clone $consulta)->where('estado', 'ok')->latest('id')->first();
            $fecha = fn ($momento) => $momento?->copy()->timezone($config->zona_horaria)->format('Y-m-d H:i');
            $estado = $ultima?->estado;
            if ($estado === 'en_curso' && $ultima->iniciado_at->lt($ahora->copy()->subMinutes(15))) {
                $estado = 'error';
            }

            return [
                'id' => $area->id,
                'nombre' => $area->nombre,
                'estado' => $estado,
                'ultima_at' => $fecha($ultima?->finalizado_at ?? $ultima?->iniciado_at),
                'ultima_exitosa_at' => $fecha($exitosa?->finalizado_at),
                'automatico_reciente' => $area->sistema && $latido > $ahora->timestamp - 180,
                'revision' => Cache::get('comisiones-empaque:revision:'.$area->id),
            ];
        })->values();
        $automatizadas = $areas->where('sistema', true);
        $activo = $automatizadas->isNotEmpty() && $porArea->where('automatico_reciente', true)->count() === $automatizadas->count();
        $listo = $config->fecha_inicio !== null && ! $rangoPendiente && $porArea->isNotEmpty()
            && $porArea->every(fn ($area) => $area['ultima_exitosa_at']);
        $estado = match (true) {
            $porArea->isEmpty() || $config->fecha_inicio === null => 'omitida',
            $porArea->contains('estado', 'error') => 'error',
            $porArea->contains('estado', 'en_curso') => 'en_curso',
            $rangoVencido => 'rango_vencido',
            ! $listo => 'pendiente',
            $this->hayCambiosPendientes($config, $automatizadas->modelKeys()) => 'cambios_pendientes',
            $automatizadas->isNotEmpty() && ! $activo => 'sin_actividad',
            default => 'ok',
        };
        $facultades = collect(['configurar', 'capturar', 'clasificar', 'confirmar_cortes', 'registrar_pagos', 'ajustar', 'exportar', 'ver_todas'])
            ->map(fn ($facultad) => $usuario?->can('gestion_interna.comisiones_empaque.'.$facultad))->all();

        return [
            'estado' => $estado,
            'listo' => $listo,
            'finalizado_at' => $listo ? $porArea->min('ultima_exitosa_at') : null,
            'automatizacion_reciente' => $activo,
            'contexto' => hash('sha256', json_encode([$usuario?->id, $this->alcance->ids($usuario), $facultades, $usuario?->can('control_pedidos.cedis'), $usuario?->can('control_pedidos.tienda.ver')])),
            'revision' => hash('sha256', json_encode([$porArea->pluck('revision', 'id')->all(), $config->version_rango, AuditoriaComisionEmpaque::query()->max('id')])),
            'areas' => $porArea->map(fn ($area) => array_diff_key($area, ['revision' => true]))->all(),
            'mensaje' => match ($estado) {
                'rango_vencido' => 'La fecha final impide incorporar empaques nuevos. Revise el rango en Configuración.',
                'error' => 'La actualización quedó incompleta. Puede reintentar.',
                'omitida' => $areas->isEmpty() ? 'No tienes áreas asignadas.' : 'Defina el inicio de sincronización en Configuración.',
                'sin_actividad' => 'Sin actividad automática reciente.',
                'pendiente' => 'Pendiente de primera sincronización del rango autorizado.',
                'cambios_pendientes' => 'Hay cambios de origen por procesar.',
                default => null,
            },
        ];
    }

    private function hayCambiosPendientes(ConfiguracionComisionEmpaque $config, array $areas): bool
    {
        if ($areas === []) {
            return false;
        }

        $inicio = DineroComisionEmpaque::inicioDia($config->fecha_inicio->toDateString(), $config->zona_horaria);
        $fin = $config->fecha_fin ? DineroComisionEmpaque::finDia($config->fecha_fin->toDateString(), $config->zona_horaria) : null;
        $sincronizar = app(SincronizarComisionesEmpaqueService::class);

        foreach (DB::table('comisiones_empaque_cambios_fuente')->lazyById(500)->chunk(500) as $lote) {
            $pedidos = ProcesarCambiosComisionesEmpaque::pedidos(collect($lote));
            if ($pedidos !== [] && $sincronizar->pedidosEnIntervalo($inicio, $fin, $areas, $pedidos)->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    public function unidades(Request $request, ?User $usuario)
    {
        $query = $this->filtrar($request, $usuario)->withMax('auditorias', 'id')->with(['area:id,nombre,codigo', 'lineas.tipo:id,nombre', 'corte:id,folio,estado', 'incidencias.responsable:id,name']);
        $totales = $this->totales(clone $query);

        $pagina = $query->orderByDesc('empacado_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $zona = ConfiguracionComisionEmpaque::actual()->zona_horaria;
        $idsRelacionados = $pagina->getCollection()->flatMap(fn ($unidad) => $unidad->duplicados_relacionados ?? [])->unique();
        $relacionados = $idsRelacionados->isEmpty() ? collect() : UnidadComisionEmpaque::query()
            ->whereIn('id', $idsRelacionados)
            ->tap(fn ($q) => $this->alcance->aplicar($q, $usuario))
            ->get(self::CAMPOS_RELACIONADOS)->keyBy('id');
        $tardias = $pagina->getCollection()->filter(fn ($unidad) => $unidad->incorporacion_tardia && ! $unidad->corte_id);
        $cortesOriginales = $tardias->isEmpty() ? collect() : CorteComisionEmpaque::query()
            ->whereIn('area_id', $tardias->pluck('area_id')->unique())
            ->where('estado', CorteComisionEmpaque::CONFIRMADO)
            ->where('limite_hasta', '>=', $tardias->min('empacado_at'))
            ->where('limite_desde', '<=', $tardias->max('empacado_at'))
            ->get(['id', 'area_id', 'folio', 'limite_desde', 'limite_hasta'])->groupBy('area_id');
        $pagina->through(function (UnidadComisionEmpaque $u) use ($zona, $cortesOriginales, $relacionados) {
            $corte = $u->incorporacion_tardia ? $cortesOriginales->get($u->area_id)?->first(
                fn ($c) => $u->empacado_at->betweenIncluded($c->limite_desde, $c->limite_hasta)
            ) : null;

            $coincidencias = $relacionados->only($u->duplicados_relacionados ?? []);

            return $this->fila($u, $zona, $coincidencias) + ['corte_original' => $corte?->folio, 'corte_original_id' => $corte?->id];
        });

        return [$pagina, $totales];
    }

    /** @return array{registros: int, cajas: int, importe: string, sin_importe: int} */
    public function totales(Builder $query): array
    {
        $registros = (clone $query)->count();
        $sinImporte = (clone $query)->whereNull('importe_centavos')->count();
        $fondo = (clone $query)->disponibleParaCorte();
        $cajas = (int) (clone $fondo)->sum('cantidad_comisionable');
        $importe = (int) (clone $fondo)->sum('importe_centavos');

        return [
            'registros' => $registros,
            'cajas' => $cajas,
            'importe' => DineroComisionEmpaque::texto($importe),
            'sin_importe' => $sinImporte,
        ];
    }

    public function filtrar(Request $request, ?User $usuario): Builder
    {
        $query = UnidadComisionEmpaque::query();
        $this->alcance->aplicar($query, $usuario);

        if ($request->filled('area_id')) {
            $query->where('area_id', (int) $request->input('area_id'));
        }
        if ($request->filled('desde') || $request->filled('hasta')) {
            $zona = ConfiguracionComisionEmpaque::actual()->zona_horaria;
            if ($request->filled('desde')) {
                $query->where('empacado_at', '>=', DineroComisionEmpaque::inicioDia($request->string('desde')->toString(), $zona));
            }
            if ($request->filled('hasta')) {
                $query->where('empacado_at', '<=', DineroComisionEmpaque::finDia($request->string('hasta')->toString(), $zona));
            }
        }
        if ($request->filled('buscar')) {
            $buscar = '%'.mb_substr(trim($request->string('buscar')->toString()), 0, 160).'%';
            $query->where(fn ($q) => $q->where('cliente_nombre', 'like', $buscar)
                ->orWhere('folio_remision', 'like', $buscar)->orWhere('logistica->folios_remision', 'like', $buscar)
                ->orWhere('folio_pedido', 'like', $buscar)->orWhere('empacador_nombre', 'like', $buscar));
        }
        if ($request->filled('cliente')) {
            $query->where('cliente_nombre', 'like', '%'.$request->input('cliente').'%');
        }
        if ($request->filled('remision')) {
            $termino = '%'.$request->input('remision').'%';
            $query->where(fn ($q) => $q->where('folio_remision', 'like', $termino)->orWhere('logistica->folios_remision', 'like', $termino));
        }
        if ($request->filled('pedido')) {
            $query->where('folio_pedido', 'like', '%'.$request->input('pedido').'%');
        }
        if ($request->filled('empacador')) {
            $query->where('empacador_nombre', 'like', '%'.$request->input('empacador').'%');
        }
        if ($request->filled('empacador_id')) {
            $query->where('empacador_user_id', $request->integer('empacador_id') ?: null);
        }
        if ($request->filled('empacador_nombre')) {
            $nombre = $request->input('empacador_nombre');
            $query->where(fn ($q) => $nombre === 'sin_identificar'
                ? $q->whereNull('empacador_nombre')->orWhere('empacador_nombre', '')
                : $q->where('empacador_nombre', $nombre));
        }
        if ($request->input('vista') === 'tardias') {
            $query->where('incorporacion_tardia', true)->whereNull('corte_id');
        } elseif ($request->input('vista') === 'excluidos') {
            $query->whereIn('situacion', [UnidadComisionEmpaque::EXCLUIDA, UnidadComisionEmpaque::RECHAZADA]);
        } elseif ($request->input('vista') === 'pendientes') {
            $query->whereIn('situacion', UnidadComisionEmpaque::POR_REVISAR);
        } elseif ($request->input('vista') === 'revisados') {
            $query->whereIn('situacion', [UnidadComisionEmpaque::ELEGIBLE, UnidadComisionEmpaque::EN_CORTE, UnidadComisionEmpaque::LIQUIDADA_FUERA]);
        }
        if ($request->filled('situacion')) {
            $query->where('situacion', $request->input('situacion'));
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public function fila(UnidadComisionEmpaque $u, ?string $zona = null, ?Collection $relacionados = null): array
    {
        $zona ??= config('app.timezone');
        $cajas = $u->lineas->where('es_bolsa', false)->sum('cantidad');
        $bolsas = $u->lineas->where('es_bolsa', true)->sum('cantidad');
        $tipos = $u->lineas->map(function ($l) {
            if ($l->es_bolsa) {
                return $l->cantidad.' bolsa'.($l->cantidad > 1 ? 's excluidas' : ' excluida');
            }

            return $l->cantidad.' '.($l->tipo?->nombre ?: 'caja sin tipo');
        })->values()->all();

        $conciliacion = $u->conciliacionVigente();
        $motivo = $conciliacion['motivo_comercial'] ?? $u->logistica['motivo_fuente'] ?? 'sin_informacion';
        $valida = $u->esComisionable();
        $potencial = $u->incorporacion_tardia && ! $u->corte_id && $u->importe_centavos !== null;
        $remisiones = collect($u->logistica['remisiones'] ?? [])->pluck('folio')->filter()->unique()->values()->all();
        if ($remisiones === [] && $u->folio_remision) {
            $remisiones = [$u->folio_remision];
        }
        $relacionados ??= $u->duplicados_relacionados ? UnidadComisionEmpaque::query()
            ->whereIn('id', $u->duplicados_relacionados)->get(self::CAMPOS_RELACIONADOS) : collect();

        return [
            'id' => $u->id,
            'huella' => $u->huella,
            'version_revision' => $u->versionRevision(),
            'fuente' => $u->logistica['huella_comercial'] ?? null,
            'remisiones' => $remisiones,
            'evidencias_remision' => collect($u->logistica['remisiones'] ?? [])->pluck('evidencia_id')->filter()->unique()->values()->all(),
            'conciliacion' => $conciliacion,
            'incidencias' => $u->relationLoaded('incidencias') ? $u->incidencias->sortByDesc('id')->map(fn ($i) => [
                'id' => $i->id,
                'estado' => $i->estado,
                'motivo' => $i->tipo === 'incorporacion_tardia' && $i->estado === 'pendiente'
                    ? ValorarUnidadComisionEmpaque::MOTIVO_TARDIA : $i->motivo,
                'corte_original_id' => $i->tipo === 'incorporacion_tardia' ? ($i->datos['corte_original_id'] ?? null) : null,
                'folio_corte_original' => $i->tipo === 'incorporacion_tardia' ? ($i->datos['folio_corte_original'] ?? null) : null,
                'responsable' => $i->responsable?->name,
                'fecha' => ($i->resuelta_at ?? $i->created_at)?->format('Y-m-d H:i'),
            ])->values()->all() : [],
            'conciliacion_previa' => $conciliacion ? null : $u->conciliacion,
            'motivo_comercial' => UnidadComisionEmpaque::MOTIVOS_COMERCIALES[$motivo],
            'motivo_comercial_codigo' => $motivo,
            'importe_potencial' => $potencial ? DineroComisionEmpaque::texto((int) $u->importe_centavos) : null,
            'importe_comisionable' => $valida ? DineroComisionEmpaque::texto((int) $u->importe_centavos) : null,
            'tarifa' => $valida && $u->cantidad_comisionable ? DineroComisionEmpaque::texto(intdiv((int) $u->importe_centavos, $u->cantidad_comisionable)) : null,
            'empacado_at' => $u->empacado_at?->timezone($zona)->format('Y-m-d H:i'),
            'incorporado_at' => $u->incorporado_at?->timezone($zona)->format('Y-m-d H:i'),
            'cliente' => $u->cliente_nombre ?: 'Sin cliente',
            'folio_pedido' => $u->folio_pedido,
            'folio_remision' => $u->folio_remision,
            'area' => $u->area?->nombre,
            'area_id' => $u->area_id,
            'tipos' => $tipos,
            'cajas' => $u->lineas->isEmpty() ? null : (int) $cajas,
            'bolsas' => (int) $bolsas,
            'cantidad_origen' => $u->cantidad_origen,
            'empacador' => $u->empacador_nombre ?: 'Sin registro de persona',
            'logistica' => $u->logistica ?: [],
            'importe' => $u->importe_centavos === null ? null : DineroComisionEmpaque::texto((int) $u->importe_centavos),
            'situacion' => $u->situacion,
            'ancla_decision' => $u->ancla_huella === $u->huella ? $u->ancla_decision : null,
            'liquidacion' => $u->liquidacion ?: UnidadComisionEmpaque::LIQUIDACION_PENDIENTE,
            'situacion_etiqueta' => UnidadComisionEmpaque::ETIQUETAS[$u->situacion] ?? $u->situacion,
            'corte' => $u->corte?->folio ?: ($u->corte?->estado === CorteComisionEmpaque::BORRADOR ? 'Borrador' : null),
            'incorporacion_tardia' => $u->incorporacion_tardia,
            'excepcion_resguardo' => $u->excepcion_resguardo,
            'origen_registro' => $u->origen_registro,
            'diferencia' => $u->diferencia,
            'notas' => $u->notas,
            'pedido_bma_id' => $u->pedido_bma_id,
            'posible_duplicado' => (bool) $u->posible_duplicado,
            'referencia' => $u->logistica['referencia'] ?? null,
            'relacionados' => $relacionados->map(function (UnidadComisionEmpaque $rel) use ($u, $zona) {
                    $criterio = collect($u->logistica['coincidencias'] ?? [])->firstWhere('id', $rel->id);

                    return [
                        'id' => $rel->id,
                        'folio_pedido' => $rel->folio_pedido,
                        'folio_remision' => $rel->folio_remision,
                        'situacion' => UnidadComisionEmpaque::ETIQUETAS[$rel->situacion] ?? $rel->situacion,
                        'clave_origen' => $rel->clave_origen,
                        'cantidad' => $rel->cantidad_origen,
                        'empacado_at' => $rel->empacado_at?->timezone($zona)->format('Y-m-d H:i'),
                        'criterio' => $criterio['criterio'] ?? null,
                    ];
                })->values()->all(),
            'lineas' => $u->lineas->map(fn ($l) => [
                'catalogo_tipo_caja_id' => $l->catalogo_tipo_caja_id,
                'cantidad' => $l->cantidad,
                'es_bolsa' => $l->es_bolsa,
                'tipo' => $l->tipo?->nombre,
            ])->values()->all(),
        ];
    }

    public function productividad(Request $request, ?User $usuario): array
    {
        $unidades = $this->filtrar(new Request($request->only(['area_id', 'desde', 'hasta'])), $usuario)
            ->with('area:id,nombre')
            ->where('posible_duplicado', false)
            ->whereNotIn('situacion', [
                UnidadComisionEmpaque::RECHAZADA, UnidadComisionEmpaque::POSIBLE_DUPLICADO,
                UnidadComisionEmpaque::REEMPAQUE_REVISION, UnidadComisionEmpaque::SOLAPE_REVISION,
            ])
            ->select(['id', 'area_id', 'empacador_user_id', 'empacador_nombre', 'pedido_bma_id', 'folio_pedido', 'situacion', 'logistica', 'cantidad_origen', 'huella', 'conciliacion', 'corte_id', 'incorporacion_tardia', 'liquidacion', 'cantidad_comisionable'])
            ->withSum(['lineas as cajas' => fn ($q) => $q->where('es_bolsa', false)], 'cantidad')
            ->withSum(['lineas as bolsas' => fn ($q) => $q->where('es_bolsa', true)], 'cantidad')
            ->get()
            ->reject(fn (UnidadComisionEmpaque $unidad) => ($unidad->conciliacionVigente()['motivo_comercial'] ?? null) === 'captura_incorrecta');
        $unidades->each(function ($unidad) {
            $unidad->sin_desglose = $unidad->cajas === null && $unidad->bolsas === null ? (int) $unidad->cantidad_origen : 0;
            $unidad->bultos = (int) $unidad->cajas + (int) $unidad->bolsas + $unidad->sin_desglose;
        });
        $totalesArea = $unidades->groupBy('area_id')->map->sum('bultos');

        return $unidades->groupBy([
            'area_id',
            fn ($u) => $u->empacador_user_id ? 'cuenta:'.$u->empacador_user_id : 'nombre:'.($u->empacador_nombre ?: ''),
        ])->flatMap(fn ($personas) => $personas->map(function ($grupo) use ($totalesArea) {
            $persona = $grupo->first();
            $cajas = (int) $grupo->sum('cajas');
            $volumenArea = (int) $totalesArea[$persona->area_id];

            $pedidos = $grupo->flatMap(function ($u) {
                $pedidos = collect($u->logistica['remisiones'] ?? [])->pluck('pedido_id')->filter();
                if ($u->pedido_bma_id) {
                    $pedidos->push($u->pedido_bma_id);
                }

                return $pedidos->isEmpty() ? [$u->folio_pedido] : $pedidos->map(fn ($id) => 'id:'.$id)->all();
            })->filter()->unique()->count();

            return [
                'area_id' => $persona->area_id,
                'area' => $persona->area?->nombre,
                'empacador_id' => $persona->empacador_user_id ?? 0,
                'nombre' => $persona->empacador_nombre ?: 'Sin empacador identificado',
                'filtro_nombre' => $persona->empacador_user_id ? null : ($persona->empacador_nombre ?: 'sin_identificar'),
                'cajas' => $cajas,
                'bolsas' => (int) $grupo->sum('bolsas'),
                'sin_desglose' => (int) $grupo->sum('sin_desglose'),
                'pagadas_fuera' => (int) $grupo->where('situacion', UnidadComisionEmpaque::LIQUIDADA_FUERA)->sum('cajas'),
                'validas' => (int) $grupo->filter->esComisionable()->sum('cantidad_comisionable'),
                'pendientes' => (int) $grupo->whereIn('situacion', UnidadComisionEmpaque::POR_REVISAR)->sum('cajas'),
                'excluidas' => (int) $grupo->whereIn('situacion', [UnidadComisionEmpaque::EXCLUIDA, UnidadComisionEmpaque::RECHAZADA])->sum('cajas'),
                'pedidos' => $pedidos,
                'porcentaje' => $volumenArea ? round(100 * $grupo->sum('bultos') / $volumenArea, 2) : 0,
            ];
        })->values())->sortBy([['area', 'asc'], ['nombre', 'asc']])->values()->all();
    }

    public function saldos(?User $usuario, ?int $areaId = null, bool $soloDetallePendiente = false): array
    {
        $porParticipante = AsignacionComisionEmpaque::query()->with(['corte.area', 'ajustes'])
            ->whereHas('corte', function ($q) use ($usuario, $areaId) {
                $q->when($areaId, fn ($cortes) => $cortes->where('area_id', $areaId))
                    ->where('estado', CorteComisionEmpaque::CONFIRMADO);
                $this->alcance->aplicar($q, $usuario);
            })->get()->groupBy('participante_id');
        $acotarParticipantes = $areaId || $soloDetallePendiente || $this->alcance->ids($usuario) !== null;
        $participantes = ParticipanteComisionEmpaque::query()->orderBy('nombre')
            ->when($acotarParticipantes, fn ($q) => $q->whereIntegerInRaw('id', $porParticipante->keys()->all()))->get();

        $filas = [];
        foreach ($participantes as $participante) {
            $asignaciones = $porParticipante->get($participante->id, collect());
            $reconocido = 0;
            $pagado = 0;
            $pendiente = 0;
            $detalle = [];
            foreach ($asignaciones as $asignacion) {
                $reconocido += $asignacion->reconocidoCentavos();
                $pagado += (int) $asignacion->pagado_centavos;
                $pend = $asignacion->pendienteCentavos();
                $pendiente += max(0, $pend);
                if ($soloDetallePendiente && $pend <= 0) {
                    continue;
                }
                $detalle[] = [
                    'id' => $asignacion->id,
                    'folio' => $asignacion->corte?->folio,
                    'area' => $asignacion->corte?->area?->nombre,
                    'porcentaje' => DineroComisionEmpaque::porcentaje((int) $asignacion->puntos_base),
                    'reconocido' => DineroComisionEmpaque::texto($asignacion->reconocidoCentavos()),
                    'pagado' => DineroComisionEmpaque::texto((int) $asignacion->pagado_centavos),
                    'pendiente' => DineroComisionEmpaque::texto($pend),
                    'pendiente_centavos' => $pend,
                ];
            }

            $filas[] = [
                'id' => $participante->id,
                'nombre' => $participante->nombre,
                'activo' => $participante->activo,
                'reconocido' => DineroComisionEmpaque::texto($reconocido),
                'pagado' => DineroComisionEmpaque::texto($pagado),
                'pendiente' => DineroComisionEmpaque::texto($pendiente),
                'asignaciones' => $detalle,
            ];
        }

        return $filas;
    }
}

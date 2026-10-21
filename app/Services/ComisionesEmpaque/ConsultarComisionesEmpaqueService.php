<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AsignacionComisionEmpaque;
use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\ParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\SincronizacionComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ConsultarComisionesEmpaqueService
{
    public function __construct(private AlcanceAreaComisionEmpaque $alcance) {}

    /** @return array<string, mixed> */
    public function resumen(?User $usuario, ?Request $request = null): array
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

        $sync = SincronizacionComisionEmpaque::query()->whereIn('estado', ['ok', 'omitida', 'error'])->latest('id')->first();
        $config = ConfiguracionComisionEmpaque::actual();
        $listo = $config->fecha_inicio !== null && $sync && $sync->estado !== 'omitida';
        $mensaje = $listo ? null : ($sync?->mensaje ?: 'Aún no hay una sincronización. Defina la fecha de inicio y pulse Actualizar.');

        $contexto = new Request($request?->only(['area_id', 'desde', 'hasta', 'cliente', 'remision', 'pedido', 'empacador']) ?? []);
        $base = $this->filtrar($contexto, $usuario);

        $elegibles = (clone $base)->where('situacion', UnidadComisionEmpaque::ELEGIBLE)->whereNull('corte_id');
        $porPagar = AsignacionComisionEmpaque::query()->whereHas('corte', function ($q) use ($usuario, $request) {
            if ($request?->filled('area_id')) {
                $q->where('area_id', (int) $request->input('area_id'));
            }
            $q->where('estado', CorteComisionEmpaque::CONFIRMADO);
            $this->alcance->aplicar($q, $usuario);
        })->get()->sum(fn (AsignacionComisionEmpaque $a) => max(0, $a->pendienteCentavos()));

        return [
            'listo' => (bool) $listo,
            'total_registros' => (clone $base)->count(),
            'revisados' => (clone $base)->whereIn('situacion', [UnidadComisionEmpaque::ELEGIBLE, UnidadComisionEmpaque::EN_CORTE, UnidadComisionEmpaque::LIQUIDADA_FUERA])->count(),
            'cajas_elegibles' => (int) $elegibles->sum('cantidad_comisionable'),
            'importe_por_cortar' => DineroComisionEmpaque::texto((int) (clone $elegibles)->sum('importe_centavos')),
            'importe_constancia' => DineroComisionEmpaque::texto((int) (clone $base)->where('liquidacion', UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA)->sum('importe_centavos')),
            'importe_confirmado_por_pagar' => DineroComisionEmpaque::texto((int) $porPagar),
            'por_revisar' => (clone $base)->whereIn('situacion', UnidadComisionEmpaque::POR_REVISAR)->count(),
            'mensaje' => $mensaje,
        ];
    }

    public function unidades(Request $request, ?User $usuario)
    {
        $query = $this->filtrar($request, $usuario)->with(['area:id,nombre,codigo', 'lineas.tipo:id,nombre', 'corte:id,folio,estado']);
        $totales = $this->totales(clone $query);

        $pagina = $query->orderByDesc('empacado_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $pagina->through(fn (UnidadComisionEmpaque $u) => $this->fila($u));

        return [$pagina, $totales];
    }

    /** @return array{registros: int, cajas: int, importe: string, sin_importe: int} */
    public function totales(Builder $query): array
    {
        $registros = (clone $query)->count();
        $sinImporte = (clone $query)->whereNull('importe_centavos')->count();
        $cajas = (int) (clone $query)->whereNotNull('cantidad_comisionable')->sum('cantidad_comisionable');
        $importe = (int) (clone $query)->whereNotNull('importe_centavos')->sum('importe_centavos');

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
        if ($request->filled('desde')) {
            $query->where('empacado_at', '>=', $request->input('desde').' 00:00:00');
        }
        if ($request->filled('hasta')) {
            $query->where('empacado_at', '<=', $request->input('hasta').' 23:59:59');
        }
        if ($request->filled('cliente')) {
            $query->where('cliente_nombre', 'like', '%'.$request->input('cliente').'%');
        }
        if ($request->filled('remision')) {
            $query->where('folio_remision', 'like', '%'.$request->input('remision').'%');
        }
        if ($request->filled('pedido')) {
            $query->where('folio_pedido', 'like', '%'.$request->input('pedido').'%');
        }
        if ($request->filled('empacador')) {
            $query->where('empacador_nombre', 'like', '%'.$request->input('empacador').'%');
        }
        if ($request->input('vista') === 'pendientes') {
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
    public function fila(UnidadComisionEmpaque $u): array
    {
        $cajas = $u->lineas->where('es_bolsa', false)->sum('cantidad');
        $bolsas = $u->lineas->where('es_bolsa', true)->sum('cantidad');
        $tipos = $u->lineas->map(function ($l) {
            if ($l->es_bolsa) {
                return $l->cantidad.' bolsa'.($l->cantidad > 1 ? 's excluidas' : ' excluida');
            }

            return $l->cantidad.' '.($l->tipo?->nombre ?: 'caja sin tipo');
        })->values()->all();

        return [
            'id' => $u->id,
            'empacado_at' => $u->empacado_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'incorporado_at' => $u->incorporado_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
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
            'relacionados' => UnidadComisionEmpaque::query()
                ->whereIn('id', $u->duplicados_relacionados ?: [])
                ->get(['id', 'folio_pedido', 'folio_remision', 'situacion', 'clave_origen', 'cantidad_origen', 'empacado_at'])
                ->map(function (UnidadComisionEmpaque $rel) use ($u) {
                    $criterio = collect($u->logistica['coincidencias'] ?? [])->firstWhere('id', $rel->id);

                    return [
                        'id' => $rel->id,
                        'folio_pedido' => $rel->folio_pedido,
                        'folio_remision' => $rel->folio_remision,
                        'situacion' => UnidadComisionEmpaque::ETIQUETAS[$rel->situacion] ?? $rel->situacion,
                        'clave_origen' => $rel->clave_origen,
                        'cantidad' => $rel->cantidad_origen,
                        'empacado_at' => $rel->empacado_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
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

    public function saldos(?User $usuario, ?int $areaId = null): array
    {
        $participantes = ParticipanteComisionEmpaque::query()->orderBy('nombre')->get();
        $filas = [];
        foreach ($participantes as $participante) {
            $asignaciones = AsignacionComisionEmpaque::query()
                ->with(['corte.area', 'ajustes'])
                ->where('participante_id', $participante->id)
                ->whereHas('corte', function ($q) use ($usuario, $areaId) {
                    if ($areaId) {
                        $q->where('area_id', $areaId);
                    }
                    $q->where('estado', CorteComisionEmpaque::CONFIRMADO);
                    $this->alcance->aplicar($q, $usuario);
                })
                ->get();

            if ($areaId && $asignaciones->isEmpty()) {
                continue;
            }
            $reconocido = 0;
            $pagado = 0;
            $pendiente = 0;
            $detalle = [];
            foreach ($asignaciones as $asignacion) {
                $reconocido += $asignacion->reconocidoCentavos();
                $pagado += (int) $asignacion->pagado_centavos;
                $pend = $asignacion->pendienteCentavos();
                $pendiente += max(0, $pend);
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

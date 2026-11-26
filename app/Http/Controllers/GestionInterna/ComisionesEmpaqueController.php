<?php

namespace App\Http\Controllers\GestionInterna;

use App\Http\Controllers\Controller;
use App\Jobs\ComisionesEmpaque\ProcesarCambiosComisionesEmpaque;
use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\AsignacionComisionEmpaque;
use App\Models\ComisionesEmpaque\AuditoriaComisionEmpaque;
use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\PagoComisionEmpaque;
use App\Models\ComisionesEmpaque\ParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\PeriodoComisionEmpaque;
use App\Models\ComisionesEmpaque\RepartoComisionEmpaque;
use App\Models\ComisionesEmpaque\TarifaComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\UsuarioAreaComisionEmpaque;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ComisionesEmpaque\AlcanceAreaComisionEmpaque;
use App\Services\ComisionesEmpaque\CargarEmpaquesComisionEmpaqueService;
use App\Services\ComisionesEmpaque\ConfigurarComisionEmpaqueService;
use App\Services\ComisionesEmpaque\ConfirmarCorteComisionEmpaqueService;
use App\Services\ComisionesEmpaque\ConsultarComisionesEmpaqueService;
use App\Services\ComisionesEmpaque\ConsultarReporteComisionesEmpaque;
use App\Services\ComisionesEmpaque\ExportarComisionesEmpaqueService;
use App\Services\ComisionesEmpaque\GestionarPeriodoComisionEmpaqueService;
use App\Services\ComisionesEmpaque\GestionarUnidadComisionEmpaqueService;
use App\Services\ComisionesEmpaque\GuardarEquipoComisionEmpaqueService;
use App\Services\ComisionesEmpaque\RegistrarPagoComisionEmpaqueService;
use App\Services\ComisionesEmpaque\SincronizarComisionesEmpaqueService;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComisionesEmpaqueController extends Controller
{
    public function __construct(
        private AlcanceAreaComisionEmpaque $alcance,
        private ConsultarComisionesEmpaqueService $consultar,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('gestion_interna.comisiones_empaque.ver');
        $usuario = $request->user();
        $preferencia = 'comisiones_empaque.'.$usuario->id;
        $cookieModo = 'gelia_comisiones_modo_'.$usuario->id;
        $modo = $request->input('modo');
        if (in_array($modo, ['simple', 'avanzado'], true)) {
            $request->session()->put($preferencia.'.modo', $modo);
            Cookie::queue($cookieModo, $modo, 60 * 24 * 180);
        }
        $modoPreferido = $request->session()->get($preferencia.'.modo', $request->cookie($cookieModo));
        $tab = $request->string('tab')->toString() ?: ($modoPreferido === 'avanzado' ? 'acumulado' : 'reporte');
        $config = ConfiguracionComisionEmpaque::actual();
        $areas = $this->alcance->areas($usuario);
        $contextoSimple = $request->session()->get($preferencia.'.consulta', []);
        $restaurarConsulta = $tab === 'reporte' || ! $request->filled('tab');
        if ($restaurarConsulta && ! $request->hasAny(['area_id', 'periodo_id', 'desde', 'hasta'])) {
            $areaGuardada = $contextoSimple['area_id'] ?? null;
            if ($areaGuardada === 'todas' || $areas->contains('id', $areaGuardada)) {
                $periodoGuardado = $contextoSimple['periodo_id'] ?? null;
                if ($periodoGuardado && ! PeriodoComisionEmpaque::query()->where('area_id', $areaGuardada)->whereKey($periodoGuardado)->exists()) {
                    unset($contextoSimple['periodo_id']);
                }
                $request->merge($contextoSimple);
            } elseif ($areas->count() === 1) {
                $request->merge(['area_id' => $areas->first()->id]);
            }
        }
        if ($tab === 'liquidaciones') {
            $tab = 'periodos';
        }
        if ($request->input('area_id') === 'todas') {
            $request->merge(['area_id' => null]);
        }
        if ($request->filled('area_id')) {
            $this->assertArea($usuario, (int) $request->input('area_id'));
        }
        if ($tab === 'reporte' && $request->filled('area_id') && ! $request->hasAny(['periodo_id', 'desde', 'hasta'])) {
            $abiertos = PeriodoComisionEmpaque::query()->where('area_id', $request->integer('area_id'))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('comisiones_empaque_cortes')
                    ->whereColumn('comisiones_empaque_cortes.area_id', 'comisiones_empaque_periodos.area_id')
                    ->where('comisiones_empaque_cortes.estado', CorteComisionEmpaque::CONFIRMADO)
                    ->whereColumn('comisiones_empaque_cortes.limite_desde', '<', 'comisiones_empaque_periodos.hasta')
                    ->whereColumn('comisiones_empaque_cortes.limite_hasta', '>=', 'comisiones_empaque_periodos.desde'))
                ->limit(2)->pluck('id');
            if ($abiertos->count() === 1) {
                $request->merge(['periodo_id' => $abiertos->first()]);
            }
        }
        $periodoSeleccionado = null;
        if ($request->filled('periodo_id')) {
            $periodoSeleccionado = PeriodoComisionEmpaque::query()->findOrFail($request->input('periodo_id'));
            abort_unless($this->alcance->permite($usuario, (int) $periodoSeleccionado->area_id), 403);
            if ($request->filled('area_id')) {
                abort_unless((int) $periodoSeleccionado->area_id === (int) $request->input('area_id'), 403);
            } else {
                $request->merge(['area_id' => $periodoSeleccionado->area_id]);
            }
        }
        if (in_array($tab, ['preparar', 'periodos', 'equipo'], true) && ! $request->has('area_id') && $areas->isNotEmpty()) {
            $request->merge(['area_id' => $areas->first()->id]);
        }
        abort_if(in_array($tab, ['configuracion', 'ajustes'], true) && ! $usuario->can('gestion_interna.comisiones_empaque.configurar'), 403);
        if ($periodoSeleccionado) {
            $request->merge([
                'desde' => $periodoSeleccionado->desde->copy()->timezone($config->zona_horaria)->toDateString(),
                'hasta' => $periodoSeleccionado->hasta->copy()->subSecond()->timezone($config->zona_horaria)->toDateString(),
            ]);
        }
        $areaId = $request->integer('area_id') ?: null;
        if ($tab === 'reporte') {
            $hoy = now($config->zona_horaria);
            if ($config->fecha_fin && $config->fecha_fin->toDateString() < $hoy->toDateString()) {
                $hoy = Carbon::parse($config->fecha_fin->toDateString(), $config->zona_horaria);
            }
            $request->merge([
                'desde' => $request->input('desde') ?: $hoy->copy()->startOfMonth()->toDateString(),
                'hasta' => $request->input('hasta') ?: $hoy->toDateString(),
            ]);
            $this->validarFechasReporte($request);
            $contextoSimple = ['area_id' => $areaId ?? 'todas'] + $request->only(['periodo_id', 'desde', 'hasta']);
            if (! $request->boolean('consulta_pasiva')) {
                $request->session()->put($preferencia.'.consulta', $contextoSimple);
            }
        }
        $origenSimple = $request->input('origen') === 'simple';
        $sincronizacion = null;
        $sync = function () use (&$sincronizacion, $usuario, $areaId) {
            return $sincronizacion ??= $this->consultar->estadoSincronizacion($usuario, $areaId);
        };
        $configuracion = in_array($tab, ['configuracion', 'ajustes', 'equipo'], true)
            && $usuario->can('gestion_interna.comisiones_empaque.configurar');
        $cargaRevision = $request->session()->get('carga_revision');
        $seccion = $tab === 'equipo' ? 'equipo' : ($cargaRevision ? 'sincronizacion' : $request->input('seccion', 'tarifas'));
        $empaques = $tab === 'acumulado';
        $equipo = $areaId && ($tab === 'periodos' || ($configuracion && $seccion === 'equipo'));
        $datosEquipo = null;
        $cargarEquipo = function () use (&$datosEquipo, $areaId, $areas, $equipo) {
            return $equipo ? ($datosEquipo ??= $this->equipoDeArea($areas->firstWhere('id', $areaId))) : null;
        };
        $periodos = fn () => PeriodoComisionEmpaque::query()->where('area_id', $areaId)->orderByDesc('desde');
        $datosEmpaques = null;
        $unidades = function () use ($request, $usuario, &$datosEmpaques) {
            return $datosEmpaques ??= $this->consultar->unidades($request, $usuario);
        };

        return Inertia::render('GestionInterna/ComisionesEmpaque/Index', [
            'tab' => $tab,
            'origen_simple' => $origenSimple,
            'contexto_simple' => $contextoSimple,
            'reporte' => fn () => $tab === 'reporte'
                ? array_diff_key(app(ConsultarReporteComisionesEmpaque::class)->consultar($request, $usuario, $config->zona_horaria), ['detalle' => true])
                : null,
            'puede' => fn () => [
                'configurar' => $usuario->can('gestion_interna.comisiones_empaque.configurar'),
                'capturar' => $usuario->can('gestion_interna.comisiones_empaque.capturar'),
                'clasificar' => $usuario->can('gestion_interna.comisiones_empaque.clasificar'),
                'confirmar' => $usuario->can('gestion_interna.comisiones_empaque.confirmar_cortes'),
                'pagar' => $usuario->can('gestion_interna.comisiones_empaque.registrar_pagos'),
                'ajustar' => $usuario->can('gestion_interna.comisiones_empaque.ajustar'),
                'exportar' => $usuario->can('gestion_interna.comisiones_empaque.exportar'),
                'ver_cedis' => $usuario->can('control_pedidos.cedis'),
                'ver_tienda' => $usuario->can('control_pedidos.tienda.ver'),
            ],
            'areas' => $areas->map->only(['id', 'codigo', 'nombre', 'activo', 'sistema']),
            'fecha_inicio' => $config->fecha_inicio?->toDateString(),
            'fecha_fin' => $config->fecha_fin?->toDateString(),
            'sincronizacion' => $sync,
            'filtros' => $request->only(['area_id', 'desde', 'hasta', 'buscar', 'cliente', 'remision', 'pedido', 'empacador', 'situacion', 'limite_desde', 'limite_hasta', 'incluir_tardias', 'periodo_id', 'vista', 'empacador_id', 'empacador_nombre', 'seccion', 'retorno_corte', 'retorno_periodo_id', 'origen', 'editar_equipo']),
            'sin_areas' => $areas->isEmpty(),
            'periodos_opciones' => fn () => $areaId ? $periodos()->get(['id', 'area_id', 'nombre', 'desde', 'hasta'])
                ->map(fn ($p) => $this->opcionPeriodo($p, $config->zona_horaria)) : [],
            'carga_revision' => $empaques || $configuracion ? $cargaRevision : null,
            'captura_nombre' => $empaques ? $usuario->name : '',
            'resumen' => fn () => $empaques || ($configuracion && $equipo)
                ? $this->consultar->resumen($usuario, $request, $sync(), incluirSaldoConfirmado: false) : null,
            'situaciones' => $empaques ? UnidadComisionEmpaque::ETIQUETAS : [],
            'motivos_comerciales' => $empaques ? UnidadComisionEmpaque::MOTIVOS_COMERCIALES : [],
            'tipos_caja' => fn () => $empaques ? CatalogoTipoCajaPedido::query()->orderBy('nombre')->get(['id', 'nombre', 'activo']) : [],
            'paqueterias' => fn () => $empaques ? CatalogoPaqueteriaPedido::query()->orderBy('nombre')->get(['id', 'nombre', 'sin_logistica']) : [],
            'acumulado' => fn () => $empaques ? $unidades()[0] : null,
            'totales' => fn () => $empaques ? $unidades()[1] : null,
            'preview' => fn () => $tab === 'preparar' && $areaId && $request->filled(['limite_desde', 'limite_hasta'])
                ? app(ConfirmarCorteComisionEmpaqueService::class)->calcular(
                    $areaId,
                    DineroComisionEmpaque::inicioDia($request->input('limite_desde'), $config->zona_horaria),
                    DineroComisionEmpaque::finDia($request->input('limite_hasta'), $config->zona_horaria),
                    $request->boolean('incluir_tardias'),
                ) : null,
            'cortes' => fn () => $tab === 'cortes' ? CorteComisionEmpaque::query()
                ->with(['area:id,nombre', 'confirmadoPor:id,name', 'asignaciones.ajustes'])
                ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
                ->tap(fn ($q) => $this->alcance->aplicar($q, $usuario))
                ->orderByDesc('id')->paginate(20)->withQueryString()
                ->through(fn (CorteComisionEmpaque $c) => $this->filaCorte($c)) : null,
            'corte' => function () use ($request, $usuario, $tab) {
                if ($tab !== 'cortes' || ! $request->filled('corte')) {
                    return null;
                }
                $corte = CorteComisionEmpaque::query()->with([
                    'area', 'confirmadoPor:id,name', 'unidades', 'asignaciones.participante', 'asignaciones.ajustes', 'ajustes',
                ])->find($request->integer('corte'));

                return $corte && $this->alcance->permite($usuario, (int) $corte->area_id) ? $this->detalleCorte($corte) : null;
            },
            'productividad' => fn () => $tab === 'productividad' ? $this->consultar->productividad($request, $usuario) : [],
            'saldos' => fn () => $tab === 'pagos' ? $this->consultar->saldos($usuario, $areaId, soloDetallePendiente: true) : [],
            'tarifas' => fn () => $configuracion && $seccion === 'tarifas' ? TarifaComisionEmpaque::query()
                ->with('area:id,nombre')->tap(fn ($q) => $this->alcance->aplicar($q, $usuario))
                ->when($areaId, fn ($q) => $q->where('area_id', $areaId))->orderByDesc('vigente_desde')->get()
                ->map(fn (TarifaComisionEmpaque $t) => [
                    'id' => $t->id,
                    'area' => $t->area?->nombre,
                    'area_id' => $t->area_id,
                    'importe' => DineroComisionEmpaque::texto((int) $t->importe_centavos),
                    'vigente_desde' => $t->vigente_desde?->format('Y-m-d H:i'),
                    'vigente_hasta' => $t->vigente_hasta?->format('Y-m-d H:i'),
                    'estado' => $t->vigente_desde?->isFuture() ? 'Programada' : ($t->vigente_hasta?->isPast() ? 'Histórica' : 'Vigente'),
                ]) : [],
            'repartos' => fn () => $configuracion && $equipo ? RepartoComisionEmpaque::query()
                ->with(['area:id,nombre', 'lineas.participante:id,nombre'])
                ->where('area_id', $areaId)->orderByDesc('vigente_desde')->get() : [],
            'alcances' => fn () => $configuracion && $seccion === 'accesos' ? UsuarioAreaComisionEmpaque::query()
                ->tap(fn ($q) => $this->alcance->aplicar($q, $usuario))
                ->with(['usuario:id,name', 'area:id,nombre'])->orderBy('user_id')->get()
                ->map(fn (UsuarioAreaComisionEmpaque $fila) => [
                    'user_id' => $fila->user_id, 'nombre' => $fila->usuario?->name,
                    'area_id' => $fila->area_id, 'area' => $fila->area?->nombre,
                ]) : [],
            'equipo' => $cargarEquipo,
            'periodos' => fn () => $tab === 'periodos' && $areaId
                ? $this->paginaPeriodos($periodos()->paginate(20)->withQueryString(), $areaId, $config->zona_horaria, $cargarEquipo())
                : null,
        ]);
    }

    private function opcionPeriodo(PeriodoComisionEmpaque $periodo, string $zona): array
    {
        return [
            'id' => $periodo->id, 'nombre' => $periodo->nombre, 'area_id' => $periodo->area_id,
            'desde' => $periodo->desde->copy()->timezone($zona)->toDateString(),
            'hasta' => $periodo->hasta->copy()->subSecond()->timezone($zona)->toDateString(),
        ];
    }

    private function equipoDeArea(AreaComisionEmpaque $area): array
    {
        $equipo = RepartoComisionEmpaque::permanente($area->id)
            ?? RepartoComisionEmpaque::query()->with('lineas.participante')->where('area_id', $area->id)->latest('vigente_desde')->first();

        return [
            'id' => $equipo?->id, 'version' => $equipo?->permanente ? $equipo->id : 0, 'area_id' => $area->id, 'area' => $area->nombre,
            'lineas' => $equipo?->lineas->map(fn ($l) => [
                'participante_id' => $l->participante_id, 'user_id' => $l->participante?->user_id,
                'nombre' => $l->participante?->nombre, 'activo' => $l->participante?->activo,
                'porcentaje' => DineroComisionEmpaque::porcentaje((int) $l->puntos_base),
                'encargado' => $l->encargado, 'color' => $l->color,
            ])->values()->all() ?? [],
        ];
    }

    private function paginaPeriodos(\Illuminate\Pagination\LengthAwarePaginator $pagina, int $areaId, string $zona, ?array $equipo)
    {
        if ($pagina->isEmpty()) {
            return $pagina;
        }
        $cortes = CorteComisionEmpaque::query()->where('area_id', $areaId)
            ->where('estado', CorteComisionEmpaque::CONFIRMADO)
            ->where(function ($q) use ($pagina) {
                foreach ($pagina as $periodo) {
                    $q->orWhere(fn ($rango) => $rango->where('limite_desde', '<', $periodo->hasta)
                        ->where('limite_hasta', '>=', $periodo->desde));
                }
            })->with('asignaciones.participante')->orderBy('id')->get();

        return $pagina->through(function ($periodo) use ($cortes, $zona, $equipo) {
            $corte = $cortes->first(fn ($c) => $c->limite_desde->lt($periodo->hasta) && $c->limite_hasta->gte($periodo->desde));

            return $this->opcionPeriodo($periodo, $zona) + [
                'motivo' => $periodo->motivo,
                'cerrado' => $corte !== null, 'corte_id' => $corte?->id, 'folio_corte' => $corte?->folio,
                'lineas' => $corte ? $corte->asignaciones->map(fn ($a) => [
                    'participante_id' => $a->participante_id, 'nombre' => $a->participante?->nombre,
                    'porcentaje' => DineroComisionEmpaque::porcentaje((int) $a->puntos_base),
                    'importe' => DineroComisionEmpaque::texto((int) $a->importe_centavos),
                ])->values()->all() : ($equipo['lineas'] ?? []),
            ];
        });
    }

    public function estadoSincronizacion(Request $request): JsonResponse
    {
        $area = $request->integer('area_id') ?: null;
        if ($area) {
            $this->assertArea($request->user(), $area);
        }

        return response()->json($this->consultar->estadoSincronizacion($request->user(), $area))
            ->header('Cache-Control', 'no-store');
    }

    public function sincronizar(Request $request, SincronizarComisionesEmpaqueService $service): JsonResponse|RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.ver');
        $areas = $this->alcance->ids($request->user());
        abort_if($areas === [], 403);
        $area = $request->integer('area_id') ?: null;
        if ($area) {
            $this->assertArea($request->user(), $area);
        }
        $sync = $service->ejecutar($request->user(), $area ? [$area] : $areas);

        if ($request->expectsJson()) {
            return response()->json(['estado' => $sync->estado, 'mensaje' => $sync->mensaje])->header('Cache-Control', 'no-store');
        }

        return back()->with($sync->estado === 'ok' ? 'success' : 'warning', $sync->mensaje);
    }

    public function manual(Request $request, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.capturar');
        $datos = $request->validate([
            'clave_idempotencia' => ['required', 'string', 'max:80'],
            'area_id' => ['required', 'integer', 'exists:comisiones_empaque_areas,id'],
            'empacado_at' => ['required', 'date'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'folio_remision' => ['required', 'string', 'max:255'],
            'paqueteria' => ['required', 'string', 'max:255'],
            'logistica' => ['required', 'in:con,sin'],
            'empacador_nombre' => ['required', 'string', 'max:255'],
            'empacador_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'liquidacion' => ['required', 'in:pendiente,pagado_fuera'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->assertArea($request->user(), (int) $datos['area_id']);
        $unidad = $service->registrarFila($datos, $request->user());
        $mensaje = $unidad->liquidacion === UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA
            ? 'Registro guardado como constancia pagada fuera. No entra al fondo.'
            : 'Registro guardado. Queda pendiente de revisión y todavía no entra al fondo.';

        return back()->with('success', $mensaje);
    }

    public function revisarCarga(Request $request, CargarEmpaquesComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.capturar');
        $datos = $request->validate(['texto' => ['required', 'string', 'max:500000']]);
        $revision = $service->interpretar($datos['texto'], $request->user());
        $revision['texto'] = $datos['texto'];

        return back()->with('carga_revision', $revision);
    }

    public function guardarCarga(Request $request, CargarEmpaquesComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.capturar');
        $datos = $request->validate(['texto' => ['required', 'string', 'max:500000']]);
        $revision = $service->interpretar($datos['texto'], $request->user());
        $revision['texto'] = $datos['texto'];
        if ($revision['errores'] !== []) {
            return back()->with('carga_revision', $revision)->withErrors([
                'texto' => 'La carga tiene filas inválidas. No se guardó ninguna.',
            ]);
        }
        $resultado = $service->guardar($datos['texto'], $request->user());

        return back()->with('success', 'Carga guardada. '.$resultado['nuevas'].' empaques nuevos y '.$resultado['repetidas'].' que ya estaban en el acumulado.');
    }

    public function revisar(Request $request, UnidadComisionEmpaque $unidad, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.clasificar');
        $this->assertArea($request->user(), (int) $unidad->area_id);
        $datos = $request->validate([
            'version_revision' => ['required', 'string', 'size:64'],
            'decision' => ['required', 'in:aceptar,rechazar,trabajo_distinto,aceptar_sin_referencia'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);
        $service->revisarManual($unidad, $datos['decision'], $datos['motivo'], $request->user(), $datos['version_revision'] ?? null);

        $mensaje = match ($datos['decision']) {
            'aceptar' => 'Registro aceptado. La tarifa del área se aplica a las cajas registradas.',
            'aceptar_sin_referencia' => 'Se aceptó la falta de referencia. La persona revisora queda registrada.',
            'trabajo_distinto' => 'Se reconoció como trabajo distinto. La decisión queda auditada.',
            default => 'Registro rechazado. No entra al acumulado.',
        };

        return back()->with('success', $mensaje);
    }

    public function pedido(Request $request)
    {
        Gate::authorize('gestion_interna.comisiones_empaque.capturar');
        $tienda = AreaComisionEmpaque::query()->where('codigo', 'tienda')->first();
        $this->assertArea($request->user(), (int) ($tienda->id ?? 0));
        $pedido = PedidoBma::query()->with('cliente:id,nombre');
        if ($request->filled('id')) {
            $pedido->whereKey((int) $request->input('id'));
        } elseif ($request->filled('folio')) {
            $pedido->where('folio', $request->input('folio'));
        } else {
            return response()->json(['message' => 'Indique el pedido o el folio.'], 422);
        }
        $encontrado = $pedido->first();
        if (! $encontrado) {
            return response()->json(['message' => 'No se encontró el pedido.'], 422);
        }

        return response()->json([
            'id' => $encontrado->id,
            'folio_pedido' => $encontrado->folio,
            'folio_remision' => $encontrado->folio_remision ?: $encontrado->numero_remision,
            'cliente_nombre' => $encontrado->cliente?->nombre,
        ]);
    }

    public function pedidoInformativo(Request $request, UnidadComisionEmpaque $unidad): JsonResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.ver');
        $this->assertArea($request->user(), (int) $unidad->area_id);
        if (! $unidad->pedido_bma_id) {
            return response()->json([
                'vinculado' => false,
                'mensaje' => 'Este empaque no está vinculado a un pedido de Gelia.',
            ]);
        }

        $pedido = PedidoBma::query()->with([
            'cliente:id,nombre',
            'estatus:id,nombre_visual',
            'paqueteria:id,nombre',
            'cajas' => fn ($q) => $q->activas()->with('tipoCaja:id,nombre'),
            'bultosEmpaque:id,pedido_bma_id',
            'documentos' => fn ($q) => $q->whereIn('tipo', ['remision', 'guia', 'evidencia_pesaje', 'evidencia_bulto_empaque', 'evidencia_ticket_bulto_empaque'])
                ->select(['id', 'pedido_bma_id', 'tipo', 'nombre_original']),
        ])->find($unidad->pedido_bma_id);

        if (! $pedido) {
            return response()->json([
                'vinculado' => false,
                'mensaje' => 'El pedido vinculado ya no está disponible.',
            ]);
        }

        return response()->json([
            'vinculado' => true,
            'folio_pedido' => $pedido->folio,
            'folio_remision' => $pedido->folio_remision ?: $pedido->numero_remision,
            'cliente' => $pedido->cliente?->nombre ?: $unidad->cliente_nombre,
            'estatus' => $pedido->estatus?->nombre_visual,
            'paqueteria' => $pedido->paqueteria?->nombre,
            'cajas_declaradas' => (int) $pedido->numero_cajas,
            'empacado_at' => $pedido->empacado_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'documentos' => $pedido->documentos->filter(fn ($documento) => \App\Support\ControlPedidos\VisibilidadPedidoBma::puedeConsultarDocumentos($request->user(), $pedido, $documento->tipo))->map(fn ($documento) => [
                'id' => $documento->id,
                'nombre' => $documento->nombre_original ?: ucfirst(str_replace('_', ' ', $documento->tipo)),
                'url' => route('control_pedidos.documentos.show', [$pedido->id, $documento->id]),
            ])->values(),
            'bultos' => $pedido->bultosEmpaque->count(),
            'cajas' => $pedido->cajas->map(fn ($caja) => [
                'tipo' => $caja->tipoCaja?->nombre ?: 'Caja sin tipo',
                'cantidad' => (int) $caja->cantidad,
            ])->values(),
        ]);
    }

    public function guardarAlcance(Request $request, ConfigurarComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');
        $datos = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'area_ids' => ['array'],
            'area_ids.*' => ['integer'],
        ]);
        foreach ($datos['area_ids'] ?? [] as $areaId) {
            $this->assertArea($request->user(), (int) $areaId);
        }
        $service->guardarAlcance((int) $datos['user_id'], $datos['area_ids'] ?? [], $request->user(), $this->alcance->ids($request->user()));

        return back()->with('success', 'Alcance de áreas actualizado.');
    }

    public function clasificar(Request $request, UnidadComisionEmpaque $unidad, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.clasificar');
        $this->assertArea($request->user(), (int) $unidad->area_id);
        $datos = $request->validate([
            'version_revision' => ['required', 'string', 'size:64'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1'],
            'lineas.*.es_bolsa' => ['required', 'boolean'],
            'lineas.*.catalogo_tipo_caja_id' => ['nullable', 'integer'],
        ]);
        $service->clasificar($unidad, $datos['lineas'], $request->user(), $datos['version_revision'] ?? null);

        return back()->with('success', 'Desglose guardado.');
    }

    public function conciliar(Request $request, UnidadComisionEmpaque $unidad, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.clasificar');
        $this->assertArea($request->user(), (int) $unidad->area_id);
        $datos = $request->validate([
            'version_revision' => ['required', 'string', 'size:64'],
            'decision' => ['required', 'in:validar,excluir,pendiente'],
            'motivo_comercial' => ['required', \Illuminate\Validation\Rule::in(array_keys(UnidadComisionEmpaque::MOTIVOS_COMERCIALES))],
            'observaciones' => ['required', 'string', 'max:2000'],
            'referencia' => ['nullable', 'string', 'max:500'],
            'huella' => ['required', 'string', 'size:64'],
            'fuente' => ['nullable', 'string', 'size:64'],
        ]);
        $service->conciliar($unidad, $datos, $request->user());

        return back()->with('success', 'Conciliación guardada.');
    }

    public function decision(Request $request, UnidadComisionEmpaque $unidad, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.clasificar');
        $this->assertArea($request->user(), (int) $unidad->area_id);
        $datos = $request->validate([
            'version_revision' => ['required', 'string', 'size:64'],
            'decision' => ['required', 'in:conservar,excluir'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);
        $service->decidirCancelacion($unidad, $datos['decision'], $datos['motivo'], $request->user(), $datos['version_revision'] ?? null);

        return back()->with('success', 'Decisión de cancelación registrada.');
    }

    public function resolver(Request $request, UnidadComisionEmpaque $unidad, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.clasificar');
        $this->assertArea($request->user(), (int) $unidad->area_id);
        $datos = $request->validate([
            'version_revision' => ['required', 'string', 'size:64'],
            'decision' => ['required', 'in:aceptar,descartar'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);
        $service->resolverRevision($unidad, $datos['decision'], $datos['motivo'], $request->user(), $datos['version_revision'] ?? null);

        return back()->with('success', 'Revisión registrada.');
    }

    public function borrador(Request $request, ConfirmarCorteComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.confirmar_cortes');
        [$area, $desde, $hasta, $tardias] = $this->intervalo($request);
        $service->guardarBorrador($area, $desde, $hasta, $tardias, $request->user());

        return redirect()->route('gestion_interna.comisiones_empaque.index', ['tab' => 'cortes', 'area_id' => $area])
            ->with('success', 'Borrador guardado. No aparta las cajas ni confirma el corte.');
    }

    public function confirmar(Request $request, ConfirmarCorteComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.confirmar_cortes');
        [$area, $desde, $hasta, $tardias] = $this->intervalo($request);
        $request->validate([
            'hash' => ['required', 'string'],
            'periodo_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('comisiones_empaque_periodos', 'id')->where('area_id', $area)],
        ]);
        $corte = $service->confirmar($area, $desde, $hasta, $tardias, $request->input('hash'), $request->user(), $request->integer('borrador_id') ?: null);

        return redirect()->route('gestion_interna.comisiones_empaque.index', ['tab' => 'cortes', 'area_id' => $area, 'corte' => $corte->id, 'periodo_id' => $request->integer('periodo_id') ?: null])
            ->with('success', 'Corte '.$corte->folio.' confirmado. Aún no está pagado.');
    }

    public function descartar(Request $request, CorteComisionEmpaque $corte): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.confirmar_cortes');
        $this->assertArea($request->user(), (int) $corte->area_id);
        if ($corte->estado !== CorteComisionEmpaque::BORRADOR) {
            return back()->with('error', 'Solo se puede descartar un borrador. Un corte confirmado se corrige con un ajuste.');
        }
        $corte->update(['estado' => CorteComisionEmpaque::DESCARTADO]);
        AuditoriaComisionEmpaque::anotar($request->user(), 'corte', $corte->id, 'descartar', null, []);

        return back()->with('success', 'Borrador descartado.');
    }

    public function pagar(Request $request, RegistrarPagoComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.registrar_pagos');
        $datos = $request->validate([
            'participante_id' => ['required', 'integer'],
            'fecha_pago' => ['required', 'date'],
            'importe' => ['required', 'string'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'medio' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'clave_idempotencia' => ['required', 'string', 'max:80'],
            'aplicaciones' => ['required', 'array', 'min:1'],
            'aplicaciones.*.asignacion_id' => ['required', 'integer'],
            'aplicaciones.*.importe' => ['required', 'string'],
        ]);
        $this->assertAsignaciones($request->user(), collect($datos['aplicaciones'])->pluck('asignacion_id')->all());
        $service->pagar(
            (int) $datos['participante_id'],
            $datos['fecha_pago'],
            $datos['importe'],
            $datos['aplicaciones'],
            $datos['referencia'] ?? null,
            $datos['medio'] ?? null,
            $datos['observaciones'] ?? null,
            $datos['clave_idempotencia'],
            $request->user(),
        );

        return back()->with('success', 'Pago registrado. No mueve dinero: solo deja constancia.');
    }

    public function revertirPago(Request $request, PagoComisionEmpaque $pago, RegistrarPagoComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.registrar_pagos');
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:2000']]);
        $pago->load('aplicaciones.asignacion.corte');
        $this->assertAsignaciones($request->user(), $pago->aplicaciones->pluck('asignacion_id')->all());
        $service->revertir($pago, $datos['motivo'], $request->user());

        return back()->with('success', 'Pago revertido. El original queda conservado.');
    }

    public function ajustar(Request $request, RegistrarPagoComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.ajustar');
        $datos = $request->validate([
            'asignacion_id' => ['nullable', 'integer'],
            'corte_id' => ['nullable', 'integer'],
            'unidad_id' => ['nullable', 'integer'],
            'participante_id' => ['nullable', 'integer'],
            'importe' => ['required', 'string'],
            'aplicar_en_saldo' => ['required', 'boolean'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);
        if (! empty($datos['asignacion_id'])) {
            $this->assertAsignaciones($request->user(), [(int) $datos['asignacion_id']]);
        }
        if (! empty($datos['corte_id'])) {
            $corte = CorteComisionEmpaque::query()->findOrFail($datos['corte_id']);
            $this->assertArea($request->user(), (int) $corte->area_id);
        }
        if (! empty($datos['unidad_id'])) {
            $unidad = UnidadComisionEmpaque::query()->findOrFail($datos['unidad_id']);
            $this->assertArea($request->user(), (int) $unidad->area_id);
        }
        $service->ajustar($datos, $request->user());

        return back()->with('success', 'Ajuste registrado.');
    }

    public function fechaInicio(Request $request): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');
        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);
        $cambio = DB::transaction(function () use ($datos, $request) {
            $config = ConfiguracionComisionEmpaque::query()->lockForUpdate()->findOrFail(ConfiguracionComisionEmpaque::actual()->id);
            $anterior = [$config->fecha_inicio?->toDateString(), $config->fecha_fin?->toDateString()];
            $config->fill($datos + ['fecha_fin' => null]);
            if ($anterior === [$config->fecha_inicio?->toDateString(), $config->fecha_fin?->toDateString()]) {
                return false;
            }

            $config->version_rango++;
            $config->save();
            AuditoriaComisionEmpaque::anotar($request->user(), 'configuracion', $config->id, 'periodo', null, $datos);
            if (! in_array(config('queue.default'), ['sync', 'null'], true)) {
                DB::afterCommit(function () {
                    try {
                        ProcesarCambiosComisionesEmpaque::dispatch();
                    } catch (\Throwable $error) {
                        report($error);
                    }
                });
            }

            return true;
        });

        return back()->with('success', $cambio
            ? 'Rango guardado. La reconciliación está pendiente de ejecución.'
            : 'El rango ya está guardado.');
    }

    public function guardarPeriodo(Request $request, GestionarPeriodoComisionEmpaqueService $service, ?PeriodoComisionEmpaque $periodo = null): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');
        $datos = $request->validate([
            'area_id' => ['required', 'integer', 'exists:comisiones_empaque_areas,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'motivo' => [$periodo ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
            'lineas' => ['prohibited'],
        ]);
        $this->assertArea($request->user(), (int) $datos['area_id']);
        if ($periodo) {
            $this->assertArea($request->user(), (int) $periodo->area_id);
        }
        $service->guardar($datos, $request->user(), $periodo);

        return back()->with('success', $periodo ? 'Periodo actualizado. El cambio quedó registrado.' : 'Periodo creado. Revise el acumulado antes de confirmar su corte.');
    }

    public function guardarEquipo(Request $request, GuardarEquipoComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');
        $datos = $request->validate([
            'area_id' => ['required', 'integer', 'exists:comisiones_empaque_areas,id'],
            'reparto_id' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'lineas.*.encargado' => ['sometimes', 'boolean'],
            'lineas.*.porcentaje' => ['required', 'string'],
            'lineas.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $this->assertArea($request->user(), (int) $datos['area_id']);
        $service->guardar((int) $datos['area_id'], $datos['lineas'], $datos['motivo'], $request->user(), (int) $datos['reparto_id'], verificarPermisos: true);

        return back()->with('success', 'Reparto permanente guardado. Los periodos abiertos usan este equipo y los cortes confirmados conservan sus importes.');
    }

    public function guardarArea(Request $request, ConfigurarComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');
        $service->guardarArea($request->all(), null, $request->user());

        return back()->with('success', 'Área creada.');
    }

    public function actualizarArea(Request $request, AreaComisionEmpaque $area, ConfigurarComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');
        $service->guardarArea($request->all(), $area, $request->user());

        return back()->with('success', 'Área actualizada.');
    }

    public function guardarParticipante(Request $request, ConfigurarComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');

        if ($request->filled('id')) {
            $request->validate([
                'id' => ['required', 'integer', 'exists:comisiones_empaque_participantes,id'],
                'nombre' => ['nullable', 'string', 'max:255'],
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'activo' => ['required', 'boolean'],
            ]);
            $service->guardarParticipante($request->all(), ParticipanteComisionEmpaque::query()->find($request->input('id')), $request->user());

            return back()->with('success', 'Colaborador actualizado.');
        }

        $datos = $request->validate([
            'colaboradores' => ['required', 'array', 'min:1', 'max:40'],
            'colaboradores.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'colaboradores.*.nombre' => ['nullable', 'string', 'max:255'],
            'colaboradores.*.activo' => ['required', 'boolean'],
        ]);
        $service->guardarParticipantes($datos['colaboradores'], $request->user());
        $cantidad = count($datos['colaboradores']);

        return back()->with('success', $cantidad === 1 ? 'Colaborador agregado al equipo.' : 'Colaboradores agregados al equipo.');
    }

    public function guardarTarifa(Request $request, ConfigurarComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');
        $datos = $request->validate([
            'area_id' => ['required', 'integer'],
            'importe' => ['required', 'string'],
            'vigente_desde' => ['required', 'date'],
        ]);
        $this->assertArea($request->user(), (int) $datos['area_id']);
        $service->guardarTarifa((int) $datos['area_id'], $datos['importe'], $datos['vigente_desde'], $request->user());

        return back()->with('success', 'Tarifa registrada. Los cortes confirmados no se recalculan.');
    }

    public function guardarReparto(Request $request, ConfigurarComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');
        $datos = $request->validate([
            'area_id' => ['required', 'integer'],
            'vigente_desde' => ['required', 'date'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.participante_id' => ['required', 'integer'],
            'lineas.*.porcentaje' => ['required', 'string'],
        ]);
        $this->assertArea($request->user(), (int) $datos['area_id']);
        $service->guardarReparto((int) $datos['area_id'], $datos['vigente_desde'], $datos['lineas'], $request->user());

        return back()->with('success', 'Reparto registrado a partir de la fecha indicada.');
    }

    public function buscarUsuarios(Request $request)
    {
        abort_unless(
            $request->user()->can('gestion_interna.comisiones_empaque.configurar')
            || $request->user()->can('gestion_interna.comisiones_empaque.clasificar'),
            403
        );
        $q = trim((string) $request->input('q'));
        $catalogo = $request->boolean('catalogo');
        $areaEquipo = null;
        if ($request->filled('equipo_area_id')) {
            Gate::authorize('gestion_interna.comisiones_empaque.configurar');
            $areaId = $request->integer('equipo_area_id');
            $this->assertArea($request->user(), $areaId);
            $areaEquipo = AreaComisionEmpaque::query()->findOrFail($areaId);
        }
        $permiso = $areaEquipo?->permisoGestionPedidos();
        $limite = $areaEquipo ? 30 : ($catalogo ? ($q === '' ? 400 : 80) : 15);

        return User::query()
            ->when($areaEquipo, fn ($consulta) => $consulta->where('es_demo', false)
                ->whereNotIn('id', ParticipanteComisionEmpaque::query()->select('user_id')->where('activo', false)))
            ->when($permiso, fn ($consulta) => $consulta->permission($permiso))
            ->with(['departamento:id,nombre', 'area:id,nombre', 'departamentos:id,nombre', 'areas:id,nombre'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('username', 'like', '%'.$q.'%')
                        ->orWhereHas('departamento', fn ($d) => $d->where('nombre', 'like', '%'.$q.'%'))
                        ->orWhereHas('area', fn ($a) => $a->where('nombre', 'like', '%'.$q.'%'))
                        ->orWhereHas('departamentos', fn ($d) => $d->where('nombre', 'like', '%'.$q.'%'))
                        ->orWhereHas('areas', fn ($a) => $a->where('nombre', 'like', '%'.$q.'%'));
                });
            })
            ->orderBy('name')
            ->limit($limite)
            ->get(['id', 'name', 'username', 'departamento_id', 'area_id'])
            ->map(function (User $usuario) use ($request, $areaEquipo) {
                $ubicacion = $this->ubicacionCuenta($usuario);

                return [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'username' => $usuario->username,
                    'departamento' => $ubicacion['departamento'],
                    'area' => $ubicacion['area'],
                    'facultades' => ! $areaEquipo && $request->user()->can('gestion_interna.comisiones_empaque.configurar')
                        ? collect([
                            'ver' => 'Consultar', 'ver_todas' => 'Todas las áreas', 'capturar' => 'Capturar',
                            'clasificar' => 'Conciliar', 'configurar' => 'Configurar', 'confirmar_cortes' => 'Confirmar liquidaciones',
                            'registrar_pagos' => 'Registrar pagos', 'ajustar' => 'Ajustar', 'exportar' => 'Exportar',
                        ])->filter(fn ($etiqueta, $permiso) => $usuario->can('gestion_interna.comisiones_empaque.'.$permiso))->values()
                        : null,
                ];
            })
            ->values();
    }

    /** @return array{departamento: ?string, area: ?string} */
    private function ubicacionCuenta(?User $usuario): array
    {
        if (! $usuario) {
            return ['departamento' => null, 'area' => null];
        }

        return [
            'departamento' => $usuario->departamento?->nombre ?? $usuario->departamentos->first()?->nombre,
            'area' => $usuario->area?->nombre ?? $usuario->areas->first()?->nombre,
        ];
    }

    /** @return array<string, mixed> */
    private function resumenParticipante(ParticipanteComisionEmpaque $participante): array
    {
        $usuario = $participante->usuario;
        $ubicacion = $this->ubicacionCuenta($usuario);

        return [
            'id' => $participante->id,
            'nombre' => $participante->nombre,
            'user_id' => $participante->user_id,
            'activo' => (bool) $participante->activo,
            'cuenta' => $usuario?->name,
            'username' => $usuario?->username,
            'departamento' => $ubicacion['departamento'],
            'area' => $ubicacion['area'],
        ];
    }

    private function validarFechasReporte(Request $request): void
    {
        $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
    }

    public function exportarReporte(Request $request, ExportarComisionesEmpaqueService $service): StreamedResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.exportar');
        $this->validarFechasReporte($request);
        $request->validate(['version' => ['required', 'string', 'size:64']]);
        if ($request->input('area_id') === 'todas') {
            $request->merge(['area_id' => null]);
        }
        if ($request->filled('area_id')) {
            $this->assertArea($request->user(), $request->integer('area_id'));
        }

        return $service->reporte($request, $request->user());
    }

    public function exportarAcumulado(Request $request, ExportarComisionesEmpaqueService $service): StreamedResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.exportar');

        return $service->acumulado($request, $request->user());
    }

    public function exportarProductividad(Request $request, ExportarComisionesEmpaqueService $service): StreamedResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.exportar');

        return $service->productividad($request, $request->user());
    }

    public function exportarTipos(Request $request, ExportarComisionesEmpaqueService $service): StreamedResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.exportar');

        return $service->tipos($request, $request->user());
    }

    public function exportarCorte(Request $request, CorteComisionEmpaque $corte, ExportarComisionesEmpaqueService $service): StreamedResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.exportar');
        $this->assertArea($request->user(), (int) $corte->area_id);

        return $service->corte($corte);
    }

    public function exportarReparto(Request $request, CorteComisionEmpaque $corte, ExportarComisionesEmpaqueService $service): StreamedResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.exportar');
        $this->assertArea($request->user(), (int) $corte->area_id);

        return $service->reparto($corte);
    }

    public function exportarPagos(Request $request, ExportarComisionesEmpaqueService $service): StreamedResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.exportar');

        if ($request->filled('area_id')) {
            $this->assertArea($request->user(), $request->integer('area_id'));
        }

        return $service->pagos($request->user(), $request->integer('area_id') ?: null);
    }

    /** @return array{0: int, 1: Carbon, 2: Carbon, 3: bool} */
    private function intervalo(Request $request): array
    {
        $datos = $request->validate([
            'area_id' => ['required', 'integer'],
            'limite_desde' => ['required', 'date'],
            'limite_hasta' => ['required', 'date', 'after_or_equal:limite_desde'],
            'incluir_tardias' => ['nullable', 'boolean'],
        ]);
        $this->assertArea($request->user(), (int) $datos['area_id']);
        $zona = ConfiguracionComisionEmpaque::actual()->zona_horaria;

        return [
            (int) $datos['area_id'],
            DineroComisionEmpaque::inicioDia($datos['limite_desde'], $zona),
            DineroComisionEmpaque::finDia($datos['limite_hasta'], $zona),
            $request->boolean('incluir_tardias'),
        ];
    }

    private function assertArea(?User $usuario, int $areaId): void
    {
        abort_unless($this->alcance->permite($usuario, $areaId), 403);
    }

    /** @param  list<int|string>  $ids */
    private function assertAsignaciones(?User $usuario, array $ids): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->values();
        $asignaciones = AsignacionComisionEmpaque::query()->with('corte:id,area_id')->whereIn('id', $ids)->get();
        abort_unless($asignaciones->count() === $ids->count(), 403);
        foreach ($asignaciones as $asignacion) {
            $this->assertArea($usuario, (int) $asignacion->corte?->area_id);
        }
    }

    /** @return array<string, mixed> */
    private function filaCorte(CorteComisionEmpaque $corte): array
    {
        $pagado = (int) $corte->asignaciones->sum('pagado_centavos');
        $pendiente = (int) $corte->asignaciones->sum(fn ($a) => max(0, $a->pendienteCentavos()));

        return [
            'id' => $corte->id,
            'folio' => $corte->folio ?: 'Borrador',
            'provisional' => $corte->estado !== CorteComisionEmpaque::CONFIRMADO,
            'area' => $corte->area?->nombre,
            'estado' => $corte->estado,
            'estado_pago' => $corte->estado === CorteComisionEmpaque::CONFIRMADO ? $corte->estadoPago() : 'no aplica',
            'desde' => optional($corte->limite_desde)->timezone(config('app.timezone'))->format('Y-m-d'),
            'hasta' => optional($corte->limite_hasta)->timezone(config('app.timezone'))->format('Y-m-d'),
            'cajas' => $corte->cajas,
            'total' => DineroComisionEmpaque::texto((int) $corte->total_centavos),
            'pagado' => DineroComisionEmpaque::texto($pagado),
            'pendiente' => DineroComisionEmpaque::texto($pendiente),
            'responsable' => $corte->confirmadoPor?->name,
        ];
    }

    /** @return array<string, mixed> */
    private function detalleCorte(CorteComisionEmpaque $corte): array
    {
        return [
            'id' => $corte->id,
            'folio' => $corte->folio,
            'estado' => $corte->estado,
            'provisional' => $corte->estado !== CorteComisionEmpaque::CONFIRMADO,
            'area' => $corte->area?->nombre,
            'desde' => optional($corte->limite_desde)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'hasta' => optional($corte->limite_hasta)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'responsable' => $corte->confirmadoPor?->name,
            'confirmado_at' => optional($corte->confirmado_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'estado_pago' => $corte->estado === CorteComisionEmpaque::CONFIRMADO ? $corte->estadoPago() : 'no aplica',
            'total' => DineroComisionEmpaque::texto((int) $corte->total_centavos),
            'unidades' => $corte->unidades->map(fn ($u) => [
                'clave' => $u->clave_origen,
                'pedido' => $u->referencia['folio_pedido'] ?? null,
                'remision' => $u->referencia['folio_remision'] ?? null,
                'cliente' => $u->referencia['cliente'] ?? null,
                'empacador' => $u->referencia['empacador'] ?? null,
                'cantidad' => $u->cantidad_comisionable,
                'importe' => DineroComisionEmpaque::texto((int) $u->importe_centavos),
                'desglose' => $u->desglose,
                'conciliacion' => $u->referencia['conciliacion'] ?? null,
                'excepcion_resguardo' => $u->excepcion_resguardo,
                'empacado_at' => optional($u->empacado_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ]),
            'asignaciones' => $corte->asignaciones->map(fn ($a) => [
                'id' => $a->id,
                'participante_id' => $a->participante_id,
                'nombre' => $a->participante?->nombre,
                'porcentaje' => DineroComisionEmpaque::porcentaje((int) $a->puntos_base),
                'reconocido' => DineroComisionEmpaque::texto($a->reconocidoCentavos()),
                'pagado' => DineroComisionEmpaque::texto((int) $a->pagado_centavos),
                'pendiente' => DineroComisionEmpaque::texto($a->pendienteCentavos()),
            ]),
            'ajustes' => $corte->ajustes->map(fn ($a) => [
                'importe' => DineroComisionEmpaque::texto((int) $a->importe_centavos),
                'motivo' => $a->motivo,
                'aplicar_en_saldo' => $a->aplicar_en_saldo,
                'fecha' => optional($a->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ]),
            'simulacion' => $corte->estado === CorteComisionEmpaque::BORRADOR ? $corte->simulacion : null,
        ];
    }
}

<?php

namespace App\Http\Controllers\GestionInterna;

use App\Http\Controllers\Controller;
use App\Models\ComisionesEmpaque\AsignacionComisionEmpaque;
use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\AuditoriaComisionEmpaque;
use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\PagoComisionEmpaque;
use App\Models\ComisionesEmpaque\ParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\RepartoComisionEmpaque;
use App\Models\ComisionesEmpaque\SincronizacionComisionEmpaque;
use App\Models\ComisionesEmpaque\TarifaComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\UsuarioAreaComisionEmpaque;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ComisionesEmpaque\AlcanceAreaComisionEmpaque;
use App\Services\ComisionesEmpaque\ConfigurarComisionEmpaqueService;
use App\Services\ComisionesEmpaque\ConfirmarCorteComisionEmpaqueService;
use App\Services\ComisionesEmpaque\ConsultarComisionesEmpaqueService;
use App\Services\ComisionesEmpaque\ExportarComisionesEmpaqueService;
use App\Services\ComisionesEmpaque\GestionarUnidadComisionEmpaqueService;
use App\Services\ComisionesEmpaque\RegistrarPagoComisionEmpaqueService;
use App\Services\ComisionesEmpaque\SincronizarComisionesEmpaqueService;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use App\Support\ComisionesEmpaque\FuentesComisionEmpaque;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $tab = $request->string('tab')->toString() ?: 'acumulado';
        $usuario = $request->user();
        $config = ConfiguracionComisionEmpaque::actual();
        $sync = SincronizacionComisionEmpaque::query()->latest('id')->first();

        $props = [
            'tab' => $tab,
            'puede' => [
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
            'areas' => $this->alcance->areas($usuario)->map(fn (AreaComisionEmpaque $a) => [
                'id' => $a->id, 'codigo' => $a->codigo, 'nombre' => $a->nombre, 'activo' => $a->activo, 'sistema' => $a->sistema,
            ]),
            'fecha_inicio' => optional($config->fecha_inicio)->toDateString(),
            'fecha_fin' => optional($config->fecha_fin)->toDateString(),
            'zona_horaria' => $config->zona_horaria,
            'sincronizacion' => $sync ? [
                'estado' => $sync->estado,
                'mensaje' => $sync->mensaje,
                'finalizado_at' => optional($sync->finalizado_at)->timezone($config->zona_horaria)->format('Y-m-d H:i'),
                'nuevas' => $sync->unidades_nuevas,
                'actualizadas' => $sync->unidades_actualizadas,
                'diferencias' => $sync->diferencias,
                'detalle' => $sync->detalle ?: [],
            ] : null,
            'resumen' => $this->consultar->resumen($usuario),
            'fuentes' => FuentesComisionEmpaque::resumen(),
            'situaciones' => UnidadComisionEmpaque::ETIQUETAS,
            'tipos_caja' => CatalogoTipoCajaPedido::query()->orderBy('nombre')->get(['id', 'nombre', 'activo']),
            'filtros' => $request->only(['area_id', 'desde', 'hasta', 'cliente', 'remision', 'pedido', 'empacador', 'situacion']),
            'sin_areas' => $this->alcance->ids($usuario) === [],
            'captura_nombre' => $usuario->name,
        ];

        if ($request->filled('area_id')) {
            $this->assertArea($usuario, (int) $request->input('area_id'));
        }

        if (in_array($tab, ['acumulado', 'preparar'], true)) {
            [$pagina, $totales] = $this->consultar->unidades($request, $usuario);
            $props['acumulado'] = $pagina;
            $props['totales'] = $totales;
        }

        if ($tab === 'preparar' && $request->filled('area_id') && $request->filled('limite_desde') && $request->filled('limite_hasta')) {
            $this->assertArea($usuario, (int) $request->input('area_id'));
            $props['preview'] = app(ConfirmarCorteComisionEmpaqueService::class)->calcular(
                (int) $request->input('area_id'),
                DineroComisionEmpaque::inicioDia($request->input('limite_desde'), $config->zona_horaria),
                DineroComisionEmpaque::finDia($request->input('limite_hasta'), $config->zona_horaria),
                $request->boolean('incluir_tardias'),
            );
        }

        if ($tab === 'cortes') {
            $props['cortes'] = CorteComisionEmpaque::query()
                ->with(['area:id,nombre', 'confirmadoPor:id,name', 'asignaciones.ajustes'])
                ->tap(fn ($q) => $this->alcance->aplicar($q, $usuario))
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (CorteComisionEmpaque $c) => $this->filaCorte($c));

            if ($request->filled('corte')) {
                $corte = CorteComisionEmpaque::query()->with([
                    'area', 'confirmadoPor:id,name', 'unidades', 'asignaciones.participante', 'asignaciones.ajustes', 'ajustes',
                ])->find($request->input('corte'));
                if ($corte && $this->alcance->permite($usuario, (int) $corte->area_id)) {
                    $props['corte'] = $this->detalleCorte($corte);
                }
            }
        }

        if ($tab === 'pagos') {
            $props['saldos'] = $this->consultar->saldos($usuario);
        }

        if ($tab === 'configuracion') {
            if (! $usuario->can('gestion_interna.comisiones_empaque.configurar')) {
                [$pagina, $totales] = $this->consultar->unidades($request, $usuario);

                return Inertia::render('GestionInterna/ComisionesEmpaque/Index', array_merge($props, [
                    'tab' => 'acumulado',
                    'acumulado' => $pagina,
                    'totales' => $totales,
                ]));
            }
            $props['participantes'] = ParticipanteComisionEmpaque::query()->with('usuario:id,name')->orderBy('nombre')->get();
            $props['tarifas'] = TarifaComisionEmpaque::query()->with('area:id,nombre')->tap(fn ($q) => $this->alcance->aplicar($q, $usuario))->orderByDesc('vigente_desde')->get()->map(fn (TarifaComisionEmpaque $t) => [
                'id' => $t->id,
                'area' => $t->area?->nombre,
                'area_id' => $t->area_id,
                'importe' => DineroComisionEmpaque::texto((int) $t->importe_centavos),
                'vigente_desde' => optional($t->vigente_desde)->format('Y-m-d H:i'),
                'vigente_hasta' => optional($t->vigente_hasta)->format('Y-m-d H:i'),
            ]);
            $props['repartos'] = RepartoComisionEmpaque::query()->with(['area:id,nombre', 'lineas.participante:id,nombre'])->tap(fn ($q) => $this->alcance->aplicar($q, $usuario))->orderByDesc('vigente_desde')->get();
            $props['alcances'] = UsuarioAreaComisionEmpaque::query()
                ->tap(fn ($q) => $this->alcance->aplicar($q, $usuario))
                ->with(['usuario:id,name', 'area:id,nombre'])
                ->orderBy('user_id')
                ->get()
                ->map(fn (UsuarioAreaComisionEmpaque $fila) => [
                    'user_id' => $fila->user_id,
                    'nombre' => $fila->usuario?->name,
                    'area_id' => $fila->area_id,
                    'area' => $fila->area?->nombre,
                ]);
        }

        return Inertia::render('GestionInterna/ComisionesEmpaque/Index', $props);
    }

    public function sincronizar(Request $request, SincronizarComisionesEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.ver');
        $sync = $service->ejecutar($request->user());

        return back()->with($sync->estado === 'ok' ? 'success' : 'warning', $sync->mensaje);
    }

    public function manual(Request $request, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.capturar');
        $tienda = AreaComisionEmpaque::query()->where('codigo', 'tienda')->first();
        $this->assertArea($request->user(), (int) ($tienda->id ?? 0));
        $datos = $request->validate([
            'clave_idempotencia' => ['required', 'string', 'max:80'],
            'empacado_at' => ['required', 'date'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'folio_pedido' => ['nullable', 'string', 'max:255'],
            'folio_remision' => ['nullable', 'string', 'max:255'],
            'pedido_bma_id' => ['nullable', 'integer'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->registrarManual($datos, $request->user());

        return back()->with('success', 'Registro manual guardado. Queda pendiente de revisión y todavía no entra al acumulado.');
    }

    public function revisar(Request $request, UnidadComisionEmpaque $unidad, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.clasificar');
        $this->assertArea($request->user(), (int) $unidad->area_id);
        $datos = $request->validate([
            'decision' => ['required', 'in:aceptar,rechazar,trabajo_distinto,aceptar_sin_referencia'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);
        $service->revisarManual($unidad, $datos['decision'], $datos['motivo'], $request->user());

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
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1'],
            'lineas.*.es_bolsa' => ['required', 'boolean'],
            'lineas.*.catalogo_tipo_caja_id' => ['nullable', 'integer'],
        ]);
        $service->clasificar($unidad, $datos['lineas'], $request->user());

        return back()->with('success', 'Desglose guardado.');
    }

    public function decision(Request $request, UnidadComisionEmpaque $unidad, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.clasificar');
        $this->assertArea($request->user(), (int) $unidad->area_id);
        $datos = $request->validate([
            'decision' => ['required', 'in:conservar,excluir'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);
        $service->decidirCancelacion($unidad, $datos['decision'], $datos['motivo'], $request->user());

        return back()->with('success', 'Decisión de cancelación registrada.');
    }

    public function resolver(Request $request, UnidadComisionEmpaque $unidad, GestionarUnidadComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.clasificar');
        $this->assertArea($request->user(), (int) $unidad->area_id);
        $datos = $request->validate([
            'decision' => ['required', 'in:aceptar,descartar'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);
        $service->resolverRevision($unidad, $datos['decision'], $datos['motivo'], $request->user());

        return back()->with('success', 'Revisión registrada.');
    }

    public function borrador(Request $request, ConfirmarCorteComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.confirmar_cortes');
        [$area, $desde, $hasta, $tardias] = $this->intervalo($request);
        $service->guardarBorrador($area, $desde, $hasta, $tardias, $request->user());

        return redirect()->route('gestion_interna.comisiones_empaque.index', ['tab' => 'cortes'])
            ->with('success', 'Borrador guardado. No aparta las cajas ni confirma el corte.');
    }

    public function confirmar(Request $request, ConfirmarCorteComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.confirmar_cortes');
        [$area, $desde, $hasta, $tardias] = $this->intervalo($request);
        $request->validate(['hash' => ['required', 'string']]);
        $corte = $service->confirmar($area, $desde, $hasta, $tardias, $request->input('hash'), $request->user(), $request->integer('borrador_id') ?: null);

        return redirect()->route('gestion_interna.comisiones_empaque.index', ['tab' => 'cortes', 'corte' => $corte->id])
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
        $config = ConfiguracionComisionEmpaque::actual();
        $config->update([
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'] ?? null,
        ]);
        AuditoriaComisionEmpaque::anotar($request->user(), 'configuracion', $config->id, 'periodo', null, $datos);

        return back()->with('success', 'Periodo guardado. El corte puede usar otras fechas, antes o después de este rango.');
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
        $service->guardarParticipante($request->all(), $request->filled('id') ? ParticipanteComisionEmpaque::query()->find($request->input('id')) : null, $request->user());

        return back()->with('success', 'Participante guardado.');
    }

    public function guardarTarifa(Request $request, ConfigurarComisionEmpaqueService $service): RedirectResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.configurar');
        $datos = $request->validate([
            'area_id' => ['required', 'integer'],
            'importe' => ['required', 'string'],
            'vigente_desde' => ['required', 'date'],
        ]);
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

        return User::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    public function exportarAcumulado(Request $request, ExportarComisionesEmpaqueService $service): StreamedResponse
    {
        Gate::authorize('gestion_interna.comisiones_empaque.exportar');

        return $service->acumulado($request, $request->user());
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

        return $service->pagos($request->user());
    }

    /** @return array{0: int, 1: \Illuminate\Support\Carbon, 2: \Illuminate\Support\Carbon, 3: bool} */
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

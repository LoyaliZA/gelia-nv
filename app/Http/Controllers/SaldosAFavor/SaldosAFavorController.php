<?php

namespace App\Http\Controllers\SaldosAFavor;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafComprobanteCaja;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafCuenta;
use App\Models\SaldosAFavor\SafIncidencia;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\SaldosAFavor\SafPedidoAplicacion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\SaldosAFavor\AjustarCreditoSafService;
use App\Services\SaldosAFavor\CancelarCreditoSafService;
use App\Services\SaldosAFavor\ConsultarCuentaClienteSafService;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use App\Services\SaldosAFavor\ReactivarCreditoSafService;
use App\Services\SaldosAFavor\RegistrarIncidenciaSafService;
use App\Services\SaldosAFavor\RevertirAplicacionSafService;
use App\Services\SaldosAFavor\RevisarCreditoSafService;
use App\Services\SaldosAFavor\RevisarPagoPedidoBmaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SaldosAFavorController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->get('q', ''));
        $estadoRevision = $request->get('estado_revision');
        $estadoFinanciero = $request->get('estado_financiero');
        $canalOrigen = $request->get('canal_origen');
        $generadoPorId = $request->get('generado_por_id');
        $montoMin = $request->get('monto_min');
        $montoMax = $request->get('monto_max');
        $antiguedadDias = $request->get('antiguedad_dias');
        $tab = $request->get('tab', 'creditos');

        $filtroCreditos = function ($query) use (
            $q,
            $estadoRevision,
            $estadoFinanciero,
            $canalOrigen,
            $generadoPorId,
            $montoMin,
            $montoMax,
            $antiguedadDias,
        ) {
            $query
                ->when($q !== '', function ($inner) use ($q) {
                    $inner->where(function ($w) use ($q) {
                        $w->where('folio', 'like', "%{$q}%")
                            ->orWhereHas('cliente', function ($c) use ($q) {
                                $c->where('numero_cliente', 'like', "%{$q}%")
                                    ->orWhere('nombre', 'like', "%{$q}%");
                            });
                    });
                })
                ->when($estadoRevision, fn ($q2) => $q2->where('estado_revision', $estadoRevision))
                ->when($estadoFinanciero, function ($q2) use ($estadoFinanciero) {
                    if ($estadoFinanciero === SafCredito::ESTADO_DISPONIBLE) {
                        $q2->whereIn('estado_financiero', SafCredito::ESTADOS_USABLES);
                    } else {
                        $q2->where('estado_financiero', $estadoFinanciero);
                    }
                })
                ->when($canalOrigen, fn ($q2) => $q2->where('canal_origen', $canalOrigen))
                ->when($generadoPorId, fn ($q2) => $q2->where('generado_por_id', (int) $generadoPorId))
                ->when($montoMin !== null && $montoMin !== '', fn ($q2) => $q2->where('monto_original', '>=', (float) $montoMin))
                ->when($montoMax !== null && $montoMax !== '', fn ($q2) => $q2->where('monto_original', '<=', (float) $montoMax))
                ->when($antiguedadDias !== null && $antiguedadDias !== '', function ($q2) use ($antiguedadDias) {
                    $q2->whereDate('fecha_generacion', '<=', now()->subDays((int) $antiguedadDias)->toDateString());
                });
        };

        $hoy = now()->toDateString();
        $creditosUsables = function ($query) use ($hoy) {
            $query
                ->whereIn('estado_financiero', SafCredito::ESTADOS_USABLES)
                ->where('monto_disponible', '>', 0)
                ->whereDate('fecha_vencimiento', '>=', $hoy);
        };

        $cuentas = SafCuenta::query()
            ->with(['cliente:id,numero_cliente,nombre'])
            ->whereHas('creditos', $filtroCreditos)
            ->withSum(['creditos as disponible' => $creditosUsables], 'monto_disponible')
            ->withSum('creditos as reservado', 'monto_reservado')
            ->withCount([
                'creditos as saldos_disponibles' => $creditosUsables,
                'creditos as pendientes_revision' => fn ($q) => $q->where('estado_revision', SafCredito::REVISION_PENDIENTE),
                'creditos as saldos_vencidos' => fn ($q) => $q->where('estado_financiero', SafCredito::ESTADO_VENCIDO),
            ])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SafCuenta $cuenta) => [
                'id' => $cuenta->id,
                'cliente_id' => $cuenta->cliente_id,
                'cliente' => $cuenta->cliente,
                'disponible' => round((float) ($cuenta->disponible ?? 0), 2),
                'reservado' => round((float) ($cuenta->reservado ?? 0), 2),
                'saldos_disponibles' => (int) ($cuenta->saldos_disponibles ?? 0),
                'pendientes_revision' => (int) ($cuenta->pendientes_revision ?? 0),
                'saldos_vencidos' => (int) ($cuenta->saldos_vencidos ?? 0),
            ]);

        $creditosPendientesRevision = SafCredito::query()
            ->with(['cliente:id,numero_cliente,nombre', 'motivo'])
            ->where('estado_revision', SafCredito::REVISION_PENDIENTE)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $pagosPendientes = PedidoBmaPago::query()
            ->with(['pedido:id,folio,cliente_id', 'pedido.cliente:id,numero_cliente,nombre', 'banco'])
            ->where('estado_revision', PedidoBmaPago::REVISION_PENDIENTE)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $comprobantesCaja = SafComprobanteCaja::query()
            ->with(['cliente:id,numero_cliente,nombre'])
            ->whereIn('estado', [
                SafComprobanteCaja::ESTADO_PENDIENTE_FIRMA,
                SafComprobanteCaja::ESTADO_FIRMADO_PENDIENTE_REVISION,
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $incidenciasAbiertas = SafIncidencia::query()
            ->with(['cliente:id,numero_cliente,nombre', 'creadoPor:id,name'])
            ->where('estado', SafIncidencia::ESTADO_ABIERTA)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $pendientes = SafCredito::where('estado_revision', SafCredito::REVISION_PENDIENTE)->count();

        return Inertia::render('SaldosAFavor/Index', [
            'cuentas' => $cuentas,
            'tab' => $tab,
            'colas' => [
                'creditos_pendientes' => $creditosPendientesRevision,
                'pagos_pendientes' => $pagosPendientes,
                'comprobantes_caja' => $comprobantesCaja,
                'incidencias' => $incidenciasAbiertas,
            ],
            'filtros' => [
                'q' => $q,
                'estado_revision' => $estadoRevision,
                'estado_financiero' => $estadoFinanciero,
                'canal_origen' => $canalOrigen,
                'generado_por_id' => $generadoPorId,
                'monto_min' => $montoMin,
                'monto_max' => $montoMax,
                'antiguedad_dias' => $antiguedadDias,
                'tab' => $tab,
            ],
            'metricas' => [
                'pendientes_revision' => $pendientes,
                'pagos_pendientes' => $pagosPendientes->count(),
                'caja_pendientes' => $comprobantesCaja->count(),
                'incidencias_abiertas' => $incidenciasAbiertas->count(),
                'disponibles' => SafCredito::whereIn('estado_financiero', SafCredito::ESTADOS_USABLES)
                    ->where('monto_disponible', '>', 0)->count(),
                'reservados' => SafCredito::where('estado_financiero', SafCredito::ESTADO_RESERVADO)->count(),
                'vencidos' => SafCredito::where('estado_financiero', SafCredito::ESTADO_VENCIDO)->count(),
            ],
            'motivos' => SafMotivo::where('activo', true)->orderBy('orden')->get(['id', 'codigo', 'nombre', 'categoria', 'requiere_detalle']),
            'sucursales' => Sucursal::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'generadores' => User::query()
                ->whereIn('id', SafCredito::query()->select('generado_por_id')->whereNotNull('generado_por_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'formas_pago' => PedidoBmaPago::formasPagoCatalogo(),
        ]);
    }

    public function cuenta(Request $request, Cliente $cliente, ConsultarCuentaClienteSafService $consultar): Response
    {
        $cuenta = $consultar->handle($cliente->id);

        $creditos = $cuenta['creditos']->loadMissing([
            'motivo:id,nombre,categoria',
            'evidencias',
            'pedidoOrigen:id,folio,cliente_id',
            'pedidoOrigen.pagosExhibicion.banco:id,nombre',
            'aplicacionesPedido' => fn ($q) => $q->where('estado', SafPedidoAplicacion::ESTADO_APLICADO)->orderByDesc('id'),
        ]);

        return Inertia::render('SaldosAFavor/Cuenta', [
            'cliente' => $cliente->only(['id', 'numero_cliente', 'nombre']),
            'cuenta' => [
                'disponible' => $cuenta['disponible'],
                'reservado' => $cuenta['reservado'],
                'aplicado' => $cuenta['aplicado'],
                'vencido' => $cuenta['vencido'],
                'moneda' => $cuenta['cuenta']->moneda,
            ],
            'creditos' => $creditos->map(function (SafCredito $c) {
                $pagos = $c->pedidoOrigen?->pagosExhibicion ?? collect();

                return [
                    ...$c->toArray(),
                    'recibos_pago' => $pagos->map(fn (PedidoBmaPago $p) => [
                        'id' => $p->id,
                        'monto' => (float) $p->monto,
                        'forma_pago' => $p->forma_pago,
                        'forma_pago_label' => PedidoBmaPago::labelForma($p->forma_pago),
                        'fecha_pago' => $p->fecha_pago?->toDateString(),
                        'referencia' => $p->referencia,
                        'banco_nombre' => $p->banco?->nombre,
                        'ruta_archivo' => $p->ruta_archivo,
                        'nombre_original' => $p->nombre_original,
                        'mime_type' => $p->mime_type,
                        'estado_revision' => $p->estado_revision,
                        'url' => $p->ruta_archivo ? asset('storage/'.$p->ruta_archivo) : null,
                    ])->values(),
                    'evidencias' => ($c->evidencias ?? collect())->map(fn ($e) => [
                        'id' => $e->id,
                        'nombre_original' => $e->nombre_original,
                        'ruta_archivo' => $e->ruta_archivo,
                        'mime_type' => $e->mime_type ?? null,
                        'created_at' => $e->created_at,
                        'url' => $e->ruta_archivo ? asset('storage/'.$e->ruta_archivo) : null,
                    ])->values(),
                    'pedido_origen_folio' => $c->pedidoOrigen?->folio,
                ];
            }),
            'movimientos' => $cuenta['movimientos'],
            'motivos' => SafMotivo::where('activo', true)->orderBy('orden')->get(['id', 'codigo', 'nombre', 'categoria', 'requiere_detalle']),
        ]);
    }

    public function buscarCliente(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json(['data' => []]);
        }

        $clientes = Cliente::query()
            ->where(function ($inner) use ($q) {
                $inner->where('numero_cliente', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%");
            })
            ->orderBy('numero_cliente')
            ->limit(20)
            ->get(['id', 'numero_cliente', 'nombre']);

        return response()->json(['data' => $clientes]);
    }

    public function generar(Request $request, GenerarCreditoSafService $service): RedirectResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'saf_motivo_id' => ['required', 'exists:saf_motivos,id'],
            'detalle_motivo' => ['nullable', 'string', 'max:2000'],
            'canal_origen' => ['nullable', 'string', 'max:64'],
            'sucursal' => ['nullable', 'string', 'max:128'],
            'departamento' => ['nullable', 'string', 'max:128'],
            'documento_origen' => ['nullable', 'string', 'max:128'],
            'pedido_bma_id' => ['nullable', 'integer'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'evidencias' => ['required', 'array', 'min:1'],
            'evidencias.*' => ['file', 'max:10240'],
        ]);

        try {
            $credito = $service->handle(
                array_merge($datos, [
                    'generado_por_id' => Auth::id(),
                    'origen_manual' => true,
                ]),
                $request->file('evidencias', []) ?? []
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['monto' => $e->getMessage()]);
        }

        return redirect()
            ->route('saldos_favor.cuenta', $credito->cliente_id)
            ->with('success', "Saldo a favor {$credito->folio} generado. Disponible a partir del siguiente pedido (pendiente de revisión).");
    }

    public function revisar(
        Request $request,
        SafCredito $credito,
        RevisarCreditoSafService $service,
    ): RedirectResponse {
        $datos = $request->validate([
            'estado_revision' => ['required', 'string'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->handle($credito->id, $datos['estado_revision'], Auth::id(), $datos['observaciones'] ?? null);

        return back()->with('success', "Revisión actualizada para {$credito->folio}.");
    }

    public function revisarPago(Request $request, PedidoBmaPago $pago): RedirectResponse
    {
        $datos = $request->validate([
            'estado_revision' => ['required', 'in:'.implode(',', PedidoBmaPago::ESTADOS_REVISION)],
            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:estado_revision,'.PedidoBmaPago::REVISION_CON_OBSERVACIONES,
                'required_if:estado_revision,'.PedidoBmaPago::REVISION_RECHAZADO,
            ],
        ]);

        app(RevisarPagoPedidoBmaService::class)->handle(
            $pago,
            $datos['estado_revision'],
            Auth::id(),
            $datos['observaciones'] ?? null
        );

        return back()->with('success', 'Exhibición actualizada.');
    }

    public function ajustar(
        Request $request,
        SafCredito $credito,
        AjustarCreditoSafService $service,
    ): RedirectResponse {
        $datos = $request->validate([
            'monto_delta' => ['required', 'numeric'],
            'saf_motivo_id' => ['required', 'exists:saf_motivos,id'],
            'observaciones' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $service->handle(
                $credito->id,
                (float) $datos['monto_delta'],
                Auth::id(),
                (int) $datos['saf_motivo_id'],
                $datos['observaciones']
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['monto_delta' => $e->getMessage()]);
        }

        return back()->with('success', "Ajuste registrado en {$credito->folio}.");
    }

    public function cancelar(
        Request $request,
        SafCredito $credito,
        CancelarCreditoSafService $service,
    ): RedirectResponse {
        $datos = $request->validate([
            'observaciones' => ['required', 'string', 'max:2000'],
        ]);

        $service->handle($credito->id, Auth::id(), $datos['observaciones']);

        return back()->with('success', "Saldo a favor {$credito->folio} cancelado.");
    }

    public function reactivar(
        Request $request,
        SafCredito $credito,
        ReactivarCreditoSafService $service,
    ): RedirectResponse {
        $datos = $request->validate([
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->handle($credito->id, Auth::id(), $datos['observaciones'] ?? null);

        return back()->with('success', "Saldo a favor {$credito->folio} reactivado.");
    }

    public function revertirAplicacion(
        Request $request,
        SafCredito $credito,
        RevertirAplicacionSafService $service,
    ): RedirectResponse {
        $datos = $request->validate([
            'saf_pedido_aplicacion_id' => ['nullable', 'integer', 'exists:saf_pedido_aplicaciones,id'],
            'monto' => ['nullable', 'numeric', 'min:0.01'],
            'observaciones' => ['required', 'string', 'max:2000'],
        ]);

        $service->handle(
            ! empty($datos['saf_pedido_aplicacion_id']) ? (int) $datos['saf_pedido_aplicacion_id'] : null,
            $credito->id,
            isset($datos['monto']) && $datos['monto'] !== '' && $datos['monto'] !== null
                ? (float) $datos['monto']
                : null,
            Auth::id(),
            $datos['observaciones']
        );

        return back()->with('success', "Aplicación revertida en {$credito->folio}.");
    }

    public function resolverIncidencia(
        Request $request,
        SafIncidencia $incidencia,
        RegistrarIncidenciaSafService $service,
    ): RedirectResponse {
        $datos = $request->validate([
            'nota' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->resolver($incidencia, Auth::id(), $datos['nota'] ?? null);

        return back()->with('success', 'Incidencia marcada como resuelta.');
    }

    public function apiCuenta(Cliente $cliente, ConsultarCuentaClienteSafService $consultar)
    {
        $cuenta = $consultar->handle($cliente->id);

        return response()->json([
            'disponible' => $cuenta['disponible'],
            'reservado' => $cuenta['reservado'],
            'aplicado' => $cuenta['aplicado'],
            'creditos_usables' => $cuenta['creditos_usables']->map(fn (SafCredito $c) => [
                'id' => $c->id,
                'folio' => $c->folio,
                'canal_origen' => $c->canal_origen,
                'monto_disponible' => (float) $c->monto_disponible,
                'fecha_vencimiento' => $c->fecha_vencimiento?->toDateString(),
                'estado_revision' => $c->estado_revision,
            ]),
        ]);
    }

    public function apiSugerir(Request $request, Cliente $cliente, ConsultarCuentaClienteSafService $consultar)
    {
        $monto = (float) $request->get('monto', 0);

        return response()->json($consultar->sugerirAplicacion($cliente->id, $monto));
    }
}

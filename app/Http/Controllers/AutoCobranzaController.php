<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\CobranzaAlerta;
use App\Models\CobranzaFactura;
use App\Services\Cobranza\ImportarReporteCobranzaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AutoCobranzaController extends Controller
{
    /**
     * Muestra la vista principal de Auto-Cobranza con KPIs, Cartera Vencida y Alertas.
     */
    public function index(Request $request)
    {
        $this->authorize('cobranza.ver');

        // 1. Clientes con saldo activo: búsqueda y orden en servidor
        $clientes = $this->queryClientesConCredito($request);

        // 2. Alertas operativas (filtradas por facturas activas y que no estén resueltas)
        $alertas = CobranzaAlerta::with(['cliente', 'factura'])
            ->where('estado', '!=', 'resuelta')
            ->whereHas('factura', function ($query) {
                $query->where('pagada', false)->where('monto', '>', 0);
            })
            ->orderBy('fecha_alerta', 'desc')
            ->get();

        // 3. Agrupación administrativa de cartera vencida
        $facturasVencidas = CobranzaFactura::where('pagada', false)->get();
        $cartera = [
            'rango_1_30' => ['cantidad' => 0, 'total' => 0.00],
            'rango_31_60' => ['cantidad' => 0, 'total' => 0.00],
            'rango_61_90' => ['cantidad' => 0, 'total' => 0.00],
            'rango_91_120' => ['cantidad' => 0, 'total' => 0.00],
            'rango_120_mas' => ['cantidad' => 0, 'total' => 0.00],
        ];

        foreach ($facturasVencidas as $factura) {
            $atraso = Carbon::parse($factura->fecha_vencimiento)->startOfDay()->diffInDays(now()->startOfDay(), false);
            
            if ($atraso >= 1 && $atraso <= 30) {
                $cartera['rango_1_30']['cantidad']++;
                $cartera['rango_1_30']['total'] += (float)$factura->monto;
            } elseif ($atraso >= 31 && $atraso <= 60) {
                $cartera['rango_31_60']['cantidad']++;
                $cartera['rango_31_60']['total'] += (float)$factura->monto;
            } elseif ($atraso >= 61 && $atraso <= 90) {
                $cartera['rango_61_90']['cantidad']++;
                $cartera['rango_61_90']['total'] += (float)$factura->monto;
            } elseif ($atraso >= 91 && $atraso <= 120) {
                $cartera['rango_91_120']['cantidad']++;
                $cartera['rango_91_120']['total'] += (float)$factura->monto;
            } elseif ($atraso > 120) {
                $cartera['rango_120_mas']['cantidad']++;
                $cartera['rango_120_mas']['total'] += (float)$factura->monto;
            }
        }

        $aumentosPendientes = Cliente::with('facturasActivas')
            ->where('alerta_aumento_credito', true)
            ->get();

        $configuracionHorarios = \Illuminate\Support\Facades\Cache::rememberForever('cobranza_horarios', function () {
            $config = \App\Models\CobranzaConfiguracion::where('llave', 'horarios_alertas')->first();
            return $config ? $config->valor : ['10:00', '12:00'];
        });

        $configuracionAlertas = \Illuminate\Support\Facades\Cache::rememberForever('cobranza_config_alertas', function () {
            $config = \App\Models\CobranzaConfiguracion::where('llave', 'config_alertas')->first();
            return $config ? $config->valor : ['intervalo_dias' => 3, 'umbral_diario' => 30];
        });

        return Inertia::render('AutoCobranza/Index', [
            'clientes' => $clientes,
            'alertas' => $alertas,
            'cartera' => $cartera,
            'aumentosPendientes' => $aumentosPendientes,
            'configuracionHorarios' => $configuracionHorarios,
            'configuracionAlertas' => $configuracionAlertas,
            'filtros' => [
                'q' => $request->q,
                'orden' => $request->orden,
            ],
            'users' => \App\Models\User::select('id', 'name', 'email')->get(),
            'notifiedUsersPagos' => \Illuminate\Support\Facades\Cache::rememberForever('cobranza_config_users_pagos', function () {
                $config = \App\Models\CobranzaConfiguracion::where('llave', 'notified_users_pagos')->first();
                return $config && $config->valor ? json_decode($config->valor, true) : [];
            }),
        ]);
    }

    public function guardarConfiguracion(Request $request)
    {
        $this->authorize('cobranza.configurar_alertas');
        $request->validate([
            'horarios' => 'required|array',
            'intervalo_dias' => 'required|integer|min:1',
            'umbral_diario' => 'required|integer|min:1'
        ]);

        $configHorarios = \App\Models\CobranzaConfiguracion::firstOrCreate(['llave' => 'horarios_alertas']);
        $configHorarios->update(['valor' => $request->horarios]);

        $configAlertas = \App\Models\CobranzaConfiguracion::firstOrCreate(['llave' => 'config_alertas']);
        $configAlertas->update(['valor' => [
            'intervalo_dias' => (int) $request->intervalo_dias,
            'umbral_diario' => (int) $request->umbral_diario,
        ]]);

        \Illuminate\Support\Facades\Cache::forever('cobranza_horarios', $request->horarios);
        \Illuminate\Support\Facades\Cache::forever('cobranza_config_alertas', [
            'intervalo_dias' => (int) $request->intervalo_dias,
            'umbral_diario' => (int) $request->umbral_diario,
        ]);

        if ($request->has('notified_users_pagos')) {
            $configUsersPagos = \App\Models\CobranzaConfiguracion::firstOrCreate(['llave' => 'notified_users_pagos']);
            $configUsersPagos->update(['valor' => json_encode($request->notified_users_pagos)]);
            \Illuminate\Support\Facades\Cache::forever('cobranza_config_users_pagos', $request->notified_users_pagos);
        }

        return redirect()->back()->with('success', 'Configuración actualizada exitosamente.');
    }

    private function queryClientesConCredito(Request $request)
    {
        $query = Cliente::with(['vendedor', 'listaDescuento', 'facturaCobranzaActiva'])
            ->whereHas('facturasCobranza', function ($q) {
                $q->where('pagada', false)->where('monto', '>', 0);
            });

        if ($request->filled('q')) {
            $termino = trim($request->q);
            $query->where(function ($sub) use ($termino) {
                if (preg_match('/^\d/', $termino)) {
                    $sub->where('numero_cliente', 'like', "{$termino}%");
                }
                $sub->orWhere('nombre', 'like', "{$termino}%")
                    ->orWhere('nombre', 'like', "%{$termino}%");
            });
        }

        $saldoSubquery = CobranzaFactura::query()
            ->select('monto')
            ->whereColumn('cliente_id', 'clientes.id')
            ->where('pagada', false)
            ->orderByDesc('monto')
            ->limit(1);

        $estadoSubquery = CobranzaFactura::query()
            ->selectRaw("
                CASE 
                    WHEN clientes.dias_credito IS NULL OR clientes.dias_credito = 0 OR clientes.monto_credito_autorizado IS NULL OR clientes.monto_credito_autorizado = 0 THEN 5
                    WHEN fecha_vencimiento < CURDATE() THEN 1
                    WHEN fecha_vencimiento >= CURDATE() AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 5 DAY) THEN 2
                    WHEN tiene_abono = 1 THEN 3
                    ELSE 4
                END
            ")
            ->whereColumn('cliente_id', 'clientes.id')
            ->where('pagada', false)
            ->orderByDesc('monto')
            ->limit(1);

        match ($request->input('orden', 'automatico')) {
            'automatico'         => $query->orderBy($estadoSubquery)->orderByDesc($saldoSubquery),
            'saldo_asc'          => $query->orderBy($saldoSubquery),
            'saldo_desc'         => $query->orderByDesc($saldoSubquery),
            'nombre_asc'         => $query->orderBy('nombre'),
            'nombre_desc'        => $query->orderByDesc('nombre'),
            'fecha_credito_desc' => $query->orderByDesc('fecha_inicio_credito'),
            'numero_asc'         => $query->ordenarPorNumeroCliente('asc'),
            default              => $query->orderBy($estadoSubquery)->orderByDesc($saldoSubquery),
        };

        return $query->paginate(6)->withQueryString();
    }

    /**
     * Procesa la subida del reporte de cobranza en CSV.
     */
    public function importarReporte(Request $request, ImportarReporteCobranzaService $importador)
    {
        $this->authorize('cobranza.importar_reporte');

        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt'
        ]);

        try {
            $resultado = $importador->ejecutar($request->file('archivo'));

            return redirect()->back()->with('success', "Reporte procesado exitosamente. Clientes procesados: {$resultado['procesados']}, creados: {$resultado['nuevos']}, con deuda vencida: {$resultado['actualizados']}.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['archivo' => 'Error al importar: ' . $e->getMessage()]);
        }
    }

    /**
     * Registra la bitácora de llamadas y cambia el estado de una alerta.
     */
    public function actualizarAlerta(Request $request, CobranzaAlerta $alerta)
    {
        $this->authorize('cobranza.ejecutar_llamadas');

        $validated = $request->validate([
            'estado' => 'required|string|in:pendiente,llamado,no_contesto,compromiso_pago',
            'observaciones' => 'nullable|string',
        ]);

        $alerta->update($validated);

        \App\Models\CobranzaBitacora::create([
            'cliente_id' => $alerta->cliente_id,
            'usuario_id' => auth()->id() ?? 1,
            'tipo_evento' => 'llamada',
            'monto_anterior' => 0,
            'monto_nuevo' => 0,
            'descripcion' => "Registro de contacto: " . ucfirst(str_replace('_', ' ', $validated['estado'])) . ". " . ($validated['observaciones'] ? "Observaciones: " . $validated['observaciones'] : ""),
        ]);

        return redirect()->back()->with('success', 'Bitácora de llamada registrada y alerta actualizada.');
    }

    /**
     * Obtiene la bitácora de un cliente.
     */
    public function bitacora($clienteId)
    {
        $this->authorize('cobranza.ver_bitacora');

        $bitacora = \App\Models\CobranzaBitacora::with('usuario:id,name')
            ->where('cliente_id', $clienteId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bitacora);
    }

    /**
     * Devuelve los clientes con historial de crédito (paginado) para la pestaña de Historial.
     */
    public function historial(Request $request)
    {
        $this->authorize('cobranza.ver');

        $query = Cliente::with(['facturasCobranza'])
            ->whereHas('facturasCobranza');

        if ($request->filled('q')) {
            $terminos = array_filter(array_map('trim', explode(',', $request->q)));
            
            $query->where(function ($sub) use ($terminos) {
                foreach ($terminos as $termino) {
                    $sub->orWhere(function ($sub2) use ($termino) {
                        if (preg_match('/^\d/', $termino)) {
                            $sub2->where('numero_cliente', 'like', "{$termino}%");
                        }
                        $sub2->orWhere('nombre', 'like', "%{$termino}%");
                    });
                }
            });
        }

        $clientes = $query->orderByDesc('id')->paginate(10);

        return response()->json($clientes);
    }

    /**
     * Permite a un administrador confirmar visualmente que una factura fue pagada.
     */
    public function verificarPago(Request $request, CobranzaFactura $cobranzaFactura)
    {
        $this->authorize('cobranza.ver');

        if (!$cobranzaFactura->pagada) {
            return response()->json(['message' => 'Solo se pueden verificar facturas que ya están pagadas.'], 422);
        }

        $cobranzaFactura->update(['verificado_manualmente' => true]);

        \App\Models\CobranzaBitacora::create([
            'cliente_id' => $cobranzaFactura->cliente_id,
            'usuario_id' => auth()->id(),
            'tipo_evento' => 'verificacion_manual',
            'monto_anterior' => $cobranzaFactura->monto,
            'monto_nuevo' => $cobranzaFactura->monto,
            'descripcion' => "Pago de factura {$cobranzaFactura->folio} verificado manualmente por el administrador.",
        ]);

        return response()->json(['message' => 'Factura verificada exitosamente.']);
    }

    /**
     * Devuelve los abonos registrados el día de hoy.
     */
    public function abonosDelDia()
    {
        $this->authorize('cobranza.ver');

        $abonos = \App\Models\CobranzaBitacora::with('cliente:id,nombre,numero_cliente')
            ->where('tipo_evento', 'abono')
            ->whereDate('created_at', now()->toDateString())
            ->orderByDesc('created_at')
            ->get();

        return response()->json($abonos);
    }

    /**
     * Marca la alerta de aumento de crédito como resuelta.
     */
    public function resolverAumento(Request $request, Cliente $cliente)
    {
        $this->authorize('cobranza.ver');

        $cliente->update(['alerta_aumento_credito' => false]);

        \App\Models\CobranzaBitacora::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => auth()->id() ?? 1,
            'tipo_evento' => 'verificacion_manual',
            'monto_anterior' => 0,
            'monto_nuevo' => 0,
            'descripcion' => 'Alerta de aumento de saldo marcada como atendida por el administrador.',
        ]);

        return redirect()->back()->with('success', 'Alerta resuelta.');
    }

    public function repararFechaInicio(Request $request, Cliente $cliente)
    {
        if (!auth()->user()->hasRole('Super Admin') && !auth()->user()->can('cobranza.reparar_fecha')) {
            abort(403, 'No tienes permiso para reparar la fecha de inicio de crédito.');
        }

        $request->validate([
            'fecha_inicio_credito' => 'required|date',
        ]);

        $nuevaFecha = \Carbon\Carbon::parse($request->fecha_inicio_credito)->startOfDay();
        $viejaFecha = $cliente->fecha_inicio_credito ? \Carbon\Carbon::parse($cliente->fecha_inicio_credito)->toDateString() : 'N/A';

        $cliente->update(['fecha_inicio_credito' => $nuevaFecha]);

        $facturaActiva = \App\Models\CobranzaFactura::where('cliente_id', $cliente->id)
            ->where('pagada', false)
            ->first();

        if ($facturaActiva) {
            $diasCredito = ($cliente->dias_credito > 0) ? $cliente->dias_credito : 30;
            $vencimientoCalculado = $nuevaFecha->copy()->addDays($diasCredito)->startOfDay();
            $facturaActiva->update([
                'fecha_vencimiento' => $vencimientoCalculado
            ]);

            $hoy = now()->startOfDay();
            $diasAtraso = $vencimientoCalculado->diffInDays($hoy, false);

            if ($diasAtraso >= 1) {
                $alertaPendiente = \App\Models\CobranzaAlerta::where('factura_id', $facturaActiva->id)
                    ->where('estado', 'pendiente')
                    ->first();

                if ($alertaPendiente) {
                    $alertaPendiente->update(['dias_atraso' => $diasAtraso, 'fecha_alerta' => now()->toDateString()]);
                } else {
                    \App\Models\CobranzaAlerta::create([
                        'cliente_id' => $cliente->id,
                        'factura_id' => $facturaActiva->id,
                        'dias_atraso' => $diasAtraso,
                        'fecha_alerta' => now()->toDateString(),
                        'estado' => 'pendiente',
                    ]);
                }
            } else {
                \App\Models\CobranzaAlerta::where('factura_id', $facturaActiva->id)
                    ->where('estado', 'pendiente')
                    ->delete();
            }
        }

        \App\Models\CobranzaBitacora::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => auth()->id() ?? 1,
            'tipo_evento' => 'inicio_credito', // Usamos un evento existente para evitar errores
            'monto_anterior' => 0,
            'monto_nuevo' => 0,
            'descripcion' => "Reparación manual de fecha de inicio de crédito. Anterior: $viejaFecha. Nueva: {$nuevaFecha->toDateString()}",
        ]);

        return redirect()->back()->with('success', 'Fecha de inicio de crédito reparada con éxito. Los cálculos han sido actualizados.');
    }
}

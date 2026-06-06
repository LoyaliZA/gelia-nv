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

        // 2. Alertas operativas pendientes para hoy (días 3, 6, 9 y 12)
        $alertas = CobranzaAlerta::with(['cliente', 'factura'])
            ->where('estado', 'pendiente')
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

        return Inertia::render('AutoCobranza/Index', [
            'clientes' => $clientes,
            'alertas' => $alertas,
            'cartera' => $cartera,
            'filtros' => $request->only(['page', 'q', 'orden']),
        ]);
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

        match ($request->input('orden', 'saldo_desc')) {
            'saldo_asc'          => $query->orderBy($saldoSubquery),
            'nombre_asc'         => $query->orderBy('nombre'),
            'nombre_desc'        => $query->orderByDesc('nombre'),
            'fecha_credito_desc' => $query->orderByDesc('fecha_inicio_credito'),
            'numero_asc'         => $query->ordenarPorNumeroCliente('asc'),
            default              => $query->orderByDesc($saldoSubquery),
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
}

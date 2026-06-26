<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Pedido;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ObtenerDashboardContabilidadService
{
    public function ejecutar(Request $request): array
    {
        $filtro = $request->input('filtro', 'mes');
        $query = Pedido::query()->with(['plataformaPago', 'lineas', 'tipoTransaccion']);

        if ($filtro === 'mes') {
            $query->whereMonth('fecha_salida', $request->input('mes', now()->month))
                ->whereYear('fecha_salida', $request->input('anio', now()->year));
        } elseif ($filtro === 'dia') {
            $query->whereDate('fecha_salida', $request->input('fecha', now()->toDateString()));
        } elseif ($filtro === 'anio') {
            $query->whereYear('fecha_salida', $request->input('anio', now()->year));
        } elseif ($filtro === 'custom') {
            $query->whereBetween('fecha_salida', [
                $request->input('inicio'),
                $request->input('fin'),
            ]);
        }

        $pedidos = $query->orderBy('fecha_salida')->get();

        $ventas = (float) $pedidos->sum('venta_total');
        $comisiones = (float) $pedidos->sum('comision_plataforma');
        $ganancias = (float) $pedidos->where('utilidad_total', '>=', 0)->sum('utilidad_total');
        $perdidas = (float) $pedidos->where('utilidad_total', '<', 0)->sum('utilidad_total');
        $enviosEmpresa = (float) $pedidos->where('envio_pagado_cliente', false)->sum('costo_envio');

        $notasAe = 0.0;
        foreach ($pedidos as $pedido) {
            $notasAe += $pedido->calcularCostoProductos();
        }

        $utilidadNeta = (float) $pedidos->sum('utilidad_total');
        $margen = $ventas > 0 ? ($utilidadNeta / $ventas) * 100 : 0;

        $comisionesPlataforma = $pedidos
            ->groupBy(fn ($p) => $p->plataformaPago?->nombre ?? 'Sin plataforma')
            ->map(fn ($grupo) => round((float) $grupo->sum('comision_plataforma'), 2));

        $grafica = $pedidos
            ->groupBy(fn ($p) => Carbon::parse($p->fecha_salida)->format('d/m/Y'))
            ->map(fn ($grupo) => [
                'venta' => round((float) $grupo->sum('venta_total'), 2),
                'utilidad' => round((float) $grupo->sum('utilidad_total'), 2),
            ]);

        return [
            'kpis' => [
                'ventas' => round($ventas, 2),
                'comisiones' => round($comisiones, 2),
                'ganancias' => round($ganancias, 2),
                'perdidas' => round($perdidas, 2),
                'enviosEmpresa' => round($enviosEmpresa, 2),
                'notasAE' => round($notasAe, 2),
                'margen' => round($margen, 2),
                'enviosClientesCount' => $pedidos->where('envio_pagado_cliente', true)->count(),
                'enviosClientesMonto' => round((float) $pedidos->where('envio_pagado_cliente', true)->sum('costo_envio'), 2),
            ],
            'plataformas' => $comisionesPlataforma,
            'grafica' => $grafica,
        ];
    }
}

<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Pedido;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarReporteContabilidadService
{
    /**
     * Obtiene todos los pedidos según los filtros aplicados sin paginar.
     */
    public function obtenerPedidos(array $filtros): Collection
    {
        $mes = (int) ($filtros['mes'] ?? now()->month);
        $anio = (int) ($filtros['anio'] ?? now()->year);
        $busqueda = isset($filtros['q']) ? trim((string) $filtros['q']) : '';
        $plataformaId = isset($filtros['plataforma_id']) && $filtros['plataforma_id'] !== ''
            ? (int) $filtros['plataforma_id']
            : null;
        $estatusId = isset($filtros['estatus_pago_id']) && $filtros['estatus_pago_id'] !== ''
            ? (int) $filtros['estatus_pago_id']
            : null;
        $tipoId = isset($filtros['tipo_transaccion_id']) && $filtros['tipo_transaccion_id'] !== ''
            ? (int) $filtros['tipo_transaccion_id']
            : null;

        $query = Pedido::query()
            ->with(['plataformaPago', 'estatusPago', 'tipoTransaccion', 'lineas'])
            ->whereMonth('fecha_salida', $mes)
            ->whereYear('fecha_salida', $anio)
            ->orderByDesc('fecha_salida')
            ->orderByDesc('id');

        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('numero_pedido', 'like', "%{$busqueda}%")
                    ->orWhere('cliente_nombre', 'like', "%{$busqueda}%");
            });
        }

        if ($plataformaId) {
            $query->where('plataforma_pago_id', $plataformaId);
        }

        if ($estatusId) {
            $query->where('estatus_pago_id', $estatusId);
        }

        if ($tipoId) {
            $query->where('tipo_transaccion_id', $tipoId);
        }

        return $query->get();
    }

    /**
     * Mapea un pedido a las filas de exportación CSV/Excel.
     */
    public function filas(Collection $pedidos): array
    {
        return $pedidos->map(function ($pedido) {
            $productosString = $pedido->lineas->map(function ($linea) {
                $estado = '';
                if ($linea->tipo_devolucion === 'devuelto') {
                    $estado = ' [Devuelto]';
                } elseif ($linea->tipo_devolucion === 'perdido_danado') {
                    $estado = ' [Perdido/Dañado]';
                }
                return sprintf('%s: %s (%d pzas)%s', $linea->sku, $linea->nombre_producto, $linea->piezas, $estado);
            })->implode('; ');

            return [
                'Fecha' => $pedido->fecha_salida ? $pedido->fecha_salida->format('Y-m-d') : '',
                'Pedido #' => $pedido->numero_pedido,
                'Cliente' => $pedido->cliente_nombre ?? 'N/A',
                'Plataforma' => $pedido->plataformaPago?->nombre ?? 'N/A',
                'Tipo' => $pedido->tipoTransaccion?->nombre ?? 'N/A',
                'Estatus Pago' => $pedido->estatusPago?->nombre ?? 'N/A',
                'Venta Total ($)' => (float) $pedido->venta_total,
                'Costo Envío ($)' => (float) $pedido->costo_envio,
                'Envío Pagado por Cliente' => $pedido->envio_pagado_cliente ? 'Sí' : 'No',
                'Comisión Plataforma ($)' => (float) $pedido->comision_plataforma,
                'Utilidad Total ($)' => (float) $pedido->utilidad_total,
                'Productos' => $productosString,
            ];
        })->all();
    }

    /**
     * Descarga el reporte en formato CSV.
     */
    public function descargarCsv(array $filtros): BinaryFileResponse|StreamedResponse
    {
        $pedidos = $this->obtenerPedidos($filtros);
        $nombreArchivo = 'reporte_contabilidad_' . now()->format('Y-m-d_His') . '.csv';

        return (new FastExcel($this->filas($pedidos)))->download($nombreArchivo);
    }

    /**
     * Descarga el reporte en formato PDF.
     */
    public function descargarPdf(array $filtros)
    {
        $pedidos = $this->obtenerPedidos($filtros);

        $ventas = (float) $pedidos->sum('venta_total');
        $comisiones = (float) $pedidos->sum('comision_plataforma');
        $utilidadNeta = (float) $pedidos->sum('utilidad_total');

        $mesesNombres = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $filtroMes = isset($filtros['mes']) ? $mesesNombres[(int)$filtros['mes']] : $mesesNombres[(int)now()->month];
        $filtroAnio = $filtros['anio'] ?? now()->year;

        $pdf = Pdf::loadView('reportes.contabilidad', [
            'titulo' => 'Reporte de Pedidos - Contabilidad Bellaroma',
            'fecha' => now()->format('Y-m-d H:i'),
            'pedidos' => $pedidos,
            'kpis' => [
                'total' => $pedidos->count(),
                'ventas' => $ventas,
                'comisiones' => $comisiones,
                'utilidad' => $utilidadNeta,
            ],
            'periodo' => $filtroMes . ' ' . $filtroAnio,
        ])->setPaper('a4', 'landscape');

        $nombreArchivo = 'reporte_contabilidad_' . now()->format('Y-m-d_His') . '.pdf';

        return $pdf->download($nombreArchivo);
    }
}

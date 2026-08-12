<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Producto;
use App\Models\ProductoVentaAlmacen;
use App\Services\Almacenes\IniciarImportacionAlmacenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteVentasController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ProductoVentaAlmacen::query()
            ->with(['producto:id,sku,descripcion', 'almacen:id,codigo,nombre']);

        if ($q = trim((string) $request->query('q', ''))) {
            $query->whereHas('producto', fn ($p) => $p->buscarPorTexto($q));
        }
        if ($almacenId = $request->query('almacen_id')) {
            $query->where('almacen_id', (int) $almacenId);
        }
        if ($periodo = trim((string) $request->query('periodo', ''))) {
            $query->where('periodo', $periodo);
        }

        $query->orderByDesc('periodo')->orderByDesc('monto_venta');

        return Inertia::render('Reportes/Ventas/Index', [
            'ventas' => $query->paginate(50)->withQueryString(),
            'almacenes' => Almacen::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'filtros' => $request->only(['q', 'almacen_id', 'periodo']),
            'total_monto' => (clone $query)->sum('monto_venta'),
        ]);
    }

    public function descargarPlantilla(): StreamedResponse
    {
        $headers = ['sku', 'codigo_almacen', 'periodo', 'monto_venta', 'cantidad_vendida'];
        $rows = [
            ['SKU-EJEMPLO', 'PDV01', date('Y-m'), '1500.00', '12'],
        ];

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'plantilla_ventas_producto.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function importPreview(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls',
        ]);

        $file = $request->file('archivo');
        $extension = $file->getClientOriginalExtension();
        $path = $file->storeAs('temp', 'import_ventas_preview.'.$extension);

        $headers = [];
        $rows = (new FastExcel)->import(Storage::path($path));
        foreach ($rows as $row) {
            $headers = array_keys($row);
            break;
        }

        return response()->json([
            'headers' => $headers,
            'file_path' => $path,
        ]);
    }

    public function importIniciar(Request $request, IniciarImportacionAlmacenService $iniciar): JsonResponse
    {
        $resultado = $iniciar->ejecutar($request, 'ventas');

        return response()->json(array_merge(['success' => true], $resultado));
    }
}

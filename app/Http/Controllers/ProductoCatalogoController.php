<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\CatalogoCategoriaProducto;
use App\Models\Producto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProductoCatalogoController extends Controller
{
    public function index(Request $request)
    {
        $almacenId = $request->query('almacen_id');
        $query = Producto::with(['almacen', 'categoria'])->orderBy('id', 'desc');

        if ($almacenId) {
            $query->where('almacen_id', $almacenId);
        }

        $productos = $query->paginate(50)->withQueryString();
        $almacenes = Almacen::all();

        return Inertia::render('Productos/Index', [
            'productos' => $productos,
            'almacenes' => $almacenes,
            'filtros' => request()->only(['almacen_id'])
        ]);
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls',
            'almacen_id' => 'required|exists:almacenes,id',
        ]);

        $file = $request->file('archivo');
        $path = $file->storeAs('temp', 'import_preview.' . $file->getClientOriginalExtension());
        $fullPath = Storage::path($path);

        $headers = [];
        $rows = (new FastExcel)->import($fullPath);
        foreach ($rows as $row) {
            $headers = array_keys($row);
            break;
        }

        return response()->json([
            'headers' => $headers,
            'file_path' => $path,
            'almacen_id' => $request->almacen_id,
        ]);
    }

    public function importProcess(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'almacen_id' => 'required|exists:almacenes,id',
            'mapping' => 'required|array',
            'mapping.sku' => 'required|string',
            'mapping.descripcion' => 'required|string',
            'mapping.categoria' => 'required|string',
            'mapping.existencia' => 'required|string',
            'mapping.costo' => 'required|string',
            'mapping.precio_venta' => 'required|string',
        ]);

        $fullPath = Storage::path($request->file_path);
        if (!file_exists($fullPath)) {
            return back()->with('error', 'Archivo temporal no encontrado.');
        }

        $mapping = $request->mapping;
        $almacenId = $request->almacen_id;

        DB::transaction(function () use ($almacenId, $fullPath, $mapping) {
            // Carga Limpia (Desactivación masiva)
            Producto::where('almacen_id', $almacenId)->update(['activo' => false]);

            // Cache de categorías para evitar consultas masivas
            $categoriasCache = CatalogoCategoriaProducto::pluck('id', 'nombre')->toArray();

            $rows = (new FastExcel)->import($fullPath);

            foreach ($rows as $row) {
                $sku = $row[$mapping['sku']] ?? null;
                $descripcion = $row[$mapping['descripcion']] ?? null;
                $categoriaNombre = $row[$mapping['categoria']] ?? 'Sin Categoría';
                $existencia = $row[$mapping['existencia']] ?? 0;
                $costo = $row[$mapping['costo']] ?? 0;
                $precioVenta = $row[$mapping['precio_venta']] ?? 0;

                if (empty($sku)) continue;

                // Resolver categoría
                if (!isset($categoriasCache[$categoriaNombre])) {
                    $cat = CatalogoCategoriaProducto::create(['nombre' => $categoriaNombre]);
                    $categoriasCache[$categoriaNombre] = $cat->id;
                }
                $categoriaId = $categoriasCache[$categoriaNombre];

                // Upsert Producto (actualizar o crear por SKU y Almacén)
                Producto::updateOrCreate(
                    [
                        'sku' => $sku,
                        'almacen_id' => $almacenId
                    ],
                    [
                        'categoria_id' => $categoriaId,
                        'descripcion' => $descripcion,
                        'existencia' => (int) $existencia,
                        'costo' => (float) $costo,
                        'precio_venta' => (float) $precioVenta,
                        'activo' => true
                    ]
                );
            }
        });

        // Limpiar archivo temporal
        @unlink($fullPath);

        return redirect()->route('admin.catalogo-maestro.index')
            ->with('success', 'Catálogo Maestro importado y sincronizado correctamente.');
    }
}

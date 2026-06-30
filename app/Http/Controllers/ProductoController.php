<?php

namespace App\Http\Controllers;

use App\Http\Requests\Productos\ImportarProductosRequest;
use App\Http\Requests\Productos\StoreProductoRequest;
use App\Http\Requests\Productos\UpdateProductoRequest;
use App\Models\Producto;
use App\Services\Productos\GenerarFolioProductoService;
use App\Services\Productos\ImportarProductosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ProductoController extends Controller
{
    public function store(
        StoreProductoRequest $request,
        GenerarFolioProductoService $generarFolio,
    ): RedirectResponse {
        Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $generarFolio->ejecutar(),
            'sku' => $request->sku,
            'descripcion' => trim($request->descripcion),
            'activo' => $request->boolean('activo', true),
        ]);

        return back()->with('success', 'Producto registrado correctamente.');
    }

    public function update(UpdateProductoRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update([
            'sku' => $request->sku,
            'descripcion' => trim($request->descripcion),
            'activo' => $request->boolean('activo', true),
        ]);

        return back()->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->delete();

        return back()->with('success', 'Producto eliminado correctamente.');
    }

    public function importar(
        ImportarProductosRequest $request,
        ImportarProductosService $importarService,
    ): RedirectResponse {
        $resultado = $importarService->ejecutar($request->file('archivo'));

        $mensaje = "Importación completada: {$resultado['creados']} creados, {$resultado['actualizados']} actualizados.";
        if (!empty($resultado['errores'])) {
            $mensaje .= ' Errores: ' . implode(' ', array_slice($resultado['errores'], 0, 5));
        }

        return back()->with(
            empty($resultado['errores']) ? 'success' : 'warning',
            $mensaje,
        );
    }
}

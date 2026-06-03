<?php

namespace App\Http\Controllers\Activos;

use App\Http\Controllers\Controller;
use App\Models\CatalogoCategoriaActivo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriaActivoController extends Controller
{
    public function store(Request $request)
    {
        $datos = $this->validarRequest($request);
        $datos['slug'] = Str::slug($datos['nombre']);
        CatalogoCategoriaActivo::create($datos);

        return back()->with('success', 'Categoría de activo creada correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $categoria = CatalogoCategoriaActivo::findOrFail($id);
        $datos = $this->validarRequest($request);
        $datos['slug'] = Str::slug($datos['nombre']);
        $categoria->update($datos);

        return back()->with('success', 'Categoría de activo actualizada.');
    }

    public function destroy(int $id)
    {
        $categoria = CatalogoCategoriaActivo::findOrFail($id);

        if ($categoria->activos()->exists()) {
            return back()->with('error', 'No se puede eliminar una categoría con activos registrados.');
        }

        $categoria->delete();

        return back()->with('success', 'Categoría de activo eliminada.');
    }

    private function validarRequest(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);
    }
}

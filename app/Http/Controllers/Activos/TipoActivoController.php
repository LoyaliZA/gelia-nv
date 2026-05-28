<?php

namespace App\Http\Controllers\Activos;

use App\Http\Controllers\Controller;
use App\Models\CatalogoTipoActivo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TipoActivoController extends Controller
{
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria' => 'required|in:fisico,tecnologico,intangible,vestimenta',
            'icono' => 'nullable|string|max:50',
            'esquema_atributos' => 'nullable|array',
            'activo' => 'boolean',
        ]);

        $datos['slug'] = Str::slug($datos['nombre']);
        CatalogoTipoActivo::create($datos);

        return back()->with('success', 'Tipo de activo creado correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $tipo = CatalogoTipoActivo::findOrFail($id);

        $datos = $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria' => 'required|in:fisico,tecnologico,intangible,vestimenta',
            'icono' => 'nullable|string|max:50',
            'esquema_atributos' => 'nullable|array',
            'activo' => 'boolean',
        ]);

        $datos['slug'] = Str::slug($datos['nombre']);
        $tipo->update($datos);

        return back()->with('success', 'Tipo de activo actualizado.');
    }

    public function destroy(int $id)
    {
        $tipo = CatalogoTipoActivo::findOrFail($id);

        if ($tipo->activos()->exists()) {
            return back()->with('error', 'No se puede eliminar un tipo con activos registrados.');
        }

        $tipo->delete();

        return back()->with('success', 'Tipo de activo eliminado.');
    }
}

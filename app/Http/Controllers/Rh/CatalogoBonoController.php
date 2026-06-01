<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreBonoRequest;
use App\Http\Requests\Rh\UpdateBonoRequest;
use App\Models\CatalogoBono;
use Illuminate\Http\RedirectResponse;

class CatalogoBonoController extends Controller
{
    public function store(StoreBonoRequest $request): RedirectResponse
    {
        CatalogoBono::create([
            'nombre' => trim($request->nombre),
            'codigo' => $request->filled('codigo') ? trim($request->codigo) : null,
            'activo' => $request->boolean('activo', true),
        ]);

        return back()->with('success', 'Bono creado correctamente.');
    }

    public function update(UpdateBonoRequest $request, CatalogoBono $bono): RedirectResponse
    {
        $bono->update([
            'nombre' => trim($request->nombre),
            'codigo' => $request->filled('codigo') ? trim($request->codigo) : null,
            'activo' => $request->boolean('activo', true),
        ]);

        return back()->with('success', 'Bono actualizado correctamente.');
    }

    public function destroy(CatalogoBono $bono): RedirectResponse
    {
        if ($bono->reglasIncidencia()->exists()) {
            return back()->with('error', 'No se puede eliminar un bono vinculado a reglas de incidencia.');
        }

        $bono->delete();

        return back()->with('success', 'Bono eliminado correctamente.');
    }
}

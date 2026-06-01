<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StorePuestoRequest;
use App\Http\Requests\Rh\UpdatePuestoRequest;
use App\Models\CatalogoPuesto;
use Illuminate\Http\RedirectResponse;

class CatalogoPuestoController extends Controller
{
    public function store(StorePuestoRequest $request): RedirectResponse
    {
        $puesto = CatalogoPuesto::create([
            'nombre' => trim($request->nombre),
            'activo' => $request->boolean('activo', true),
        ]);

        $puesto->bonos()->sync($request->input('bono_ids', []));

        return back()->with('success', 'Puesto creado correctamente.');
    }

    public function update(UpdatePuestoRequest $request, CatalogoPuesto $puesto): RedirectResponse
    {
        $puesto->update([
            'nombre' => trim($request->nombre),
            'activo' => $request->boolean('activo', true),
        ]);

        $puesto->bonos()->sync($request->input('bono_ids', []));

        return back()->with('success', 'Puesto actualizado correctamente.');
    }

    public function destroy(CatalogoPuesto $puesto): RedirectResponse
    {
        if ($puesto->colaboradores()->exists()) {
            return back()->with('error', 'No se puede eliminar un puesto asignado a colaboradores.');
        }

        $puesto->delete();

        return back()->with('success', 'Puesto eliminado correctamente.');
    }
}

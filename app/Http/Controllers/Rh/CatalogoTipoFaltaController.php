<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreTipoFaltaRequest;
use App\Http\Requests\Rh\UpdateTipoFaltaRequest;
use App\Models\CatalogoTipoFalta;
use Illuminate\Http\RedirectResponse;

class CatalogoTipoFaltaController extends Controller
{
    public function store(StoreTipoFaltaRequest $request): RedirectResponse
    {
        CatalogoTipoFalta::create([
            'nombre' => trim($request->nombre),
            'factor_penalizacion_puntualidad' => $request->factor_penalizacion_puntualidad,
            'factor_penalizacion_productividad' => $request->factor_penalizacion_productividad,
            'aplica_deduccion_salario_base' => $request->boolean('aplica_deduccion_salario_base'),
            'activo' => $request->boolean('activo', true),
        ]);

        return back()->with('success', 'Tipo de falta creado correctamente.');
    }

    public function update(UpdateTipoFaltaRequest $request, CatalogoTipoFalta $tipoFalta): RedirectResponse
    {
        $tipoFalta->update([
            'nombre' => trim($request->nombre),
            'factor_penalizacion_puntualidad' => $request->factor_penalizacion_puntualidad,
            'factor_penalizacion_productividad' => $request->factor_penalizacion_productividad,
            'aplica_deduccion_salario_base' => $request->boolean('aplica_deduccion_salario_base'),
            'activo' => $request->boolean('activo', true),
        ]);

        return back()->with('success', 'Tipo de falta actualizado correctamente.');
    }

    public function destroy(CatalogoTipoFalta $tipoFalta): RedirectResponse
    {
        if ($tipoFalta->incidencias()->exists()) {
            return back()->with('error', 'No se puede eliminar un tipo de falta con incidencias registradas.');
        }

        $tipoFalta->delete();

        return back()->with('success', 'Tipo de falta eliminado correctamente.');
    }
}

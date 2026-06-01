<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\StoreReglaIncidenciaRequest;
use App\Http\Requests\Rh\UpdateReglaIncidenciaRequest;
use App\Models\CatalogoReglaIncidencia;
use App\Services\Rh\GenerarFolioReglaIncidenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CatalogoReglaIncidenciaController extends Controller
{
    public function store(
        StoreReglaIncidenciaRequest $request,
        GenerarFolioReglaIncidenciaService $generarFolio,
    ): RedirectResponse {
        $regla = CatalogoReglaIncidencia::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $generarFolio->ejecutar(),
            'nombre' => trim($request->nombre),
            'tipo_comportamiento' => $request->tipo_comportamiento,
            'monto_fijo' => $request->input('monto_fijo'),
            'catalogo_bono_id' => $request->input('catalogo_bono_id'),
            'activo' => $request->boolean('activo', true),
        ]);

        $this->syncRelaciones($regla, $request->validated());

        return back()->with('success', 'Regla de incidencia creada correctamente.');
    }

    public function update(UpdateReglaIncidenciaRequest $request, CatalogoReglaIncidencia $reglaIncidencia): RedirectResponse
    {
        $reglaIncidencia->update([
            'nombre' => trim($request->nombre),
            'tipo_comportamiento' => $request->tipo_comportamiento,
            'monto_fijo' => $request->input('monto_fijo'),
            'catalogo_bono_id' => $request->input('catalogo_bono_id'),
            'activo' => $request->boolean('activo', true),
        ]);

        $this->syncRelaciones($reglaIncidencia, $request->validated());

        return back()->with('success', 'Regla de incidencia actualizada correctamente.');
    }

    public function destroy(CatalogoReglaIncidencia $reglaIncidencia): RedirectResponse
    {
        $reglaIncidencia->delete();

        return back()->with('success', 'Regla de incidencia eliminada correctamente.');
    }

    private function syncRelaciones(CatalogoReglaIncidencia $regla, array $datos): void
    {
        $regla->departamentosAplicables()->sync($datos['departamentos_aplicables'] ?? []);
        $regla->areasAplicables()->sync($datos['areas_aplicables'] ?? []);
        $regla->departamentosVisibilidad()->sync($datos['departamentos_visibilidad'] ?? []);
        $regla->areasVisibilidad()->sync($datos['areas_visibilidad'] ?? []);
    }
}

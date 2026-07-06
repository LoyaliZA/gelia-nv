<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\CatalogoTurno;
use App\Support\MatrizHorarioTurno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CatalogoTurnoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_turnos,nombre',
            'activo' => 'required|boolean',
            'matriz_horario' => 'required|array',
        ]);

        $datos['matriz_horario'] = MatrizHorarioTurno::normalizar($datos['matriz_horario']);

        CatalogoTurno::create($datos);

        return back()->with('success', 'Turno creado correctamente.');
    }

    public function update(Request $request, CatalogoTurno $turno): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_turnos,nombre,' . $turno->id,
            'activo' => 'required|boolean',
            'matriz_horario' => 'required|array',
        ]);

        $datos['matriz_horario'] = MatrizHorarioTurno::normalizar($datos['matriz_horario']);

        $turno->update($datos);

        return back()->with('success', 'Turno actualizado correctamente.');
    }

    public function destroy(CatalogoTurno $turno): RedirectResponse
    {
        if ($turno->colaboradores()->exists()) {
            return back()->with('error', 'No se puede eliminar el turno porque hay colaboradores asignados a él.');
        }

        $turno->delete();

        return back()->with('success', 'Turno eliminado correctamente.');
    }
}

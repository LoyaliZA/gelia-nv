<?php

namespace App\Http\Controllers\Soporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SoporteConfiguracion;
use App\Models\SoporteCatalogoModulo;
use App\Models\SoporteCatalogoCategoria;
use App\Models\SoporteCatalogoPrioridad;
use App\Models\SoporteCatalogoEstado;

class SoporteConfiguracionController extends Controller
{
    public function index()
    {
        $configuracion = SoporteConfiguracion::first() ?? SoporteConfiguracion::create();
        
        return Inertia::render('Admin/SoporteConfiguracion/Index', [
            'configuracion' => $configuracion,
            'modulos' => SoporteCatalogoModulo::all(),
            'categorias' => SoporteCatalogoCategoria::all(),
            'prioridades' => SoporteCatalogoPrioridad::all(),
            'estados' => SoporteCatalogoEstado::all(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'horario_inicio' => 'required|date_format:H:i',
            'horario_fin' => 'required|date_format:H:i|after:horario_inicio',
            'mensaje_fuera_horario' => 'nullable|string',
            'hora_notificacion_diaria' => 'required|date_format:H:i',
            'modo_pruebas' => 'boolean',
        ]);

        $configuracion = SoporteConfiguracion::first() ?? new SoporteConfiguracion();
        $configuracion->fill($validated);
        $configuracion->save();

        return redirect()->back()->with('success', 'Configuración de Soporte actualizada correctamente.');
    }

    public function storeCatalogo(Request $request, $tipo)
    {
        $modelo = $this->obtenerModeloCatalogo($tipo);
        if (!$modelo) {
            abort(404);
        }

        $rules = ['nombre' => 'required|string|max:255', 'activo' => 'boolean'];
        
        if ($tipo === 'modulos') {
            $rules['permiso_requerido'] = 'nullable|string|max:255';
        }
        if ($tipo === 'prioridades') {
            $rules['tiempo_respuesta_horas'] = 'required|integer|min:1';
        }
        if ($tipo === 'estados') {
            $rules['color'] = 'required|string|max:20';
        }

        $validated = $request->validate($rules);
        
        $modelo::create($validated);

        return redirect()->back()->with('success', 'Registro creado exitosamente.');
    }

    public function updateCatalogo(Request $request, $tipo, $id)
    {
        $modeloClass = $this->obtenerModeloCatalogo($tipo);
        if (!$modeloClass) {
            abort(404);
        }

        $modelo = $modeloClass::findOrFail($id);

        $rules = ['nombre' => 'required|string|max:255', 'activo' => 'boolean'];
        
        if ($tipo === 'modulos') {
            $rules['permiso_requerido'] = 'nullable|string|max:255';
        }
        if ($tipo === 'prioridades') {
            $rules['tiempo_respuesta_horas'] = 'required|integer|min:1';
        }
        if ($tipo === 'estados') {
            $rules['color'] = 'required|string|max:20';
        }

        $validated = $request->validate($rules);
        $modelo->update($validated);

        return redirect()->back()->with('success', 'Registro actualizado exitosamente.');
    }

    private function obtenerModeloCatalogo($tipo)
    {
        switch ($tipo) {
            case 'modulos': return SoporteCatalogoModulo::class;
            case 'categorias': return SoporteCatalogoCategoria::class;
            case 'prioridades': return SoporteCatalogoPrioridad::class;
            case 'estados': return SoporteCatalogoEstado::class;
            default: return null;
        }
    }
}

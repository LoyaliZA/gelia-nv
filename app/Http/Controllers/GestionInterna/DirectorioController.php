<?php

namespace App\Http\Controllers\GestionInterna;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DirectorioCorreo;
use App\Models\DirectorioTelefono;
use App\Models\DirectorioExtension;
use App\Models\RhColaborador;
use App\Models\User;
use App\Models\Area;
use Illuminate\Support\Facades\Gate;

class DirectorioController extends Controller
{
    public function index()
    {
        Gate::authorize('gestion_interna.directorio.ver');

        $correos = DirectorioCorreo::with(['colaborador:id,nombre,apellido_paterno', 'usuario:id,name'])->latest()->get();
        $telefonos = DirectorioTelefono::with(['colaborador:id,nombre,apellido_paterno', 'usuario:id,name'])->latest()->get();
        $extensiones = DirectorioExtension::with(['area:id,nombre', 'encargado:id,nombre,apellido_paterno'])->latest()->get();

        $colaboradores = RhColaborador::select('id', 'nombre', 'apellido_paterno', 'apellido_materno')->where('activo', true)->get();
        $usuarios = User::select('id', 'name')->get();
        $areas = Area::with('departamento:id,nombre')->select('id', 'nombre', 'departamento_id')->get();

        return Inertia::render('GestionInterna/Directorio/Index', [
            'correos' => $correos,
            'telefonos' => $telefonos,
            'extensiones' => $extensiones,
            'colaboradores' => $colaboradores,
            'usuarios' => $usuarios,
            'areas' => $areas,
        ]);
    }

    public function storeCorreo(Request $request)
    {
        Gate::authorize('gestion_interna.directorio.gestionar');
        $validated = $request->validate([
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id',
            'user_id' => 'nullable|exists:users,id',
            'email' => 'required|email|max:255',
        ]);
        if (empty($validated['rh_colaborador_id']) && empty($validated['user_id'])) {
            return back()->withErrors(['general' => 'Debe seleccionar un colaborador o usuario.']);
        }
        DirectorioCorreo::create($validated);
        return back()->with('success', 'Correo agregado exitosamente.');
    }

    public function updateCorreo(Request $request, $id)
    {
        Gate::authorize('gestion_interna.directorio.gestionar');
        $validated = $request->validate([
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id',
            'user_id' => 'nullable|exists:users,id',
            'email' => 'required|email|max:255',
        ]);
        if (empty($validated['rh_colaborador_id']) && empty($validated['user_id'])) {
            return back()->withErrors(['general' => 'Debe seleccionar un colaborador o usuario.']);
        }
        DirectorioCorreo::findOrFail($id)->update($validated);
        return back()->with('success', 'Correo actualizado exitosamente.');
    }

    public function destroyCorreo($id)
    {
        Gate::authorize('gestion_interna.directorio.gestionar');
        DirectorioCorreo::findOrFail($id)->delete();
        return back()->with('success', 'Correo eliminado.');
    }

    public function storeTelefono(Request $request)
    {
        Gate::authorize('gestion_interna.directorio.gestionar');
        $validated = $request->validate([
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id',
            'user_id' => 'nullable|exists:users,id',
            'telefono' => 'required|string|max:50',
        ]);
        if (empty($validated['rh_colaborador_id']) && empty($validated['user_id'])) {
            return back()->withErrors(['general' => 'Debe seleccionar un colaborador o usuario.']);
        }
        DirectorioTelefono::create($validated);
        return back()->with('success', 'Teléfono agregado exitosamente.');
    }

    public function updateTelefono(Request $request, $id)
    {
        Gate::authorize('gestion_interna.directorio.gestionar');
        $validated = $request->validate([
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id',
            'user_id' => 'nullable|exists:users,id',
            'telefono' => 'required|string|max:50',
        ]);
        if (empty($validated['rh_colaborador_id']) && empty($validated['user_id'])) {
            return back()->withErrors(['general' => 'Debe seleccionar un colaborador o usuario.']);
        }
        DirectorioTelefono::findOrFail($id)->update($validated);
        return back()->with('success', 'Teléfono actualizado exitosamente.');
    }

    public function destroyTelefono($id)
    {
        Gate::authorize('gestion_interna.directorio.gestionar');
        DirectorioTelefono::findOrFail($id)->delete();
        return back()->with('success', 'Teléfono eliminado.');
    }

    public function storeExtension(Request $request)
    {
        Gate::authorize('gestion_interna.directorio.gestionar');
        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id', // encargado
            'extension' => 'required|string|max:20',
        ]);
        DirectorioExtension::create($validated);
        return back()->with('success', 'Extensión agregada exitosamente.');
    }

    public function updateExtension(Request $request, $id)
    {
        Gate::authorize('gestion_interna.directorio.gestionar');
        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
            'rh_colaborador_id' => 'nullable|exists:rh_colaboradores,id',
            'extension' => 'required|string|max:20',
        ]);
        DirectorioExtension::findOrFail($id)->update($validated);
        return back()->with('success', 'Extensión actualizada exitosamente.');
    }

    public function destroyExtension($id)
    {
        Gate::authorize('gestion_interna.directorio.gestionar');
        DirectorioExtension::findOrFail($id)->delete();
        return back()->with('success', 'Extensión eliminada.');
    }
}

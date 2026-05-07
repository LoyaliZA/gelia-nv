<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Departamento;
use App\Actions\Organizacion\CrearDepartamentoAction;

class OrganizacionController extends Controller
{
    // Muestra la vista de React
    public function index(): Response
    {
        // Cargamos los departamentos con sus áreas anidadas
        $departamentos = Departamento::with('areas')->get();
        
        return Inertia::render('Admin/Organizacion', [
            'departamentos' => $departamentos
        ]);
    }

    // Guarda usando el Action Pattern
    public function storeDepartamento(Request $request, CrearDepartamentoAction $crearDepartamento)
    {
        $datosValidados = $request->validate([
            'nombre' => 'required|string|unique:departamentos,nombre',
            'activo' => 'boolean'
        ]);

        // Delegamos la responsabilidad de negocio al Action
        $crearDepartamento->execute($datosValidados);

        return back()->with('success', 'Departamento creado exitosamente.');
    }
}
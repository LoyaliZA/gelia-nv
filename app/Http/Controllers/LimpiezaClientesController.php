<?php

namespace App\Http\Controllers;

use App\Services\Clientes\LimpiezaClientesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class LimpiezaClientesController extends Controller
{
    public function index()
    {
        Gate::authorize('funciones.limpieza_clientes');

        return Inertia::render('FuncionesOperativas/LimpiezaClientes');
    }

    public function procesar(Request $request, LimpiezaClientesService $service)
    {
        Gate::authorize('funciones.limpieza_clientes');

        $validator = Validator::make($request->all(), [
            'clientes' => 'required|file|mimes:csv,txt',
            'columnas_clientes' => 'nullable|string',
            'incluir_sin_id' => 'nullable|boolean',
            'orden_clientes' => 'nullable|string|in:id_asc,id_desc,nombre_asc,nombre_desc',
            'filtro_especial' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $columnasSeleccionadas = $request->input('columnas_clientes') 
            ? explode(',', $request->input('columnas_clientes')) 
            : ['ID', 'NOMBRE']; 
            
        $incluirSinId = $request->boolean('incluir_sin_id', true); 
        $orden = $request->input('orden_clientes');
        $filtroEspecial = $request->boolean('filtro_especial', false); 

        return $service->procesar($request->file('clientes'), $columnasSeleccionadas, $incluirSinId, $orden, $filtroEspecial);
    }
}

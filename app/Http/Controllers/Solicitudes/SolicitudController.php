<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solicitudes\StoreSolicitudRequest;
use App\Services\Solicitudes\CrearSolicitudService;
use App\Services\Solicitudes\ListarSolicitudesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SolicitudController extends Controller
{
    /**
     * Muestra el dashboard principal de solicitudes.
     */
    public function index(Request $request, ListarSolicitudesService $listarService): Response
    {
        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all());
        
        // Consultamos el catálogo dinámico
        $procesos = \App\Models\CatalogoProceso::where('activo', true)->get();

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => $solicitudes,
            'filtros' => $request->all(),
            'procesos' => $procesos, // Lo inyectamos a la vista
        ]);
    }

    /**
     * Almacena una nueva solicitud. (Código previamente creado)
     */
    public function store(StoreSolicitudRequest $request, CrearSolicitudService $crearSolicitudService): RedirectResponse
    {
        $datosValidados = $request->validated();
        $crearSolicitudService->ejecutar($datosValidados, Auth::id());

        return redirect()->back()->with('success', 'Solicitud creada correctamente.');
    }
}
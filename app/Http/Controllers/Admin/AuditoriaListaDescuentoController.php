<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ListarAuditoriasListasService;
use App\Models\CatalogoListaDescuento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaListaDescuentoController extends Controller
{
    /**
     * Despliega el panel de control de auditoría de catálogos.
     */
    public function index(Request $request, ListarAuditoriasListasService $servicio): Response
    {
        // Seguro arquitectónico: Bloquear accesos no autorizados
        Gate::authorize('configuracion.ver_auditoria');

        $auditorias = $servicio->ejecutar($request->all());
        $listas = CatalogoListaDescuento::orderBy('nombre')->get(['id', 'nombre']);

        return Inertia::render('Admin/Auditorias', [
            'auditorias' => $auditorias,
            'listas'     => $listas,
            'filtros'    => $request->all()
        ]);
    }
}
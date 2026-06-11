<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ListarAuditoriasListasService;
use App\Models\CatalogoListaDescuento;
use App\Models\AuditoriaConfiguracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaListaDescuentoController extends Controller
{
    /**
     * Despliega el panel de control de auditoría de catálogos y configuración.
     */
    public function index(Request $request, ListarAuditoriasListasService $servicio): Response
    {
        // Seguro arquitectónico: Bloquear accesos no autorizados
        Gate::authorize('configuracion.ver_auditoria');

        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $tab = $request->input('tab', 'catalogos');

        if ($tab === 'configuracion' && !$isSuperAdmin) {
            abort(403, 'No tienes permiso para ver esta sección.');
        }

        $auditorias = [];
        $auditoriasConfiguracion = [];
        $usuariosFiltro = [];

        if ($tab === 'catalogos') {
            $auditorias = $servicio->ejecutar($request->all());
        } elseif ($tab === 'configuracion') {
            $queryConfig = AuditoriaConfiguracion::with(['usuario', 'usuarioAfectado'])->latest();
            
            // Filtros básicos para config
            if ($request->filled('modulo')) {
                $queryConfig->where('modulo', $request->modulo);
            }
            if ($request->filled('accion')) {
                $queryConfig->where('accion', 'like', '%' . $request->accion . '%');
            }
            
            // Filtros de fecha
            if ($request->filled('fecha_inicio')) {
                $queryConfig->whereDate('created_at', '>=', $request->fecha_inicio);
            }
            if ($request->filled('fecha_fin')) {
                $queryConfig->whereDate('created_at', '<=', $request->fecha_fin);
            }
            
            // Filtros de usuario
            if ($request->filled('user_id')) {
                $queryConfig->where('user_id', $request->user_id);
            }
            if ($request->filled('target_user_id')) {
                $queryConfig->where('target_user_id', $request->target_user_id);
            }

            $auditoriasConfiguracion = $queryConfig->paginate(15)->withQueryString();
            
            // Obtener usuarios para los selectores
            $usuariosFiltro = \App\Models\User::orderBy('name')->get(['id', 'name', 'apellido_paterno']);
        }

        $listas = CatalogoListaDescuento::orderBy('nombre')->get(['id', 'nombre']);

        return Inertia::render('Admin/Auditorias', [
            'auditorias' => $auditorias,
            'auditoriasConfiguracion' => $auditoriasConfiguracion,
            'usuariosFiltro' => $usuariosFiltro,
            'listas'     => $listas,
            'filtros'    => $request->all(),
            'tabActivo'  => $tab,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }
}
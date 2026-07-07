<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ListarAuditoriasAccesosService;
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
     * Despliega el panel de control de auditoría de catálogos, configuración y accesos.
     */
    public function index(
        Request $request,
        ListarAuditoriasListasService $servicio,
        ListarAuditoriasAccesosService $accesosService
    ): Response {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $puedeVerAccesos = $user->can('sistema.auditorias.accesos.ver');
        $puedeVerCatalogos = $user->can('sistema.auditorias.ver');
        $tab = $request->input('tab');

        if (!$tab) {
            $tab = $puedeVerCatalogos ? 'catalogos' : 'accesos';
        }

        if ($tab === 'accesos') {
            Gate::authorize('sistema.auditorias.accesos.ver');
        } elseif ($tab === 'catalogos') {
            Gate::authorize('sistema.auditorias.ver');
        } else {
            Gate::authorize('sistema.auditorias.ver');
        }

        if ($tab === 'configuracion' && !$isSuperAdmin) {
            abort(403, 'No tienes permiso para ver esta sección.');
        }

        $auditorias = [];
        $auditoriasConfiguracion = [];
        $auditoriasAccesos = [];
        $resumenAccesos = ['sesiones_activas' => 0, 'promedio_duracion_segundos' => 0];
        $usuariosFiltro = [];

        if ($tab === 'catalogos') {
            $auditorias = $servicio->ejecutar($request->all());
        } elseif ($tab === 'configuracion') {
            $queryConfig = AuditoriaConfiguracion::with(['usuario', 'usuarioAfectado'])->latest();

            if ($request->filled('modulo')) {
                $queryConfig->where('modulo', $request->modulo);
            }
            if ($request->filled('accion')) {
                $queryConfig->where('accion', 'like', '%' . $request->accion . '%');
            }
            if ($request->filled('fecha_inicio')) {
                $queryConfig->whereDate('created_at', '>=', $request->fecha_inicio);
            }
            if ($request->filled('fecha_fin')) {
                $queryConfig->whereDate('created_at', '<=', $request->fecha_fin);
            }
            if ($request->filled('user_id')) {
                $queryConfig->where('user_id', $request->user_id);
            }
            if ($request->filled('target_user_id')) {
                $queryConfig->where('target_user_id', $request->target_user_id);
            }

            $auditoriasConfiguracion = $queryConfig->paginate(15)->withQueryString();
            $usuariosFiltro = \App\Models\User::orderBy('name')->get(['id', 'name', 'apellido_paterno']);
        } elseif ($tab === 'accesos') {
            $auditoriasAccesos = $accesosService->ejecutar($request->all());
            $resumenAccesos = $accesosService->resumen();
            $usuariosFiltro = \App\Models\User::orderBy('name')->get(['id', 'name', 'apellido_paterno']);
        }

        $listas = CatalogoListaDescuento::orderBy('nombre')->get(['id', 'nombre']);

        return Inertia::render('Admin/Auditorias', [
            'auditorias'              => $auditorias,
            'auditoriasConfiguracion' => $auditoriasConfiguracion,
            'auditoriasAccesos'       => $auditoriasAccesos,
            'resumenAccesos'          => $resumenAccesos,
            'usuariosFiltro'          => $usuariosFiltro,
            'listas'                  => $listas,
            'filtros'                 => $request->all(),
            'tabActivo'               => $tab,
            'isSuperAdmin'            => $isSuperAdmin,
            'puedeVerAccesos'         => $puedeVerAccesos,
            'puedeVerCatalogos'       => $puedeVerCatalogos,
        ]);
    }
}

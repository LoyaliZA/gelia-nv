<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SolicitudTag;
use App\Models\User;

class DashboardController extends Controller
{
    /**
     * Calcula y envía las estadísticas del dashboard basadas estrictamente en los permisos del usuario.
     */

    public function index(Request $request)
    {
        $user = $request->user();
        $estadisticas = [];

        // Nombres actualizados según RolesSeeder
        if ($user->can('solicitudes.crear')) {
            $estadisticas['mis_activas'] = SolicitudTag::where('vendedor_id', $user->id)
                ->where('catalogo_estado_solicitud_id', '!=', 2)
                ->count();
            // ... resto de lógica de vendedora
        }

        if ($user->can('usuarios.gestionar') || $user->can('configuracion.ver_auditoria')) {
            $mesActual = now()->month;
            $anioActual = now()->year;

            $estadisticas['solicitudes_mes'] = SolicitudTag::whereMonth('created_at', $mesActual)
                ->whereYear('created_at', $anioActual)
                ->count();

            $estadisticas['cotizado_global'] = SolicitudTag::whereMonth('created_at', $mesActual)
                ->whereYear('created_at', $anioActual)
                ->sum('monto_cotizado');

            $estadisticas['usuarios_activos'] = User::count();
        }

        return Inertia::render('Dashboards/Index', [
            'estadisticas' => $estadisticas
        ]);
    }

    /**
     * Actualiza las preferencias visuales del dashboard del usuario.
     */
    public function actualizarPreferencias(Request $request)
    {
        $request->validate([
            'ocultos' => 'array'
        ]);

        $config = \App\Models\ConfiguracionUsuario::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['tema_visual' => ['modo' => 'light', 'color_nombre' => 'rosa']] // Valores por defecto si no existía
        );

        $prefsActuales = $config->dashboard_prefs ?? [];
        $prefsActuales['ocultos'] = $request->ocultos;

        $config->update(['dashboard_prefs' => $prefsActuales]);

        return back(); // Inertia se encarga de recargar la vista suavemente
    }
}

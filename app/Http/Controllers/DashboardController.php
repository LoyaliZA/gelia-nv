<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Calcula y envía las estadísticas del dashboard basadas estrictamente en los permisos del usuario.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $estadisticas = [];
        $ultimasSolicitudes = []; // Inicializamos la variable vacía por defecto

        // Estadísticas operativas (Ejemplo: Vendedores/Asesores)
        if ($user->can('solicitudes.crear') || $user->can('solicitudes.gestionar')) {
            $estadisticas['mis_activas'] = SolicitudTag::where('vendedor_id', $user->id)
                ->where('catalogo_estado_solicitud_id', '!=', 2) // Asumiendo que 2 es un estado cerrado/finalizado
                ->count();
            // ... resto de lógica operativa si la necesitas
        }

        // Estadísticas administrativas globales
        if ($user->can('usuarios.gestionar') || $user->can('configuracion.ver_auditoria') || $user->can('clientes.carga_masiva')) {
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

        // --- NUEVA LÓGICA: LIVE SOLICITUDES ---
        // Extraemos las 4 más recientes con sus relaciones (cliente y estado) si el usuario tiene permiso
        if ($user->can('configuracion.ver_auditoria') || $user->can('solicitudes.gestionar')) {
            $ultimasSolicitudes = SolicitudTag::with(['cliente', 'estado'])
                ->latest()
                ->take(4)
                ->get();
        }

        // Asegúrate de que la ruta del render coincida con la ubicación real de tu componente en React
        // Ejemplo: si tu archivo está en resources/js/Pages/Admin/AdminDashboard.jsx, usa 'Admin/AdminDashboard'
        return Inertia::render('Dashboards/Index', [
            'estadisticas' => $estadisticas,
            'ultimas_solicitudes' => $ultimasSolicitudes // Enviamos la data a Inertia/React
        ]);

    }

    /**
     * Actualiza las preferencias visuales del dashboard del usuario (Tarjetas Ocultas).
     */
    public function actualizarPreferencias(Request $request)
    {
        // 1. Validamos que recibimos el array con la nomenclatura oficial del frontend
        $request->validate([
            'dashboard_ocultos' => 'sometimes|array',
            'dashboard_layout' => 'nullable|array',
            'dashboard_layout.*.i' => 'required_with:dashboard_layout|string|max:64',
            'dashboard_layout.*.x' => 'required_with:dashboard_layout|integer|min:0|max:11',
            'dashboard_layout.*.y' => 'required_with:dashboard_layout|integer|min:0|max:200',
            'dashboard_layout.*.w' => 'required_with:dashboard_layout|integer|min:1|max:12',
            'dashboard_layout.*.h' => 'required_with:dashboard_layout|integer|min:1|max:100',
        ]);

        $user = $request->user();

        // 2. Obtenemos la configuración actual directamente con Query Builder
        $configActual = DB::table('configuraciones_usuarios')
            ->where('user_id', $user->id)
            ->first();

        // 3. Decodificamos el JSON existente o creamos un array vacío si es la primera vez
        $temaVisual = $configActual ? json_decode($configActual->tema_visual, true) : [];

        // 4. Tarjetas ocultas y disposición del panel (grid)
        if ($request->has('dashboard_ocultos')) {
            $temaVisual['dashboard_ocultos'] = $request->input('dashboard_ocultos', []);
        }
        if ($request->has('dashboard_layout') && is_array($request->input('dashboard_layout'))) {
            $temaVisual['dashboard_layout'] = $request->input('dashboard_layout');
        }

        // 5. Guardamos o insertamos el JSON actualizado sin tocar colores, layout ni fuentes
        DB::table('configuraciones_usuarios')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'tema_visual' => json_encode($temaVisual),
                'updated_at'  => now(),
            ]
        );

        // 6. Redirigimos de vuelta. Inertia hará una recarga suave y el frontend leerá el nuevo JSON de 'auth'
        return back()->with('success', 'Preferencias del panel guardadas.');
    }
}

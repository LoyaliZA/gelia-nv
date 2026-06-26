<?php

namespace App\Http\Controllers;

use App\Models\BellaromaConfig;
use App\Models\BellaromaTemplate;
use App\Models\User;
use App\Services\PlantillaBellaroma\PlantillaBellaromaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PlantillaBellaromaController extends Controller
{
    public function index()
    {
        Gate::authorize('plantilla_pedidos.ver');

        $timezone = config('app.timezone', 'America/Mexico_City');
        $hoy = now($timezone)->format('Y-m-d');
        $ahora = now($timezone);

        $templatesHoy = BellaromaTemplate::where(function($q) use ($hoy) {
                $q->whereNull('fecha_entrega')
                  ->whereDate('created_at', $hoy);
            })
            ->orWhere(function($q) use ($hoy, $ahora) {
                $q->whereNotNull('fecha_entrega')
                  ->whereDate('fecha_entrega', $hoy)
                  ->where('fecha_entrega', '<=', $ahora);
            })
            ->orderByDesc('id')
            ->get();

        $templatesHistorial = BellaromaTemplate::where(function($q) use ($hoy) {
                $q->whereNull('fecha_entrega')
                  ->whereDate('created_at', '<', $hoy);
            })
            ->orWhere(function($q) use ($hoy, $ahora) {
                $q->whereNotNull('fecha_entrega')
                  ->whereDate('fecha_entrega', '<', $hoy)
                  ->where('fecha_entrega', '<=', $ahora);
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $templatesProgramados = [];
        if (auth()->user()->can('plantilla_pedidos.ver_programadas')) {
            $templatesProgramados = BellaromaTemplate::whereNotNull('fecha_entrega')
                ->where('fecha_entrega', '>', $ahora)
                ->orderBy('fecha_entrega', 'asc')
                ->get();
        }

        $configUsers = BellaromaConfig::where('llave', 'notified_users')->first();
        $notifiedUserIds = $configUsers && $configUsers->valor ? json_decode($configUsers->valor, true) : [];

        // Obtener usuarios para el selector
        $users = User::select('id', 'name', 'email')->get();

        // Determinar permisos del usuario actual para la vista
        $user = auth()->user();
        $permisos = [
            'ver' => $user->can('plantilla_pedidos.ver'),
            'generar' => $user->can('plantilla_pedidos.generar'),
            'configurar' => $user->can('plantilla_pedidos.configurar'),
            'descargar' => $user->can('plantilla_pedidos.descargar'),
            'visualizar' => $user->can('plantilla_pedidos.visualizar'),
            'eliminar' => $user->can('plantilla_pedidos.eliminar'),
            'ver_programadas' => $user->can('plantilla_pedidos.ver_programadas'),
        ];

        return Inertia::render('PlantillaBellaroma/Index', [
            'templatesHoy' => $templatesHoy,
            'templatesHistorial' => $templatesHistorial,
            'templatesProgramados' => $templatesProgramados,
            'notifiedUserIds' => $notifiedUserIds,
            'users' => $users,
            'permisos' => $permisos,
        ]);
    }

    public function generar(Request $request, PlantillaBellaromaService $service)
    {
        Gate::authorize('plantilla_pedidos.generar');

        $request->validate([
            'existencias' => 'required|file',
            'precios' => 'required|file',
            'tipo_entrega' => 'required|string|in:inmediata,manana,fecha',
            'fecha_programada' => 'nullable|date',
        ]);

        $template = $service->procesar(
            $request->file('existencias'),
            $request->file('precios'),
            $request->input('tipo_entrega', 'inmediata'),
            $request->input('fecha_programada')
        );

        return response()->json([
            'message' => 'Plantilla generada exitosamente.',
            'template' => $template,
            'download_url' => route('plantilla_bellaroma.descargar', $template->id)
        ]);
    }

    public function descargar($id)
    {
        Gate::authorize('plantilla_pedidos.descargar');

        $template = BellaromaTemplate::findOrFail($id);

        if (!Storage::disk('public')->exists($template->ruta_fisica)) {
            abort(404, 'El archivo físico ya no existe en el servidor.');
        }

        return Storage::disk('public')->download($template->ruta_fisica, $template->nombre_archivo);
    }

    public function eliminar($id)
    {
        Gate::authorize('plantilla_pedidos.eliminar');

        $template = BellaromaTemplate::findOrFail($id);

        if (Storage::disk('public')->exists($template->ruta_fisica)) {
            Storage::disk('public')->delete($template->ruta_fisica);
        }
        $template->delete();

        return response()->json(['message' => 'Plantilla eliminada del sistema.']);
    }

    public function guardarConfiguracion(Request $request)
    {
        Gate::authorize('plantilla_pedidos.configurar');

        $request->validate([
            'notified_users' => 'nullable|array',
            'notified_users.*' => 'exists:users,id'
        ]);

        BellaromaConfig::updateOrCreate(
            ['llave' => 'notified_users'],
            [
                'valor' => json_encode($request->notified_users ?? []),
                'descripcion' => 'IDs de usuarios que reciben notificación y correo de plantilla Bellaroma'
            ]
        );

        return response()->json(['success' => true, 'message' => 'Configuración actualizada exitosamente.']);
    }
}

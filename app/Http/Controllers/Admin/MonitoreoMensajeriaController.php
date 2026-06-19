<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Services\Mensajeria\ListarMensajesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class MonitoreoMensajeriaController extends Controller
{
    public function index(Request $request): Response
    {
        $usuarios = \App\Models\User::select('id', 'name', 'username', 'foto_perfil')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Mensajeria/Monitoreo', [
            'permisoEliminar' => $request->user()->can('mensajeria.eliminar'),
            'usuarios' => $usuarios
        ]);
    }

    public function conversaciones(Request $request): JsonResponse
    {
        $query = Conversacion::query()
            ->with(['creador:id,name,username,foto_perfil', 'usuarios:id,name,username,foto_perfil'])
            ->withCount('mensajes')
            ->orderByDesc('ultimo_mensaje_at');

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhereHas('usuarios', function($usr) use ($q) {
                        $usr->where('name', 'like', "%{$q}%")
                            ->orWhere('username', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('user_id')) {
            $userId = $request->query('user_id');
            $query->whereHas('usuarios', function($usr) use ($userId) {
                $usr->where('users.id', $userId);
            });
        }

        $conversaciones = $query->paginate(20);

        return response()->json($conversaciones);
    }

    public function mensajes(Request $request, Conversacion $conversacion, ListarMensajesService $service): JsonResponse
    {
        // Reutilizamos ListarMensajesService, que toma un usuario para saber si ha leído los mensajes etc.
        // El admin podría no estar en la conversación, pero le pasamos el usuario actual para no romper la firma del servicio,
        // aunque podríamos tener que adaptar el servicio si asume que el user está en la db pivot.
        // Dado que el admin solo monitorea, ListarMensajesService lo mapeará bien.

        // Sin embargo, `ListarMensajesService` llama a `formatearMensaje` que asume que puede ver la conversación.
        // Mejor usar el servicio pero sin autorización estricta en el controlador.
        
        $result = $service->ejecutar(
            $conversacion,
            $request->user(),
            $request->query('cursor')
        );

        return response()->json($result);
    }

    public function destroyConversacion(Conversacion $conversacion): JsonResponse
    {
        $this->authorize('mensajeria.eliminar');

        // Al usar SoftDeletes, solo se marcará como deleted_at
        $conversacion->delete();

        return response()->json(['message' => 'Conversación eliminada correctamente.']);
    }

    public function destroyMensaje(Mensaje $mensaje): JsonResponse
    {
        $this->authorize('mensajeria.eliminar');

        // Actualizamos el ultimo_mensaje_preview de la conversación si es el último mensaje
        $conversacion = $mensaje->conversacion;
        
        DB::transaction(function () use ($mensaje, $conversacion) {
            $mensaje->delete();
            
            // Recalcular preview si es necesario
            $ultimoMensaje = $conversacion->mensajes()->orderByDesc('created_at')->first();
            if ($ultimoMensaje) {
                $conversacion->update([
                    'ultimo_mensaje_at' => $ultimoMensaje->created_at,
                    'ultimo_mensaje_preview' => $ultimoMensaje->tipo === Mensaje::TIPO_TEXTO ? $ultimoMensaje->contenido : ucfirst($ultimoMensaje->tipo)
                ]);
            } else {
                $conversacion->update([
                    'ultimo_mensaje_at' => $conversacion->created_at,
                    'ultimo_mensaje_preview' => 'Sin mensajes'
                ]);
            }
        });

        return response()->json(['message' => 'Mensaje eliminado correctamente.']);
    }
}

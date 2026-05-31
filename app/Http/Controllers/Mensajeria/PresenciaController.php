<?php

namespace App\Http\Controllers\Mensajeria;

use App\Http\Controllers\Controller;
use App\Services\Presencia\PresenciaUsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PresenciaController extends Controller
{
    public function catalogo(PresenciaUsuarioService $service): JsonResponse
    {
        return response()->json([
            'estados' => $service->catalogo(),
            'defaults' => $service->prefsPorDefecto(),
        ]);
    }

    public function show(PresenciaUsuarioService $service): JsonResponse
    {
        $user = Auth::user();
        $service->sincronizarAutomatico($user);

        return response()->json([
            'presencia' => $service->formatear($user),
            'prefs' => $service->obtenerPrefs($user),
        ]);
    }

    public function update(Request $request, PresenciaUsuarioService $service): JsonResponse
    {
        try {
            return $this->ejecutarActualizacion($request, $service);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'presencia')) {
                return response()->json([
                    'message' => 'Falta la migración de presencia. Ejecuta: php artisan migrate',
                ], 503);
            }
            throw $e;
        }
    }

    private function ejecutarActualizacion(Request $request, PresenciaUsuarioService $service): JsonResponse
    {
        $datos = $request->validate([
            'estado' => 'nullable|string|max:40',
            'mensaje' => 'nullable|string|max:120',
            'modo' => 'nullable|string|in:manual,automatico',
            'automatizar' => 'nullable|boolean',
            'duracion_minutos' => 'nullable|integer|min:0|max:480',
            'horarios' => 'nullable|array',
            'horarios.*.estado' => 'required_with:horarios|string|max:40',
            'horarios.*.dias' => 'nullable|array',
            'horarios.*.dias.*' => 'integer|min:1|max:7',
            'horarios.*.inicio' => 'required_with:horarios|string|max:5',
            'horarios.*.fin' => 'required_with:horarios|string|max:5',
            'inactividad_minutos' => 'nullable|integer|min:0|max:480',
            'inactividad_estado' => 'nullable|string|max:40',
        ]);

        $presencia = $service->actualizar(Auth::user(), $datos);

        return response()->json(['presencia' => $presencia]);
    }

    public function heartbeat(PresenciaUsuarioService $service): JsonResponse
    {
        $presencia = $service->registrarActividad(Auth::user());

        return response()->json(['presencia' => $presencia]);
    }
}

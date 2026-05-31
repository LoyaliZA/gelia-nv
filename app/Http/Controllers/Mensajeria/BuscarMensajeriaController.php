<?php

namespace App\Http\Controllers\Mensajeria;

use App\Http\Controllers\Controller;
use App\Models\Conversacion;
use App\Services\Mensajeria\BuscarMensajeriaService;
use App\Services\Mensajeria\CargarMensajesAlrededorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BuscarMensajeriaController extends Controller
{
    public function buscar(Request $request, BuscarMensajeriaService $service): JsonResponse
    {
        $conversacionId = $request->query('conversacion_id');
        $conversacionId = $conversacionId !== null ? (int) $conversacionId : null;

        if ($conversacionId) {
            $conversacion = Conversacion::findOrFail($conversacionId);
            $this->authorize('view', $conversacion);
        }

        return response()->json(
            $service->ejecutar(
                Auth::user(),
                $request->query('q'),
                $conversacionId
            )
        );
    }

    public function contexto(
        Request $request,
        Conversacion $conversacion,
        CargarMensajesAlrededorService $service
    ): JsonResponse {
        $this->authorize('view', $conversacion);

        $mensajeId = (int) $request->query('mensaje_id');
        if ($mensajeId < 1) {
            return response()->json(['error' => 'mensaje_id requerido'], 422);
        }

        return response()->json(
            $service->ejecutar($conversacion, Auth::user(), $mensajeId)
        );
    }
}

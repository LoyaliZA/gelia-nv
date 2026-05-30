<?php

namespace App\Http\Controllers\Mensajeria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mensajeria\StoreMensajeRequest;
use App\Models\Conversacion;
use App\Services\Mensajeria\EnviarMensajeService;
use App\Services\Mensajeria\ListarMensajesService;
use App\Services\Mensajeria\MarcarConversacionLeidaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MensajeController extends Controller
{
    public function index(Request $request, Conversacion $conversacion, ListarMensajesService $service): JsonResponse
    {
        $this->authorize('view', $conversacion);

        $result = $service->ejecutar(
            $conversacion,
            Auth::user(),
            $request->query('cursor')
        );

        return response()->json($result);
    }

    public function store(
        StoreMensajeRequest $request,
        Conversacion $conversacion,
        EnviarMensajeService $service
    ): JsonResponse {
        $this->authorize('sendMessage', $conversacion);

        $mensaje = $service->ejecutar($conversacion, Auth::user(), $request->validated());

        return response()->json(['mensaje' => $mensaje], 201);
    }

    public function marcarLeida(
        Conversacion $conversacion,
        MarcarConversacionLeidaService $service
    ): JsonResponse {
        $this->authorize('view', $conversacion);

        $service->ejecutar($conversacion, Auth::user());

        return response()->json(['ok' => true]);
    }
}

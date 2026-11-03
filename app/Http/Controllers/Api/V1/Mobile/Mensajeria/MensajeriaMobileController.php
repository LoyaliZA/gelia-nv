<?php

namespace App\Http\Controllers\Api\V1\Mobile\Mensajeria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mensajeria\StoreConversacionRequest;
use App\Http\Requests\Mensajeria\StoreMensajeRequest;
use App\Models\Conversacion;
use App\Models\User;
use App\Services\Mensajeria\BuscarUsuariosMensajeriaService;
use App\Services\Mensajeria\CrearConversacionService;
use App\Services\Mensajeria\EnviarMensajeService;
use App\Services\Mensajeria\ListarConversacionesService;
use App\Services\Mensajeria\ListarMensajesService;
use App\Services\Mensajeria\MarcarConversacionLeidaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MensajeriaMobileController extends Controller
{
    public function conversaciones(Request $request, ListarConversacionesService $listar): JsonResponse
    {
        return response()->json([
            'data' => $listar->ejecutar($this->usuario($request))->values(),
        ]);
    }

    public function storeConversacion(
        StoreConversacionRequest $request,
        CrearConversacionService $crear,
        ListarConversacionesService $listar,
    ): JsonResponse {
        $usuario = $this->usuario($request);
        $conversacion = $crear->ejecutar($usuario, $request->validated());
        $formateada = $listar->ejecutar($usuario)->firstWhere('id', $conversacion->id);

        return response()->json([
            'data' => $formateada,
        ], 201);
    }

    public function usuarios(Request $request, BuscarUsuariosMensajeriaService $buscar): JsonResponse
    {
        return response()->json([
            'data' => $buscar->ejecutar(
                $this->usuario($request),
                $request->query('q'),
                (int) $request->query('limit', 20),
            ),
        ]);
    }

    public function mensajes(Request $request, Conversacion $conversacion, ListarMensajesService $listar): JsonResponse
    {
        $this->authorize('view', $conversacion);

        return response()->json($listar->ejecutar(
            $conversacion,
            $this->usuario($request),
            $request->query('cursor'),
        ));
    }

    public function enviar(StoreMensajeRequest $request, Conversacion $conversacion, EnviarMensajeService $enviar): JsonResponse
    {
        $this->authorize('sendMessage', $conversacion);

        return response()->json([
            'data' => $enviar->ejecutar($conversacion, $this->usuario($request), $request->validated()),
        ], 201);
    }

    public function leer(Request $request, Conversacion $conversacion, MarcarConversacionLeidaService $marcar): JsonResponse
    {
        $this->authorize('view', $conversacion);
        $marcar->ejecutar($conversacion, $this->usuario($request));

        return response()->json(['ok' => true]);
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}

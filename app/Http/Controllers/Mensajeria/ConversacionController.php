<?php

namespace App\Http\Controllers\Mensajeria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mensajeria\StoreConversacionRequest;
use App\Models\Conversacion;
use App\Services\Mensajeria\BuscarUsuariosMensajeriaService;
use App\Services\Mensajeria\CrearConversacionService;
use App\Services\Mensajeria\ListarConversacionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ConversacionController extends Controller
{
    public function index(ListarConversacionesService $service): Response
    {
        $conversaciones = $service->ejecutar(Auth::user());

        return Inertia::render('Mensajeria/Index', [
            'conversacionesIniciales' => $conversaciones->values()->all(),
        ]);
    }

    public function list(Request $request, ListarConversacionesService $service): JsonResponse
    {
        $conversaciones = $service->ejecutar(Auth::user());

        return response()->json([
            'conversaciones' => $conversaciones->values()->all(),
        ]);
    }

    public function store(StoreConversacionRequest $request, CrearConversacionService $service): JsonResponse
    {
        $conversacion = $service->ejecutar(Auth::user(), $request->validated());

        $listar = app(ListarConversacionesService::class);
        $formateada = $listar->ejecutar(Auth::user())
            ->firstWhere('id', $conversacion->id);

        return response()->json([
            'conversacion' => $formateada,
        ], 201);
    }

    public function usuarios(Request $request, BuscarUsuariosMensajeriaService $service): JsonResponse
    {
        $usuarios = $service->ejecutar(
            Auth::user(),
            $request->query('q'),
            (int) $request->query('limit', 20)
        );

        return response()->json(['usuarios' => $usuarios]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Mobile\Comercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comercial\RegistrarVisitaProgramadaRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Comercial\VisitasProgramadas\ListarVisitasProgramadasService;
use App\Services\Comercial\VisitasProgramadas\RegistrarVisitaProgramadaService;
use App\Services\Comercial\VisitasProgramadas\SerializarVisitaProgramadaService;
use App\Support\Comercial\VisitaProgramadaCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitaProgramadaMobileController extends Controller
{
    public function index(
        Request $request,
        ListarVisitasProgramadasService $listar,
        SerializarVisitaProgramadaService $serializar,
    ): JsonResponse {
        $this->authorizeGestionar($request);

        /** @var User $user */
        $user = $request->user();
        $ahora = now();
        $paginado = $listar->paginarParaRegistrador($user, 'vigentes', $ahora, 20);

        return response()->json([
            'data' => $paginado->getCollection()
                ->map(fn ($visita) => $serializar->item($visita, $ahora))
                ->values(),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
            ],
        ]);
    }

    public function historial(
        Request $request,
        ListarVisitasProgramadasService $listar,
        SerializarVisitaProgramadaService $serializar,
    ): JsonResponse {
        $this->authorizeGestionar($request);

        /** @var User $user */
        $user = $request->user();
        $ahora = now();
        $paginado = $listar->paginarParaRegistrador($user, 'historial', $ahora, 20);

        return response()->json([
            'data' => $paginado->getCollection()
                ->map(fn ($visita) => $serializar->item($visita, $ahora))
                ->values(),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
            ],
        ]);
    }

    public function store(
        RegistrarVisitaProgramadaRequest $request,
        RegistrarVisitaProgramadaService $registrar,
        SerializarVisitaProgramadaService $serializar,
    ): JsonResponse {
        $visita = $registrar->handle($request->user(), $request->validated());

        return response()->json([
            'visita' => $serializar->item($visita, now()),
        ], 201);
    }

    public function catalogos(Request $request): JsonResponse
    {
        $this->authorizeGestionar($request);

        return response()->json([
            'intenciones' => VisitaProgramadaCatalogo::intenciones(),
            'sucursales' => Sucursal::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ]);
    }

    private function authorizeGestionar(Request $request): void
    {
        abort_unless($request->user()?->can('visitas_programadas.gestionar'), 403);
    }
}

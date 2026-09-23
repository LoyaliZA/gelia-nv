<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Mobile\MobileClienteAlcanceService;
use App\Services\Mobile\MobileClienteSerializerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileClienteController extends Controller
{
    public function __construct(
        protected MobileClienteAlcanceService $alcance,
        protected MobileClienteSerializerService $serializer
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        if (! $this->alcance->tieneAccesoMovil($user)) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $termino = trim($validated['q']);
        $perPage = (int) ($validated['per_page'] ?? 10);

        $query = $this->alcance->queryPara($user)
            ->with(['listaDescuento', 'vendedor', 'tipo'])
            ->where(function ($inner) use ($termino) {
                $inner->where('numero_cliente', 'like', "%{$termino}%")
                    ->orWhere('nombre', 'like', "%{$termino}%")
                    ->orWhere('nombre_razon_social', 'like', "%{$termino}%");
            })
            ->orderBy('nombre');

        $paginado = $query->paginate($perPage);

        return response()->json([
            'data' => $paginado->getCollection()
                ->map(fn (Cliente $cliente) => $this->serializer->serializar($cliente, $user))
                ->values(),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
            ],
        ]);
    }

    public function show(Request $request, string $numeroCliente): JsonResponse
    {
        $user = $this->user($request);

        if (! $this->alcance->tieneAccesoMovil($user)) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])
            ->where('numero_cliente', $numeroCliente)
            ->first();

        if (! $cliente || ! $this->alcance->puedeAcceder($user, $cliente)) {
            return response()->json(['message' => 'Cliente no encontrado.'], 404);
        }

        return response()->json([
            'data' => $this->serializer->serializar($cliente, $user),
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

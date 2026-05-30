<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClienteExternoRequest;
use App\Http\Requests\Api\V1\UpdateClienteExternoRequest;
use App\Models\ApiAplicacion;
use App\Models\ApiRecurso;
use App\Models\Cliente;
use App\Services\ApiExterna\ApiFieldFilterService;
use App\Services\ApiExterna\ApiPermisoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteExternoController extends Controller
{
    public function __construct(
        protected ApiPermisoService $permisoService,
        protected ApiFieldFilterService $fieldFilterService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var ApiAplicacion $aplicacion */
        $aplicacion = $request->user();
        /** @var ApiRecurso $recurso */
        $recurso = $request->attributes->get('api_recurso');

        $termino = $request->query('q', '');
        $perPage = min((int) $request->query('per_page', 25), 100);

        $query = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])
            ->when($termino, function ($q, $termino) {
                $q->where(function ($inner) use ($termino) {
                    $inner->where('numero_cliente', 'like', "%{$termino}%")
                        ->orWhere('nombre', 'like', "%{$termino}%")
                        ->orWhere('nombre_razon_social', 'like', "%{$termino}%");
                });
            })
            ->orderBy('numero_cliente');

        $paginado = $query->paginate($perPage);

        return response()->json([
            'data' => $this->fieldFilterService->filtrarColeccion(
                $paginado->getCollection(),
                $aplicacion,
                $recurso
            ),
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
        /** @var ApiAplicacion $aplicacion */
        $aplicacion = $request->user();
        /** @var ApiRecurso $recurso */
        $recurso = $request->attributes->get('api_recurso');

        $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])
            ->where('numero_cliente', $numeroCliente)
            ->first();

        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado.'], 404);
        }

        $slugs = $this->fieldFilterService->slugsHabilitados($aplicacion, $recurso);

        return response()->json([
            'data' => $this->fieldFilterService->filtrarCliente($cliente, $slugs),
        ]);
    }

    public function store(StoreClienteExternoRequest $request): JsonResponse
    {
        /** @var ApiAplicacion $aplicacion */
        $aplicacion = $request->user();
        /** @var ApiRecurso $recurso */
        $recurso = $request->attributes->get('api_recurso');

        $cliente = Cliente::create($request->validated());

        $slugs = $this->fieldFilterService->slugsHabilitados($aplicacion, $recurso);

        return response()->json([
            'data' => $this->fieldFilterService->filtrarCliente($cliente->fresh(['listaDescuento', 'vendedor', 'tipo']), $slugs),
        ], 201);
    }

    public function update(UpdateClienteExternoRequest $request, string $numeroCliente): JsonResponse
    {
        /** @var ApiAplicacion $aplicacion */
        $aplicacion = $request->user();
        /** @var ApiRecurso $recurso */
        $recurso = $request->attributes->get('api_recurso');

        $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();

        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado.'], 404);
        }

        $cliente->update($request->validated());

        $slugs = $this->fieldFilterService->slugsHabilitados($aplicacion, $recurso);

        return response()->json([
            'data' => $this->fieldFilterService->filtrarCliente($cliente->fresh(['listaDescuento', 'vendedor', 'tipo']), $slugs),
        ]);
    }
}

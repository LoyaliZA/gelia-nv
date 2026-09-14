<?php

namespace App\Http\Controllers\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubePrecioSeleccionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tiendanube\StoreTiendanubePrecioSeleccionRequest;
use App\Http\Requests\Tiendanube\UpdateTiendanubePrecioSeleccionRequest;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Services\Tiendanube\Precios\TiendanubePrecioSeleccionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TiendanubePrecioSeleccionController extends Controller
{
    public function __construct(
        private readonly TiendanubePrecioSeleccionService $selecciones,
    ) {}

    public function store(StoreTiendanubePrecioSeleccionRequest $request): JsonResponse
    {
        try {
            $payload = $this->selecciones->crear(
                $this->storeId(),
                (int) $request->user()->id,
                $request->validated(),
                $request->user()->can('tiendanube.precios.ver')
            );
        } catch (TiendanubePrecioSeleccionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'version' => ['nullable', 'integer', 'min:1'],
            'page_variante_ids' => ['nullable', 'array', 'max:50'],
            'page_variante_ids.*' => ['integer', 'min:1'],
        ]);

        try {
            $payload = $this->selecciones->consultar(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                array_map('intval', $request->input('page_variante_ids', [])),
                $request->filled('version') ? (int) $request->input('version') : null
            );
        } catch (TiendanubePrecioSeleccionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function update(UpdateTiendanubePrecioSeleccionRequest $request, string $id): JsonResponse
    {
        try {
            $payload = $this->selecciones->actualizar(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                $request->validated(),
                $request->user()->can('tiendanube.precios.ver')
            );
        } catch (TiendanubePrecioSeleccionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function resolver(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        try {
            $payload = $this->selecciones->resolver(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                (int) $request->input('page', 1),
                (int) $request->input('per_page', 100)
            );
        } catch (TiendanubePrecioSeleccionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    private function jsonError(TiendanubePrecioSeleccionException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'codigo' => $e->codigo,
        ], $e->httpStatus);
    }

    private function storeId(): int
    {
        $id = TiendanubeConfiguracion::obtener()->store_id;
        if (! $id) {
            throw new TiendanubePrecioSeleccionException('No hay tienda configurada.', 'tienda_faltante');
        }

        return (int) $id;
    }
}

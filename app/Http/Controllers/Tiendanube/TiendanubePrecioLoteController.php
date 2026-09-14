<?php

namespace App\Http\Controllers\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tiendanube\ActualizarTiendanubePrecioLoteItemRequest;
use App\Http\Requests\Tiendanube\AprobarTiendanubePrecioLoteRequest;
use App\Http\Requests\Tiendanube\CrearTiendanubePrecioLoteRequest;
use App\Http\Requests\Tiendanube\RecalcularTiendanubePrecioLoteRequest;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteAprobacionService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteItemService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TiendanubePrecioLoteController extends Controller
{
    public function __construct(
        private readonly TiendanubePrecioLoteService $lotes,
        private readonly TiendanubePrecioLoteItemService $items,
        private readonly TiendanubePrecioLoteAprobacionService $aprobacion,
    ) {}

    public function store(CrearTiendanubePrecioLoteRequest $request): JsonResponse
    {
        try {
            $payload = $this->lotes->crear(
                $this->storeId(),
                (int) $request->user()->id,
                $request->validated()
            );
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        try {
            $payload = $this->lotes->consultar(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                $request->user()->can('tiendanube.precios.ver')
            );
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function items(Request $request, string $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');
        $filtros = $request->validate([
            'filtro' => ['nullable', 'in:todas,con_cambio,sin_cambio,con_errores,sin_costo'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $payload = $this->lotes->listarItems(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                $filtros,
                $request->user()->can('tiendanube.precios.ver')
            );
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function progreso(Request $request, string $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        try {
            $payload = $this->lotes->progreso($id, $this->storeId(), (int) $request->user()->id);
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function simular(RecalcularTiendanubePrecioLoteRequest $request, string $id): JsonResponse
    {
        try {
            $payload = $this->lotes->recalcular(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                $request->validated()
            );
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function actualizarItem(ActualizarTiendanubePrecioLoteItemRequest $request, string $id, int $itemId): JsonResponse
    {
        try {
            $payload = $this->items->actualizar(
                $id,
                $itemId,
                $this->storeId(),
                (int) $request->user()->id,
                $request->validated(),
                $request->user()->can('tiendanube.precios.ver')
            );
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function aprobar(AprobarTiendanubePrecioLoteRequest $request, string $id): JsonResponse
    {
        try {
            $payload = $this->aprobacion->aprobar(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                (string) $request->validated('checksum'),
                $request->user()->can('tiendanube.precios.aprobar'),
                $request->user()->can('tiendanube.precios.ver')
            );
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function cancelar(Request $request, string $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        try {
            $payload = $this->lotes->cancelar($id, $this->storeId(), (int) $request->user()->id);
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function revisionAprobada(Request $request, string $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        try {
            $payload = $this->aprobacion->obtenerRevisionAprobada(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                $request->integer('revision_id') ?: null
            );
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    private function jsonError(TiendanubePrecioLoteException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'codigo' => $e->codigo,
            'errores' => $e->errores,
        ], $e->httpStatus);
    }

    private function storeId(): int
    {
        $id = TiendanubeConfiguracion::obtener()->store_id;
        if (! $id) {
            throw new TiendanubePrecioLoteException('No hay tienda configurada.', 'tienda_faltante', 422);
        }

        return (int) $id;
    }
}

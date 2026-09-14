<?php

namespace App\Http\Controllers\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubePrecioEjecucionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tiendanube\AplicarTiendanubePrecioLoteRequest;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionAdmisionService;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TiendanubePrecioEjecucionController extends Controller
{
    public function __construct(
        private readonly TiendanubePrecioEjecucionAdmisionService $admision,
        private readonly TiendanubePrecioEjecucionService $ejecuciones,
    ) {}

    public function aplicar(AplicarTiendanubePrecioLoteRequest $request, string $id): JsonResponse
    {
        try {
            $payload = $this->admision->admitir(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                $request->user()->can('tiendanube.precios.aplicar'),
                (string) $request->validated('checksum')
            );
        } catch (TiendanubePrecioEjecucionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        try {
            $ejecucion = $this->ejecuciones->porLote($id, $this->storeId(), (int) $request->user()->id);
            if (! $ejecucion) {
                throw new TiendanubePrecioEjecucionException('No hay una ejecución de API para este lote.', 'no_encontrada', 404);
            }
        } catch (TiendanubePrecioEjecucionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($this->ejecuciones->payload($ejecucion));
    }

    public function items(Request $request, string $ejecucionId): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');
        $filtros = $request->validate([
            'filtro' => ['nullable', 'in:todas,problemas,conflicto,resultado_incierto,fallido,confirmado,pendiente,cancelado'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $payload = $this->ejecuciones->listarItems(
                $ejecucionId,
                $this->storeId(),
                (int) $request->user()->id,
                $filtros
            );
        } catch (TiendanubePrecioEjecucionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function cancelar(Request $request, string $ejecucionId): JsonResponse
    {
        $this->authorize('tiendanube.precios.aplicar');

        try {
            $payload = $this->ejecuciones->cancelar(
                $ejecucionId,
                $this->storeId(),
                (int) $request->user()->id
            );
        } catch (TiendanubePrecioEjecucionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function reintentar(Request $request, string $ejecucionId, int $itemId): JsonResponse
    {
        $this->authorize('tiendanube.precios.aplicar');

        try {
            $payload = $this->ejecuciones->reintentarItem(
                $ejecucionId,
                $itemId,
                $this->storeId(),
                (int) $request->user()->id
            );
        } catch (TiendanubePrecioEjecucionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function verificar(Request $request, string $ejecucionId, int $itemId): JsonResponse
    {
        $this->authorize('tiendanube.precios.aplicar');

        try {
            $payload = $this->ejecuciones->verificarItem(
                $ejecucionId,
                $itemId,
                $this->storeId(),
                (int) $request->user()->id
            );
        } catch (TiendanubePrecioEjecucionException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    private function jsonError(TiendanubePrecioEjecucionException $e): JsonResponse
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
            throw new TiendanubePrecioEjecucionException('No hay tienda configurada.', 'tienda_faltante', 422);
        }

        return (int) $id;
    }
}

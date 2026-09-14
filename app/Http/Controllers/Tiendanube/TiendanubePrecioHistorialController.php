<?php

namespace App\Http\Controllers\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tiendanube\ConciliarTiendanubePrecioHistorialRequest;
use App\Http\Requests\Tiendanube\ListarTiendanubePrecioHistorialRequest;
use App\Http\Requests\Tiendanube\RestaurarTiendanubePrecioHistorialRequest;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Services\Tiendanube\Precios\Historial\TiendanubePrecioConciliacionService;
use App\Services\Tiendanube\Precios\Historial\TiendanubePrecioHistorialQueryService;
use App\Services\Tiendanube\Precios\Historial\TiendanubePrecioRestauracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TiendanubePrecioHistorialController extends Controller
{
    public function __construct(
        private readonly TiendanubePrecioHistorialQueryService $historial,
        private readonly TiendanubePrecioConciliacionService $conciliacion,
        private readonly TiendanubePrecioRestauracionService $restauracion,
    ) {}

    public function index(ListarTiendanubePrecioHistorialRequest $request): Response
    {
        $user = $request->user();
        $config = TiendanubeConfiguracion::obtener();
        $storeId = $config->store_id ? (int) $config->store_id : 0;
        $puedeVerCosto = $user->can('tiendanube.precios.ver');
        $filtros = $request->validated();

        return Inertia::render('Tiendanube/Precios/Historial', [
            'configuracion' => [
                'store_id' => $storeId ?: null,
                'store_name' => $config->store_name,
            ],
            'listado' => $storeId > 0
                ? $this->historial->listarOperaciones($storeId, $filtros, $puedeVerCosto)
                : ['data' => [], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1], 'vacio' => true, 'sin_resultados' => false],
            'filtros' => $filtros,
            'permisos' => [
                'ver' => $user->can('tiendanube.ver'),
                'precios_ver' => $puedeVerCosto,
                'reglas_ver' => $user->can('tiendanube.precios.reglas.ver'),
                'precios_aprobar' => $user->can('tiendanube.precios.aprobar'),
                'restaurar' => $user->can('tiendanube.precios.reglas.ver')
                && (bool) config('tiendanube.precios_restauracion_habilitada', true),
                'configurar' => $user->can('tiendanube.configurar'),
            ],
        ]);
    }

    public function operaciones(ListarTiendanubePrecioHistorialRequest $request): JsonResponse
    {
        return response()->json($this->historial->listarOperaciones(
            $this->storeId(),
            $request->validated(),
            $request->user()->can('tiendanube.precios.ver')
        ));
    }

    public function show(Request $request, string $loteId): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        try {
            $payload = $this->historial->consultarOperacion(
                $loteId,
                $this->storeId(),
                $request->user()->can('tiendanube.precios.ver'),
                $request->boolean('diagnostico') && $request->user()->can('tiendanube.configurar')
            );
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function conciliar(ConciliarTiendanubePrecioHistorialRequest $request, string $loteId): JsonResponse
    {
        try {
            $payload = $this->conciliacion->conciliar(
                $loteId,
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

    public function prepararRestauracion(RestaurarTiendanubePrecioHistorialRequest $request, string $loteId): JsonResponse
    {
        try {
            $payload = $this->restauracion->preparar(
                $loteId,
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

    public function restaurar(RestaurarTiendanubePrecioHistorialRequest $request, string $loteId): JsonResponse
    {
        try {
            $payload = $this->restauracion->confirmar(
                $loteId,
                $this->storeId(),
                (int) $request->user()->id,
                $request->validated(),
                $request->user()->can('tiendanube.precios.ver')
            );
        } catch (TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload, 201);
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

<?php

namespace App\Http\Controllers\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubePrecioReglaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tiendanube\PreviewTiendanubePrecioReglaRequest;
use App\Http\Requests\Tiendanube\StoreTiendanubePrecioReglaRequest;
use App\Http\Requests\Tiendanube\UpdateTiendanubePrecioReglaRequest;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\User;
use App\Services\Tiendanube\Precios\TiendanubePrecioReglaMetadatosService;
use App\Services\Tiendanube\Precios\TiendanubePrecioReglaPreviewService;
use App\Services\Tiendanube\Precios\TiendanubePrecioReglaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TiendanubePrecioReglasController extends Controller
{
    public function __construct(
        private readonly TiendanubePrecioReglaService $reglas,
        private readonly TiendanubePrecioReglaPreviewService $preview,
        private readonly TiendanubePrecioReglaMetadatosService $metadatos,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        $user = $request->user();
        $config = TiendanubeConfiguracion::obtener();
        $storeId = $config->store_id ? (int) $config->store_id : null;

        return Inertia::render('Tiendanube/Precios/Reglas', [
            'configuracion' => [
                'store_id' => $storeId,
                'store_name' => $config->store_name,
            ],
            'reglas' => $storeId ? $this->reglas->listar($storeId, $request->boolean('incluir_archivadas')) : [],
            'metadatos' => $storeId ? $this->metadatos->paraUi($storeId) : null,
            'permisos' => $this->permisosPayload($user),
        ]);
    }

    public function listar(Request $request): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        return response()->json([
            'reglas' => $this->reglas->listar($this->storeId(), $request->boolean('incluir_archivadas')),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        try {
            $regla = $this->reglas->obtener($id, $this->storeId());
        } catch (TiendanubePrecioReglaException $e) {
            return $this->jsonError($e);
        }

        return response()->json($regla);
    }

    public function store(StoreTiendanubePrecioReglaRequest $request): JsonResponse
    {
        try {
            $regla = $this->reglas->crear(
                $this->storeId(),
                (int) $request->user()->id,
                $request->validated()
            );
        } catch (TiendanubePrecioReglaException $e) {
            return $this->jsonError($e);
        }

        return response()->json($regla, 201);
    }

    public function update(UpdateTiendanubePrecioReglaRequest $request, int $id): JsonResponse
    {
        try {
            $regla = $this->reglas->actualizar(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                $request->validated()
            );
        } catch (TiendanubePrecioReglaException $e) {
            return $this->jsonError($e);
        }

        return response()->json($regla);
    }

    public function duplicar(Request $request, int $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.administrar');

        try {
            $regla = $this->reglas->duplicar($id, $this->storeId(), (int) $request->user()->id);
        } catch (TiendanubePrecioReglaException $e) {
            return $this->jsonError($e);
        }

        return response()->json($regla, 201);
    }

    public function archivar(Request $request, int $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.administrar');

        try {
            $regla = $this->reglas->archivar($id, $this->storeId(), (int) $request->user()->id);
        } catch (TiendanubePrecioReglaException $e) {
            return $this->jsonError($e);
        }

        return response()->json($regla);
    }

    public function previsualizar(PreviewTiendanubePrecioReglaRequest $request): JsonResponse
    {
        try {
            $payload = $this->preview->previsualizar(
                $this->storeId(),
                (int) $request->user()->id,
                $request->validated(),
                $request->user()->can('tiendanube.precios.ver')
            );
        } catch (TiendanubePrecioReglaException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function metadatos(Request $request): JsonResponse
    {
        $this->authorize('tiendanube.precios.reglas.ver');

        return response()->json($this->metadatos->paraUi($this->storeId()));
    }

    private function jsonError(TiendanubePrecioReglaException $e): JsonResponse
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
            throw new TiendanubePrecioReglaException('No hay tienda configurada.', 'tienda_faltante', 422);
        }

        return (int) $id;
    }

    /**
     * @return array<string, bool>
     */
    private function permisosPayload(?User $user): array
    {
        return [
            'ver' => $user?->can('tiendanube.ver') ?? false,
            'precios_ver' => $user?->can('tiendanube.precios.ver') ?? false,
            'reglas_ver' => $user?->can('tiendanube.precios.reglas.ver') ?? false,
            'reglas_administrar' => $user?->can('tiendanube.precios.reglas.administrar') ?? false,
        ];
    }
}

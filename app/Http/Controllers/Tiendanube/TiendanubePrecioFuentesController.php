<?php

namespace App\Http\Controllers\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubePrecioFuenteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tiendanube\ConfirmTiendanubePrecioImportRequest;
use App\Http\Requests\Tiendanube\ImportTiendanubePrecioPreviewRequest;
use App\Http\Requests\Tiendanube\ResolverTiendanubePrecioFuentesRequest;
use App\Http\Requests\Tiendanube\StoreTiendanubePrecioCostoRequest;
use App\Http\Requests\Tiendanube\StoreTiendanubePrecioListaRequest;
use App\Http\Requests\Tiendanube\UpdateTiendanubePrecioListaRequest;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Models\Tiendanube\TiendanubePrecioImport;
use App\Models\Tiendanube\TiendanubePrecioLista;
use App\Services\Tiendanube\Precios\TiendanubePrecioCostoCapturaService;
use App\Services\Tiendanube\Precios\TiendanubePrecioFuenteResolverService;
use App\Services\Tiendanube\Precios\TiendanubePrecioImportService;
use App\Services\Tiendanube\Precios\TiendanubePrecioListaService;
use App\Services\Tiendanube\Precios\TiendanubePrecioVarianteResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TiendanubePrecioFuentesController extends Controller
{
    public function fuentes(Request $request): Response
    {
        Gate::authorize('tiendanube.ver');

        $user = $request->user();
        $config = TiendanubeConfiguracion::obtener();
        $storeId = $config->store_id ? (int) $config->store_id : null;
        $puedeVer = $user->can('tiendanube.precios.ver');
        $listas = $storeId
            ? app(TiendanubePrecioListaService::class)->listar($storeId)->map(function (TiendanubePrecioLista $lista) {
                return [
                    'id' => $lista->id,
                    'nombre' => $lista->nombre,
                    'archived_at' => $lista->archived_at?->toIso8601String(),
                ];
            })->values()
            : collect();

        return Inertia::render('Tiendanube/Precios/Fuentes', [
            'configuracion' => [
                'store_id' => $storeId,
                'store_name' => $config->store_name,
            ],
            'listas' => $listas,
            'variante_id_inicial' => $request->integer('variante_id') ?: null,
            'permisos' => [
                'ver' => $user->can('tiendanube.ver'),
                'precios_ver' => $puedeVer,
                'precios_editar' => $user->can('tiendanube.precios.editar'),
                'precios_importar' => $user->can('tiendanube.precios.importar'),
                'precios_exportar' => $user->can('tiendanube.precios.exportar'),
                'configurar' => $user->can('tiendanube.configurar'),
            ],
        ]);
    }

    public function resolver(ResolverTiendanubePrecioFuentesRequest $request): JsonResponse
    {
        try {
            $datos = app(TiendanubePrecioFuenteResolverService::class)->resolver(
                $this->storeId(),
                $request->validated('variante_ids'),
                $request->validated('tipos')
            );
        } catch (TiendanubePrecioFuenteException $e) {
            return $this->jsonError($e);
        }

        return response()->json(['variantes' => $datos]);
    }

    public function resolverVariante(Request $request): JsonResponse
    {
        Gate::authorize('tiendanube.precios.editar');

        $varianteId = $request->query('variante_id');
        $sku = trim((string) $request->query('sku', ''));

        $resolver = app(TiendanubePrecioVarianteResolverService::class);
        if ($varianteId !== null && $varianteId !== '') {
            $resolucion = $resolver->resolverPorVarianteId((int) $varianteId);
        } else {
            $resolucion = $resolver->resolverPorSku($sku);
        }

        $fuentes = null;
        if (
            $resolucion['estado'] === TiendanubePrecioVarianteResolverService::ESTADO_ENCONTRADO
            && $request->user()->can('tiendanube.precios.ver')
            && $resolucion['variante_id']
        ) {
            $fuentes = app(TiendanubePrecioFuenteResolverService::class)->resolver(
                $this->storeId(),
                [(int) $resolucion['variante_id']]
            )[0] ?? null;
        }

        return response()->json([
            ...$resolucion,
            'fuentes' => $fuentes,
        ]);
    }

    public function storeCosto(StoreTiendanubePrecioCostoRequest $request): JsonResponse
    {
        try {
            $version = app(TiendanubePrecioCostoCapturaService::class)->capturar(
                $this->storeId(),
                (int) $request->validated('variante_id'),
                (string) $request->validated('valor'),
                (string) $request->validated('moneda'),
                $request->validated('motivo'),
                $request->user(),
                $request->validated('tipo') ?: TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
                ['lista_id' => $request->validated('lista_id')]
            );
        } catch (TiendanubePrecioFuenteException $e) {
            return response()->json(['message' => $e->getMessage(), 'codigo' => $e->codigo], 422);
        }

        return response()->json([
            'ok' => true,
            'version' => [
                'id' => $version->id,
                'tipo' => $version->tipo,
                'lista_id' => $version->lista_id,
                'version' => $version->version,
                'moneda' => $version->moneda,
                'valor_decimal' => $version->valorDecimalString(),
                'variante_id' => $version->variante_id,
                'producto_id' => $version->producto_id,
                'tienda_id' => $version->store_id,
                'fecha' => $version->fecha?->toIso8601String(),
            ],
        ], 201);
    }

    public function storeLista(StoreTiendanubePrecioListaRequest $request): JsonResponse
    {
        try {
            $lista = app(TiendanubePrecioListaService::class)->crear(
                $this->storeId(),
                (string) $request->validated('nombre')
            );
        } catch (TiendanubePrecioFuenteException $e) {
            return response()->json(['message' => $e->getMessage(), 'codigo' => $e->codigo], 422);
        }

        return response()->json([
            'id' => $lista->id,
            'nombre' => $lista->nombre,
            'archived_at' => null,
        ], 201);
    }

    public function updateLista(UpdateTiendanubePrecioListaRequest $request, int $id): JsonResponse
    {
        $lista = TiendanubePrecioLista::query()
            ->where('store_id', $this->storeId())
            ->findOrFail($id);

        try {
            $service = app(TiendanubePrecioListaService::class);
            if ($request->boolean('archivar')) {
                $lista = $service->archivar($lista);
            }
            if ($request->filled('nombre')) {
                $lista = $service->renombrar($lista, (string) $request->validated('nombre'));
            }
        } catch (TiendanubePrecioFuenteException $e) {
            return response()->json(['message' => $e->getMessage(), 'codigo' => $e->codigo], 422);
        }

        return response()->json([
            'id' => $lista->id,
            'nombre' => $lista->nombre,
            'archived_at' => $lista->archived_at?->toIso8601String(),
        ]);
    }

    public function importar(ImportTiendanubePrecioPreviewRequest $request): JsonResponse
    {
        try {
            $import = app(TiendanubePrecioImportService::class)->previsualizar(
                $request->file('archivo'),
                $this->storeId(),
                (string) $request->input('delimiter', ','),
                (string) $request->input('decimal_sep', '.'),
                [
                    'identificador' => $request->validated('identificador'),
                    'columna_identificador' => $request->validated('columna_identificador'),
                    'columnas_importes' => $request->validated('columnas_importes'),
                    'columna_moneda' => $request->validated('columna_moneda'),
                    'moneda_fija' => $request->input('moneda_fija', 'MXN'),
                ],
                $request->user()
            );
        } catch (TiendanubePrecioFuenteException $e) {
            return response()->json(['message' => $e->getMessage(), 'codigo' => $e->codigo], 422);
        }

        return response()->json($this->importPayload($import));
    }

    public function revisionImport(int $id): JsonResponse
    {
        Gate::authorize('tiendanube.precios.importar');

        $import = TiendanubePrecioImport::query()
            ->where('store_id', $this->storeId())
            ->with('items')
            ->findOrFail($id);

        return response()->json($this->importPayload($import));
    }

    public function confirmarImport(ConfirmTiendanubePrecioImportRequest $request, int $id): JsonResponse
    {
        $import = TiendanubePrecioImport::query()
            ->where('store_id', $this->storeId())
            ->findOrFail($id);

        try {
            $import = app(TiendanubePrecioImportService::class)->confirmar(
                $import,
                $request->validated('item_ids'),
                $request->user()
            );
        } catch (TiendanubePrecioFuenteException $e) {
            return response()->json(['message' => $e->getMessage(), 'codigo' => $e->codigo], 422);
        }

        return response()->json($this->importPayload($import));
    }

    private function jsonError(TiendanubePrecioFuenteException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage(), 'codigo' => $e->codigo], 422);
    }

    private function storeId(): int
    {
        $id = TiendanubeConfiguracion::obtener()->store_id;
        if (! $id) {
            throw new TiendanubePrecioFuenteException('No hay tienda configurada.', 'tienda_faltante');
        }

        return (int) $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function importPayload(TiendanubePrecioImport $import): array
    {
        return [
            'id' => $import->id,
            'estado' => $import->estado,
            'total_filas' => $import->total_filas,
            'validas' => $import->validas,
            'errores' => $import->errores,
            'items' => $import->items->map(fn ($item) => [
                'id' => $item->id,
                'fila' => $item->fila,
                'sku' => $item->sku,
                'variante_id' => $item->variante_id,
                'producto_id' => $item->producto_id,
                'destino_tipo' => $item->destino_tipo,
                'lista_id' => $item->lista_id,
                'valor_raw' => $item->valor_raw,
                'valor_decimal' => $item->valor_decimal !== null ? (string) $item->valor_decimal : null,
                'moneda' => $item->moneda,
                'estado' => $item->estado,
                'motivo' => $item->motivo,
                'valor_anterior' => $item->valor_anterior !== null ? (string) $item->valor_anterior : null,
                'moneda_anterior' => $item->moneda_anterior,
                'seleccionado' => (bool) $item->seleccionado,
                'candidatos' => $item->candidatos_json,
                'mensaje' => $item->mensaje,
            ]),
        ];
    }
}

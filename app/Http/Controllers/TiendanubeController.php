<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tiendanube\StoreTiendanubeProductoImagenRequest;
use App\Http\Requests\Tiendanube\StoreTiendanubeProductoRequest;
use App\Http\Requests\Tiendanube\UpdateTiendanubeProductoRequest;
use App\Jobs\Tiendanube\SyncTiendanubeCatalogoJob;
use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeImageImport;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeCatalogoWipeService;
use App\Services\Tiendanube\TiendanubeImageImportService;
use App\Services\Tiendanube\TiendanubeProductoWriteService;
use App\Services\Tiendanube\TiendanubeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TiendanubeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('tiendanube.ver');

        $config = TiendanubeConfiguracion::obtener();
        $query = $request->input('search');
        $filtroAlertaImagenes = $request->boolean('imagenes_alerta');
        $procesoActivo = TiendanubeSyncLog::activo();
        $imageImportActivo = TiendanubeImageImport::activo();

        $productos = TiendanubeProducto::query()
            ->with(['variantes', 'imagenes'])
            ->when($query, function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('id', $query)
                        ->orWhere('seo_title', 'LIKE', "%{$query}%")
                        ->orWhere('brand', 'LIKE', "%{$query}%")
                        ->orWhere('tags', 'LIKE', "%{$query}%")
                        ->orWhereHas('variantes', fn ($v) => $v->where('sku', 'LIKE', "%{$query}%"));
                });
            })
            ->when($filtroAlertaImagenes, function ($q) {
                $q->whereHas('imagenes', fn ($img) => $img->where('requiere_revision', true));
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (TiendanubeProducto $p) {
                return [
                    'id' => $p->id,
                    'nombre' => $p->nombreVisible(),
                    'sku' => $p->skuPrincipal(),
                    'published' => $p->published,
                    'seo_title' => $p->seo_title,
                    'brand' => $p->brand,
                    'imagen' => $p->imagenes->first()?->src,
                    'tiene_alerta_imagenes' => $p->imagenes->contains(fn ($img) => (bool) $img->requiere_revision),
                    'synced_at' => $p->synced_at?->toIso8601String(),
                ];
            });

        $user = $request->user();

        return Inertia::render('Tiendanube/Index', [
            'configuracion' => [
                'store_id' => $config->store_id,
                'app_id' => $config->app_id,
                'scopes' => $config->scopes,
                'store_name' => $config->store_name,
                'store_url' => $config->store_url,
                'credenciales_configuradas' => $config->credencialesConfiguradas(),
                'tiene_token' => ! empty($config->accessTokenDecrypted()),
                'webhook_url' => app(TiendanubeWebhookService::class)->webhookUrl(),
                'webhook_events' => app(TiendanubeWebhookService::class)->eventosRecomendados(),
            ],
            'productos' => $productos,
            'totales' => [
                'productos' => TiendanubeProducto::count(),
                'categorias' => TiendanubeCategoria::count(),
                'productos_alerta_imagenes' => TiendanubeProducto::query()
                    ->whereHas('imagenes', fn ($img) => $img->where('requiere_revision', true))
                    ->count(),
            ],
            'procesoActivo' => $procesoActivo,
            'imageImportActivo' => $imageImportActivo,
            'ultimosImportImagenes' => TiendanubeImageImport::orderByDesc('id')->limit(5)->get(),
            'ultimosSyncs' => TiendanubeSyncLog::orderByDesc('id')->limit(5)->get(),
            'categorias' => TiendanubeCategoria::orderBy('id')->get()->map(fn (TiendanubeCategoria $c) => [
                'id' => $c->id,
                'nombre' => $c->nombreVisible(),
            ]),
            'filters' => [
                'search' => $query,
                'imagenes_alerta' => $filtroAlertaImagenes,
            ],
            'permisos' => [
                'ver' => $user->can('tiendanube.ver'),
                'configurar' => $user->can('tiendanube.configurar'),
                'sincronizar' => $user->can('tiendanube.sincronizar'),
                'editar' => $user->can('tiendanube.productos.editar'),
            ],
        ]);
    }

    public function guardarConfiguracion(Request $request, TiendanubeCatalogoWipeService $wipe): JsonResponse
    {
        Gate::authorize('tiendanube.configurar');

        $request->validate([
            'store_id' => 'nullable|integer|min:1',
            'app_id' => 'nullable|string|max:64',
            'access_token' => 'nullable|string',
            'scopes' => 'nullable|string|max:500',
            'limpiar_catalogo' => 'nullable|boolean',
            'iniciar_sync' => 'nullable|boolean',
        ]);

        $config = TiendanubeConfiguracion::obtener();
        $storeAnterior = $config->store_id;
        $storeNuevo = $request->filled('store_id') ? (int) $request->input('store_id') : $storeAnterior;
        $cambioTienda = $storeAnterior && $storeNuevo && (int) $storeAnterior !== (int) $storeNuevo;
        $limpiar = $request->boolean('limpiar_catalogo');

        if ($cambioTienda && ! $limpiar) {
            return response()->json([
                'success' => false,
                'requires_wipe_confirmation' => true,
                'message' => 'Al cambiar de tienda se borrará el catálogo local de la tienda anterior. Confirma para continuar.',
                'store_id_anterior' => $storeAnterior,
                'store_id_nuevo' => $storeNuevo,
            ], 409);
        }

        if ($request->filled('store_id')) {
            $config->store_id = (int) $request->input('store_id');
        }
        if ($request->filled('app_id')) {
            $config->app_id = $request->input('app_id');
        }
        if ($request->filled('scopes')) {
            $config->scopes = $request->input('scopes');
        }
        if ($request->filled('access_token')) {
            $config->setAccessTokenPlain($request->input('access_token'));
        }

        $config->save();

        $borrados = null;
        if ($limpiar) {
            $borrados = $wipe->wipe();
        }

        $syncLogId = null;
        if ($limpiar && $request->boolean('iniciar_sync') && $config->credencialesConfiguradas() && ! TiendanubeSyncLog::activo()) {
            $log = TiendanubeSyncLog::create([
                'tipo' => 'completo',
                'estado' => 'pendiente',
            ]);
            SyncTiendanubeCatalogoJob::dispatch($log->id);
            $syncLogId = $log->id;
        }

        return response()->json([
            'success' => true,
            'message' => $limpiar
                ? 'Configuración guardada. Catálogo local limpiado'.($syncLogId ? ' y sincronización iniciada.' : '.')
                : 'Configuración Tiendanube guardada.',
            'configuracion' => [
                'store_id' => $config->store_id,
                'app_id' => $config->app_id,
                'credenciales_configuradas' => $config->credencialesConfiguradas(),
            ],
            'catalogo_borrado' => $borrados,
            'sync_log_id' => $syncLogId,
        ]);
    }

    public function limpiarCatalogo(Request $request, TiendanubeCatalogoWipeService $wipe): JsonResponse
    {
        Gate::authorize('tiendanube.configurar');

        $request->validate([
            'iniciar_sync' => 'nullable|boolean',
        ]);

        $config = TiendanubeConfiguracion::obtener();
        $borrados = $wipe->wipe();

        $syncLogId = null;
        if ($request->boolean('iniciar_sync')) {
            if (! $config->credencialesConfiguradas()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Catálogo limpiado, pero faltan credenciales para sincronizar.',
                    'catalogo_borrado' => $borrados,
                ], 422);
            }
            if (TiendanubeSyncLog::activo()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Catálogo limpio, pero ya hay una sincronización en curso.',
                    'catalogo_borrado' => $borrados,
                ], 409);
            }

            $log = TiendanubeSyncLog::create([
                'tipo' => 'completo',
                'estado' => 'pendiente',
            ]);
            SyncTiendanubeCatalogoJob::dispatch($log->id);
            $syncLogId = $log->id;
        }

        return response()->json([
            'success' => true,
            'message' => $syncLogId
                ? 'Catálogo local borrado. Sincronización iniciada. Credenciales intactas.'
                : 'Catálogo local borrado. Credenciales intactas.',
            'catalogo_borrado' => $borrados,
            'sync_log_id' => $syncLogId,
            'configuracion' => [
                'store_id' => $config->store_id,
                'credenciales_configuradas' => $config->credencialesConfiguradas(),
            ],
        ]);
    }

    public function probarConexion(TiendanubeApiClient $api): JsonResponse
    {
        Gate::authorize('tiendanube.configurar');

        try {
            $store = $api->getStore();
            $config = TiendanubeConfiguracion::obtener();
            $config->fill([
                'store_name' => $store['name']['es']
                    ?? $store['name']['es_MX']
                    ?? (is_string($store['name'] ?? null) ? $store['name'] : $config->store_name),
                'store_url' => $store['original_domain']
                    ?? $store['url_with_protocol']
                    ?? ($store['domains'][0] ?? null)
                    ?? $config->store_url,
            ])->save();

            return response()->json([
                'success' => true,
                'message' => 'Conexión exitosa con Tiendanube.',
                'store' => [
                    'id' => $store['id'] ?? $config->store_id,
                    'name' => $config->store_name,
                    'url' => $config->store_url,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function sincronizar(): JsonResponse
    {
        Gate::authorize('tiendanube.sincronizar');

        $config = TiendanubeConfiguracion::obtener();
        if (! $config->credencialesConfiguradas()) {
            return response()->json([
                'success' => false,
                'message' => 'Configura store_id y access_token antes de sincronizar.',
            ], 422);
        }

        if (TiendanubeSyncLog::activo()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya hay una sincronización en curso.',
            ], 409);
        }

        $log = TiendanubeSyncLog::create([
            'tipo' => 'completo',
            'estado' => 'pendiente',
        ]);

        SyncTiendanubeCatalogoJob::dispatch($log->id);

        return response()->json([
            'success' => true,
            'message' => 'Sincronización iniciada.',
            'sync_log_id' => $log->id,
        ]);
    }

    public function progreso(int $id): JsonResponse
    {
        Gate::authorize('tiendanube.ver');

        $log = TiendanubeSyncLog::findOrFail($id);

        return response()->json([
            'id' => $log->id,
            'tipo' => $log->tipo,
            'estado' => $log->estado,
            'total_categorias' => $log->total_categorias,
            'total_productos' => $log->total_productos,
            'procesados_categorias' => $log->procesados_categorias,
            'procesados_productos' => $log->procesados_productos,
            'porcentaje' => $log->progresoPorcentaje(),
            'mensaje_error' => $log->mensaje_error,
            'updated_at' => $log->updated_at?->toIso8601String(),
        ]);
    }

    public function producto(int $id): JsonResponse
    {
        Gate::authorize('tiendanube.ver');

        $producto = TiendanubeProducto::with(['imagenes', 'variantes', 'categorias'])
            ->findOrFail($id);

        return response()->json([
            'id' => $producto->id,
            'nombre' => $producto->nombreVisible(),
            'name' => $producto->name,
            'description' => $producto->description,
            'handle' => $producto->handle,
            'brand' => $producto->brand,
            'published' => $producto->published,
            'free_shipping' => $producto->free_shipping,
            'requires_shipping' => $producto->requires_shipping,
            'video_url' => $producto->video_url,
            'seo_title' => $producto->seo_title,
            'seo_description' => $producto->seo_description,
            'tags' => $producto->tags,
            'attributes' => $producto->attributes,
            'canonical_url' => $producto->canonical_url,
            'synced_at' => $producto->synced_at?->toIso8601String(),
            'gelia_producto_id' => $producto->gelia_producto_id,
            'imagenes' => $producto->imagenes,
            'variantes' => $producto->variantes,
            'categorias' => $producto->categorias->map(fn (TiendanubeCategoria $c) => [
                'id' => $c->id,
                'nombre' => $c->nombreVisible(),
                'seo_title' => $c->seo_title,
            ]),
            'categoria_ids' => $producto->categorias->pluck('id')->values(),
        ]);
    }

    public function storeProducto(StoreTiendanubeProductoRequest $request, TiendanubeProductoWriteService $write): JsonResponse
    {
        try {
            $producto = $write->crear($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Producto creado en Tiendanube.',
                'producto_id' => $producto->id,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function updateProducto(UpdateTiendanubeProductoRequest $request, int $id, TiendanubeProductoWriteService $write): JsonResponse
    {
        TiendanubeProducto::findOrFail($id);

        $datos = $request->validated();
        if ($request->boolean('replace_categories')) {
            $datos['replace_categories'] = true;
        }

        try {
            $producto = $write->actualizar($id, $datos);

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado en Tiendanube.',
                'producto_id' => $producto->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function storeImagen(StoreTiendanubeProductoImagenRequest $request, int $id, TiendanubeProductoWriteService $write): JsonResponse
    {
        TiendanubeProducto::findOrFail($id);

        try {
            $imagen = $write->agregarImagen(
                $id,
                $request->input('src'),
                $request->file('file'),
                $request->filled('position') ? (int) $request->input('position') : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Imagen agregada.',
                'imagen' => $imagen,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function importarImagenes(Request $request, TiendanubeImageImportService $service): JsonResponse
    {
        Gate::authorize('tiendanube.productos.editar');

        $request->validate([
            'zip' => ['required', 'file', 'max:512000', 'mimes:zip'],
        ]);

        try {
            $import = $service->iniciarDesdeZip($request->file('zip'), $request->user());

            $resumen = $import->resumenMotivos();

            return response()->json([
                'success' => true,
                'message' => 'Importación iniciada.',
                'import_id' => $import->id,
                'preview' => [
                    'total' => $import->total_archivos,
                    'matched' => $resumen['matched'],
                    'nombre_invalido' => $resumen['nombre_invalido'],
                    'sku_no_encontrado' => $resumen['sku_no_encontrado'],
                    'archivo_grande' => $resumen['archivo_grande'],
                    'sin_match' => $resumen['omitidos'] + $resumen['errores'],
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function progresoImportImagenes(int $id): JsonResponse
    {
        Gate::authorize('tiendanube.ver');

        $import = TiendanubeImageImport::findOrFail($id);

        $errores = $import->items()
            ->whereIn('estado', ['error', 'omitido'])
            ->orderByDesc('id')
            ->limit(100)
            ->get(['filename', 'sku', 'estado', 'motivo', 'mensaje', 'position']);

        $resumen = $import->resumenMotivos();
        $totalFallidos = $resumen['omitidos'] + $resumen['errores'];

        return response()->json([
            'id' => $import->id,
            'estado' => $import->estado,
            'total_archivos' => $import->total_archivos,
            'procesados' => $import->procesados,
            'exitosos' => $import->exitosos,
            'fallidos' => $import->fallidos,
            'porcentaje' => $import->progresoPorcentaje(),
            'mensaje_error' => $import->mensaje_error,
            'resumen' => $resumen,
            'errores_total' => $totalFallidos,
            'errores' => $errores->map(fn ($i) => [
                'filename' => $i->filename,
                'sku' => $i->sku,
                'estado' => $i->estado,
                'motivo' => $i->motivo,
                'mensaje' => $i->mensaje,
                'position' => $i->position,
            ]),
            'updated_at' => $import->updated_at?->toIso8601String(),
        ]);
    }

    public function reporteImportImagenes(int $id)
    {
        Gate::authorize('tiendanube.ver');

        $import = TiendanubeImageImport::findOrFail($id);

        $filename = 'tiendanube-import-'.$import->id.'-errores.csv';

        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['filename', 'sku', 'position', 'estado', 'motivo', 'mensaje', 'producto_id']);

            $import->items()
                ->whereIn('estado', ['error', 'omitido'])
                ->orderBy('id')
                ->cursor()
                ->each(function ($item) use ($out) {
                    fputcsv($out, [
                        $item->filename,
                        $item->sku,
                        $item->position,
                        $item->estado,
                        $item->motivo,
                        $item->mensaje,
                        $item->producto_id,
                    ]);
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function listarWebhooks(TiendanubeWebhookService $webhooks): JsonResponse
    {
        Gate::authorize('tiendanube.configurar');

        try {
            return response()->json([
                'success' => true,
                'webhooks' => $webhooks->listar(),
                'webhook_url' => $webhooks->webhookUrl(),
                'eventos_recomendados' => $webhooks->eventosRecomendados(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function crearWebhook(Request $request, TiendanubeWebhookService $webhooks): JsonResponse
    {
        Gate::authorize('tiendanube.configurar');

        $data = $request->validate([
            'event' => ['required', 'string', 'max:150'],
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $webhook = $webhooks->crear($data['event'], $data['url'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Webhook creado en Tiendanube.',
                'webhook' => $webhook,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function actualizarWebhook(Request $request, int $id, TiendanubeWebhookService $webhooks): JsonResponse
    {
        Gate::authorize('tiendanube.configurar');

        $data = $request->validate([
            'event' => ['required', 'string', 'max:150'],
            'url' => ['required', 'string', 'max:2048'],
        ]);

        try {
            $webhook = $webhooks->actualizar($id, $data['event'], $data['url']);

            return response()->json([
                'success' => true,
                'message' => 'Webhook actualizado.',
                'webhook' => $webhook,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function eliminarWebhook(int $id, TiendanubeWebhookService $webhooks): JsonResponse
    {
        Gate::authorize('tiendanube.configurar');

        try {
            $webhooks->eliminar($id);

            return response()->json([
                'success' => true,
                'message' => 'Webhook eliminado.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function aplicarWebhooksRecomendados(Request $request, TiendanubeWebhookService $webhooks): JsonResponse
    {
        Gate::authorize('tiendanube.configurar');

        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $resultado = $webhooks->aplicarRecomendados($data['url'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Webhooks recomendados aplicados.',
                'resultado' => $resultado,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

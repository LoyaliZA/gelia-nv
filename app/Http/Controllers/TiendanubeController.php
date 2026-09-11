<?php

namespace App\Http\Controllers;

use App\Exceptions\Tiendanube\TiendanubeActualizacionParcialException;
use App\Exceptions\Tiendanube\TiendanubeOperacionConflictException;
use App\Http\Requests\Tiendanube\StoreTiendanubeProductoImagenRequest;
use App\Http\Requests\Tiendanube\StoreTiendanubeProductoRequest;
use App\Http\Requests\Tiendanube\UpdateTiendanubeProductoRequest;
use App\Jobs\Tiendanube\SyncTiendanubeCatalogoJob;
use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeImageImport;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoImagenOperacion;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Models\Tiendanube\TiendanubeUbicacion;
use App\Models\Tiendanube\TiendanubeWebhookDelivery;
use App\Services\Tiendanube\OptimizarImagenTiendanubeService;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeCatalogoWipeService;
use App\Services\Tiendanube\TiendanubeImageImportService;
use App\Services\Tiendanube\TiendanubeImageSkuResolverService;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use App\Services\Tiendanube\TiendanubeProductoImagenOperacionService;
use App\Services\Tiendanube\TiendanubeProductoWriteService;
use App\Services\Auditoria\RegistrarAuditoriaConfiguracionService;
use App\Services\Tiendanube\TiendanubeWebhookInboxService;
use App\Services\Tiendanube\TiendanubeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
            ->when($query, fn ($q) => $q->buscarTextoCatalogo((string) $query, true))
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
                'locations_probe' => $config->locations_probe,
                'multi_inventario_activo' => (bool) $config->multi_inventario_activo,
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
            'ubicaciones' => $this->ubicacionesActivas(),
            'inventario' => $this->inventarioEstado($config),
        ]);
    }

    public function imagenesIndex(Request $request): Response
    {
        Gate::authorize('tiendanube.productos.editar');

        $config = TiendanubeConfiguracion::obtener();
        $query = $request->input('search');
        $filtroAlertaImagenes = $request->boolean('imagenes_alerta');
        $filtroSinImagen = $request->boolean('sin_imagen');
        $imageImportActivo = TiendanubeImageImport::activo();

        $productos = TiendanubeProducto::query()
            ->with(['variantes', 'imagenes'])
            ->when($query, fn ($q) => $q->buscarTextoCatalogo((string) $query, false))
            ->when($filtroAlertaImagenes, function ($q) {
                $q->whereHas('imagenes', fn ($img) => $img->where('requiere_revision', true));
            })
            ->when($filtroSinImagen, function ($q) {
                $q->whereDoesntHave('imagenes');
            })
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString()
            ->through(function (TiendanubeProducto $p) {
                $primera = $p->imagenes->sortBy('position')->first();

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombreVisible(),
                    'sku' => $p->skuPrincipal(),
                    'imagen' => $primera?->src,
                    'width' => $primera?->width,
                    'height' => $primera?->height,
                    'requiere_revision' => (bool) ($primera?->requiere_revision),
                    'alerta_pequena' => (bool) ($primera?->alerta_pequena),
                    'alerta_no_cuadrada' => (bool) ($primera?->alerta_no_cuadrada),
                    'num_imagenes' => $p->imagenes->count(),
                    'tiene_alerta_imagenes' => $p->imagenes->contains(fn ($img) => (bool) $img->requiere_revision),
                ];
            });

        $user = $request->user();

        return Inertia::render('Tiendanube/Imagenes', [
            'configuracion' => [
                'credenciales_configuradas' => $config->credencialesConfiguradas(),
                'store_name' => $config->store_name,
            ],
            'productos' => $productos,
            'totales' => [
                'productos' => TiendanubeProducto::count(),
                'sin_imagen' => TiendanubeProducto::query()->whereDoesntHave('imagenes')->count(),
                'productos_alerta_imagenes' => TiendanubeProducto::query()
                    ->whereHas('imagenes', fn ($img) => $img->where('requiere_revision', true))
                    ->count(),
            ],
            'imageImportActivo' => $imageImportActivo,
            'ultimosImportImagenes' => TiendanubeImageImport::orderByDesc('id')->limit(5)->get(),
            'filters' => [
                'search' => $query,
                'imagenes_alerta' => $filtroAlertaImagenes,
                'sin_imagen' => $filtroSinImagen,
            ],
            'permisos' => [
                'ver' => $user->can('tiendanube.ver'),
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

        $ops = app(TiendanubeOperacionTiendaService::class);

        try {
            if ($cambioTienda || $limpiar) {
                $ops->assertAdmisible(
                    $cambioTienda
                        ? TiendanubeOperacionTiendaService::TIPO_CAMBIO_TIENDA
                        : TiendanubeOperacionTiendaService::TIPO_CATALOGO_WIPE,
                    $storeAnterior ? (int) $storeAnterior : null
                );
            }
        } catch (TiendanubeOperacionConflictException $e) {
            return $this->jsonTiendanubeError($e);
        }

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

        if ($cambioTienda || $request->filled('access_token')) {
            $config->increment('config_generation');
            $config->refresh();
        }

        $borrados = null;
        if ($limpiar) {
            $borrados = $wipe->wipe();
        }

        $syncLogId = null;
        if ($limpiar && $request->boolean('iniciar_sync') && $config->credencialesConfiguradas()) {
            try {
                $log = $this->despacharSyncCompleto($config);
                $syncLogId = $log->id;
            } catch (TiendanubeOperacionConflictException $e) {
                return $this->jsonTiendanubeError($e);
            }
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
        $ops = app(TiendanubeOperacionTiendaService::class);

        try {
            $ops->assertAdmisible(
                TiendanubeOperacionTiendaService::TIPO_CATALOGO_WIPE,
                $config->store_id ? (int) $config->store_id : null
            );
        } catch (TiendanubeOperacionConflictException $e) {
            return $this->jsonTiendanubeError($e);
        }

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

            try {
                $log = $this->despacharSyncCompleto($config);
                $syncLogId = $log->id;
            } catch (TiendanubeOperacionConflictException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Catálogo limpio, pero ya hay una sincronización en curso.',
                    'catalogo_borrado' => $borrados,
                ], 409);
            }
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
            $probe = $api->probeReadOnly();
            $store = $probe['store'];
            $config = TiendanubeConfiguracion::obtener();
            $config->fill([
                'store_name' => $store['name']['es']
                    ?? $store['name']['es_MX']
                    ?? (is_string($store['name'] ?? null) ? $store['name'] : $config->store_name),
                'store_url' => $store['original_domain']
                    ?? $store['url_with_protocol']
                    ?? ($store['domains'][0] ?? null)
                    ?? $config->store_url,
                'locations_probe' => $probe['checks']['locations'] ?? $config->locations_probe,
            ])->save();

            return response()->json([
                'success' => true,
                'message' => 'Conexión exitosa con Tiendanube.',
                'store' => [
                    'id' => $store['id'] ?? $config->store_id,
                    'name' => $config->store_name,
                    'url' => $config->store_url,
                ],
                'api_version' => $probe['api_version'],
                'api_host' => $probe['api_host'],
                'checks' => $probe['checks'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function sincronizar(Request $request, TiendanubeWebhookService $webhooks): JsonResponse
    {
        Gate::authorize('tiendanube.sincronizar');

        $request->validate([
            'confirmar_depuracion_masiva' => 'nullable|boolean',
        ]);

        $config = TiendanubeConfiguracion::obtener();
        if (! $config->credencialesConfiguradas()) {
            return response()->json([
                'success' => false,
                'message' => 'Configura store_id y access_token antes de sincronizar.',
            ], 422);
        }

        try {
            $resultado = $webhooks->asegurarRecomendados();
            if (($resultado['errores'] ?? []) !== []) {
                Log::warning('Tiendanube: webhooks recomendados con errores antes del sync', [
                    'errores' => $resultado['errores'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Tiendanube: no se pudieron asegurar webhooks antes del sync', [
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $log = $this->despacharSyncCompleto($config, $request->boolean('confirmar_depuracion_masiva'));
        } catch (TiendanubeOperacionConflictException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ya hay una sincronización en curso.',
            ], 409);
        }

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
            'eliminados_productos' => $log->eliminados_productos,
            'eliminados_categorias' => $log->eliminados_categorias,
            'fase' => $log->fase,
            'candidatos_productos' => $log->candidatos_productos,
            'candidatos_categorias' => $log->candidatos_categorias,
            'pendientes_confirmacion' => $log->pendientes_confirmacion,
            'porcentaje' => $log->progresoPorcentaje(),
            'mensaje_error' => $log->mensaje_error,
            'updated_at' => $log->updated_at?->toIso8601String(),
        ]);
    }

    public function producto(int $id): JsonResponse
    {
        Gate::authorize('tiendanube.ver');

        $producto = TiendanubeProducto::with(['imagenes', 'variantes.nivelesInventario.ubicacion', 'categorias'])
            ->findOrFail($id);

        $config = TiendanubeConfiguracion::obtener();

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
            'variantes' => $producto->variantes->map(function (TiendanubeProductoVariante $v) {
                $row = $v->toArray();
                $row['stock_resumen'] = $v->stockResumen();

                return $row;
            }),
            'categorias' => $producto->categorias->map(fn (TiendanubeCategoria $c) => [
                'id' => $c->id,
                'nombre' => $c->nombreVisible(),
                'seo_title' => $c->seo_title,
            ]),
            'categoria_ids' => $producto->categorias->pluck('id')->values(),
            'ubicaciones' => $this->ubicacionesActivas(),
            'inventario' => $this->inventarioEstado($config),
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
            return $this->jsonTiendanubeError($e);
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
        } catch (TiendanubeActualizacionParcialException $e) {
            return response()->json([
                'success' => false,
                'parcial' => true,
                'producto_actualizado' => true,
                'producto_id' => $e->producto?->id ?? $id,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->jsonTiendanubeError($e);
        }
    }

    public function storeImagen(StoreTiendanubeProductoImagenRequest $request, int $id, TiendanubeProductoWriteService $write): JsonResponse
    {
        TiendanubeProducto::findOrFail($id);

        try {
            $reemplazar = $request->boolean('reemplazar', true);

            $carga = $write->agregarImagen(
                $id,
                $request->input('src'),
                $request->file('file'),
                $request->filled('position') ? (int) $request->input('position') : null,
                $reemplazar,
                OptimizarImagenTiendanubeService::opcionesDesdeRequest($request),
                $request->input('solicitud_clave'),
                $request->user()?->id
            );

            $operacion = $carga->operacion;
            $parcial = $operacion->esParcial();
            $message = $parcial
                ? 'Imagen cargada, pero no se pudieron retirar todas las anteriores. Puede completar el reemplazo sin volver a subir el archivo.'
                : ($reemplazar ? 'Imagen reemplazada.' : 'Imagen agregada.');

            return response()->json([
                'success' => true,
                'parcial' => $parcial,
                'message' => $message,
                'imagen' => $carga->imagen,
                'operacion' => $operacion->toApi(),
            ], 201);
        } catch (\Throwable $e) {
            return $this->jsonTiendanubeError($e);
        }
    }

    public function reconciliarImagenOperacion(string $id, TiendanubeProductoImagenOperacionService $operaciones): JsonResponse
    {
        Gate::authorize('tiendanube.productos.editar');

        $op = TiendanubeProductoImagenOperacion::query()->findOrFail($id);

        try {
            $carga = $operaciones->reconciliar($op);
            $operacion = $carga->operacion;
            $parcial = $operacion->esParcial();

            return response()->json([
                'success' => ! $parcial,
                'parcial' => $parcial,
                'message' => $parcial
                    ? 'Aún faltan imágenes anteriores por retirar.'
                    : 'Reemplazo completado.',
                'imagen' => $carga->imagen,
                'operacion' => $operacion->toApi(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function resolverSku(Request $request, TiendanubeImageSkuResolverService $resolver): JsonResponse
    {
        Gate::authorize('tiendanube.productos.editar');

        $sku = trim((string) $request->query('sku', ''));
        $resolucion = $resolver->resolver($sku);
        $primero = $resolucion['candidatos'][0] ?? null;

        return response()->json([
            'sku' => $resolucion['sku'],
            'estado' => $resolucion['estado'],
            'encontrado' => $resolucion['estado'] === TiendanubeImageSkuResolverService::ESTADO_ENCONTRADO,
            'producto_id' => $resolucion['producto_id'],
            'nombre' => $primero['nombre'] ?? null,
            'imagen_actual' => $primero['imagen_actual'] ?? null,
            'candidatos' => $resolucion['candidatos'],
        ]);
    }

    public function importarImagenes(Request $request, TiendanubeImageImportService $service): JsonResponse
    {
        Gate::authorize('tiendanube.productos.editar');

        $request->validate([
            'zip' => ['required', 'file', 'max:512000', 'mimes:zip'],
            'convertir_webp' => ['sometimes', 'boolean'],
            'modo_1280' => ['sometimes', 'string', 'in:none,fit,square'],
        ]);

        try {
            $import = $service->iniciarDesdeZip(
                $request->file('zip'),
                $request->user(),
                OptimizarImagenTiendanubeService::opcionesDesdeRequest($request)
            );

            // Con cola async el índice del ZIP corre en el job; con sync ya puede haber resumen.
            $resumen = $import->items()->exists() ? $import->resumenMotivos() : null;

            return response()->json([
                'success' => true,
                'message' => 'Importación iniciada. Extracción e indexado en segundo plano.',
                'import_id' => $import->id,
                'preview' => $resumen ? [
                    'total' => $import->total_archivos,
                    'matched' => $resumen['matched'],
                    'nombre_invalido' => $resumen['nombre_invalido'],
                    'sku_no_encontrado' => $resumen['sku_no_encontrado'],
                    'archivo_grande' => $resumen['archivo_grande'],
                    'sin_match' => $resumen['omitidos'] + $resumen['errores'],
                ] : null,
            ], 201);
        } catch (\Throwable $e) {
            return $this->jsonTiendanubeError($e);
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

        $requiereRevision = $import->estado === TiendanubeImageImport::ESTADO_REQUIERE_REVISION
            || $import->estado === TiendanubeImageImport::ESTADO_LISTA && ! $import->confirmado_at;

        return response()->json([
            'id' => $import->id,
            'estado' => $import->estado,
            'total_archivos' => $import->total_archivos,
            'procesados' => $import->procesados,
            'exitosos' => $import->exitosos,
            'fallidos' => $import->fallidos,
            'porcentaje' => $import->progresoPorcentaje(),
            'mensaje_error' => $import->mensaje_error,
            'confirmado_at' => $import->confirmado_at?->toIso8601String(),
            'requiere_revision' => $requiereRevision,
            'resumen' => $resumen,
            'errores_total' => $totalFallidos,
            'alertas_dimension' => app(TiendanubeImageImportService::class)->contarAlertasDimension($import),
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

    public function importarImagenesArchivos(Request $request, TiendanubeImageImportService $service): JsonResponse
    {
        Gate::authorize('tiendanube.productos.editar');

        $request->validate([
            'imagenes' => ['required', 'array', 'min:1', 'max:100'],
            'imagenes.*' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp'],
            'reemplazar' => ['sometimes', 'boolean'],
            'convertir_webp' => ['sometimes', 'boolean'],
            'modo_1280' => ['sometimes', 'string', 'in:none,fit,square'],
        ]);

        try {
            $reemplazarPrimera = $request->boolean('reemplazar', true);
            $import = $service->iniciarDesdeArchivos(
                $request->file('imagenes', []),
                $request->user(),
                $reemplazarPrimera,
                OptimizarImagenTiendanubeService::opcionesDesdeRequest($request)
            );

            $resumen = $import->resumenMotivos();

            return response()->json([
                'success' => true,
                'message' => 'Importación de archivos iniciada.',
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
            return $this->jsonTiendanubeError($e);
        }
    }

    public function revisionImportImagenes(int $id, TiendanubeImageImportService $service): JsonResponse
    {
        Gate::authorize('tiendanube.productos.editar');

        $import = TiendanubeImageImport::findOrFail($id);

        return response()->json([
            'id' => $import->id,
            'estado' => $import->estado,
            'confirmado_at' => $import->confirmado_at?->toIso8601String(),
            'reemplazar_primera' => (bool) $import->reemplazar_primera,
            'items' => $service->filasRevision($import),
        ]);
    }

    public function confirmarImportImagenes(Request $request, int $id, TiendanubeImageImportService $service): JsonResponse
    {
        Gate::authorize('tiendanube.productos.editar');

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.producto_id' => ['nullable', 'integer'],
            'items.*.excluido' => ['sometimes', 'boolean'],
        ]);

        try {
            $import = $service->confirmarRevision(
                TiendanubeImageImport::findOrFail($id),
                $request->input('items', [])
            );

            return response()->json([
                'success' => true,
                'message' => $import->confirmado_at && in_array($import->estado, [
                    TiendanubeImageImport::ESTADO_LISTA,
                    TiendanubeImageImport::ESTADO_PROCESANDO,
                ], true)
                    ? 'Importación confirmada. Carga en segundo plano.'
                    : 'Revisión guardada.',
                'import_id' => $import->id,
                'estado' => $import->estado,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonTiendanubeError($e);
        }
    }

    public function reintentarImportImagenes(int $id, TiendanubeImageImportService $service): JsonResponse
    {
        Gate::authorize('tiendanube.productos.editar');

        try {
            $import = $service->reintentarFallidos(TiendanubeImageImport::findOrFail($id));

            return response()->json([
                'success' => true,
                'message' => 'Se reencolaron los errores recuperables.',
                'import_id' => $import->id,
                'estado' => $import->estado,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonTiendanubeError($e);
        }
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

    public function reporteImportDimensiones(Request $request, int $id)
    {
        Gate::authorize('tiendanube.ver');

        $import = TiendanubeImageImport::findOrFail($id);
        $detalle = $this->filtrosDetalleAlerta($request);
        $filename = 'tiendanube-import-'.$import->id.'-dimensiones.csv';

        return response()->streamDownload(function () use ($import, $detalle) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['producto_id', 'sku', 'filename', 'imagen_id', 'src', 'width', 'height', 'detalle']);

            $import->items()
                ->where('estado', 'ok')
                ->whereNotNull('imagen_tn_id')
                ->orderBy('id')
                ->cursor()
                ->each(function ($item) use ($out, $detalle) {
                    $img = TiendanubeProductoImagen::find($item->imagen_tn_id);
                    if (! $img || ! $this->imagenCoincideDetalle($img, $detalle)) {
                        return;
                    }
                    fputcsv($out, [
                        $item->producto_id,
                        $item->sku,
                        $item->filename,
                        $img->id,
                        $img->src,
                        $img->width,
                        $img->height,
                        $this->etiquetasAlertaImagen($img),
                    ]);
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function reporteAlertasImagenes(Request $request)
    {
        Gate::authorize('tiendanube.ver');

        $detalle = $this->filtrosDetalleAlerta($request);
        $filename = 'tiendanube-imagenes-alertas-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($detalle) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['producto_id', 'sku', 'filename', 'imagen_id', 'src', 'width', 'height', 'detalle']);

            $this->queryImagenesPorDetalle($detalle)
                ->orderBy('producto_id')
                ->orderBy('id')
                ->cursor()
                ->each(function (TiendanubeProductoImagen $img) use ($out) {
                    $sku = TiendanubeProductoVariante::where('producto_id', $img->producto_id)->orderBy('id')->value('sku');
                    fputcsv($out, [
                        $img->producto_id,
                        $sku,
                        basename((string) $img->src) ?: null,
                        $img->id,
                        $img->src,
                        $img->width,
                        $img->height,
                        $this->etiquetasAlertaImagen($img),
                    ]);
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function reporteSinFoto(Request $request)
    {
        Gate::authorize('tiendanube.ver');

        $filename = 'tiendanube-sin-foto-'.now()->format('Ymd-His').'.csv';
        $filtrarPublicado = $request->has('publicado');
        $soloPublicado = $filtrarPublicado ? $request->boolean('publicado') : null;

        return response()->streamDownload(function () use ($filtrarPublicado, $soloPublicado) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['producto_id', 'sku', 'nombre', 'estado_publicacion', 'synced_at', 'detalle']);

            $query = TiendanubeProducto::query()->whereDoesntHave('imagenes');
            if ($filtrarPublicado) {
                $query->where('published', $soloPublicado);
            }

            $query
                ->with(['variantes' => fn ($q) => $q->orderBy('id')->limit(1)])
                ->orderBy('id')
                ->cursor()
                ->each(function (TiendanubeProducto $p) use ($out) {
                    fputcsv($out, [
                        $p->id,
                        $p->variantes->first()?->sku,
                        $p->nombreVisible(),
                        $p->published ? 'publicado' : 'no publicado',
                        $p->synced_at?->toIso8601String(),
                        'sin imagen',
                    ]);
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<string>
     */
    private function filtrosDetalleAlerta(Request $request): array
    {
        $raw = $request->input('detalle', ['pequena', 'no_cuadrada']);
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        $selected = array_values(array_intersect(['pequena', 'no_cuadrada'], array_map('strval', (array) $raw)));

        return $selected !== [] ? $selected : ['pequena', 'no_cuadrada'];
    }

    /**
     * @param  list<string>  $detalle
     */
    private function queryImagenesPorDetalle(array $detalle)
    {
        return TiendanubeProductoImagen::query()
            ->where('requiere_revision', true)
            ->where(function ($q) use ($detalle) {
                if (in_array('pequena', $detalle, true)) {
                    $q->orWhere('alerta_pequena', true);
                }
                if (in_array('no_cuadrada', $detalle, true)) {
                    $q->orWhere('alerta_no_cuadrada', true);
                }
            });
    }

    /**
     * @param  list<string>  $detalle
     */
    private function imagenCoincideDetalle(TiendanubeProductoImagen $img, array $detalle): bool
    {
        if (! $img->requiere_revision) {
            return false;
        }

        return (in_array('pequena', $detalle, true) && $img->alerta_pequena)
            || (in_array('no_cuadrada', $detalle, true) && $img->alerta_no_cuadrada);
    }

    private function etiquetasAlertaImagen(TiendanubeProductoImagen $img): string
    {
        $etiquetas = [];
        if ($img->alerta_pequena) {
            $etiquetas[] = 'lado menor < 800px';
        }
        if ($img->alerta_no_cuadrada) {
            $etiquetas[] = 'no cuadrada';
        }

        return $etiquetas !== [] ? implode('; ', $etiquetas) : 'requiere revisión';
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

    public function listarEntregasWebhook(): JsonResponse
    {
        Gate::authorize('tiendanube.configurar');

        $entregas = TiendanubeWebhookDelivery::query()
            ->latest('id')
            ->limit(20)
            ->get([
                'id',
                'event',
                'resource_id',
                'status',
                'error',
                'attempts',
                'next_attempt_at',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'entregas' => $entregas->map(fn (TiendanubeWebhookDelivery $entrega) => [
                'id' => $entrega->id,
                'event' => $entrega->event,
                'resource_id' => $entrega->resource_id,
                'status' => $entrega->status,
                'error' => $entrega->error,
                'attempts' => $entrega->attempts,
                'next_attempt_at' => $entrega->next_attempt_at,
                'created_at' => $entrega->created_at,
                'puede_reintentar' => $entrega->puedeReintentar(),
            ]),
        ]);
    }

    public function reintentarEntregaWebhook(
        TiendanubeWebhookDelivery $delivery,
        TiendanubeWebhookInboxService $inbox
    ): JsonResponse {
        Gate::authorize('tiendanube.configurar');

        try {
            $inbox->scheduleManualRetry($delivery, (int) auth()->id());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        RegistrarAuditoriaConfiguracionService::ejecutar(
            'Tiendanube',
            'Reintento entrega webhook',
            [
                'delivery_id' => $delivery->id,
                'event' => $delivery->event,
                'resource_id' => $delivery->resource_id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Entrega reencolada.',
            'entrega' => [
                'id' => $delivery->id,
                'status' => $delivery->status,
                'puede_reintentar' => $delivery->puedeReintentar(),
            ],
        ]);
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

    /**
     * @return list<array{id: string, nombre: string, is_default: bool}>
     */
    private function ubicacionesActivas(): array
    {
        return TiendanubeUbicacion::query()
            ->where('activa', true)
            ->orderBy('priority')
            ->get()
            ->map(fn (TiendanubeUbicacion $u) => [
                'id' => $u->id,
                'nombre' => $u->nombreVisible(),
                'is_default' => $u->is_default,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{multi_activo: bool, locations_probe: mixed, escritura_habilitada: bool}
     */
    private function inventarioEstado(TiendanubeConfiguracion $config): array
    {
        $probeOk = ($config->locations_probe ?? null) === 'ok';
        $espejo = TiendanubeUbicacion::query()
            ->where('activa', true)
            ->whereNotNull('synced_at')
            ->exists();

        return [
            'multi_activo' => (bool) $config->multi_inventario_activo,
            'locations_probe' => $config->locations_probe,
            'escritura_habilitada' => $probeOk && $espejo,
        ];
    }

    private function despacharSyncCompleto(TiendanubeConfiguracion $config, bool $confirmarDepuracionMasiva = false): TiendanubeSyncLog
    {
        $ops = app(TiendanubeOperacionTiendaService::class);
        $storeId = (int) $config->store_id;
        $generation = (int) ($config->config_generation ?: 1);

        $ops->assertAdmisible(TiendanubeOperacionTiendaService::TIPO_CATALOGO_SYNC, $storeId);

        $log = TiendanubeSyncLog::create([
            'tipo' => 'completo',
            'estado' => 'pendiente',
            'fase' => 'pendiente',
            'store_id' => $storeId,
            'config_generation' => $generation,
            'confirmar_depuracion_masiva' => $confirmarDepuracionMasiva,
        ]);

        try {
            $ops->adquirirExclusiva(
                $storeId,
                TiendanubeOperacionTiendaService::TIPO_CATALOGO_SYNC,
                $log->id,
                $generation
            );
        } catch (TiendanubeOperacionConflictException $e) {
            $log->update([
                'estado' => 'error',
                'fase' => 'error',
                'mensaje_error' => $e->getMessage(),
            ]);

            throw $e;
        }

        SyncTiendanubeCatalogoJob::dispatch($log->id);

        return $log;
    }

    private function jsonTiendanubeError(\Throwable $e, int $default = 400): JsonResponse
    {
        $status = $e instanceof TiendanubeOperacionConflictException ? 409 : $default;

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], $status);
    }
}

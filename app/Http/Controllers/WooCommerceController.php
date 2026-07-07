<?php

namespace App\Http\Controllers;

use App\Jobs\WooCommerce\FetchWooCommercePricesJob;
use App\Jobs\WooCommerce\UpdateWooCommercePricesJob;
use App\Models\User;
use App\Models\Woocommerce\WoocommerceConfiguracion;
use App\Models\Woocommerce\WoocommerceMargin;
use App\Models\Woocommerce\WoocommerceProduct;
use App\Models\Woocommerce\WoocommerceSyncDetail;
use App\Models\Woocommerce\WoocommerceSyncLog;
use App\Models\Woocommerce\WoocommerceTemplate;
use App\Services\WooCommerce\WooCommerceCatalogoService;
use App\Services\WooCommerce\WooCommercePreciosService;
use App\Traits\InteractsWithWooCommerceApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WooCommerceController extends Controller
{
    use InteractsWithWooCommerceApi;

    public function __construct(
        private WooCommercePreciosService $service,
        private WooCommerceCatalogoService $catalogoService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('woocommerce.ver');

        $hoy = date('Y-m-d');
        $query = $request->input('search');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'desc');

        $templatesHoy = WoocommerceTemplate::whereDate('created_at', $hoy)->orderByDesc('id')->get();
        $templatesHistorial = WoocommerceTemplate::whereDate('created_at', '<', $hoy)->orderByDesc('id')->limit(50)->get();

        $config = WoocommerceConfiguracion::obtener();
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();

        $procesoActivo = WoocommerceSyncLog::activo();

        $productos = WoocommerceProduct::when($query, function ($q) use ($query) {
            return $q->where('sku', 'LIKE', "%{$query}%")
                ->orWhere('nombre', 'LIKE', "%{$query}%");
        })->orderBy($sort, $order)->paginate(15)->withQueryString();

        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        $user = auth()->user();

        return Inertia::render('WooCommerce/Index', [
            'templatesHoy' => $templatesHoy,
            'templatesHistorial' => $templatesHistorial,
            'configuracion' => [
                'store_url' => $config->store_url,
                'iva' => $config->iva,
                'credenciales_configuradas' => $config->credencialesConfiguradas(),
                'notified_user_ids' => $config->notified_users ?? [],
                'mapeo_precios' => $config->mapeoPreciosEfectivo(),
            ],
            'margenes' => $margenes,
            'productos' => $productos,
            'procesoActivo' => $procesoActivo,
            'procesosFantasma' => WoocommerceSyncLog::fantasmas(),
            'filters' => [
                'search' => $query,
                'sort' => $sort,
                'order' => $order,
            ],
            'users' => $users,
            'permisos' => [
                'ver' => $user->can('woocommerce.ver'),
                'sincronizar' => $user->can('woocommerce.sincronizar'),
                'configurar' => $user->can('woocommerce.configurar'),
                'auditoria' => $user->can('woocommerce.auditoria'),
                'emergencia' => $user->can('woocommerce.emergencia'),
            ],
        ]);
    }

    public function guardarConfiguracion(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.configurar');

        $request->validate([
            'store_url' => 'nullable|url',
            'iva' => 'required|numeric|min:1',
            'margenes' => 'required|array',
            'notified_users' => 'nullable|array',
            'notified_users.*' => 'integer|exists:users,id',
            'consumer_key' => 'nullable|string',
            'consumer_secret' => 'nullable|string',
            'mapeo_precios' => 'nullable|array',
            'mapeo_precios.sku' => 'required_with:mapeo_precios|string',
            'mapeo_precios.precio_base' => 'required_with:mapeo_precios|string',
        ]);

        $config = WoocommerceConfiguracion::obtener();
        $datos = $request->only(['store_url', 'iva', 'notified_users']);

        if ($request->has('mapeo_precios')) {
            $datos['mapeo_precios'] = [
                'sku' => (string) $request->input('mapeo_precios.sku'),
                'precio_base' => (string) $request->input('mapeo_precios.precio_base'),
            ];
        }

        if ($request->filled('consumer_key')) {
            $datos['consumer_key'] = Crypt::encryptString($request->consumer_key);
        }
        if ($request->filled('consumer_secret')) {
            $datos['consumer_secret'] = Crypt::encryptString($request->consumer_secret);
        }

        $config->update($datos);

        foreach ($request->margenes as $id => $datosMargen) {
            WoocommerceMargin::where('id', $id)->update([
                'multiplicador_rebaja' => $datosMargen['rebaja'],
                'multiplicador_normal' => $datosMargen['normal'],
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Configuración actualizada correctamente.']);
    }

    public function probarConexionApi(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.configurar');

        $request->validate([
            'store_url' => 'nullable|url',
            'consumer_key' => 'nullable|string',
            'consumer_secret' => 'nullable|string',
        ]);

        $config = WoocommerceConfiguracion::obtener();
        $url = rtrim($request->input('store_url') ?: ($config->store_url ?? ''), '/');
        $key = $request->filled('consumer_key')
            ? $request->consumer_key
            : $config->consumerKeyDecrypted();
        $secret = $request->filled('consumer_secret')
            ? $request->consumer_secret
            : $config->consumerSecretDecrypted();

        if (empty($url) || empty($key) || empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Indica la URL de la tienda y las credenciales REST (nuevas o ya guardadas).',
            ], 422);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'GeliaSystem-ConnectionTest/1.0',
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
                ->withBasicAuth($key, $secret)
                ->timeout(30)
                ->get("{$url}/wp-json/wc/v3/products", [
                    'per_page' => 1,
                    '_fields' => 'id,sku',
                ]);

            if (in_array($response->status(), [403, 429, 503])) {
                return response()->json([
                    'success' => false,
                    'message' => "Bloqueo de seguridad detectado (HTTP {$response->status()}).",
                ], 400);
            }

            if (!$response->successful()) {
                $mensaje = $response->json('message') ?? $response->body();

                return response()->json([
                    'success' => false,
                    'message' => "Error HTTP {$response->status()}: {$mensaje}",
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conexión exitosa con WooCommerce REST API.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function sincronizarCatalogo(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $request->validate(['woocommerce_csv' => 'required|file|mimes:csv,txt']);

        $this->catalogoService->sincronizarDesdeCsv($request->file('woocommerce_csv')->getRealPath());

        return response()->json(['success' => true, 'message' => 'Catálogo sincronizado con éxito.']);
    }

    public function importPreview(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $request->validate([
            'listado_aromas' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('listado_aromas');
        $path = $file->storeAs('temp', 'woo_precios_preview_' . uniqid() . '.' . $file->getClientOriginalExtension());
        $fullPath = Storage::path($path);

        $cabeceras = $this->service->leerCabeceras($fullPath);
        $config = WoocommerceConfiguracion::obtener();

        return response()->json([
            'success' => true,
            'headers' => $cabeceras['headers'],
            'sin_cabecera' => $cabeceras['sin_cabecera'],
            'file_path' => $path,
            'mapeo_sugerido' => $this->service->sugerirMapeo(
                $cabeceras['headers'],
                $config->mapeoPreciosEfectivo()
            ),
        ]);
    }

    public function previsualizarMapeo(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $request->validate([
            'file_path' => 'required|string',
            'mapping' => 'required|array',
            'mapping.sku' => 'required|string',
            'mapping.precio_base' => 'required|string',
        ]);

        $fullPath = $this->resolverRutaArchivoTemporal($request->input('file_path'));
        $mapping = $this->normalizarMapping($request->input('mapping'));

        return response()->json([
            'success' => true,
            'muestra' => $this->service->previsualizarMapeo($fullPath, $mapping),
        ]);
    }

    public function previsualizar(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        [$fullPath, $mapping] = $this->resolverArchivoYMapeo($request);
        $preciosWizerp = $this->service->extraerPreciosDesdeExcel($fullPath, $mapping);
        $cambios = $this->service->generarAnalisisDeCambios($preciosWizerp);

        return response()->json([
            'success' => true,
            'total_cambios' => count($cambios),
            'detalles' => $cambios,
        ]);
    }

    public function procesar(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        [$fullPath, $mapping] = $this->resolverArchivoYMapeo($request);

        $iva = $this->service->obtenerIva();
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();
        $preciosWizerp = $this->service->extraerPreciosDesdeExcel($fullPath, $mapping);

        $fileName = 'WOOCOMMERCE-SYNC-' . date('d-m-Y_H-i-s') . '.csv';
        $ruta = 'woocommerce/' . $fileName;

        $tempPath = tempnam(sys_get_temp_dir(), 'woo');
        $fileOut = fopen($tempPath, 'w');
        fputcsv($fileOut, ['SKU', 'Nombre', 'Precio rebajado', 'Precio normal']);

        foreach (WoocommerceProduct::all() as $prod) {
            $base = $this->service->resolverPrecioPorSku($preciosWizerp, $prod->sku);
            if ($base === null) {
                continue;
            }
            $rebaja = $this->service->calcular($base, 'rebaja', $margenes, $iva);
            $normal = $this->service->calcular($base, 'normal', $margenes, $iva);
            fputcsv($fileOut, [$prod->sku, $prod->nombre, $rebaja, $normal]);
        }
        fclose($fileOut);

        Storage::disk('public')->put($ruta, file_get_contents($tempPath));
        $size = round(filesize($tempPath) / 1024, 2) . ' KB';
        unlink($tempPath);

        $template = WoocommerceTemplate::create([
            'nombre_archivo' => $fileName,
            'ruta_fisica' => $ruta,
            'tamano_kb' => $size,
        ]);

        $this->limpiarArchivoTemporal($request->input('file_path'));

        return response()->json([
            'success' => true,
            'download_url' => route('woocommerce.descargar', $template->id),
        ]);
    }

    public function sincronizar(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        if (!WoocommerceConfiguracion::obtener()->credencialesConfiguradas()) {
            return response()->json(['success' => false, 'message' => 'Configura las credenciales de WooCommerce antes de sincronizar.'], 422);
        }

        [$fullPath, $mapping] = $this->resolverArchivoYMapeo($request);
        $preciosWizerp = $this->service->extraerPreciosDesdeExcel($fullPath, $mapping);
        $cambios = $this->service->generarAnalisisDeCambios($preciosWizerp);

        $preciosFiltrados = [];
        foreach ($cambios as $cambio) {
            $precio = $this->service->resolverPrecioPorSku($preciosWizerp, $cambio['sku']);
            if ($precio !== null) {
                $preciosFiltrados[$cambio['sku']] = $precio;
            }
        }

        $total = count($preciosFiltrados);
        if ($total === 0) {
            return response()->json(['success' => false, 'message' => 'No hay cambios en los precios para sincronizar.']);
        }

        $log = WoocommerceSyncLog::create([
            'tipo' => 'upload_prices',
            'total_productos' => $total,
            'procesados' => 0,
            'estado' => 'pendiente',
            'payload' => $preciosFiltrados,
        ]);

        UpdateWooCommercePricesJob::dispatch($log->id, 0);

        $this->limpiarArchivoTemporal($request->input('file_path'));

        return response()->json(['success' => true, 'log_id' => $log->id]);
    }

    /**
     * @return array{0: string, 1: array{sku: string, precio_base: string}|null}
     */
    private function resolverArchivoYMapeo(Request $request): array
    {
        if ($request->filled('file_path') && $request->has('mapping')) {
            $request->validate([
                'file_path' => 'required|string',
                'mapping' => 'required|array',
                'mapping.sku' => 'required|string',
                'mapping.precio_base' => 'required|string',
            ]);

            return [
                $this->resolverRutaArchivoTemporal($request->input('file_path')),
                $this->normalizarMapping($request->input('mapping')),
            ];
        }

        $request->validate(['listado_aromas' => 'required|file|mimes:xlsx,xls,csv']);

        return [$request->file('listado_aromas')->getRealPath(), null];
    }

    private function resolverRutaArchivoTemporal(string $filePath): string
    {
        if (! str_starts_with($filePath, 'temp/')) {
            abort(422, 'Ruta de archivo temporal inválida.');
        }

        $fullPath = Storage::path($filePath);
        if (! file_exists($fullPath)) {
            abort(422, 'Archivo temporal no encontrado. Vuelve a subir el Excel.');
        }

        return $fullPath;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array{sku: string, precio_base: string}
     */
    private function normalizarMapping(array $mapping): array
    {
        return [
            'sku' => trim((string) ($mapping['sku'] ?? '')),
            'precio_base' => trim((string) ($mapping['precio_base'] ?? '')),
        ];
    }

    private function limpiarArchivoTemporal(?string $filePath): void
    {
        if ($filePath && str_starts_with($filePath, 'temp/') && Storage::exists($filePath)) {
            Storage::delete($filePath);
        }
    }

    public function progreso(int $id): JsonResponse
    {
        Gate::authorize('woocommerce.ver');

        $log = WoocommerceSyncLog::findOrFail($id);
        WoocommerceSyncLog::marcarZombieSiAplica($log);

        return response()->json($log->fresh());
    }

    public function syncActivo(): JsonResponse
    {
        Gate::authorize('woocommerce.ver');

        return response()->json(['log' => WoocommerceSyncLog::activo()]);
    }

    public function fetchPrecios(): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        if (!WoocommerceConfiguracion::obtener()->credencialesConfiguradas()) {
            return response()->json(['success' => false, 'message' => 'Configura las credenciales de WooCommerce.'], 422);
        }

        $total = WoocommerceProduct::count();
        if ($total === 0) {
            return response()->json(['success' => false, 'message' => 'El catálogo local está vacío. Importa el CSV de WooCommerce primero.'], 400);
        }

        $log = WoocommerceSyncLog::create([
            'tipo' => 'fetch_prices',
            'total_productos' => $total,
            'procesados' => 0,
            'estado' => 'pendiente',
            'payload' => ['page' => 1],
        ]);

        FetchWooCommercePricesJob::dispatch($log->id, 1);

        return response()->json(['success' => true, 'log_id' => $log->id]);
    }

    public function actualizarPreciosLocales(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $request->validate(['archivo_precios_locales' => 'required|file|mimes:csv,txt']);
        $path = $request->file('archivo_precios_locales')->getRealPath();
        $productosActualizados = 0;

        (new FastExcel)->import($path, function ($linea) use (&$productosActualizados) {
            $linea = array_change_key_case($linea, CASE_LOWER);
            $sku = trim((string) ($linea['sku'] ?? ''));
            $precioNormal = isset($linea['precio normal']) ? (float) $linea['precio normal'] : null;
            $precioRebaja = isset($linea['precio rebajado']) ? (float) $linea['precio rebajado'] : null;

            if ($sku !== '' && $precioNormal !== null) {
                $actualizado = WoocommerceProduct::where('sku', $sku)->update([
                    'precio_normal' => $precioNormal,
                    'precio_rebajado' => $precioRebaja,
                    'updated_at' => now(),
                ]);

                if ($actualizado) {
                    $productosActualizados++;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Se actualizaron internamente los precios de {$productosActualizados} productos.",
        ]);
    }

    public function auditoria(Request $request): Response
    {
        Gate::authorize('woocommerce.auditoria');

        $search = $request->input('search');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $logs = WoocommerceSyncLog::query()
            ->when($search, fn ($q) => $q->where('id', 'LIKE', "%{$search}%")->orWhere('estado', 'LIKE', "%{$search}%"))
            ->when($fechaInicio && $fechaFin, fn ($q) => $q->whereBetween('created_at', ["{$fechaInicio} 00:00:00", "{$fechaFin} 23:59:59"]))
            ->when($fechaInicio && !$fechaFin, fn ($q) => $q->whereDate('created_at', $fechaInicio))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('WooCommerce/Auditoria', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ],
        ]);
    }

    public function descargarAuditoria(int $id)
    {
        Gate::authorize('woocommerce.auditoria');

        $detalles = WoocommerceSyncDetail::where('sync_log_id', $id)->get();
        if ($detalles->isEmpty()) {
            return back()->withErrors(['error' => 'No hay detalles de auditoría para este proceso.']);
        }

        $fileName = 'AUDITORIA-PRECIOS-' . $id . '-' . date('Ymd_Hi') . '.csv';

        return (new FastExcel($detalles))->download($fileName, function ($detalle) {
            return [
                'SKU' => $detalle->sku,
                'Normal Anterior' => $detalle->precio_anterior_normal !== null ? '$' . $detalle->precio_anterior_normal : '---',
                'Normal Nuevo' => '$' . $detalle->precio_nuevo_normal,
                'Rebaja Anterior' => $detalle->precio_anterior_rebajado !== null ? '$' . $detalle->precio_anterior_rebajado : '---',
                'Rebaja Nueva' => '$' . $detalle->precio_nuevo_rebajado,
                'Estado' => strtoupper($detalle->estado),
                'Mensaje / Error' => $detalle->mensaje,
                'Fecha Ejecución' => $detalle->created_at->format('d/m/Y H:i:s'),
            ];
        });
    }

    public function alertas(): Response
    {
        Gate::authorize('woocommerce.ver');

        $productosCriticos = WoocommerceProduct::where(function ($query) {
            $query->whereNull('precio_normal')->orWhere('precio_normal', '<=', 0);
        })->get();

        return Inertia::render('WooCommerce/Alertas', [
            'productosCriticos' => $productosCriticos,
        ]);
    }

    public function consultarPrecioIndividual(int $id): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $producto = WoocommerceProduct::findOrFail($id);

        try {
            $url = $this->urlProductoWoo($producto);

            $response = $this->getWooClient('GeliaSystem-SingleTest/1.0')->get($url);

            $this->validateSecurityResponse($response);
            $data = $response->json();

            $nuevoNormal = isset($data['regular_price']) && $data['regular_price'] !== '' ? (float) $data['regular_price'] : null;
            $nuevoRebajado = isset($data['sale_price']) && $data['sale_price'] !== '' ? (float) $data['sale_price'] : null;

            $producto->update([
                'precio_normal' => $nuevoNormal,
                'precio_rebajado' => $nuevoRebajado,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Precio actualizado desde WooCommerce.',
                'precio_normal' => $nuevoNormal,
                'precio_rebajado' => $nuevoRebajado,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function actualizarPrecioIndividual(Request $request, int $id): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $request->validate([
            'precio_normal' => 'required|numeric|min:0',
            'precio_rebajado' => 'required|numeric|min:0',
        ]);

        $producto = WoocommerceProduct::findOrFail($id);
        $url = $this->urlProductoWoo($producto);

        $response = $this->getWooClient('GeliaSystem-Admin/1.0')->put($url, [
            'regular_price' => (string) $request->precio_normal,
            'sale_price' => (string) $request->precio_rebajado,
        ]);

        if ($response->successful()) {
            $producto->update([
                'precio_normal' => $request->precio_normal,
                'precio_rebajado' => $request->precio_rebajado,
            ]);

            return response()->json(['success' => true, 'message' => 'Precio sincronizado en WooCommerce y GELIANV.']);
        }

        return response()->json(['success' => false, 'message' => $response->json('message', 'Error desconocido en API')], 400);
    }

    public function emergenciaOcultar(Request $request): JsonResponse
    {
        Gate::authorize('woocommerce.emergencia');

        $ids = $request->input('productos_ids', []);
        if (empty($ids)) {
            return response()->json(['error' => 'No hay productos seleccionados'], 400);
        }

        $errores = 0;
        foreach ($ids as $id) {
            $prod = WoocommerceProduct::find($id);
            if (!$prod) {
                continue;
            }

            $url = $prod->tipo === 'variation'
                ? "{$this->getWooBaseUrl()}/wp-json/wc/v3/products/{$prod->parent_id}/variations/{$prod->id}"
                : "{$this->getWooBaseUrl()}/wp-json/wc/v3/products/{$prod->id}";

            $response = $this->getWooClient('GeliaSystem-Admin/1.0')->put($url, ['status' => 'draft']);
            if (!$response->successful()) {
                $errores++;
            }
            usleep(300000);
        }

        return response()->json([
            'success' => true,
            'message' => "Proceso de emergencia completado. Errores detectados: {$errores}",
        ]);
    }

    public function cancelarSync(int $id)
    {
        Gate::authorize('woocommerce.sincronizar');

        $log = WoocommerceSyncLog::findOrFail($id);

        if (in_array($log->estado, ['pendiente', 'en_proceso'])) {
            $log->update(['estado' => 'cancelado']);
            Artisan::call('queue:restart');

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'El proceso fue cancelado y el motor de colas ha sido reiniciado.',
                ]);
            }

            return back()->with('success', 'El proceso fue cancelado y el motor de colas ha sido reiniciado.');
        }

        if (request()->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'El proceso no se puede cancelar porque ya finalizó o dio error.',
            ], 422);
        }

        return back()->with('error', 'El proceso no se puede cancelar porque ya finalizó o dio error.');
    }

    public function descartarSync(int $id): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $log = WoocommerceSyncLog::findOrFail($id);

        if (!$log->esFantasma()) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden eliminar procesos pendientes, en curso, interrumpidos o con error.',
            ], 422);
        }

        if (in_array($log->estado, ['pendiente', 'en_proceso'], true)) {
            Artisan::call('queue:restart');
        }

        $log->delete();

        return response()->json([
            'success' => true,
            'message' => "Proceso #{$id} eliminado correctamente.",
        ]);
    }

    public function descartarTodosFantasmas(): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $fantasmas = WoocommerceSyncLog::fantasmas();
        $habiaActivos = $fantasmas->contains(
            fn ($log) => in_array($log->estado, ['pendiente', 'en_proceso'], true)
        );

        $eliminados = $fantasmas->count();
        foreach ($fantasmas as $log) {
            $log->delete();
        }

        if ($habiaActivos) {
            Artisan::call('queue:restart');
        }

        return response()->json([
            'success' => true,
            'message' => $eliminados > 0
                ? "Se eliminaron {$eliminados} proceso(s) fantasma."
                : 'No había procesos fantasma por eliminar.',
            'eliminados' => $eliminados,
        ]);
    }

    public function continuarSync(int $id): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $log = WoocommerceSyncLog::findOrFail($id);
        WoocommerceSyncLog::marcarZombieSiAplica($log);
        $log = $log->fresh();

        if (!$log->puedeContinuar() && !in_array($log->estado, ['interrumpido', 'error', 'en_proceso'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Este proceso no se puede continuar.',
            ], 422);
        }

        if ($log->procesados >= $log->total_productos) {
            return response()->json([
                'success' => false,
                'message' => 'El proceso ya procesó todos los productos.',
            ], 422);
        }

        $offset = (int) $log->procesados;
        $payloadOriginal = $log->payload ?? [];
        $tipo = $log->tipo ?? 'upload_prices';

        if ($tipo !== 'fetch_prices' && empty($payloadOriginal)) {
            return response()->json([
                'success' => false,
                'message' => 'No hay datos guardados para continuar este proceso.',
            ], 422);
        }

        Artisan::call('queue:restart');

        $log->update([
            'estado' => 'completado',
            'mensaje_error' => "Cerrado parcialmente en {$offset}/{$log->total_productos}. Continuado en nuevo proceso.",
        ]);

        if ($tipo === 'fetch_prices') {
            $page = (int) ($payloadOriginal['page'] ?? max(1, (int) floor($offset / 100) + 1));

            $nuevoLog = WoocommerceSyncLog::create([
                'tipo' => 'fetch_prices',
                'total_productos' => $log->total_productos,
                'procesados' => $offset,
                'estado' => 'pendiente',
                'payload' => ['page' => $page],
            ]);

            FetchWooCommercePricesJob::dispatch($nuevoLog->id, $page);
        } else {
            $allSkus = array_values(array_keys($payloadOriginal));
            $skusRestantes = array_slice($allSkus, $offset);
            $preciosRestantes = [];

            foreach ($skusRestantes as $sku) {
                $preciosRestantes[$sku] = $payloadOriginal[$sku];
            }

            if (empty($preciosRestantes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No quedan productos pendientes por procesar.',
                ], 422);
            }

            $nuevoLog = WoocommerceSyncLog::create([
                'tipo' => 'upload_prices',
                'total_productos' => $log->total_productos,
                'procesados' => $offset,
                'estado' => 'pendiente',
                'payload' => $preciosRestantes,
            ]);

            UpdateWooCommercePricesJob::dispatch($nuevoLog->id, 0);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nuevo proceso iniciado desde el producto ' . ($offset + 1) . '.',
            'log_id' => $nuevoLog->id,
        ]);
    }

    /** @deprecated Usar continuarSync */
    public function reanudarSync(int $id): JsonResponse
    {
        return $this->continuarSync($id);
    }

    public function descargar(int $id): StreamedResponse
    {
        Gate::authorize('woocommerce.ver');

        $t = WoocommerceTemplate::findOrFail($id);

        return Storage::disk('public')->download($t->ruta_fisica, $t->nombre_archivo);
    }

    public function eliminar(int $id): JsonResponse
    {
        Gate::authorize('woocommerce.sincronizar');

        $t = WoocommerceTemplate::findOrFail($id);
        Storage::disk('public')->delete($t->ruta_fisica);
        $t->delete();

        return response()->json(['success' => true]);
    }

    private function urlProductoWoo(WoocommerceProduct $producto): string
    {
        $base = $this->getWooBaseUrl() . '/wp-json/wc/v3/products';

        if ($producto->tipo === 'variation' && $producto->parent_id) {
            return "{$base}/{$producto->parent_id}/variations/{$producto->id}";
        }

        return "{$base}/{$producto->id}";
    }
}

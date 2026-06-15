<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use App\Models\WoocommerceTemplate;
use App\Models\WoocommerceProduct;
use App\Models\WoocommerceMargin;
use App\Models\WoocommerceConfig;
use App\Models\WoocommerceSyncLog;
use App\Jobs\UpdateWooCommercePricesJob;
use App\Jobs\FetchWooCommercePricesJob;

// CAMBIO NUEVO: Importación del Trait de seguridad
use App\Traits\InteractsWithWooCommerceApi;

class BellaromaCargaPreciosController extends Controller
{
    // CAMBIO NUEVO: Implementación del Trait en la clase
    use InteractsWithWooCommerceApi;

    /**
     * Vista principal: Historial, Configuración e Inventario Paginado.
     */
    public function index(Request $request)
    {
        $hoy = date('Y-m-d');
        $query = $request->input('search');

        // Variables para los filtros
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'desc');

        $templatesHoy = WoocommerceTemplate::whereDate('created_at', $hoy)->orderByDesc('id')->get();
        $templatesHistorial = WoocommerceTemplate::whereDate('created_at', '<', $hoy)->orderByDesc('id')->limit(50)->get();

        $configIva = WoocommerceConfig::where('llave', 'iva')->first();
        $iva = $configIva ? (float) $configIva->valor : 1.16;
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();
        $adminEmail = WoocommerceConfig::where('llave', 'admin_email')->first()->valor ?? '';
        $notifyEmails = WoocommerceConfig::where('llave', 'notify_emails')->first()->valor ?? '';

        // Detección de proceso en background con SEGURO ANTI-ZOMBIES (10 minutos de inactividad máxima)
        $procesoActivo = WoocommerceSyncLog::whereIn('estado', ['pendiente', 'en_proceso'])
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->latest()
            ->first();

        // Búsqueda, Filtro y Paginación
        $productos = WoocommerceProduct::when($query, function ($q) use ($query) {
            return $q->where('sku', 'LIKE', "%{$query}%")
                ->orWhere('nombre', 'LIKE', "%{$query}%");
        })->orderBy($sort, $order)->paginate(15)->withQueryString();

        return view('woocommerce', compact('templatesHoy', 'templatesHistorial', 'iva', 'margenes', 'productos', 'procesoActivo', 'sort', 'order', 'adminEmail', 'notifyEmails'));
    }

    /**
     * Seguridad: Validación de PIN.
     */
    public function verificarPin(Request $request)
    {
        $request->validate(['pin' => 'required|string']);
        $pinConfig = WoocommerceConfig::where('llave', 'admin_pin')->first();

        if ($pinConfig && Hash::check($request->pin, $pinConfig->valor)) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'PIN incorrecto.'], 401);
    }

    /**
     * Configuración: Guarda IVA y multiplicadores.
     */
    public function guardarConfiguracion(Request $request)
    {
        $request->validate([
            'iva' => 'required|numeric|min:1',
            'margenes' => 'required|array',
            'admin_email' => 'required|email',
            'notify_emails' => 'nullable|string'
        ]);

        WoocommerceConfig::updateOrCreate(['llave' => 'iva'], ['valor' => (string) $request->iva]);
        WoocommerceConfig::updateOrCreate(['llave' => 'admin_email'], ['valor' => trim($request->admin_email)]);
        WoocommerceConfig::updateOrCreate(['llave' => 'notify_emails'], ['valor' => trim($request->notify_emails)]);

        foreach ($request->margenes as $id => $datos) {
            WoocommerceMargin::where('id', $id)->update([
                'multiplicador_rebaja' => $datos['rebaja'],
                'multiplicador_normal' => $datos['normal']
            ]);
        }

        return response()->json(['message' => 'Ajustes actualizados correctamente.']);
    }

    public function sincronizarProductos(Request $request)
    {
        $request->validate(['woocommerce_csv' => 'required|file']);
        $path = $request->file('woocommerce_csv')->getRealPath();

        // 1. Mapeo Inicial: Guardamos SKU -> ID para encontrar padres después
        $skuToIdMap = [];
        $fileIn = fopen($path, 'r');
        $headersRaw = fgetcsv($fileIn);
        $headers = array_map(fn($i) => strtolower(trim((string)$i)), $headersRaw);

        $idxSku = array_search('sku', $headers);
        $idxId = array_search('id', $headers);
        $idxTipo = array_search('tipo', $headers);
        $idxNombre = array_search('nombre', $headers);
        $idxSuperior = array_search('superior', $headers);

        while (($row = fgetcsv($fileIn)) !== false) {
            $sku = trim($row[$idxSku] ?? '');
            $idReal = trim($row[$idxId] ?? '');
            if ($sku !== '' && $idReal !== '') {
                $skuToIdMap[$sku] = (int)$idReal;
            }
        }
        fclose($fileIn);

        // 2. Procesamiento Final: Insertar con jerarquía
        WoocommerceProduct::truncate();
        $nuevos = [];
        $fileIn = fopen($path, 'r');
        fgetcsv($fileIn); // Saltar cabeceras

        while (($row = fgetcsv($fileIn)) !== false) {
            $sku = trim($row[$idxSku] ?? '');
            $idReal = trim($row[$idxId] ?? '');

            if ($sku !== '' && $idReal !== '') {
                $parentSku = trim($row[$idxSuperior] ?? '');
                // Si existe un SKU superior, buscamos su ID en nuestro mapa
                $parentId = ($parentSku !== '' && isset($skuToIdMap[$parentSku]))
                    ? $skuToIdMap[$parentSku]
                    : null;

                $nuevos[] = [
                    'id' => (int) $idReal,
                    'sku' => $sku,
                    'nombre' => trim($row[$idxNombre] ?? 'Sin Nombre'),
                    'tipo' => strtolower(trim($row[$idxTipo] ?? 'simple')),
                    'parent_id' => $parentId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }
        fclose($fileIn);

        foreach (array_chunk($nuevos, 500) as $chunk) {
            WoocommerceProduct::insert($chunk);
        }

        return response()->json(['message' => 'Catálogo sincronizado con éxito. Soporte de variaciones activo.']);
    }

    /**
     * FASE 1: Previsualización (Dry-Run).
     */
    public function previsualizarCarga(Request $request)
    {
        $request->validate(['listado_aromas' => 'required|file']);

        $configIva = WoocommerceConfig::where('llave', 'iva')->first();
        $iva = $configIva ? (float) $configIva->valor : 1.16;
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();

        $preciosWizerp = $this->extraerPreciosDesdeExcel($request->file('listado_aromas')->getRealPath());
        $cambiosPendientes = $this->generarAnalisisDeCambios($preciosWizerp, $iva, $margenes);

        return response()->json([
            'success' => true,
            'total_cambios' => count($cambiosPendientes),
            'detalles' => $cambiosPendientes
        ]);
    }

    /**
     * Generación: Crea el archivo CSV de resurtido para descarga manual.
     */
    public function procesar(Request $request)
    {
        $request->validate(['listado_aromas' => 'required|file']);

        $configIva = WoocommerceConfig::where('llave', 'iva')->first();
        $iva = $configIva ? (float) $configIva->valor : 1.16;
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();

        $preciosWizerp = [];
        (new FastExcel)->withoutHeaders()->import($request->file('listado_aromas')->getRealPath(), function ($linea) use (&$preciosWizerp) {
            $sku = trim((string)($linea[1] ?? ''));
            $precio = (float)($linea[5] ?? 0);
            if ($sku !== '' && $precio > 0) $preciosWizerp[$sku] = $precio;
        });

        $productosWoo = WoocommerceProduct::all();
        $fileName = 'WOOCOMMERCE-SYNC-' . date('d-m-Y_H-i-s') . '.csv';
        $ruta = 'woocommerce/' . $fileName;

        $tempPath = tempnam(sys_get_temp_dir(), 'woo');
        $fileOut = fopen($tempPath, 'w');
        fputcsv($fileOut, ['SKU', 'Nombre', 'Precio rebajado', 'Precio normal']);

        foreach ($productosWoo as $prod) {
            if (isset($preciosWizerp[$prod->sku])) {
                $base = $preciosWizerp[$prod->sku];
                $rebaja = $this->calcular($base, 'rebaja', $margenes, $iva);
                $normal = $this->calcular($base, 'normal', $margenes, $iva);
                fputcsv($fileOut, [$prod->sku, $prod->nombre, $rebaja, $normal]);
            }
        }
        fclose($fileOut);

        Storage::disk('public')->put($ruta, file_get_contents($tempPath));
        $size = round(filesize($tempPath) / 1024, 2) . ' KB';
        unlink($tempPath);

        $template = WoocommerceTemplate::create([
            'nombre_archivo' => $fileName,
            'ruta_fisica' => $ruta,
            'tamano_kb' => $size
        ]);

        return response()->json(['download_url' => route('woocommerce.descargar', $template->id)]);
    }

    /**
     * FASE 2: Ejecución de la Carga Masiva.
     */
    public function iniciarCargaMasiva(Request $request)
    {
        $request->validate(['listado_aromas' => 'required|file']);

        $preciosWizerp = $this->extraerPreciosDesdeExcel($request->file('listado_aromas')->getRealPath());
        
        // 1. Extraemos los parámetros de cálculo
        $configIva = WoocommerceConfig::where('llave', 'iva')->first();
        $iva = $configIva ? (float) $configIva->valor : 1.16;
        $margenes = WoocommerceMargin::orderBy('precio_min')->get();

        // 2. Comparamos contra la BD para obtener SOLO los que tienen cambios reales
        $cambios = $this->generarAnalisisDeCambios($preciosWizerp, $iva, $margenes);
        
        // 3. Filtramos el array original para enviar solo los SKUs necesarios al Job
        $preciosFiltrados = [];
        foreach ($cambios as $cambio) {
            $preciosFiltrados[$cambio['sku']] = $preciosWizerp[$cambio['sku']];
        }

        $total = count($preciosFiltrados);

        // Validación extra: Si no hay cambios, no iniciamos el Job
        if ($total === 0) {
            return response()->json(['success' => false, 'message' => 'No hay cambios en los precios para sincronizar.']);
        }

        $log = WoocommerceSyncLog::create([
            'total_productos' => $total,
            'procesados' => 0,
            'estado' => 'pendiente'
        ]);

        // Mandamos al worker únicamente el array filtrado
        UpdateWooCommercePricesJob::dispatch($log->id, $preciosFiltrados);

        return response()->json(['success' => true, 'log_id' => $log->id]);
    }

    public function consultarProgreso($id)
    {
        return response()->json(WoocommerceSyncLog::findOrFail($id));
    }

    public function descargar($id)
    {
        $t = WoocommerceTemplate::findOrFail($id);
        return Storage::disk('public')->download($t->ruta_fisica, $t->nombre_archivo);
    }

    public function eliminar($id)
    {
        $t = WoocommerceTemplate::findOrFail($id);
        Storage::disk('public')->delete($t->ruta_fisica);
        $t->delete();
        return response()->json(['success' => true]);
    }

    private function extraerPreciosDesdeExcel(string $rutaArchivo): array
    {
        $precios = [];
        (new FastExcel)->withoutHeaders()->import($rutaArchivo, function ($linea) use (&$precios) {
            $sku = trim((string)($linea[1] ?? ''));
            $precio = (float)($linea[5] ?? 0);
            if ($sku !== '' && $precio > 0) {
                $precios[$sku] = $precio;
            }
        });
        return $precios;
    }

    private function generarAnalisisDeCambios(array $preciosWizerp, float $iva, $margenes): array
    {
        $cambios = [];
        $productosLocales = WoocommerceProduct::all();

        foreach ($productosLocales as $prod) {
            if (!isset($preciosWizerp[$prod->sku])) continue;

            $base = $preciosWizerp[$prod->sku];
            $normal = $this->calcular($base, 'normal', $margenes, $iva);
            $rebaja = $this->calcular($base, 'rebaja', $margenes, $iva);

            if ($prod->precio_normal != $normal || $prod->precio_rebajado != $rebaja) {
                $cambios[] = [
                    'sku' => $prod->sku,
                    'nombre' => $prod->nombre,
                    'precio_normal_anterior' => $prod->precio_normal,
                    'precio_normal_nuevo' => $normal,
                    'precio_rebaja_anterior' => $prod->precio_rebajado,
                    'precio_rebaja_nuevo' => $rebaja
                ];
            }
        }
        return $cambios;
    }

    private function calcular($base, $tipo, $margenes, $iva)
    {
        $mult = 1.0;
        foreach ($margenes as $m) {
            if ($base >= $m->precio_min && $base <= $m->precio_max) {
                $mult = ($tipo === 'rebaja') ? $m->multiplicador_rebaja : $m->multiplicador_normal;
                break;
            }
        }
        return round(($base * $mult) / $iva, 2);
    }

    public function descargarPreciosApi()
    {
        $total = WoocommerceProduct::count();

        if ($total === 0) {
            return response()->json(['success' => false, 'message' => 'El catálogo local está vacío. Sube el CSV primero.'], 400);
        }

        $log = WoocommerceSyncLog::create([
            'total_productos' => $total,
            'procesados' => 0,
            'estado' => 'pendiente'
        ]);

        FetchWooCommercePricesJob::dispatch($log->id);

        return response()->json(['success' => true, 'log_id' => $log->id]);
    }

    /**
     * Vista de Auditoría: Muestra el historial de sincronizaciones con filtros.
     */
    public function auditoriaIndex(Request $request)
    {
        $search = $request->input('search');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $logs = WoocommerceSyncLog::query()
            ->when($search, function ($q) use ($search) {
                return $q->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('estado', 'LIKE', "%{$search}%");
            })
            ->when($fechaInicio && $fechaFin, function ($q) use ($fechaInicio, $fechaFin) {
                return $q->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);
            })
            ->when($fechaInicio && !$fechaFin, function ($q) use ($fechaInicio) {
                return $q->whereDate('created_at', $fechaInicio);
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('woocommerce.auditoria', compact('logs', 'search', 'fechaInicio', 'fechaFin'));
    }

    /**
     * Descarga el CSV de Auditoría de un proceso específico.
     */
    public function descargarAuditoria($id)
    {
        $detalles = \App\Models\WoocommerceSyncDetail::where('sync_log_id', $id)->get();

        if ($detalles->isEmpty()) {
            return back()->withErrors(['error' => 'No hay detalles de auditoría para este proceso.']);
        }

        $fileName = 'AUDITORIA-PRECIOS-' . $id . '-' . date('Ymd_Hi') . '.csv';

        return (new \Rap2hpoutre\FastExcel\FastExcel($detalles))->download($fileName, function ($detalle) {
            return [
                'SKU' => $detalle->sku,
                'Normal Anterior' => $detalle->precio_anterior_normal ? '$' . $detalle->precio_anterior_normal : '---',
                'Normal Nuevo' => '$' . $detalle->precio_nuevo_normal,
                'Rebaja Anterior' => $detalle->precio_anterior_rebajado ? '$' . $detalle->precio_anterior_rebajado : '---',
                'Rebaja Nueva' => '$' . $detalle->precio_nuevo_rebajado,
                'Estado' => strtoupper($detalle->estado),
                'Mensaje / Error' => $detalle->mensaje,
                'Fecha Ejecución' => $detalle->created_at->format('d/m/Y H:i:s'),
            ];
        });
    }

    /**
     * Consulta el precio de un producto específico directamente a la API de WooCommerce.
     * Utiliza el Trait para garantizar la evasión del WAF.
     */
    public function consultarPrecioIndividual($id)
    {
        $producto = WoocommerceProduct::findOrFail($id);
        $baseUrl = config('services.woocommerce.url');

        try {
            // Utilizamos el cliente HTTP blindado
            $response = $this->getWooClient('GeliaSystem-SingleTest/1.0')
                             ->get("{$baseUrl}/wp-json/wc/v3/products/{$producto->id}");

            $this->validateSecurityResponse($response);
            $data = $response->json();

            $nuevoNormal = isset($data['regular_price']) && $data['regular_price'] !== '' ? (float) $data['regular_price'] : null;
            $nuevoRebajado = isset($data['sale_price']) && $data['sale_price'] !== '' ? (float) $data['sale_price'] : null;

            // Mantenemos la base de datos local sincronizada
            $producto->update([
                'precio_normal' => $nuevoNormal,
                'precio_rebajado' => $nuevoRebajado
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Precio actualizado desde WooCommerce.',
                'precio_normal' => $nuevoNormal,
                'precio_rebajado' => $nuevoRebajado
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function actualizarPrecioIndividual(Request $request, $id)
    {
        $request->validate([
            'precio_normal' => 'required|numeric|min:0',
            'precio_rebajado' => 'required|numeric|min:0'
        ]);

        $producto = WoocommerceProduct::findOrFail($id);

        // CAMBIO NUEVO: Lectura de URL desde configuración segura.
        $baseUrl = config('services.woocommerce.url');

        // CAMBIO NUEVO: Sustitución de Http::withBasicAuth por el cliente encapsulado del Trait.
        $response = $this->getWooClient('GeliaSystem-Admin/1.0')
                         ->put("{$baseUrl}/wp-json/wc/v3/products/{$producto->id}", [
                             'regular_price' => (string) $request->precio_normal,
                             'sale_price' => (string) $request->precio_rebajado
                         ]);

        if ($response->successful()) {
            $producto->update([
                'precio_normal' => $request->precio_normal,
                'precio_rebajado' => $request->precio_rebajado
            ]);
            return response()->json(['success' => true, 'message' => 'Precio sincronizado en WooCommerce y GELIA.']);
        }

        return response()->json(['success' => false, 'message' => $response->json('message', 'Error desconocido en API')], 400);
    }

    /**
     * Sincroniza los precios en la base de datos local utilizando un archivo CSV exportado por Gelia.
     * Evita consultas a la API de WooCommerce.
     */
    public function actualizarPreciosLocales(Request $request)
    {
        $request->validate(['archivo_precios_locales' => 'required|file|mimes:csv,txt']);
        $path = $request->file('archivo_precios_locales')->getRealPath();

        $productosActualizados = 0;

        // Utilizamos FastExcel para iterar el CSV eficientemente sin saturar memoria
        (new FastExcel)->import($path, function ($linea) use (&$productosActualizados) {
            // Normalización de claves para evitar errores por mayúsculas/minúsculas en cabeceras
            $linea = array_change_key_case($linea, CASE_LOWER);
            
            $sku = trim((string)($linea['sku'] ?? ''));
            $precioNormal = isset($linea['precio normal']) ? (float)$linea['precio normal'] : null;
            $precioRebaja = isset($linea['precio rebajado']) ? (float)$linea['precio rebajado'] : null;

            if ($sku !== '' && $precioNormal !== null) {
                $actualizado = WoocommerceProduct::where('sku', $sku)->update([
                    'precio_normal' => $precioNormal,
                    'precio_rebajado' => $precioRebaja,
                    'updated_at' => now()
                ]);

                if ($actualizado) {
                    $productosActualizados++;
                }
            }
        });

        return response()->json([
            'success' => true, 
            'message' => "Se actualizaron internamente los precios de {$productosActualizados} productos."
        ]);
    }

    /**
     * Retorna la vista o los datos de productos con anomalías
     */
    public function alertasInventario()
    {
        $productosCriticos = WoocommerceProduct::where(function ($query) {
            $query->whereNull('precio_normal')
                ->orWhere('precio_normal', '<=', 0);
        })
            ->get();

        return view('woocommerce.alertas', compact('productosCriticos'));
    }

    /**
     * Acción de emergencia: Ocultar productos sin precio en WooCommerce
     */
    public function emergenciaOcultarProductos(Request $request)
    {
        $ids = $request->input('productos_ids', []);
        if (empty($ids)) return response()->json(['error' => 'No hay productos seleccionados'], 400);

        $errores = 0;
        
        // CAMBIO NUEVO: Lectura de URL desde configuración segura.
        $baseUrl = config('services.woocommerce.url');

        foreach ($ids as $id) {
            $prod = WoocommerceProduct::find($id);
            if (!$prod) continue;

            $url = $prod->tipo === 'variation'
                ? "{$baseUrl}/wp-json/wc/v3/products/{$prod->parent_id}/variations/{$prod->id}"
                : "{$baseUrl}/wp-json/wc/v3/products/{$prod->id}";

            // CAMBIO NUEVO: Sustitución por el cliente encapsulado del Trait.
            $response = $this->getWooClient('GeliaSystem-Admin/1.0')->put($url, [
                'status' => 'draft'
            ]);

            if (!$response->successful()) {
                $errores++;
            }
            
            // CAMBIO NUEVO: Prevención de Rate Limiting en caso de selección masiva.
            usleep(300000); 
        }

        return response()->json([
            'success' => true,
            'message' => "Proceso de emergencia completado. Errores detectados: {$errores}"
        ]);
    }

    /**
     * Seguro manual para cancelar procesos zombie
     */
    public function forzarCancelacionSync($id)
    {
        $log = WoocommerceSyncLog::findOrFail($id);

        if ($log->estado === 'en_proceso') {
            $log->update([
                'estado' => 'cancelado',
            ]);

            // Fuerzas al worker a reiniciar su memoria y matar procesos estancados.
            \Illuminate\Support\Facades\Artisan::call('queue:restart');

            return back()->with('success', 'El proceso fue cancelado y el motor de colas ha sido reiniciado.');
        }

        return back()->with('error', 'El proceso no se puede cancelar porque ya finalizó o dio error.');
    }

    public function reanudarSync($id)
    {
        $log = WoocommerceSyncLog::findOrFail($id);

        if ($log->estado === 'en_proceso') {
            Artisan::call('queue:retry', ['id' => 'all']);
            return back()->with('success', 'Proceso reanudado. El sistema continuará desde donde se detuvo.');
        }

        return back()->with('error', 'Solo se pueden reanudar procesos que estén en curso.');
    }
}
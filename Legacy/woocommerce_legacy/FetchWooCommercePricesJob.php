<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\WoocommerceProduct;
use App\Models\WoocommerceSyncLog;
use App\Models\WoocommerceSyncDetail;

// Importación del Trait
use App\Traits\InteractsWithWooCommerceApi;

class FetchWooCommercePricesJob implements ShouldQueue
{
    // Implementación del Trait en la clase
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InteractsWithWooCommerceApi;

    protected $syncLogId;

    public function __construct($syncLogId)
    {
        $this->syncLogId = $syncLogId;
    }

    public function handle()
    {
        $log = WoocommerceSyncLog::find($this->syncLogId);
        if (!$log) return;

        $log->update(['estado' => 'en_proceso']);

        $baseUrl = config('services.woocommerce.url') . '/wp-json/wc/v3/products';

        $page = 1;
        $procesados = 0;

        do {
            try {

                // CORRECCIÓN: Revisar si el usuario canceló desde la interfaz antes de hacer la siguiente petición
                if ($log->fresh()->estado === 'cancelado') {
                    return; // Aborta la ejecución inmediatamente
                }

                $response = $this->getWooClient('GeliaSystem-FetchBot/1.0')
                    ->get($baseUrl, [
                        'per_page' => 100,
                        'page'     => $page,
                        '_fields'  => 'id,sku,regular_price,sale_price'
                    ]);

                if (!$response->successful()) {
                    throw new \Exception('Error en la API de WooCommerce: ' . $response->body());
                }

                $productosWoo = $response->json();
                if (empty($productosWoo)) break;

                $this->procesarPaginaLocal($productosWoo, $log);

                $procesados += count($productosWoo);
                $log->update(['procesados' => $procesados]);
                $page++;

                $this->aplicarLatenciaSegura();
            } catch (\Exception $e) {
                $log->update(['estado' => 'error', 'mensaje' => $e->getMessage()]);
                return;
            }
        } while (count($productosWoo) === 100);

        $log->update(['estado' => 'completado', 'procesados' => $log->total_productos]);
    }

    /**
     * Módulo de persistencia local de datos leídos.
     */
    private function procesarPaginaLocal(array $productosWoo, $log): void
    {
        foreach ($productosWoo as $wp) {
            if (empty($wp['sku'])) continue;

            $productoLocal = WoocommerceProduct::where('sku', $wp['sku'])->first();

            if ($productoLocal) {
                $nuevoNormal = $wp['regular_price'] !== '' ? $wp['regular_price'] : null;
                $nuevoRebajado = $wp['sale_price'] !== '' ? $wp['sale_price'] : null;

                WoocommerceSyncDetail::create([
                    'sync_log_id' => $log->id,
                    'sku' => $wp['sku'],
                    'precio_anterior_normal' => $productoLocal->precio_normal,
                    'precio_nuevo_normal' => $nuevoNormal,
                    'precio_anterior_rebajado' => $productoLocal->precio_rebajado,
                    'precio_nuevo_rebajado' => $nuevoRebajado,
                    'estado' => 'exito',
                    'mensaje' => 'Descargado desde WooCommerce'
                ]);

                $productoLocal->update([
                    'precio_normal' => $nuevoNormal,
                    'precio_rebajado' => $nuevoRebajado,
                ]);
            }
        }
    }
}

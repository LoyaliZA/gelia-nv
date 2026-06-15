<?php

namespace App\Jobs\WooCommerce;

use App\Models\Woocommerce\WoocommerceProduct;
use App\Models\Woocommerce\WoocommerceSyncDetail;
use App\Models\Woocommerce\WoocommerceSyncLog;
use App\Traits\InteractsWithWooCommerceApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchWooCommercePricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InteractsWithWooCommerceApi;

    public function __construct(protected int $syncLogId) {}

    public function handle(): void
    {
        $log = WoocommerceSyncLog::find($this->syncLogId);
        if (!$log) {
            return;
        }

        $log->update(['estado' => 'en_proceso']);

        $baseUrl = $this->getWooBaseUrl() . '/wp-json/wc/v3/products';
        $page = 1;
        $procesados = 0;

        do {
            try {
                if ($log->fresh()->estado === 'cancelado') {
                    return;
                }

                $response = $this->getWooClient('GeliaSystem-FetchBot/1.0')
                    ->get($baseUrl, [
                        'per_page' => 100,
                        'page' => $page,
                        '_fields' => 'id,sku,regular_price,sale_price',
                    ]);

                if (!$response->successful()) {
                    throw new \Exception('Error en la API de WooCommerce: ' . $response->body());
                }

                $productosWoo = $response->json();
                if (empty($productosWoo)) {
                    break;
                }

                $this->procesarPaginaLocal($productosWoo, $log);

                $procesados += count($productosWoo);
                $log->update(['procesados' => $procesados]);
                $page++;

                $this->aplicarLatenciaSegura();
            } catch (\Exception $e) {
                $log->update(['estado' => 'error', 'mensaje_error' => $e->getMessage()]);
                return;
            }
        } while (count($productosWoo) === 100);

        $log->update(['estado' => 'completado', 'procesados' => $log->total_productos]);
    }

    private function procesarPaginaLocal(array $productosWoo, WoocommerceSyncLog $log): void
    {
        foreach ($productosWoo as $wp) {
            if (empty($wp['sku'])) {
                continue;
            }

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
                    'mensaje' => 'Descargado desde WooCommerce',
                ]);

                $productoLocal->update([
                    'precio_normal' => $nuevoNormal,
                    'precio_rebajado' => $nuevoRebajado,
                ]);
            }
        }
    }
}

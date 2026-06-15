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
use Throwable;

class FetchWooCommercePricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InteractsWithWooCommerceApi;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        protected int $syncLogId,
        protected int $page = 1
    ) {}

    public function handle(): void
    {
        $log = WoocommerceSyncLog::find($this->syncLogId);
        if (!$log || $log->estado === 'cancelado') {
            return;
        }

        if ($log->estado === 'pendiente') {
            $log->update(['estado' => 'en_proceso']);
        }

        try {
            if ($log->fresh()->estado === 'cancelado') {
                return;
            }

            $baseUrl = $this->getWooBaseUrl() . '/wp-json/wc/v3/products';

            $response = $this->getWooClient('GeliaSystem-FetchBot/1.0')
                ->get($baseUrl, [
                    'per_page' => 100,
                    'page' => $this->page,
                    '_fields' => 'id,sku,regular_price,sale_price',
                ]);

            if (!$response->successful()) {
                throw new \Exception('Error en la API de WooCommerce: ' . $response->body());
            }

            $productosWoo = $response->json();
            if (empty($productosWoo)) {
                $this->finalizarFetch($log);

                return;
            }

            $this->procesarPaginaLocal($productosWoo, $log);

            $procesados = (int) $log->fresh()->procesados + count($productosWoo);
            $log->update([
                'procesados' => min($procesados, $log->total_productos),
                'payload' => ['page' => $this->page + 1],
            ]);

            if ($log->fresh()->estado === 'cancelado') {
                return;
            }

            if (count($productosWoo) === 100) {
                self::dispatch($this->syncLogId, $this->page + 1);

                return;
            }

            $this->finalizarFetch($log);
        } catch (\Exception $e) {
            $log->update(['estado' => 'error', 'mensaje_error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = WoocommerceSyncLog::find($this->syncLogId);
        if (!$log || in_array($log->estado, ['completado', 'cancelado'])) {
            return;
        }

        $mensaje = $exception?->getMessage() ?? 'El proceso fue interrumpido (timeout o error del worker).';
        $log->update([
            'estado' => 'interrumpido',
            'mensaje_error' => $mensaje,
        ]);
    }

    private function finalizarFetch(WoocommerceSyncLog $log): void
    {
        if ($log->fresh()->estado === 'cancelado') {
            return;
        }

        $log->update([
            'estado' => 'completado',
            'procesados' => $log->total_productos,
        ]);
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

        $this->aplicarLatenciaSegura();
    }
}

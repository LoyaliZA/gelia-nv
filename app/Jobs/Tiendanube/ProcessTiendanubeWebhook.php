<?php

namespace App\Jobs\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeWebhookDelivery;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTiendanubeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $deliveryId
    ) {}

    public function handle(
        TiendanubeApiClient $api,
        TiendanubeCatalogoSyncService $sync
    ): void {
        $delivery = TiendanubeWebhookDelivery::find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['processed', 'ignored'], true)) {
            return;
        }

        $event = (string) ($delivery->event ?? '');
        $config = TiendanubeConfiguracion::obtener();

        if ($delivery->store_id && $config->store_id && (int) $delivery->store_id !== (int) $config->store_id) {
            $delivery->markIgnored('store_id no coincide con la configuración local.');

            return;
        }

        try {
            match (true) {
                in_array($event, ['product/created', 'product/updated'], true) => $this->syncProduct($api, $sync, $delivery),
                $event === 'product/deleted' => $this->deleteProduct($delivery),
                in_array($event, ['category/created', 'category/updated'], true) => $this->syncCategory($api, $sync, $delivery),
                $event === 'category/deleted' => $this->deleteCategory($delivery),
                $event === 'app/uninstalled' => $this->handleUninstalled($config, $delivery),
                in_array($event, ['store/redact', 'customers/redact', 'customers/data_request'], true) => $this->handlePrivacy($delivery),
                default => $delivery->markIgnored($event === '' ? 'Evento ausente.' : "Evento no soportado: {$event}"),
            };
        } catch (\Throwable $e) {
            $delivery->markFailed($e->getMessage());
            throw $e;
        }
    }

    private function syncProduct(
        TiendanubeApiClient $api,
        TiendanubeCatalogoSyncService $sync,
        TiendanubeWebhookDelivery $delivery
    ): void {
        $id = (int) $delivery->resource_id;
        if ($id < 1) {
            $delivery->markIgnored('resource_id de producto inválido.');

            return;
        }

        $producto = $api->getProduct($id);
        $sync->upsertProducto($producto);
        $delivery->markProcessed();
    }

    private function deleteProduct(TiendanubeWebhookDelivery $delivery): void
    {
        $id = (int) $delivery->resource_id;
        if ($id < 1) {
            $delivery->markIgnored('resource_id de producto inválido.');

            return;
        }

        TiendanubeProducto::query()->whereKey($id)->delete();
        $delivery->markProcessed();
    }

    private function syncCategory(
        TiendanubeApiClient $api,
        TiendanubeCatalogoSyncService $sync,
        TiendanubeWebhookDelivery $delivery
    ): void {
        $id = (int) $delivery->resource_id;
        if ($id < 1) {
            $delivery->markIgnored('resource_id de categoría inválido.');

            return;
        }

        $categoria = $api->getCategory($id);
        $sync->upsertCategoria($categoria);
        $delivery->markProcessed();
    }

    private function deleteCategory(TiendanubeWebhookDelivery $delivery): void
    {
        $id = (int) $delivery->resource_id;
        if ($id < 1) {
            $delivery->markIgnored('resource_id de categoría inválido.');

            return;
        }

        TiendanubeCategoria::query()->whereKey($id)->delete();
        $delivery->markProcessed();
    }

    private function handleUninstalled(TiendanubeConfiguracion $config, TiendanubeWebhookDelivery $delivery): void
    {
        $config->forceFill([
            'access_token' => null,
            'store_name' => $config->store_name,
        ])->save();

        Log::warning('Tiendanube app/uninstalled: token invalidado.', [
            'store_id' => $delivery->store_id,
            'delivery_id' => $delivery->id,
        ]);

        $delivery->markProcessed();
    }

    private function handlePrivacy(TiendanubeWebhookDelivery $delivery): void
    {
        Log::info('Tiendanube privacy webhook recibido.', [
            'event' => $delivery->event,
            'store_id' => $delivery->store_id,
            'delivery_id' => $delivery->id,
        ]);

        $delivery->markProcessed();
    }

    public function failed(?\Throwable $e): void
    {
        $delivery = TiendanubeWebhookDelivery::find($this->deliveryId);
        $delivery?->markFailed($e?->getMessage() ?? 'Error desconocido al procesar webhook.');
    }
}

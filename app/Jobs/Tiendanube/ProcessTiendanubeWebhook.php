<?php

namespace App\Jobs\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeWebhookDelivery;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use App\Services\Tiendanube\TiendanubePrivacyService;
use App\Services\Tiendanube\TiendanubeWebhookInboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessTiendanubeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public const RESOURCE_LOCK_PREFIX = 'tiendanube:webhook:';

    public function __construct(
        public int $deliveryId
    ) {}

    public function handle(
        TiendanubeApiClient $api,
        TiendanubeCatalogoSyncService $sync,
        TiendanubePrivacyService $privacy,
        TiendanubeWebhookInboxService $inbox
    ): void {
        $delivery = TiendanubeWebhookDelivery::find($this->deliveryId);
        if (! $delivery) {
            return;
        }

        $token = (string) Str::uuid();
        if (! $inbox->tryAcquire($delivery, $token)) {
            return;
        }

        $event = (string) ($delivery->event ?? '');
        $config = TiendanubeConfiguracion::obtener();

        if ($delivery->store_id && $config->store_id && (int) $delivery->store_id !== (int) $config->store_id) {
            $inbox->markIgnored($delivery, $token, 'store_id no coincide con la configuración local.');

            return;
        }

        $lock = null;

        try {
            $lock = $this->lockRecurso($delivery);

            match (true) {
                in_array($event, ['product/created', 'product/updated'], true) => $this->syncProduct($api, $sync, $inbox, $delivery, $token),
                $event === 'product/deleted' => $this->deleteProduct($inbox, $delivery, $token),
                in_array($event, ['category/created', 'category/updated'], true) => $this->syncCategory($api, $sync, $inbox, $delivery, $token),
                $event === 'category/deleted' => $this->deleteCategory($inbox, $delivery, $token),
                $event === 'app/uninstalled' => $this->handleUninstalled($config, $inbox, $delivery, $token),
                in_array($event, ['store/redact', 'customers/redact', 'customers/data_request'], true) => $this->handlePrivacy($privacy, $inbox, $delivery, $token),
                default => $inbox->markIgnored(
                    $delivery,
                    $token,
                    $event === '' ? 'Evento ausente.' : "Evento no soportado: {$event}"
                ),
            };
        } catch (\Throwable $e) {
            $inbox->failOrRetry($delivery, $token, $e->getMessage());
        } finally {
            $lock?->release();
        }
    }

    private function syncProduct(
        TiendanubeApiClient $api,
        TiendanubeCatalogoSyncService $sync,
        TiendanubeWebhookInboxService $inbox,
        TiendanubeWebhookDelivery $delivery,
        string $token
    ): void {
        $id = (int) $delivery->resource_id;
        if ($id < 1) {
            $inbox->markIgnored($delivery, $token, 'resource_id de producto inválido.');

            return;
        }

        $producto = $api->getProduct($id);
        $sync->upsertProducto($producto);
        $inbox->markProcessed($delivery, $token);
    }

    private function deleteProduct(
        TiendanubeWebhookInboxService $inbox,
        TiendanubeWebhookDelivery $delivery,
        string $token
    ): void {
        $id = (int) $delivery->resource_id;
        if ($id < 1) {
            $inbox->markIgnored($delivery, $token, 'resource_id de producto inválido.');

            return;
        }

        TiendanubeProducto::query()->whereKey($id)->delete();
        $inbox->markProcessed($delivery, $token);
    }

    private function syncCategory(
        TiendanubeApiClient $api,
        TiendanubeCatalogoSyncService $sync,
        TiendanubeWebhookInboxService $inbox,
        TiendanubeWebhookDelivery $delivery,
        string $token
    ): void {
        $id = (int) $delivery->resource_id;
        if ($id < 1) {
            $inbox->markIgnored($delivery, $token, 'resource_id de categoría inválido.');

            return;
        }

        $categoria = $api->getCategory($id);
        $sync->upsertCategoria($categoria);
        $inbox->markProcessed($delivery, $token);
    }

    private function deleteCategory(
        TiendanubeWebhookInboxService $inbox,
        TiendanubeWebhookDelivery $delivery,
        string $token
    ): void {
        $id = (int) $delivery->resource_id;
        if ($id < 1) {
            $inbox->markIgnored($delivery, $token, 'resource_id de categoría inválido.');

            return;
        }

        TiendanubeCategoria::query()->whereKey($id)->delete();
        $inbox->markProcessed($delivery, $token);
    }

    private function handleUninstalled(
        TiendanubeConfiguracion $config,
        TiendanubeWebhookInboxService $inbox,
        TiendanubeWebhookDelivery $delivery,
        string $token
    ): void {
        $config->forceFill([
            'access_token' => null,
            'store_name' => $config->store_name,
        ])->save();

        Log::warning('Tiendanube app/uninstalled: token invalidado.', [
            'store_id' => $delivery->store_id,
            'delivery_id' => $delivery->id,
        ]);

        $inbox->markProcessed($delivery, $token);
    }

    private function handlePrivacy(
        TiendanubePrivacyService $privacy,
        TiendanubeWebhookInboxService $inbox,
        TiendanubeWebhookDelivery $delivery,
        string $token
    ): void {
        $privacy->handle($delivery);
        $inbox->markProcessed($delivery, $token);
    }

    private function lockRecurso(TiendanubeWebhookDelivery $delivery): ?\Illuminate\Contracts\Cache\Lock
    {
        $event = (string) ($delivery->event ?? '');
        $resourceId = (string) ($delivery->resource_id ?? '');
        if ($resourceId === '' || $resourceId === '0') {
            return null;
        }

        $tipo = match (true) {
            str_starts_with($event, 'product/') => 'product',
            str_starts_with($event, 'category/') => 'category',
            default => null,
        };
        if ($tipo === null) {
            return null;
        }

        $storeId = (int) ($delivery->store_id ?? 0);
        $lock = Cache::lock(self::RESOURCE_LOCK_PREFIX.$storeId.':'.$tipo.':'.$resourceId, 120);
        $lock->block(10);

        return $lock;
    }
}

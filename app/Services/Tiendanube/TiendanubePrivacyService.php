<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeImageImport;
use App\Models\Tiendanube\TiendanubeImageImportItem;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Models\Tiendanube\TiendanubeWebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TiendanubePrivacyService
{
    public function handle(TiendanubeWebhookDelivery $delivery): void
    {
        match ($delivery->event) {
            'store/redact' => $this->storeRedact($delivery),
            'customers/redact' => $this->customersRedact($delivery),
            'customers/data_request' => $this->customersDataRequest($delivery),
            default => throw new InvalidArgumentException('Evento de privacidad no soportado: '.(string) $delivery->event),
        };
    }

    private function storeRedact(TiendanubeWebhookDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            TiendanubeImageImportItem::query()->delete();
            TiendanubeImageImport::query()->delete();
            TiendanubeProducto::query()->delete();
            TiendanubeCategoria::query()->delete();
            TiendanubeSyncLog::query()->delete();

            $config = TiendanubeConfiguracion::query()->first();
            if ($config) {
                $config->forceFill([
                    'access_token' => null,
                    'store_id' => null,
                    'store_name' => null,
                    'store_url' => null,
                    'scopes' => null,
                ])->save();
            }
        });

        Log::warning('Tiendanube store/redact: datos locales de la tienda eliminados.', [
            'store_id' => $delivery->store_id,
            'delivery_id' => $delivery->id,
        ]);
    }

    private function customersRedact(TiendanubeWebhookDelivery $delivery): void
    {
        // GELIA no almacena PII de clientes de Tiendanube; nada que borrar.
        Log::info('Tiendanube customers/redact: sin datos de clientes locales.', [
            'store_id' => $delivery->store_id,
            'delivery_id' => $delivery->id,
        ]);
    }

    private function customersDataRequest(TiendanubeWebhookDelivery $delivery): void
    {
        // GELIA no almacena PII de clientes de Tiendanube; nada que reportar al merchant.
        Log::info('Tiendanube customers/data_request: sin datos de clientes que reportar.', [
            'store_id' => $delivery->store_id,
            'delivery_id' => $delivery->id,
        ]);
    }
}

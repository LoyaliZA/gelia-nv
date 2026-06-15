<?php

namespace App\Traits;

use App\Models\Woocommerce\WoocommerceConfiguracion;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait InteractsWithWooCommerceApi
{
    protected function getWooClient(string $botName = 'GeliaSystem-Bot/1.0'): PendingRequest
    {
        $config = WoocommerceConfiguracion::obtener();
        $key = $config->consumerKeyDecrypted();
        $secret = $config->consumerSecretDecrypted();

        if (empty($key) || empty($secret)) {
            throw new \Exception('Las credenciales de WooCommerce no están configuradas.');
        }

        return Http::withHeaders([
            'User-Agent' => $botName,
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Connection' => 'keep-alive',
        ])
            ->withBasicAuth($key, $secret)
            ->timeout(120)
            ->retry(3, 2000, function ($exception) {
                return method_exists($exception, 'response')
                    && $exception->response
                    && $exception->response->status() === 429;
            });
    }

    protected function getWooBaseUrl(): string
    {
        $url = WoocommerceConfiguracion::obtener()->store_url;

        if (empty($url)) {
            throw new \Exception('La URL de la tienda WooCommerce no está configurada.');
        }

        return rtrim($url, '/');
    }

    protected function validateSecurityResponse($response): void
    {
        $status = $response->status();

        if (in_array($status, [403, 429, 503])) {
            throw new \Exception("Bloqueo de seguridad detectado en destino (HTTP {$status}). Proceso abortado.");
        }

        if (!$response->successful()) {
            throw new \Exception('Error de red o de API: ' . $response->body());
        }
    }

    protected function aplicarLatenciaSegura(): void
    {
        usleep(500000);
    }
}

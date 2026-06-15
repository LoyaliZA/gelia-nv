<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

trait InteractsWithWooCommerceApi
{
    /**
     * Construye un cliente HTTP configurado para evadir falsos positivos en Firewalls (WAF).
     * Mantiene la autenticación nativa y agrega reintentos automáticos.
     */
    protected function getWooClient(string $botName = 'GeliaSystem-Bot/1.0'): PendingRequest
    {
        $key = config('services.woocommerce.key');
        $secret = config('services.woocommerce.secret');

        if (empty($key) || empty($secret)) {
            throw new \Exception('Fallo crítico: Las credenciales de WooCommerce (Key/Secret) no están configuradas o la caché no se ha limpiado.');
        }

        return Http::withHeaders([
            'User-Agent'       => $botName,
            'Accept'           => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Connection'       => 'keep-alive'
        ])
        ->withBasicAuth($key, $secret)
        ->timeout(60)
        ->retry(3, 1500, function ($exception, $request) {
            return $exception->response && $exception->response->status() === 429;
        });
    }

    /**
     * Centralización de la validación de bloqueos (Cloudflare).
     * Se invoca después de cada petición para abortar si hay códigos de error de WAF.
     */
    protected function validateSecurityResponse($response): void
    {
        $status = $response->status();
        
        if (in_array($status, [403, 429, 503])) {
            throw new \Exception("Bloqueo de seguridad detectado en destino (HTTP {$status}). Proceso abortado.");
        }

        if (!$response->successful()) {
            throw new \Exception("Error de red o de API: " . $response->body());
        }
    }

    /**
     * Controlador de latencia dinámica para Jobs.
     * Mantiene una cadencia segura de peticiones por minuto.
     */
    protected function aplicarLatenciaSegura(): void
    {
        // 500,000 microsegundos = 0.5 segundos de pausa técnica
        usleep(500000); 
    }
}
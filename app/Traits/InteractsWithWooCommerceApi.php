<?php

namespace App\Traits;

use App\Models\Woocommerce\WoocommerceConfiguracion;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

trait InteractsWithWooCommerceApi
{
    public const USER_AGENT = 'Gelia/1.0 (sincronizacion-precios)';

    public const HEADER_IDENTIFICACION = 'X-Gelia-Integration-Token';

    public const LOTE_MAXIMO = 10;

    public const PAUSA_MIN_SEGUNDOS = 8;

    public const PAUSA_MAX_SEGUNDOS = 15;

    protected function getWooClient(): PendingRequest
    {
        $config = WoocommerceConfiguracion::obtener();
        $key = $config->consumerKeyDecrypted();
        $secret = $config->consumerSecretDecrypted();

        if (empty($key) || empty($secret)) {
            throw new \Exception('Las credenciales de WooCommerce no están configuradas.');
        }

        return $this->clienteWoo($key, $secret, $config->integrationTokenDecrypted());
    }

    protected function clienteWoo(string $key, string $secret, ?string $integrationToken = null, int $timeout = 120): PendingRequest
    {
        $headers = [
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.base64_encode($key.':'.$secret),
        ];

        if (is_string($integrationToken) && $integrationToken !== '') {
            $headers[self::HEADER_IDENTIFICACION] = $integrationToken;
        }

        return Http::withHeaders($headers)
            ->withOptions(['allow_redirects' => false])
            ->timeout($timeout);
    }

    protected function getWooBaseUrl(): string
    {
        $url = WoocommerceConfiguracion::obtener()->store_url;

        if (empty($url)) {
            throw new \Exception('La URL de la tienda WooCommerce no está configurada.');
        }

        if (! str_starts_with(strtolower($url), 'https://')) {
            throw new \Exception('La URL de la tienda debe usar HTTPS.');
        }

        return rtrim($url, '/');
    }

    protected function segundosPausaEntrePeticiones(): int
    {
        return random_int(self::PAUSA_MIN_SEGUNDOS, self::PAUSA_MAX_SEGUNDOS);
    }

    protected function validateSecurityResponse(Response $response): void
    {
        $this->afirmarRespuestaWoo($response);
    }

    protected function afirmarRespuestaWoo(Response $response, array $secretosExtra = []): void
    {
        $status = $response->status();

        if (in_array($status, [403, 429, 503], true)) {
            throw new \Exception("Bloqueo de seguridad detectado en destino (HTTP {$status}). Proceso abortado.");
        }

        if (! $response->successful()) {
            $cuerpo = $this->redactarSecretosWoo((string) $response->body(), $secretosExtra);
            throw new \Exception('Error de red o de API: '.$cuerpo);
        }
    }

    protected function redactarSecretosWoo(string $mensaje, array $secretosExtra = []): string
    {
        $config = WoocommerceConfiguracion::obtener();
        $secretos = array_filter(array_merge([
            $config->consumerKeyDecrypted(),
            $config->consumerSecretDecrypted(),
            $config->integrationTokenDecrypted(),
        ], $secretosExtra));

        foreach ($secretos as $secreto) {
            if (is_string($secreto) && strlen($secreto) >= 8) {
                $mensaje = str_replace($secreto, '[redactado]', $mensaje);
            }
        }

        return preg_replace('/(consumer_key|consumer_secret)=([^&\s]+)/i', '$1=[redactado]', $mensaje) ?? $mensaje;
    }
}

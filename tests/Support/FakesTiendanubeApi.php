<?php

namespace Tests\Support;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use Illuminate\Support\Facades\Crypt;

trait FakesTiendanubeApi
{
    protected int $tiendanubeStoreId = 8004291;

    /**
     * @param  array<string, mixed>  $config
     */
    protected function configureTiendanubeHttp(array $config = []): void
    {
        config(array_merge([
            'tiendanube.api_base' => null,
            'tiendanube.api_host' => 'https://api.tiendanube.com',
            'tiendanube.api_version' => 'v1',
            'tiendanube.per_page' => 50,
            'tiendanube.user_agent' => 'Gelianv',
            'tiendanube.user_agent_contact' => 'integraciones@example.com',
            'tiendanube.retry_sleep_ms' => 0,
        ], $config));
    }

    protected function seedTiendanubeCredentials(): void
    {
        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => $this->tiendanubeStoreId,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
        ])->save();
    }

    protected function tiendanubeUrl(string $path, string $version = 'v1'): string
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return 'https://api.tiendanube.com/'.$version.'/'.$this->tiendanubeStoreId.$path;
    }
}

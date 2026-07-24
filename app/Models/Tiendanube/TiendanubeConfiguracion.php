<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TiendanubeConfiguracion extends Model
{
    protected $table = 'tiendanube_configuracion';

    protected $fillable = [
        'store_id',
        'access_token',
        'app_id',
        'scopes',
        'store_name',
        'store_url',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
        ];
    }

    public static function obtener(): self
    {
        $config = static::firstOrCreate([]);

        if (! $config->credencialesConfiguradas()) {
            $config->hidratarDesdeEnv();
        }

        return $config->fresh() ?? $config;
    }

    public function hidratarDesdeEnv(): void
    {
        $storeId = config('tiendanube.store_id');
        $token = config('tiendanube.access_token');
        $appId = config('tiendanube.app_id');

        if (! $storeId && ! $token && ! $appId) {
            return;
        }

        $datos = [];
        if ($storeId && ! $this->store_id) {
            $datos['store_id'] = (int) $storeId;
        }
        if ($token && ! $this->access_token) {
            $datos['access_token'] = Crypt::encryptString($token);
        }
        if ($appId && ! $this->app_id) {
            $datos['app_id'] = (string) $appId;
        }

        if ($datos !== []) {
            $this->fill($datos)->save();
        }
    }

    public function accessTokenDecrypted(): ?string
    {
        if (! $this->access_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token);
        } catch (\Exception) {
            // Token plano (p.ej. seed sin cifrar) — ponytail: aceptar una vez y normalizar al guardar.
            return $this->access_token;
        }
    }

    public function setAccessTokenPlain(?string $token): void
    {
        $this->access_token = $token ? Crypt::encryptString($token) : null;
    }

    public function credencialesConfiguradas(): bool
    {
        return ! empty($this->store_id) && ! empty($this->accessTokenDecrypted());
    }

    public function appIdEfectivo(): string
    {
        return (string) ($this->app_id ?: config('tiendanube.app_id') ?: '0');
    }
}

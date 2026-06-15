<?php

namespace App\Models\Woocommerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class WoocommerceConfiguracion extends Model
{
    protected $table = 'woocommerce_configuracion';

    protected $fillable = [
        'store_url',
        'consumer_key',
        'consumer_secret',
        'iva',
        'notified_users',
    ];

    protected $casts = [
        'iva' => 'float',
        'notified_users' => 'array',
    ];

    public static function obtener(): self
    {
        return static::firstOrCreate([], [
            'iva' => 1.16,
            'notified_users' => [],
        ]);
    }

    public function getStoreUrlAttribute(?string $value): ?string
    {
        return $value ? rtrim($value, '/') : null;
    }

    public function consumerKeyDecrypted(): ?string
    {
        if (!$this->consumer_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->consumer_key);
        } catch (\Exception) {
            return null;
        }
    }

    public function consumerSecretDecrypted(): ?string
    {
        if (!$this->consumer_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($this->consumer_secret);
        } catch (\Exception) {
            return null;
        }
    }

    public function credencialesConfiguradas(): bool
    {
        return !empty($this->store_url)
            && !empty($this->consumerKeyDecrypted())
            && !empty($this->consumerSecretDecrypted());
    }
}

<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubeUbicacion extends Model
{
    protected $table = 'tiendanube_ubicaciones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'store_id',
        'name',
        'is_default',
        'priority',
        'tags',
        'activa',
        'remote_updated_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'name' => 'array',
            'is_default' => 'boolean',
            'priority' => 'integer',
            'tags' => 'array',
            'activa' => 'boolean',
            'remote_updated_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function niveles(): HasMany
    {
        return $this->hasMany(TiendanubeVarianteNivel::class, 'ubicacion_id');
    }

    public function nombreVisible(): string
    {
        $name = $this->name;
        if (! is_array($name)) {
            return (string) ($name ?? $this->id);
        }

        return (string) ($name['es'] ?? $name['es_MX'] ?? $name['es_AR'] ?? $name['pt_BR'] ?? $name['en_US'] ?? reset($name) ?: $this->id);
    }
}

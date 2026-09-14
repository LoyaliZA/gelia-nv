<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubePrecioLista extends Model
{
    protected $table = 'tiendanube_precio_listas';

    protected $fillable = [
        'store_id',
        'nombre',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(TiendanubePrecioFuenteVersion::class, 'lista_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function archivada(): bool
    {
        return $this->archived_at !== null;
    }
}

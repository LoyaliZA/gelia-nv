<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubePrecioRegla extends Model
{
    protected $table = 'tiendanube_precio_reglas';

    protected $fillable = [
        'store_id',
        'nombre',
        'descripcion',
        'habilitada',
        'archived_at',
        'version_actual_id',
        'version',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'habilitada' => 'boolean',
            'archived_at' => 'datetime',
            'version_actual_id' => 'integer',
            'version' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(TiendanubePrecioReglaVersion::class, 'regla_id');
    }

    public function versionActual(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioReglaVersion::class, 'version_actual_id');
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

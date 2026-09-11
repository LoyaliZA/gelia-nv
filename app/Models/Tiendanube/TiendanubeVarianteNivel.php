<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeVarianteNivel extends Model
{
    protected $table = 'tiendanube_variante_niveles';

    protected $fillable = [
        'variante_id',
        'ubicacion_id',
        'stock',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'variante_id' => 'integer',
            'stock' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(TiendanubeProductoVariante::class, 'variante_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(TiendanubeUbicacion::class, 'ubicacion_id');
    }
}

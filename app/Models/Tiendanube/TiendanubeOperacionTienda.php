<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;

class TiendanubeOperacionTienda extends Model
{
    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_LIBERADA = 'liberada';

    public const ESTADO_EXPIRADA = 'expirada';

    protected $table = 'tiendanube_operaciones_tienda';

    protected $fillable = [
        'store_id',
        'tipo',
        'owner_id',
        'config_generation',
        'lease_token',
        'lease_expires_at',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'owner_id' => 'integer',
            'config_generation' => 'integer',
            'lease_expires_at' => 'datetime',
        ];
    }
}

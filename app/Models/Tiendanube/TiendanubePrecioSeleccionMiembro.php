<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioSeleccionMiembro extends Model
{
    protected $table = 'tiendanube_precio_seleccion_miembros';

    public $incrementing = false;

    protected $fillable = [
        'seleccion_id',
        'variante_id',
    ];

    protected function casts(): array
    {
        return [
            'variante_id' => 'integer',
        ];
    }

    public function seleccion(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioSeleccion::class, 'seleccion_id');
    }
}

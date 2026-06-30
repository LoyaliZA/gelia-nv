<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    protected $table = 'inventarios';

    protected $fillable = [
        'producto_id',
        'almacen_id',
        'ubicacion',
        'existencia',
        'apartado',
        'transito_oc',
        'transito_ot',
        'minimo',
        'maximo',
    ];

    protected $appends = ['disponible'];

    protected function casts(): array
    {
        return [
            'existencia' => 'decimal:3',
            'apartado' => 'decimal:3',
            'transito_oc' => 'decimal:3',
            'transito_ot' => 'decimal:3',
            'minimo' => 'decimal:3',
            'maximo' => 'decimal:3',
        ];
    }

    public function getDisponibleAttribute(): string
    {
        return bcsub((string) ($this->existencia ?? 0), (string) ($this->apartado ?? 0), 3);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }
}

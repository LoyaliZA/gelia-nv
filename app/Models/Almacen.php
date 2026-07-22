<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends Model
{
    protected $table = 'almacenes';

    protected $fillable = [
        'codigo',
        'nombre',
        'sucursal_id',
        'tipo_almacen_id',
        'activo',
        'visible_en_pedidos',
        'visible_en_traspasos',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'visible_en_pedidos' => 'boolean',
            'visible_en_traspasos' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function tipoAlmacen(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoAlmacen::class, 'tipo_almacen_id');
    }

    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }

    public function costos(): HasMany
    {
        return $this->hasMany(ProductoCosto::class);
    }
}

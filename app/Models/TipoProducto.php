<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoProducto extends Model
{
    protected $table = 'tipos_producto';

    protected $fillable = [
        'nombre',
        'codigo',
        'controla_inventario',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'controla_inventario' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'tipo_producto_id');
    }
}

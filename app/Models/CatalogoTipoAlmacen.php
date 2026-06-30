<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTipoAlmacen extends Model
{
    protected $table = 'catalogo_tipos_almacen';

    protected $fillable = [
        'nombre',
    ];

    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class, 'tipo_almacen_id');
    }
}

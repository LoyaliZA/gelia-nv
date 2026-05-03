<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoListaDescuento extends Model
{
    protected $table = 'catalogo_listas_descuento';

    protected $fillable = [
        'nombre',
        'monto_requerido',
        'activo'
    ];

    protected $casts = [
        'monto_requerido' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'lista_actual_id');
    }
}
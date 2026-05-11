<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CatalogoTipoCliente extends Model
{
    use HasFactory;

    protected $table = 'catalogo_tipo_clientes';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
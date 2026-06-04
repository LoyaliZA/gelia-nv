<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoCategoriaProducto extends Model
{
    protected $table = 'catalogo_categoria_productos';

    protected $fillable = [
        'nombre',
    ];
}

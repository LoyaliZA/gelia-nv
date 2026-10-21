<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoImagen extends Model
{
    protected $table = 'producto_imagenes';

    protected $fillable = [
        'producto_id',
        'posicion',
        'principal_producto_id',
        'lote_archivo_id',
        'clave_original',
        'clave_miniatura',
        'mime',
        'tamano_bytes',
        'ancho',
        'alto',
        'estado',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function esPrincipal(): bool
    {
        return $this->principal_producto_id !== null;
    }
}

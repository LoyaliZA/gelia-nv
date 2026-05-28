<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoModeloActivo extends Model
{
    protected $table = 'catalogo_modelos_activo';

    protected $fillable = ['catalogo_marca_activo_id', 'nombre', 'slug', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function marca(): BelongsTo
    {
        return $this->belongsTo(CatalogoMarcaActivo::class, 'catalogo_marca_activo_id');
    }
}

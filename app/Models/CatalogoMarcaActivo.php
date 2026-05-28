<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoMarcaActivo extends Model
{
    protected $table = 'catalogo_marcas_activo';

    protected $fillable = ['catalogo_tipo_activo_id', 'nombre', 'slug', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoActivo::class, 'catalogo_tipo_activo_id');
    }

    public function modelos(): HasMany
    {
        return $this->hasMany(CatalogoModeloActivo::class, 'catalogo_marca_activo_id');
    }
}

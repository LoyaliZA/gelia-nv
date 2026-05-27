<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoBanco extends Model
{
    protected $table = 'catalogo_bancos';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudTag::class, 'catalogo_banco_id');
    }
}

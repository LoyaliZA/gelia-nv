<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivoFoto extends Model
{
    protected $table = 'activo_fotos';

    protected $fillable = [
        'activo_id',
        'ruta',
        'nombre_original',
        'orden',
        'es_principal',
        'tamano_bytes',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
    ];

    protected $appends = ['url'];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function getUrlAttribute(): string
    {
        return '/storage/' . ltrim($this->ruta, '/');
    }
}

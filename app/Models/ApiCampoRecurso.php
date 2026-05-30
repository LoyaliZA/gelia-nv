<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiCampoRecurso extends Model
{
    protected $table = 'api_campos_recurso';

    protected $fillable = [
        'api_recurso_id',
        'slug',
        'etiqueta',
        'es_sensible',
        'habilitado_global',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'es_sensible' => 'boolean',
            'habilitado_global' => 'boolean',
        ];
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(ApiRecurso::class, 'api_recurso_id');
    }

    public function aplicacionCampos(): HasMany
    {
        return $this->hasMany(ApiAplicacionCampo::class);
    }
}

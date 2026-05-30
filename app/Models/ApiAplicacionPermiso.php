<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiAplicacionPermiso extends Model
{
    protected $table = 'api_aplicacion_permisos';

    protected $fillable = [
        'api_aplicacion_id',
        'api_recurso_id',
        'puede_leer',
        'puede_escribir',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'puede_leer' => 'boolean',
            'puede_escribir' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(ApiAplicacion::class, 'api_aplicacion_id');
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(ApiRecurso::class, 'api_recurso_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiAplicacionCampo extends Model
{
    protected $table = 'api_aplicacion_campos';

    protected $fillable = [
        'api_aplicacion_id',
        'api_campo_recurso_id',
        'habilitado',
    ];

    protected function casts(): array
    {
        return [
            'habilitado' => 'boolean',
        ];
    }

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(ApiAplicacion::class, 'api_aplicacion_id');
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(ApiCampoRecurso::class, 'api_campo_recurso_id');
    }
}

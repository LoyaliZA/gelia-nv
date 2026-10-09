<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaDespliegue extends Model
{
    use BelongsToUsuario;

    protected $table = 'auditorias_despliegues';

    protected $fillable = [
        'accion',
        'superficie',
        'origen',
        'user_id',
        'detalles',
    ];

    protected $casts = [
        'detalles' => 'array',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsToUsuario('user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaConfiguracion extends Model
{
    protected $table = 'auditorias_configuraciones';

    protected $fillable = [
        'user_id',
        'target_user_id',
        'modulo',
        'accion',
        'detalles',
        'created_at',
    ];

    protected $casts = [
        'detalles' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * El usuario que realiza la acción.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * El usuario al que se le aplicó el cambio (si aplica).
     */
    public function usuarioAfectado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversacionParticipante extends Model
{
    public const ROL_ADMIN = 'admin';
    public const ROL_MIEMBRO = 'miembro';

    protected $table = 'conversacion_participantes';

    protected $fillable = [
        'conversacion_id',
        'user_id',
        'rol',
        'ultimo_leido_at',
        'silenciado',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_leido_at' => 'datetime',
            'silenciado' => 'boolean',
        ];
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

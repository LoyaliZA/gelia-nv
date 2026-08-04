<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeliaAiMensaje extends Model
{
    public $timestamps = false;

    protected $table = 'gelia_ai_mensajes';

    protected $fillable = [
        'conversacion_id',
        'role',
        'content',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(GeliaAiConversacion::class, 'conversacion_id');
    }
}

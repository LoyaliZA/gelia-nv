<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeliaAiUso extends Model
{
    public $timestamps = false;

    protected $table = 'gelia_ai_usos';

    protected $fillable = [
        'user_id',
        'conversacion_id',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'rounds',
        'mode',
        'modelo',
        'mensaje_chars',
        'reply_chars',
        'con_archivos',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'rounds' => 'integer',
            'mensaje_chars' => 'integer',
            'reply_chars' => 'integer',
            'con_archivos' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(GeliaAiConversacion::class, 'conversacion_id');
    }
}

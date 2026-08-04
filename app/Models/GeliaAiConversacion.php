<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeliaAiConversacion extends Model
{
    protected $table = 'gelia_ai_conversaciones';

    protected $fillable = [
        'user_id',
        'titulo',
        'temporal',
    ];

    protected function casts(): array
    {
        return [
            'temporal' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(GeliaAiMensaje::class, 'conversacion_id')->orderBy('id');
    }
}

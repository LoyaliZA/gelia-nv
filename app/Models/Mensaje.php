<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mensaje extends Model
{
    use BelongsToUsuario, SoftDeletes;

    public const TIPO_TEXTO = 'texto';
    public const TIPO_IMAGEN = 'imagen';
    public const TIPO_VIDEO = 'video';
    public const TIPO_AUDIO = 'audio';
    public const TIPO_ARCHIVO = 'archivo';

    protected $table = 'mensajes';

    protected $fillable = [
        'conversacion_id',
        'user_id',
        'tipo',
        'contenido',
        'reply_to_id',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsToUsuario('user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Mensaje::class, 'reply_to_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(MensajeAdjunto::class);
    }

    public function adjunto(): HasOne
    {
        return $this->hasOne(MensajeAdjunto::class);
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(MensajeLectura::class);
    }
}

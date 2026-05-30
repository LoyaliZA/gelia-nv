<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conversacion extends Model
{
    public const TIPO_DIRECTO = 'directo';
    public const TIPO_GRUPO = 'grupo';

    protected $table = 'conversaciones';

    protected $fillable = [
        'tipo',
        'nombre',
        'foto',
        'creado_por',
        'ultimo_mensaje_at',
        'ultimo_mensaje_preview',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_mensaje_at' => 'datetime',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function participantes(): HasMany
    {
        return $this->hasMany(ConversacionParticipante::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversacion_participantes')
            ->withPivot(['rol', 'ultimo_leido_at', 'silenciado'])
            ->withTimestamps();
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    public function esGrupo(): bool
    {
        return $this->tipo === self::TIPO_GRUPO;
    }
}

<?php

namespace App\Models;

use App\Support\FormPublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnlaceDireccion extends Model
{
    protected $table = 'enlaces_direccion';

    protected $fillable = [
        'cliente_id',
        'token_hash',
        'codigo_publico',
        'accion_permitida',
        'direccion_id',
        'expira_en',
        'usado_en',
        'revocado_en',
        'creado_por',
    ];

    protected $casts = [
        'expira_en' => 'datetime',
        'usado_en' => 'datetime',
        'revocado_en' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(ClienteDireccion::class, 'direccion_id');
    }

    public function creadoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function estaVigente(): bool
    {
        if ($this->revocado_en !== null) {
            return false;
        }

        if ($this->usado_en !== null) {
            return false;
        }

        if ($this->expira_en !== null && $this->expira_en->isPast()) {
            return false;
        }

        return true;
    }

    public function fueUsado(): bool
    {
        return $this->usado_en !== null;
    }

    public function urlPublica(): ?string
    {
        if (! $this->codigo_publico) {
            return null;
        }

        return FormPublicUrl::direccionShow($this->codigo_publico);
    }
}

<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvPantallaSalaToken extends Model
{
    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_REVOCADA = 'revocada';

    protected $table = 'pdv_pantalla_sala_tokens';

    protected $fillable = [
        'sucursal_id',
        'token_hash',
        'estado',
        'expira_en',
        'revocado_en',
        'creado_por',
        'ultimo_acceso_at',
    ];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'revocado_en' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function estaVigente(): bool
    {
        if ($this->estado !== self::ESTADO_ACTIVA) {
            return false;
        }

        if ($this->revocado_en !== null) {
            return false;
        }

        if ($this->expira_en !== null && $this->expira_en->isPast()) {
            return false;
        }

        return true;
    }
}

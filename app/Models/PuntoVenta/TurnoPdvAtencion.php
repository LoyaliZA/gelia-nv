<?php

namespace App\Models\PuntoVenta;

use App\Models\User;
use Database\Factories\PuntoVenta\TurnoPdvAtencionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TurnoPdvAtencion extends Model
{
    use HasFactory;

    protected $table = 'pdv_turno_atenciones';

    protected $fillable = [
        'turno_id',
        'user_id',
        'numero_secuencia',
        'inicio_at',
        'atencion_inicio_at',
        'fin_at',
        'motivo_cierre',
        'motivo_cierre_detalle',
        'es_transferencia',
        'transferido_por_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'numero_secuencia' => 'integer',
            'inicio_at' => 'datetime',
            'atencion_inicio_at' => 'datetime',
            'fin_at' => 'datetime',
            'es_transferencia' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function newFactory(): TurnoPdvAtencionFactory
    {
        return TurnoPdvAtencionFactory::new();
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoPdv::class, 'turno_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transferidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferido_por_id');
    }

    public function prorroga(): HasOne
    {
        return $this->hasOne(TurnoPdvProrroga::class, 'atencion_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(TurnoPdvEvento::class, 'atencion_id');
    }
}

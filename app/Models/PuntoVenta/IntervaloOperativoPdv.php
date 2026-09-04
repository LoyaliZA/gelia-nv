<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Database\Factories\PuntoVenta\IntervaloOperativoPdvFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntervaloOperativoPdv extends Model
{
    use HasFactory;

    protected $table = 'pdv_intervalos_operativos';

    protected $fillable = [
        'jornada_id',
        'user_id',
        'sucursal_id',
        'tipo',
        'atencion_id',
        'inicio_at',
        'fin_at',
        'intervalo_abierto_marcador',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoIntervaloOperativoPdv::class,
            'inicio_at' => 'datetime',
            'fin_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (IntervaloOperativoPdv $intervalo): void {
            $intervalo->intervalo_abierto_marcador = $intervalo->fin_at === null ? '1' : null;
        });
    }

    protected static function newFactory(): IntervaloOperativoPdvFactory
    {
        return IntervaloOperativoPdvFactory::new();
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(JornadaPdv::class, 'jornada_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(TurnoPdvAtencion::class, 'atencion_id');
    }

    public function estaAbierto(): bool
    {
        return $this->fin_at === null;
    }

    public function duracionSegundos(?\DateTimeInterface $referencia = null): int
    {
        $referencia = $referencia ?? now();
        $fin = $this->fin_at ?? $referencia;

        return max(0, $this->inicio_at->diffInSeconds($fin, false));
    }
}

<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Database\Factories\PuntoVenta\JornadaPdvFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JornadaPdv extends Model
{
    use HasFactory;

    protected $table = 'pdv_jornadas';

    protected $fillable = [
        'user_id',
        'sucursal_id',
        'estado',
        'apertura_at',
        'cierre_at',
        'jornada_activa_marcador',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoJornadaPdv::class,
            'apertura_at' => 'datetime',
            'cierre_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JornadaPdv $jornada): void {
            $estado = $jornada->estado instanceof EstadoJornadaPdv
                ? $jornada->estado
                : EstadoJornadaPdv::from((string) $jornada->estado);

            $jornada->jornada_activa_marcador = $estado->esActiva() ? '1' : null;
        });
    }

    protected static function newFactory(): JornadaPdvFactory
    {
        return JornadaPdvFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function intervalos(): HasMany
    {
        return $this->hasMany(IntervaloOperativoPdv::class, 'jornada_id');
    }

    public function estaAbierta(): bool
    {
        return $this->estado === EstadoJornadaPdv::Abierta;
    }

    public function estaActiva(): bool
    {
        return $this->estado->esActiva();
    }

    public function duracionSegundos(?\DateTimeInterface $referencia = null): int
    {
        $referencia = $referencia ?? now();
        $fin = $this->cierre_at ?? $referencia;

        return max(0, $this->apertura_at->diffInSeconds($fin, false));
    }
}

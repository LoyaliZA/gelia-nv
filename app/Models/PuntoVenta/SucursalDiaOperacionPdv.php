<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Database\Factories\PuntoVenta\SucursalDiaOperacionPdvFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SucursalDiaOperacionPdv extends Model
{
    use HasFactory;

    protected $table = 'pdv_sucursal_dias';

    protected $fillable = [
        'sucursal_id',
        'fecha_operativa',
        'hora_cierre',
        'acepta_altas',
        'apertura_manual_at',
        'apertura_manual_por_id',
        'cierre_manual_at',
        'cierre_manual_por_id',
        'cierre_automatico_invalidado',
        'ampliacion_hasta_at',
        'ampliacion_por_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'fecha_operativa' => 'date',
            'acepta_altas' => 'boolean',
            'apertura_manual_at' => 'datetime',
            'cierre_manual_at' => 'datetime',
            'cierre_automatico_invalidado' => 'boolean',
            'ampliacion_hasta_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function newFactory(): SucursalDiaOperacionPdvFactory
    {
        return SucursalDiaOperacionPdvFactory::new();
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function aperturaManualPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'apertura_manual_por_id');
    }

    public function cierreManualPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cierre_manual_por_id');
    }

    public function ampliacionPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ampliacion_por_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(OperacionPdvEvento::class, 'sucursal_dia_id');
    }

    public function aplicarCierreHorario(
        \DateTimeInterface $ocurridoAt,
        string $horaCierreSnapshot,
    ): void {
        $this->acepta_altas = false;
        $this->hora_cierre = $horaCierreSnapshot;
    }

    public function aplicaAperturaManual(
        User $actor,
        ?\DateTimeInterface $ocurridoAt = null
    ): void {
        $ocurridoAt = $ocurridoAt ?? now();

        $this->apertura_manual_at = $ocurridoAt;
        $this->apertura_manual_por_id = $actor->id;
        $this->acepta_altas = true;
    }

    public function aplicaCierreManual(
        User $actor,
        ?\DateTimeInterface $ocurridoAt = null
    ): void {
        $ocurridoAt = $ocurridoAt ?? now();

        $this->cierre_manual_at = $ocurridoAt;
        $this->cierre_manual_por_id = $actor->id;
        $this->cierre_automatico_invalidado = true;
        $this->acepta_altas = false;
    }

    public function aplicarAmpliacion(
        User $actor,
        \DateTimeInterface $hasta,
        ?\DateTimeInterface $ocurridoAt = null
    ): void {
        $ocurridoAt = $ocurridoAt ?? now();

        $this->ampliacion_hasta_at = $hasta;
        $this->ampliacion_por_id = $actor->id;
        $this->cierre_automatico_invalidado = true;
        $this->acepta_altas = true;
        $this->cierre_manual_at = null;
        $this->cierre_manual_por_id = null;
    }

    public function aplicarReaperturaManual(): void
    {
        $this->acepta_altas = true;
        $this->cierre_manual_at = null;
        $this->cierre_manual_por_id = null;
        $this->cierre_automatico_invalidado = false;
    }
}

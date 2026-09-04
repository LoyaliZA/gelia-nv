<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Database\Factories\PuntoVenta\SucursalDiaOperacionPdvFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SucursalDiaOperacionPdv extends Model
{
    use HasFactory;

    protected $table = 'pdv_sucursal_dias';

    protected $fillable = [
        'sucursal_id',
        'fecha_operativa',
        'hora_cierre',
        'acepta_altas',
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

    public function cierreManualPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cierre_manual_por_id');
    }

    public function ampliacionPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ampliacion_por_id');
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
}

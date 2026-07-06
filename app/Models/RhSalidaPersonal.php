<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class RhSalidaPersonal extends Model
{
    use SoftDeletes;

    protected $table = 'rh_salidas_personales';

    protected $fillable = [
        'uuid',
        'folio',
        'rh_colaborador_id',
        'fecha_evento',
        'motivo',
        'hora_salida',
        'evidencia_foto_salida',
        'hora_regreso',
        'evidencia_foto_regreso',
        'minutos_ausente',
        'salario_por_minuto_snapshot',
        'monto_a_deducir',
        'fecha_deduccion_nomina',
        'registrado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_evento' => 'date',
            'fecha_deduccion_nomina' => 'date',
            'minutos_ausente' => 'integer',
            'salario_por_minuto_snapshot' => 'decimal:8',
            'monto_a_deducir' => 'decimal:2',
        ];
    }

    public function getFotoSalidaUrlAttribute(): ?string
    {
        if (!$this->evidencia_foto_salida) {
            return null;
        }
        return asset('storage/' . $this->evidencia_foto_salida);
    }

    public function getFotoRegresoUrlAttribute(): ?string
    {
        if (!$this->evidencia_foto_regreso) {
            return null;
        }
        return asset('storage/' . $this->evidencia_foto_regreso);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RhColaborador::class, 'rh_colaborador_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}

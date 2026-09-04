<?php

namespace App\Models\PuntoVenta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoPdvProrroga extends Model
{
    protected $table = 'pdv_turno_prorrogas';

    protected $fillable = [
        'atencion_id',
        'referencia_inicio_at',
        'alertado_at',
        'snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'referencia_inicio_at' => 'datetime',
            'alertado_at' => 'datetime',
            'snapshot_json' => 'array',
        ];
    }

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(TurnoPdvAtencion::class, 'atencion_id');
    }
}

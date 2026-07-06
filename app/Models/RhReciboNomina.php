<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhReciboNomina extends Model
{
    protected $table = 'rh_recibos_nomina';

    protected $fillable = [
        'rh_colaborador_id',
        'fecha_inicio',
        'fecha_fin',
        'firma_colaborador_path',
        'firmado_por_id',
        'firmado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'firmado_en' => 'datetime',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RhColaborador::class, 'rh_colaborador_id');
    }

    public function firmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmado_por_id');
    }
}

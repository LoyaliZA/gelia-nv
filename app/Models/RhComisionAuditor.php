<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhComisionAuditor extends Model
{
    protected $table = 'rh_comisiones_auditor';

    protected $fillable = [
        'uuid',
        'user_id',
        'rh_deduccion_id',
        'catalogo_regla_incidencia_id',
        'monto',
        'estado',
        'fecha_acreditacion',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_acreditacion' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deduccion(): BelongsTo
    {
        return $this->belongsTo(RhDeduccion::class, 'rh_deduccion_id');
    }

    public function reglaIncidencia(): BelongsTo
    {
        return $this->belongsTo(CatalogoReglaIncidencia::class, 'catalogo_regla_incidencia_id');
    }
}

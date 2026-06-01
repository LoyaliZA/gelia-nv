<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhMovimientoComisionColaborador extends Model
{
    protected $table = 'rh_movimientos_comision_colaborador';

    public const TIPO_CARGO = 'cargo';

    public const TIPO_ABONO = 'abono';

    protected $fillable = [
        'rh_colaborador_id',
        'rh_deduccion_id',
        'tipo',
        'monto',
        'saldo_resultante',
        'concepto',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'saldo_resultante' => 'decimal:2',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RhColaborador::class, 'rh_colaborador_id');
    }

    public function deduccion(): BelongsTo
    {
        return $this->belongsTo(RhDeduccion::class, 'rh_deduccion_id');
    }
}

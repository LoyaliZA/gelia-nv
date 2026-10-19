<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoAplicacionComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_pago_aplicaciones';

    protected $fillable = ['pago_id', 'asignacion_id', 'importe_centavos'];

    protected $casts = ['importe_centavos' => 'integer'];

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(AsignacionComisionEmpaque::class, 'asignacion_id');
    }
}

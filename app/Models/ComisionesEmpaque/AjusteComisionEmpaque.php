<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;

class AjusteComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_ajustes';

    protected $fillable = [
        'corte_id', 'asignacion_id', 'unidad_id', 'participante_id', 'importe_centavos',
        'aplicar_en_saldo', 'motivo', 'responsable_id',
    ];

    protected $casts = [
        'importe_centavos' => 'integer',
        'aplicar_en_saldo' => 'boolean',
    ];
}

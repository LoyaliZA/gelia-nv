<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;

class SincronizacionComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_sincronizaciones';

    protected $fillable = [
        'estado', 'mensaje', 'diferencias', 'unidades_nuevas', 'unidades_actualizadas', 'detalle',
        'ejecutado_por_id', 'iniciado_at', 'finalizado_at',
    ];

    protected $casts = [
        'detalle' => 'array',
        'iniciado_at' => 'datetime',
        'finalizado_at' => 'datetime',
        'diferencias' => 'integer',
        'unidades_nuevas' => 'integer',
        'unidades_actualizadas' => 'integer',
    ];
}

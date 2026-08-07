<?php

namespace App\Models\SaldosAFavor;

use Illuminate\Database\Eloquent\Model;

class SafMotivo extends Model
{
    protected $table = 'saf_motivos';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria',
        'requiere_detalle',
        'activo',
        'orden',
    ];

    protected $casts = [
        'requiere_detalle' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];
}

<?php

namespace App\Models\PuntoVenta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MotivoPausaPdv extends Model
{
    protected $table = 'pdv_motivos_pausa';

    protected $fillable = [
        'slug',
        'nombre',
        'activo',
        'requiere_detalle',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'requiere_detalle' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function intervalos(): HasMany
    {
        return $this->hasMany(IntervaloOperativoPdv::class, 'motivo_pausa_id');
    }
}

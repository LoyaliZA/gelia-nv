<?php

namespace App\Models\PuntoVenta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

class MedicionEventoPdv extends Model
{
    protected $table = 'pdv_medicion_eventos';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'efectivo_at' => 'immutable_datetime',
            'registrado_at' => 'immutable_datetime',
            'estado_anterior' => 'array',
            'estado_nuevo' => 'array',
            'contexto' => 'array',
            'secuencia' => 'integer',
            'contrato_version' => 'integer',
            'esquema_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos de medición son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los eventos de medición no se eliminan.'));
    }

    protected function asDateTime($value)
    {
        return Carbon::parse($value, 'UTC')->utc();
    }
}

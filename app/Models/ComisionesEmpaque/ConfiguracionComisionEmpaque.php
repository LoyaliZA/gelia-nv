<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_configuracion';

    protected $fillable = ['fecha_inicio', 'fecha_fin', 'zona_horaria'];

    protected $casts = ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];

    public static function actual(): self
    {
        return static::query()->firstOrCreate([], [
            'zona_horaria' => config('app.timezone', 'America/Mexico_City'),
        ]);
    }
}

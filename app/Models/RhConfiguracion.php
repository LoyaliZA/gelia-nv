<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhConfiguracion extends Model
{
    protected $table = 'rh_configuraciones';

    protected $fillable = [
        'folio_prefijo',
        'folio_separador',
        'folio_padding',
        'folio_incluir_anio',
        'dias_periodo_pago',
        'decimales_salario_minuto',
        'he_folio_prefijo',
        'he_folio_padding',
        'he_multiplicador_pago',
        'he_minutos_minimos',
        'he_tarifa_hora_fija',
        'he_usar_tarifa_fija',
        'he_gracia_minutos_despues_salida',
        'inc_folio_prefijo',
        'inc_folio_padding',
        'pre_folio_prefijo',
        'pre_folio_padding',
    ];

    protected function casts(): array
    {
        return [
            'folio_incluir_anio' => 'boolean',
            'folio_padding' => 'integer',
            'dias_periodo_pago' => 'integer',
            'decimales_salario_minuto' => 'integer',
            'he_folio_padding' => 'integer',
            'he_multiplicador_pago' => 'decimal:2',
            'he_minutos_minimos' => 'integer',
            'he_tarifa_hora_fija' => 'decimal:2',
            'he_usar_tarifa_fija' => 'boolean',
            'he_gracia_minutos_despues_salida' => 'integer',
            'inc_folio_padding' => 'integer',
            'pre_folio_padding' => 'integer',
        ];
    }

    public static function obtener(): self
    {
        $config = static::query()->first();

        if ($config) {
            return $config;
        }

        return static::create([
            'folio_prefijo' => config('rh.folio_prefijo', 'COL'),
            'folio_separador' => config('rh.folio_separador', '-'),
            'folio_padding' => config('rh.folio_padding', 6),
            'folio_incluir_anio' => config('rh.folio_incluir_anio', false),
            'dias_periodo_pago' => config('rh.dias_periodo_pago', 30),
            'decimales_salario_minuto' => config('rh.decimales_salario_minuto', 8),
            'he_folio_prefijo' => config('rh.he_folio_prefijo', 'HE'),
            'he_folio_padding' => config('rh.he_folio_padding', 6),
            'he_multiplicador_pago' => config('rh.he_multiplicador_pago', 2.00),
            'he_minutos_minimos' => config('rh.he_minutos_minimos', 30),
            'he_tarifa_hora_fija' => config('rh.he_tarifa_hora_fija', 39.00),
            'he_usar_tarifa_fija' => config('rh.he_usar_tarifa_fija', true),
            'he_gracia_minutos_despues_salida' => config('rh.he_gracia_minutos_despues_salida', 30),
            'inc_folio_prefijo' => config('rh.inc_folio_prefijo', 'DED'),
            'inc_folio_padding' => config('rh.inc_folio_padding', 6),
            'pre_folio_prefijo' => config('rh.pre_folio_prefijo', 'PRE'),
            'pre_folio_padding' => config('rh.pre_folio_padding', 6),
        ]);
    }
}

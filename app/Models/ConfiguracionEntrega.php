<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionEntrega extends Model
{
    /**
     * Nombre de la tabla asociada al modelo.
     */
    protected $table = 'configuracion_entregas';

    /**
     * Atributos asignables de forma masiva.
     */
    protected $fillable = [
        'latitud_origen',
        'longitud_origen',
        'radio_tolerancia_km',
        'tarifa_envio_extra',
        'cobro_extra_por_km',
        'usar_api_distancia',
        'api_key_google',
        'google_map_id',
    ];

    /**
     * Conversión de tipos de atributos nativos.
     */
    protected function casts(): array
    {
        return [
            'latitud_origen' => 'decimal:8',
            'longitud_origen' => 'decimal:8',
            'radio_tolerancia_km' => 'decimal:2',
            'tarifa_envio_extra' => 'decimal:2',
            'cobro_extra_por_km' => 'boolean',
            'usar_api_distancia' => 'boolean',
        ];
    }
}
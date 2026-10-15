<?php

return [

    // No se activa por defecto ni se declara cobertura integral durante V1.
    'medicion' => [
        'estado' => env('PDV_MEDICION_ESTADO', 'desactivada'),
        'sucursales' => array_values(array_filter(explode(',', env('PDV_MEDICION_SUCURSALES', '')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exportación operativa de resguardos PDV
    |--------------------------------------------------------------------------
    |
    | Umbrales aprobados para decidir generación síncrona vs Job en segundo plano.
    |
    */
    'resguardos' => [
        'exportacion' => [
            'pesado_registros' => 200,
            'expira_horas' => 48,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exportación de reportes de métricas PDV
    |--------------------------------------------------------------------------
    */
    'reportes' => [
        'exportacion' => [
            'pesado_dias_rango' => 31,
            'expira_horas' => 48,
        ],
    ],

];

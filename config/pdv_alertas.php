<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preferencias de alertas PDV por defecto (nuevos usuarios)
    |--------------------------------------------------------------------------
    | Independientes de alertas_prefs de Mensajería (CONTRATO_PANTALLAS_PDV §9).
    */
    'defaults' => [
        'canales' => [
            'sonido' => true,
            'voz' => true,
            'web_push' => true,
        ],
        'tono_id' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Terminal de alertas generales de sucursal (fase 12)
    |--------------------------------------------------------------------------
    */
    'terminal' => [
        'latido_segundos' => 30,
        'vencimiento_sin_latido_segundos' => 120,
        'max_activas_por_sucursal' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Aviso previo a vencimiento de plazos de atención (backend)
    |--------------------------------------------------------------------------
    */
    'aviso_previo_minutos' => [
        'espera_inicial' => 1,
        'prorroga' => 2,
    ],

];

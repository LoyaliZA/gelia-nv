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

];

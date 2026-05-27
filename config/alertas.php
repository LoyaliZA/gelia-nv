<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tonos de notificación disponibles
    |--------------------------------------------------------------------------
    | Agregar entradas aquí al subir nuevos MP3 en public/assets/sounds/
    */
    'tonos' => [
        [
            'id'      => 'default',
            'nombre'  => 'Campana clásica',
            'archivo' => 'notification.mp3',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preferencias de alertas por defecto (nuevos usuarios)
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'canales' => [
            'sonido'     => true,
            'voz'        => true,
            'escritorio' => true,
            'app'        => true,
        ],
        'tono_id' => 'default',
        'tipos'   => [
            'nueva'                    => true,
            'reparada'                 => true,
            'rechazada'                => true,
            'pago_rechazado'           => true,
            'pago_confirmado'          => true,
            'actualizacion'            => true,
            'alerta_pago_insuficiente' => true,
            'alerta_ascenso_lista'     => true,
            'consulta_nueva'           => true,
            'consulta_respondida'      => true,
            'rollback_confirmado'      => true,
            'resumen_vencidos'         => true,
        ],
    ],

];

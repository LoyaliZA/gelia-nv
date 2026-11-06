<?php

return [

    'estados' => [
        'disponible' => [
            'etiqueta' => 'En línea',
            'emoji' => null,
            'color' => null,
        ],
    ],

    'defaults' => [
        'estado' => 'disponible',
        'modo' => 'automatico',
        'automatizar' => true,
        'mensaje' => null,
        'expira_at' => null,
        'ultima_actividad_at' => null,
        'inactividad_minutos' => 0,
        'inactividad_estado' => 'disponible',
        'horarios' => [],
    ],

];

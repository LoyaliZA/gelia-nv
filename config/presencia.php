<?php

return [

    'estados' => [
        'disponible' => [
            'etiqueta' => 'Disponible',
            'emoji' => '🟢',
            'color' => '#22c55e',
        ],
        'en_junta' => [
            'etiqueta' => 'En junta',
            'emoji' => '📅',
            'color' => '#8b5cf6',
        ],
        'comiendo' => [
            'etiqueta' => 'Comiendo',
            'emoji' => '🍽️',
            'color' => '#f59e0b',
        ],
        'en_ruta_venta' => [
            'etiqueta' => 'En ruta de venta',
            'emoji' => '🚗',
            'color' => '#3b82f6',
        ],
        'ocupado' => [
            'etiqueta' => 'Ocupado',
            'emoji' => '🔴',
            'color' => '#ef4444',
        ],
        'ausente' => [
            'etiqueta' => 'Ausente',
            'emoji' => '⏸️',
            'color' => '#94a3b8',
        ],
    ],

    'defaults' => [
        'estado' => 'disponible',
        'modo' => 'automatico',
        'automatizar' => true,
        'mensaje' => null,
        'expira_at' => null,
        'ultima_actividad_at' => null,
        'inactividad_minutos' => 45,
        'inactividad_estado' => 'ausente',
        'horarios' => [
            [
                'estado' => 'comiendo',
                'dias' => [1, 2, 3, 4, 5],
                'inicio' => '13:00',
                'fin' => '14:00',
            ],
            [
                'estado' => 'en_junta',
                'dias' => [1, 2, 3, 4, 5],
                'inicio' => '09:00',
                'fin' => '10:00',
            ],
        ],
    ],

];

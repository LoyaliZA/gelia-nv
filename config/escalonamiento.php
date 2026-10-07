<?php

return [
    // Valor por defecto en despliegue; en runtime prevalece configuraciones_sistema (UI Finanzas → Cierre).
    'autoridad_activa' => (bool) env('ESCALONAMIENTO_AUTORIDAD_ACTIVA', false),

    'periodo_oficial_id' => env('ESCALONAMIENTO_PERIODO_OFICIAL_ID')
        ? (int) env('ESCALONAMIENTO_PERIODO_OFICIAL_ID')
        : null,

    'lista_publico_general_nombre' => 'Público General',
    'lista_publico_general_nombres_alternativos' => ['PUBLICO GENERAL', 'Público General'],
    'lista_publico_general_id' => env('ESCALONAMIENTO_LISTA_PG_ID'),

    'incidencias_bloquean_autorizacion' => [
        'irregularidad_mantenimiento',
        'divergencia_lista_operativa',
        'moneda_no_soportada',
    ],

    'meses_inactividad' => 3,
];

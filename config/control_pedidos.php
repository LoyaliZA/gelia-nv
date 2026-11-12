<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Direcciones normalizadas en Pedidos BMA
    |--------------------------------------------------------------------------
    |
    | Cuando es false, la UI y el flujo usan el textarea/endpoint heredados.
    | Los snapshots existentes no se borran.
    |
    */
    'direcciones_normalizadas' => (bool) env('CONTROL_PEDIDOS_DIRECCIONES_NORMALIZADAS', true),

    /** Suma de anexos y retiros de la consulta abierta. No es un tope por archivo. */
    'ajuste_tienda_adjuntos_max_bytes' => 52_428_800,
];

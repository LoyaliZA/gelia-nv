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
    'direcciones_normalizadas' => (bool) env('CONTROL_PEDIDOS_DIRECCIONES_NORMALIZADAS', false),
];

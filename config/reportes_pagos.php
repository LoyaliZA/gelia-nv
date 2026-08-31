<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clasificación de formas de pago para ingreso bancario (reportes)
    |--------------------------------------------------------------------------
    |
    | Categorías válidas:
    | - ingreso_bancario     → suma al total a localizar en bancos
    | - pago_no_bancario     → cubre pedido pero no es dinero nuevo en banco
    |
    | SAF no aparece aquí: se registra en el cierre del pedido (saf_aplicado),
    | nunca como exhibición de pago.
    |
    */
    'clasificacion_forma_pago' => [
        'transferencia' => 'ingreso_bancario',
        'deposito' => 'ingreso_bancario',
        'tarjeta' => 'ingreso_bancario',
        'efectivo' => 'pago_no_bancario',
        'otro' => 'pago_no_bancario',
    ],

    /*
    | Formas que exigen banco en la exhibición (validación de captura).
    | Debe coincidir con las marcadas como ingreso_bancario salvo decisión explícita.
    */
    'requiere_banco' => [
        'transferencia' => true,
        'deposito' => true,
        'tarjeta' => false,
        'efectivo' => false,
        'otro' => false,
    ],

    /*
    | Estados de revisión que cuentan como "pago validado" para ingreso bancario.
    */
    'estados_revision_validados' => [
        'verificado',
        'con_observaciones',
    ],

    /*
    | Umbrales para considerar exportación "pesada" (modal segundo plano).
    */
    'exportacion' => [
        'pesado_pedidos' => 80,
        'pesado_exhibiciones' => 200,
        'pesado_vouchers' => 150,
        'pesado_bytes' => 15_000_000,
    ],

];

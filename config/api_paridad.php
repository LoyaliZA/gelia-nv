<?php

/**
 * Paridad entre las rutas web y el espejo móvil (docs/ai/API_MOVIL.md).
 *
 * `modulos`: prefijos web que deben existir como api/v1/mobile/{uri}.
 * `excluidas`: rutas web sin equivalente móvil, con su motivo. Al agregar una
 * ruta web nueva en estos módulos, `php artisan api:paridad-rutas` falla hasta
 * que se espeja en routes/api/mobile-*.php o se excluye aquí con motivo.
 */

return [
    'modulos' => ['punto-venta', 'control-pedidos'],

    'excluidas' => [
        'GET punto-venta/configurar-sucursal' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET punto-venta/operacion/vendedores' => 'Solo redirige a otra ruta; no devuelve datos.',
        'GET punto-venta/operacion/vendedores/datos' => 'Solo redirige a otra ruta; no devuelve datos.',
        'GET punto-venta/pantalla-sala' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET punto-venta/publicidad' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET punto-venta/reportes' => 'Solo redirige a otra ruta; no devuelve datos.',
        'GET punto-venta/reportes/auditoria-operacion' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/auditar' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/cedis' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/delegado' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/direcciones' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/direcciones/cliente/{cliente}' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/direcciones/solicitudes' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/direcciones/solicitudes/{solicitud}' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/municipal/consultas-tienda' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/plazos' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
        'GET control-pedidos/tienda' => 'Pantalla Inertia sin versión JSON; la app móvil usa su propia vista y los endpoints de datos.',
    ],
];

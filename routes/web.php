<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/web/public.php';
require __DIR__.'/web/guest.php';

Route::middleware(['auth'])->group(function () {
    require __DIR__.'/web/core.php';
    require __DIR__.'/web/modules/gelia-ai.php';
    require __DIR__.'/web/modules/mensajeria.php';
    require __DIR__.'/web/modules/mis-clientes.php';
    require __DIR__.'/web/modules/limpieza-clientes.php';
    require __DIR__.'/web/modules/plantilla-bellaroma.php';
    require __DIR__.'/web/modules/integraciones/tiendanube.php';
    require __DIR__.'/web/modules/integraciones/woocommerce.php';
    require __DIR__.'/web/modules/solicitudes.php';
    require __DIR__.'/web/modules/facturas.php';
    require __DIR__.'/web/modules/traspasos.php';
    require __DIR__.'/web/modules/cancelaciones-cotizaciones.php';
    require __DIR__.'/web/modules/auto-cobranza.php';
    require __DIR__.'/web/modules/contabilidad.php';
    require __DIR__.'/web/modules/reportes.php';
    require __DIR__.'/web/modules/saldos-favor.php';
    require __DIR__.'/web/modules/entregas.php';
    require __DIR__.'/web/modules/funciones-operativas.php';
    require __DIR__.'/web/modules/almacenes.php';
    require __DIR__.'/web/modules/activos.php';
    require __DIR__.'/web/modules/gestion-interna.php';
    require __DIR__.'/web/modules/rh.php';
    require __DIR__.'/web/modules/control-pedidos/index.php';

    Route::prefix('admin')->name('admin.')->group(function () {
        require __DIR__.'/web/admin/index.php';
    });

    require __DIR__.'/web/internal-api.php';
});

require __DIR__.'/soporte.php';

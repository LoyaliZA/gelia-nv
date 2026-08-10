<?php

/**
 * Self-check UI CEDIS responder pesaje (colores, fotos, SKU, detalle por producto).
 * Uso: php tests/Unit/ControlPedidos/check_cedis_responder_pesaje_ui.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
$modal = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx');
$visor = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalVistaPreviaDocumento.jsx');
$routes = file_get_contents($root.'/routes/web.php');

$checks = [
    ['usa InputConEscanner', str_contains($modal, 'InputConEscanner')],
    ['usa ModalVistaPreviaDocumento', str_contains($modal, 'ModalVistaPreviaDocumento')],
    ['createObjectURL miniaturas', str_contains($modal, 'createObjectURL')],
    ['GaleriaEvidencias', str_contains($modal, 'GaleriaEvidencias')],
    ['lookup gestion_interna.productos.buscar', str_contains($modal, 'gestion_interna.productos.buscar')],
    ['sin bg-black/5 en modal', ! str_contains($modal, 'bg-black/5')],
    ['sin Detalle por producto opcional libre', ! str_contains($modal, 'Detalle por producto (opcional)')],
    ['Productos revisados + SKU', str_contains($modal, 'Productos revisados') && str_contains($modal, 'SKU / código')],
    ['revisionDesdeProducto', str_contains($modal, 'revisionDesdeProducto')],
    ['visor sin bg-black/5 body', ! str_contains($visor, 'bg-black/5')],
    ['visor cierre móvil', str_contains($visor, 'Cerrar vista de la foto')],
    ['middleware CEDIS en buscar', str_contains($routes, 'control_pedidos.cedis')
        && str_contains($routes, "name('productos.buscar')")],
];

foreach ($checks as [$label, $ok]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fallos++;
    }
}

exit($fallos > 0 ? 1 : 0);

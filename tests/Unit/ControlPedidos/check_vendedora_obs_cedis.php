<?php

/**
 * Self-check gates vista vendedora (listado Obs. CEDIS + form + notif).
 * Uso: php tests/Unit/ControlPedidos/check_vendedora_obs_cedis.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$styles = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js');
$filtros = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/FiltrosPedidos.jsx');
$tabla = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/TablaPedidos.jsx');
$listar = file_get_contents($root.'/app/Services/ControlPedidos/ListarPedidosBmaService.php');
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');
$seccion = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/SeccionRevisionFisicaPedido.jsx');
$detalle = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalDetallePedido.jsx');
$responder = file_get_contents($root.'/app/Services/ControlPedidos/ResponderPesajePedidoBmaService.php');
$alerta = file_get_contents($root.'/app/Notifications/AlertaPedidoBma.php');

$checks = [
    ['tab OBS_CEDIS en TABS', str_contains($styles, "id: 'OBS_CEDIS'")],
    ['badge Observaciones CEDIS', str_contains($styles, 'badgeObservacionesCedis')],
    ['badge Sin existencias', str_contains($styles, 'badgeSinExistencias')],
    ['helper pedidoTieneSinExistencias', str_contains($styles, 'pedidoTieneSinExistencias')],
    ['filtros conteo obs_cedis', str_contains($filtros, 'OBS_CEDIS: metricas.obs_cedis')],
    ['listar filtro OBS_CEDIS', str_contains($listar, "'OBS_CEDIS' => \$query->where('tiene_observaciones_fisicas', true)")],
    ['listar metrica obs_cedis', str_contains($listar, "'obs_cedis' =>")],
    ['tabla usa badgeObservacionesCedis', str_contains($tabla, 'badgeObservacionesCedis')],
    ['tabla usa badgeSinExistencias', str_contains($tabla, 'badgeSinExistencias')],
    ['form usa SeccionRevisionFisicaPedido', str_contains($form, 'SeccionRevisionFisicaPedido')],
    ['detalle usa SeccionRevisionFisicaPedido', str_contains($detalle, 'SeccionRevisionFisicaPedido')],
    ['seccion productos con detalle', str_contains($seccion, 'Productos con detalle')],
    ['seccion foto por envío', str_contains($seccion, 'Foto por envío')],
    ['seccion sin existencias copy', str_contains($seccion, 'Sin existencias en CEDIS')],
    ['notif url OBS_CEDIS o SIN_EXISTENCIA', str_contains($responder, 'tab=OBS_CEDIS') || str_contains($responder, 'tab=SIN_EXISTENCIA')],
    ['tab SIN_EXISTENCIA styles', str_contains($styles, "id: 'SIN_EXISTENCIA'")],
    ['notif extra con_observaciones_fisicas', str_contains($responder, "'con_observaciones_fisicas' => \$tieneObs")],
    ['voz con observaciones', str_contains($alerta, 'con observaciones. Revísalas')],
    ['titulo pesaje con observaciones', str_contains($alerta, 'Pesaje con observaciones')],
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

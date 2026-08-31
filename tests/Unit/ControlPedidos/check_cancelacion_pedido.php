<?php

/**
 * Self-check RF-06 cancelación pre-hitos.
 * Uso: php tests/Unit/ControlPedidos/check_cancelacion_pedido.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
require_once __DIR__.'/_routes_helper.php';

$servicio = file_get_contents($root.'/app/Services/ControlPedidos/CancelarPedidoBmaService.php');
$pedido = file_get_contents($root.'/app/Models/ControlPedidos/PedidoBma.php');
$estatus = file_get_contents($root.'/app/Models/ControlPedidos/CatalogoEstatusPedido.php');
$migracion = file_get_contents($root.'/database/migrations/2026_08_09_190000_add_cancelacion_pedido_bma.php');
$routes = control_pedidos_routes_content($root);
$acciones = file_get_contents($root.'/app/Support/ControlPedidos/AccionesHistorialPedidoBma.php');
$modal = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalCancelarPedido.jsx');
$tabla = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/TablaPedidos.jsx');

$checks = [
    ['FASE_CANCELADO', str_contains($estatus, "FASE_CANCELADO = 'CANCELADO'")],
    ['puedeCancelarDirecto', str_contains($pedido, 'function puedeCancelarDirecto')],
    ['servicio idempotente cancelado_at', str_contains($servicio, 'cancelado_at')],
    ['servicio liberar SAF', str_contains($servicio, 'liberarReservasPendientes')],
    ['servicio lockForUpdate', str_contains($servicio, 'lockForUpdate')],
    ['accion CANCELACION', str_contains($acciones, 'CANCELACION')],
    ['permiso cancelar migracion', str_contains($migracion, 'control_pedidos.cancelar')],
    ['ruta cancelar', str_contains($routes, "name('cancelar')")],
    ['UI ModalCancelarPedido', str_contains($modal, 'Confirmar cancelación')],
    ['tabla Ban cancelar', str_contains($tabla, 'onCancelar')],
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

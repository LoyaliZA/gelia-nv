<?php

/**
 * Self-check: no nullificar almacén/paquetería en update/autoguard; form hidrata FKs.
 * Uso: php tests/Unit/ControlPedidos/check_conservar_almacen_paqueteria.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$actualizar = file_get_contents($root.'/app/Services/ControlPedidos/ActualizarPedidoBmaService.php');
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx');

$checks = [
    ['update conserva almacen vacío', str_contains($actualizar, "empty(\$attrs['almacen_id']) && \$pedido->almacen_id")],
    ['update conserva paqueteria vacía', str_contains($actualizar, "empty(\$attrs['catalogo_paqueteria_id']) && \$pedido->catalogo_paqueteria_id")],
    ['formDefaults almacén desde relación', str_contains($form, 'pedido?.almacen_id ?? pedido?.almacen?.id')],
    ['formDefaults paquetería desde relación', str_contains($form, 'pedido?.catalogo_paqueteria_id ?? pedido?.paqueteria?.id')],
    ['sync CEDIS merge almacén', str_contains($form, 'pedido.almacen_id ?? pedido.almacen?.id ?? prev.almacen_id')],
    ['sync CEDIS merge paquetería', str_contains($form, 'pedido.catalogo_paqueteria_id ?? pedido.paqueteria?.id ?? prev.catalogo_paqueteria_id')],
    ['hidratación almacén/paquetería', str_contains($form, 'Hidratar almacén/paquetería')],
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

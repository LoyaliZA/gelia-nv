<?php

/**
 * Self-check: reexpedición por zona (costo_adicional configurable).
 * Uso: php tests/Unit/ControlPedidos/check_reexpedicion_aparte.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
$resolver = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/resolverReexpedicionForm.js');
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');
$modelo = file_get_contents($root.'/app/Models/ControlPedidos/CatalogoZonaPedido.php');
$migs = glob($root.'/database/migrations/*costo_adicional_zonas_pedido.php');
$mig = $migs[0] ?? '';

$checks = [
    ['helper costoReexpedicionDeZona', str_contains($resolver, 'costoReexpedicionDeZona')],
    ['cargo desde zona seleccionada', str_contains($resolver, 'zonaIdSeleccionada')],
    ['modelo costo_adicional', str_contains($modelo, 'costo_adicional')],
    ['migracion costo_adicional', $mig !== '' && str_contains(file_get_contents($mig), 'costo_adicional')],
    ['UI linea Reexpedición', str_contains($form, '>Reexpedición</span>')],
    ['copy zona admin', str_contains($form, 'Admin → Zonas Pedido')],
    ['effect por catalogo_zona_id', str_contains($form, 'costoReexpedicionDeZona(catalogos.zonas, data.catalogo_zona_id)')],
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

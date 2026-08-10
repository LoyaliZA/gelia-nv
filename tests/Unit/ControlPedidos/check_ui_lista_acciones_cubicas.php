<?php

/**
 * Self-check: UI lista/cards depto + acciones cúbicas.
 * Uso: php tests/Unit/ControlPedidos/check_ui_lista_acciones_cubicas.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$bloque = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/BloqueVendedorPedido.jsx');
$cubico = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/BotonAccionCubico.jsx');
$aud = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Auditar/Partials/TablaAuditoria.jsx');
$del = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Delegado/Partials/TablaDelegado.jsx');
$cedis = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx');

$checks = [
    ['bloque variante nombre', str_contains($bloque, "variante === 'nombre'")],
    ['bloque variante etiquetas', str_contains($bloque, "variante === 'etiquetas'")],
    ['BotonAccionCubico existe', str_contains($cubico, 'group/accion')],
    ['BotonAccionCubico expand hover', str_contains($cubico, 'group-hover/accion:max-w-[9rem]')],
    ['BotonAccionCubico conLabel', str_contains($cubico, 'conLabel')],
    ['TablaPedidos usa BotonAccionCubico', str_contains(
        file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/TablaPedidos.jsx'),
        'BotonAccionCubico'
    )],
    ['Auditar importa BotonAccionCubico', str_contains($aud, 'BotonAccionCubico')],
    ['Auditar columna Vendedor', str_contains($aud, 'Vendedor_')],
    ['Auditar etiquetas top-right', str_contains($aud, 'variante="etiquetas"')],
    ['Delegado importa BotonAccionCubico', str_contains($del, 'BotonAccionCubico')],
    ['Delegado columna Vendedor', str_contains($del, '>Vendedor<')],
    ['CEDIS importa BotonAccionCubico', str_contains($cedis, 'BotonAccionCubico')],
    ['CEDIS etiquetas top-right', str_contains($cedis, 'variante="etiquetas"')],
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

<?php

/**
 * Self-check: vendedor + departamento en cards Auxiliares / CEDIS / Guías.
 * Uso: php tests/Unit/ControlPedidos/check_vendedor_depto_cards.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$auditoria = file_get_contents($root.'/app/Services/ControlPedidos/ListarPedidosAuditoriaService.php');
$cedis = file_get_contents($root.'/app/Services/ControlPedidos/ListarPedidosCedisService.php');
$delegado = file_get_contents($root.'/app/Services/ControlPedidos/ListarPedidosDelegadoService.php');
$styles = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js');
$bloque = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/BloqueVendedorPedido.jsx');
$tablaAud = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Auditar/Partials/TablaAuditoria.jsx');
$tarjetas = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx');
$tablaDel = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Delegado/Partials/TablaDelegado.jsx');

$checks = [
    ['auditoria eager depto', str_contains($auditoria, 'vendedor.departamento:id,nombre')],
    ['auditoria eager departamentos', str_contains($auditoria, 'vendedor.departamentos:id,nombre')],
    ['cedis eager depto', str_contains($cedis, 'vendedor.departamento:id,nombre')],
    ['delegado eager depto', str_contains($delegado, 'vendedor.departamento:id,nombre')],
    ['helper nombreDepartamentoVendedor', str_contains($styles, 'nombreDepartamentoVendedor')],
    ['helper nombresDepartamentosVendedor', str_contains($styles, 'nombresDepartamentosVendedor')],
    ['badge departamento', str_contains($styles, 'badgeDepartamentoVendedor')],
    ['bloque usa helpers', str_contains($bloque, 'nombresDepartamentosVendedor') && str_contains($bloque, 'badgeDepartamentoVendedor')],
    ['bloque icono User', str_contains($bloque, '<User') && str_contains($bloque, 'vendedor.name')],
    ['TablaAuditoria importa bloque', str_contains($tablaAud, 'BloqueVendedorPedido')],
    ['TarjetasCedis importa bloque', str_contains($tarjetas, 'BloqueVendedorPedido')],
    ['TablaDelegado importa bloque', str_contains($tablaDel, 'BloqueVendedorPedido')],
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

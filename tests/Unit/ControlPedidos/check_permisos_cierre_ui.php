<?php

/**
 * Self-check UI y gates de permisos de cierre Control Pedidos.
 * Uso: php tests/Unit/ControlPedidos/check_permisos_cierre_ui.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$revisar = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Auditar/Partials/ModalRevisarPedido.jsx');
$tarjetas = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx');
$detalleCedis = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis.jsx');
$import = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Delegado/Partials/PanelImportExport.jsx');
$auditoria = file_get_contents($root.'/app/Http/Controllers/ControlPedidos/PedidoBmaAuditoriaController.php');
$cedis = file_get_contents($root.'/app/Http/Controllers/ControlPedidos/PedidoBmaCedisController.php');
$liberarReq = file_get_contents($root.'/app/Http/Requests/ControlPedidos/LiberarResguardoPedidoBmaRequest.php');
$enviadoReq = file_get_contents($root.'/app/Http/Requests/ControlPedidos/MarcarEnviadoPedidoBmaRequest.php');
$importReq = file_get_contents($root.'/app/Http/Requests/ControlPedidos/ImportarGuiasPedidoRequest.php');
$migracion = file_get_contents($root.'/database/migrations/2026_08_18_151700_add_permisos_cierre_control_pedidos.php');

$checks = [
    ['UI aprobar exige extra', str_contains($revisar, "can('control_pedidos.auditar.aprobar')")],
    ['UI liberar exige extra', str_contains($revisar, "can('control_pedidos.liberar_resguardo')")],
    ['UI CEDIS tarjetas enviar', str_contains($tarjetas, 'control_pedidos.cedis.enviar')],
    ['UI CEDIS detalle enviar', str_contains($detalleCedis, 'control_pedidos.cedis.enviar')],
    ['UI importar extra', str_contains($import, 'control_pedidos.delegado.importar')],
    ['Gate aprobar', str_contains($auditoria, "Gate::authorize('control_pedidos.auditar.aprobar')")],
    ['Gate liberar', str_contains($auditoria, "Gate::authorize('control_pedidos.liberar_resguardo')")],
    ['Gate enviar', str_contains($cedis, "Gate::authorize('control_pedidos.cedis.enviar')")],
    ['Request liberar', str_contains($liberarReq, 'control_pedidos.liberar_resguardo')],
    ['Request enviar', str_contains($enviadoReq, 'control_pedidos.cedis.enviar')],
    ['Request importar', str_contains($importReq, 'control_pedidos.delegado.importar')],
    ['Migracion backfill auditar', str_contains($migracion, "'control_pedidos.auditar'")
        && str_contains($migracion, 'control_pedidos.auditar.aprobar')],
    ['Migracion backfill cedis', str_contains($migracion, 'control_pedidos.cedis.enviar')],
    ['Migracion backfill delegado', str_contains($migracion, 'control_pedidos.delegado.importar')],
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

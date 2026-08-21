<?php

/**
 * Self-check: auto-reporte auxiliar (bitácora, sin cambio de fase).
 * Uso: php tests/Unit/ControlPedidos/check_auto_reporte_auxiliar.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
$svc = file_get_contents($root.'/app/Services/ControlPedidos/ReportarErrorDatosPedidoBmaService.php');
$modal = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalReportarErrorDatos.jsx');
$auditar = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Auditar/Partials/ModalRevisarPedido.jsx');
$routes = file_get_contents($root.'/routes/web.php');

$checks = [
    ['servicio auto-reporte', str_contains($svc, 'ejecutarAutoReporteAuxiliar')],
    ['sin bloqueo auto-reporte', ! str_contains($svc, 'La remisión se corrige aquí mismo en auditoría')],
    ['UI grupo auxiliar en auditar', str_contains($modal, 'Mi error (remisión / pago)')],
    ['copy auto-reporte', str_contains($modal, 'Se registrará en bitácora')],
    ['folio editable auditar', str_contains($auditar, 'folio_remision.update')],
    ['ruta folio update', str_contains($routes, 'folio_remision.update')],
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

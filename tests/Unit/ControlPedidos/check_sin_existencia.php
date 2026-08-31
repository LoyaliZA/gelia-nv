<?php

/**
 * Self-check atender productos sin existencia.
 * Uso: php tests/Unit/ControlPedidos/check_sin_existencia.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$modelo = file_get_contents($root.'/app/Models/ControlPedidos/PedidoBmaRevisionProducto.php');
$pedido = file_get_contents($root.'/app/Models/ControlPedidos/PedidoBma.php');
$enviar = file_get_contents($root.'/app/Services/ControlPedidos/EnviarPedidoBmaService.php');
$aprobar = file_get_contents($root.'/app/Services/ControlPedidos/AprobarPedidoBmaService.php');
$empacar = file_get_contents($root.'/app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php');
$responder = file_get_contents($root.'/app/Services/ControlPedidos/ResponderPesajePedidoBmaService.php');
$atender = file_get_contents($root.'/app/Services/ControlPedidos/AtenderSinExistenciaPedidoBmaService.php');
$cedis = file_get_contents($root.'/app/Services/ControlPedidos/GestionarSinExistenciaCedisPedidoBmaService.php');
$alerta = file_get_contents($root.'/app/Notifications/AlertaPedidoBma.php');
$historial = file_get_contents($root.'/app/Support/ControlPedidos/AccionesHistorialPedidoBma.php');
$migracion = file_get_contents($root.'/database/migrations/2026_08_13_150000_add_resolucion_sin_existencia_revisiones_producto.php');
$modalPesaje = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx');
$seccion = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/SeccionRevisionFisicaPedido.jsx');
$styles = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js');
require_once __DIR__.'/_routes_helper.php';
$routes = control_pedidos_routes_content($root);
$listar = file_get_contents($root.'/app/Services/ControlPedidos/ListarPedidosBmaService.php');
$auditoria = file_get_contents($root.'/app/Services/ControlPedidos/ListarPedidosAuditoriaService.php');
$manual = file_get_contents($root.'/app/Support/Manuales/Content/ControlPedidosManualContent.php');

$checks = [
    ['migracion resolucion', str_contains($migracion, 'resolucion')],
    ['migracion sku', str_contains($migracion, 'sku')],
    ['modelo RESOLUCION_ESPERAR', str_contains($modelo, "RESOLUCION_ESPERAR = 'esperar'")],
    ['modelo estaSinExistenciaAbierta', str_contains($modelo, 'function estaSinExistenciaAbierta')],
    ['pedido assertSinExistenciaAtendida', str_contains($pedido, 'function assertSinExistenciaAtendida')],
    ['cancelar permitido con sin existencia abierta', str_contains($pedido, 'tieneSinExistenciaAbierta')],
    ['enviar llama assert', str_contains($enviar, 'assertSinExistenciaAtendida')],
    ['aprobar llama assert', str_contains($aprobar, 'assertSinExistenciaAtendida')],
    ['empacar llama assert', str_contains($empacar, 'assertSinExistenciaAtendida')],
    ['pesaje persiste sku', str_contains($responder, "'sku' => \$rev['sku']")],
    ['pesaje notif pedido_sin_existencia', str_contains($responder, "'pedido_sin_existencia'")],
    ['atender retirar/sustituir', str_contains($atender, 'MOTIVO_REPESAJE_QUITA_PIEZAS') && str_contains($atender, 'RESOLUCION_SUSTITUIR')],
    ['atender recálculo resolverTotales', str_contains($atender, 'resolverTotales')],
    ['atender SAF', str_contains($atender, 'reconciliarSaf')],
    ['atender auditoría si EN_CEDIS', str_contains($atender, 'FASE_PENDIENTE_AUXILIAR')],
    ['cedis reportar', str_contains($cedis, 'function reportar')],
    ['cedis stock_ok', str_contains($cedis, 'RESOLUCION_STOCK_OK')],
    ['cedis no borra estado_fisico', ! str_contains($cedis, "'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_BUENO")],
    ['alerta tipo pedido_sin_existencia', str_contains($alerta, "'pedido_sin_existencia'")],
    ['historial DECISION_SIN_EXISTENCIA', str_contains($historial, 'DECISION_SIN_EXISTENCIA')],
    ['UI permite eliminar pieza', str_contains($modalPesaje, "tipo: 'quitar_pieza'") && str_contains($modalPesaje, 'prev.filter((_, i) => i !== idx)')],
    ['UI sin_existencia sigue en estados', str_contains($modalPesaje, "sin_existencia")],
    ['UI persiste sku form', str_contains($modalPesaje, 'revisiones[${i}][sku]')],
    ['UI atender pieza', str_contains($seccion, 'Atender pieza')],
    ['tab SIN_EXISTENCIA', str_contains($styles, "id: 'SIN_EXISTENCIA'")],
    ['ruta atender', str_contains($routes, 'atender-sin-existencia')],
    ['ruta reportar cedis', str_contains($routes, 'reportar-sin-existencia')],
    ['ruta stock cedis', str_contains($routes, 'confirmar-stock-sin-existencia')],
    ['listar tab SIN_EXISTENCIA', str_contains($listar, "'SIN_EXISTENCIA'")],
    ['auditoria eager revisionesProducto', str_contains($auditoria, "'revisionesProducto'")],
    ['manual sin existencias', str_contains($manual, 'Sin existencias')],
];

require_once $root.'/vendor/autoload.php';

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;

$abierta = new PedidoBmaRevisionProducto(['estado_fisico' => 'sin_existencia', 'resolucion' => 'esperar']);
$cerrada = new PedidoBmaRevisionProducto(['estado_fisico' => 'sin_existencia', 'resolucion' => 'stock_ok']);
$checks[] = ['runtime esperar abierta', $abierta->estaSinExistenciaAbierta() === true];
$checks[] = ['runtime stock_ok cierra', $cerrada->estaSinExistenciaAbierta() === false];
$checks[] = ['runtime estado se conserva', $cerrada->estado_fisico === 'sin_existencia'];

$p = new PedidoBma([]);
$p->setRelation('revisionesProducto', collect([$abierta]));
$checks[] = ['runtime pedido abierta', $p->tieneSinExistenciaAbierta() === true];
$pCerrado = new PedidoBma([]);
$pCerrado->setRelation('revisionesProducto', collect([$cerrada]));
$checks[] = ['runtime pedido stock_ok no bloquea', $pCerrado->tieneSinExistenciaAbierta() === false];
try {
    $p->assertSinExistenciaAtendida();
    $checks[] = ['runtime assert abierta lanza', false];
} catch (\RuntimeException $e) {
    $checks[] = ['runtime assert abierta lanza', str_contains($e->getMessage(), 'sin existencias')];
}
$pCerrado->assertSinExistenciaAtendida();
$checks[] = ['runtime assert stock_ok ok', true];

foreach ($checks as [$label, $ok]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fallos++;
    }
}

exit($fallos > 0 ? 1 : 0);

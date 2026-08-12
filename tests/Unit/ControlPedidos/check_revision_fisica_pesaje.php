<?php

/**
 * Self-check RF-05 revisión física en pesaje.
 * Uso: php tests/Unit/ControlPedidos/check_revision_fisica_pesaje.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$servicio = file_get_contents($root.'/app/Services/ControlPedidos/ResponderPesajePedidoBmaService.php');
$request = file_get_contents($root.'/app/Http/Requests/ControlPedidos/ResponderPesajePedidoBmaRequest.php');
$modelo = file_get_contents($root.'/app/Models/ControlPedidos/PedidoBmaRevisionProducto.php');
$doc = file_get_contents($root.'/app/Models/ControlPedidos/PedidoBmaDocumento.php');
$migracion = file_get_contents($root.'/database/migrations/2026_08_09_180000_add_revision_fisica_pesaje_control_pedidos.php');
$modal = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx');

$checks = [
    ['modelo ESTADOS', str_contains($modelo, "ESTADO_DANADO = 'danado'")],
    ['requiereEvidencia helper', str_contains($modelo, 'function requiereEvidencia')],
    ['migracion revisiones_producto', str_contains($migracion, 'pedido_bma_revisiones_producto')],
    ['migracion estado_fisico_general', str_contains($migracion, 'estado_fisico_general')],
    ['doc tipo evidencia_condicion', str_contains($doc, 'TIPO_EVIDENCIA_CONDICION')],
    ['servicio deriva estado general', str_contains($servicio, 'derivarEstadoGeneral')],
    ['servicio exige evidencia malo', str_contains($servicio, 'malo/dañado requiere')],
    ['servicio flags unica/mejor', str_contains($servicio, 'unica_pieza') && str_contains($servicio, 'mejor_ejemplar')],
    ['request estado_fisico_general opcional', str_contains($request, "'estado_fisico_general' => ['nullable'")],
    ['UI revisión física', str_contains($modal, 'Revisión física de productos')],
    ['UI sin Estado general', ! str_contains($modal, 'Estado general')],
    ['UI Única pieza', str_contains($modal, 'Única pieza')],
    ['UI Mejor ejemplar', str_contains($modal, 'Mejor ejemplar')],
];

require_once $root.'/vendor/autoload.php';

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;

$checks[] = ['bueno no requiere evidencia', PedidoBmaRevisionProducto::requiereEvidencia('bueno') === false];
$checks[] = ['danado requiere evidencia', PedidoBmaRevisionProducto::requiereEvidencia('danado') === true];
$checks[] = ['sin_existencia en ESTADOS', in_array('sin_existencia', PedidoBmaRevisionProducto::ESTADOS, true)];
$checks[] = ['sin_existencia no exige foto', PedidoBmaRevisionProducto::requiereEvidencia('sin_existencia') === false];
$checks[] = ['sin_existencia exige comentario', PedidoBmaRevisionProducto::requiereComentario('sin_existencia') === true];
$checks[] = ['sin_existencia obs ventas', PedidoBmaRevisionProducto::esObservacionParaVentas('sin_existencia') === true];
$checks[] = ['UI label Sin existencias', str_contains($modal, 'sin_existencia') || str_contains(
    file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js'),
    'Sin existencias'
)];

foreach ($checks as [$label, $ok]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fallos++;
    }
}

exit($fallos > 0 ? 1 : 0);

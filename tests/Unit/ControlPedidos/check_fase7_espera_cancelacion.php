<?php

/**
 * Self-check Fase 7 — espera, cancelación operativa y liberación.
 * Ejecutar: php tests/Unit/ControlPedidos/check_fase7_espera_cancelacion.php
 */

$root = dirname(__DIR__, 3);
require_once __DIR__.'/_routes_helper.php';
$fallos = 0;

$assert = function (bool $ok, string $label) use (&$fallos): void {
    echo ($ok ? 'OK  ' : 'FAIL').' '.$label.PHP_EOL;
    if (! $ok) {
        $fallos++;
    }
};

$archivos = [
    'database/migrations/2026_08_24_210000_fase7_espera_cancelacion_liberacion.php',
    'app/Services/ControlPedidos/CancelacionOperativaConfig.php',
    'app/Services/ControlPedidos/CalcularFechaLimiteResguardoService.php',
    'app/Services/ControlPedidos/MarcarEsperaPagoPedidoService.php',
    'app/Services/ControlPedidos/RouterCancelacionPedidoBmaService.php',
    'app/Services/ControlPedidos/SolicitarCancelacionOperativaService.php',
    'app/Services/ControlPedidos/FinalizarCancelacionOperativaService.php',
    'app/Services/ControlPedidos/MatrizResolucionFinancieraCancelacionService.php',
    'app/Services/ControlPedidos/ReactivarCancelacionOperativaService.php',
    'app/Services/ControlPedidos/ResolverFinancieroCancelacionService.php',
    'app/Services/ControlPedidos/SolicitarLiberacionPorVencimientoEsperaService.php',
    'app/Services/ControlPedidos/LiberarTareaPreparacionService.php',
    'app/Services/ControlPedidos/AssertPedidoNoBloqueadoFase7.php',
    'app/Models/ControlPedidos/PedidoBmaCancelacionOperativa.php',
    'app/Models/ControlPedidos/PedidoBmaCancelacionOperativaTarea.php',
    'app/Console/Commands/ControlPedidos/EvaluarVencimientoEsperaPreparacionCommand.php',
    'resources/js/Pages/ControlPedidos/Partials/ModalLiberarMercancia.jsx',
    'resources/js/Pages/ControlPedidos/Partials/ModalCancelarPedido.jsx',
];

foreach ($archivos as $rel) {
    $assert(is_file($root.'/'.$rel), "existe {$rel}");
}

$needles = [
    'app/Services/ControlPedidos/RouterCancelacionPedidoBmaService.php' => ['debeUsarOperativa', 'flujo'],
    'app/Services/ControlPedidos/MatrizResolucionFinancieraCancelacionService.php' => ['requiere_resolutor', 'puede_auto'],
    'app/Services/ControlPedidos/LiberarTareaPreparacionService.php' => ['ConflictHttpException', 'FinalizarCancelacionOperativaService'],
    'app/Services/ControlPedidos/ReactivarCancelacionOperativaService.php' => ['ESTADO_REVERTIDA', 'ConflictHttpException'],
    'app/Support/ControlPedidos/MaquinaEstadosTareaPreparacion.php' => ['LIBERACION_SOLICITADA', 'ESTADO_RESPONDIDA'],
    'app/Support/ControlPedidos/AccionesHistorialPedidoBma.php' => ['ESPERA_PAGO', 'REACTIVACION_CANCELACION', 'BLOQUEO_FINANCIERO_CANCELACION'],
    'routes/console.php' => ['evaluar-vencimiento-espera-preparacion'],
    'resources/js/Pages/ControlPedidos/Partials/ModalLiberarMercancia.jsx' => ['Ya devolví estas piezas a disponibilidad', 'Liberar mercancía'],
    'resources/js/Pages/ControlPedidos/Partials/ModalCancelarPedido.jsx' => ['puede_reactivar', 'Solicitar cancelación', 'flujo'],
    'resources/js/Pages/ControlPedidos/Cedis/Index.jsx' => ['LIBERACIONES', 'liberaciones_pendientes'],
    'app/Services/ControlPedidos/CancelarPedidoBmaService.php' => ['liberarReservasPendientes'],
];

$routesContent = control_pedidos_routes_content($root);
foreach (['espera_pago', 'cancelacion_operativa.reactivar', 'cedis.liberar'] as $needle) {
    $assert(str_contains($routesContent, $needle), "routes control-pedidos contiene «{$needle}»");
}

foreach ($needles as $rel => $lista) {
    $path = $root.'/'.$rel;
    $contenido = is_file($path) ? file_get_contents($path) : '';
    foreach ($lista as $needle) {
        $assert(str_contains($contenido, $needle), "{$rel} contiene «{$needle}»");
    }
}

// Matriz: pagos > 0 bloquea sin resolución registrada.
$matrizSrc = file_get_contents($root.'/app/Services/ControlPedidos/MatrizResolucionFinancieraCancelacionService.php');
$assert(
    str_contains($matrizSrc, 'Hay pagos registrados') && str_contains($matrizSrc, 'requiere_resolutor\' => true'),
    'matriz bloquea con pagos > 0'
);

// Dual-path: CancelarPedidoBmaService no se elimina.
$assert(
    is_file($root.'/app/Services/ControlPedidos/CancelarPedidoBmaService.php'),
    'cancelación inmediata histórica conservada'
);

// Liberar no debe liberar SAF; eso es CancelarPedidoBmaService al finalizar.
$liberarSrc = file_get_contents($root.'/app/Services/ControlPedidos/LiberarTareaPreparacionService.php');
$assert(! str_contains($liberarSrc, 'liberarReservasPendientes'), 'LiberarTarea no libera SAF directamente');

$solicitarSrc = file_get_contents($root.'/app/Services/ControlPedidos/SolicitarCancelacionOperativaService.php');
$assert(! str_contains($solicitarSrc, 'liberarReservasPendientes'), 'Solicitar operativa no libera SAF');

echo PHP_EOL.($fallos === 0 ? "Fase 7 check OK\n" : "Fase 7 check FAIL ({$fallos})\n");
exit($fallos > 0 ? 1 : 0);

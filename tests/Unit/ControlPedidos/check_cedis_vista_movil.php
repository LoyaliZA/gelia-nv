<?php

/**
 * Self-check: vista móvil CEDIS (PDF, dirección, evidencias, confirmaciones, 44px).
 * Uso: php tests/Unit/ControlPedidos/check_cedis_vista_movil.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$index = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Index.jsx');
$tarjetas = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx');
$filtros = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/FiltrosCedis.jsx');
$pesaje = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx');
$detalle = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis.jsx');
$apartado = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalMarcarApartadoResguardo.jsx');
$visor = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalVistaPreviaDocumento.jsx');
$libs = file_get_contents($root.'/resources/js/utils/loadPreviewLibs.js');
$dir = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/DireccionPedidoResumen.jsx');

$checks = [
    ['KPI scroll móvil', str_contains($index, 'md:hidden') && str_contains($index, 'snap-x snap-mandatory')],
    ['KPI grid desktop', str_contains($index, 'hidden md:grid')],
    ['abrirDetalle no redirige pesaje', str_contains($index, 'setModalDetalle({ abierto: true, pedido })')
        && ! str_contains($index, "estatus_envio === 'pendiente_pesaje'")],
    ['Detalle siempre en tarjeta', str_contains($tarjetas, 'label="Detalle"')],
    ['filtro min-h 44', str_contains($filtros, 'min-h-[44px]')],
    ['PDF móvil VisorPdfPaginas', str_contains($pesaje, 'VisorPdfPaginas') && str_contains($pesaje, 'Abrir en pestaña')],
    ['iframe PDF desktop conservado', str_contains($pesaje, '<iframe')],
    ['visor PDF páginas en campo', str_contains($visor, 'VisorPdfPaginas') && str_contains($visor, 'esCampo')],
    ['loadPdfJs en preview libs', str_contains($libs, 'function loadPdfJs')],
    ['dirección en pesaje', str_contains($pesaje, 'DireccionPedidoResumen') && str_contains($pesaje, 'conCopia')],
    ['tel en dirección', str_contains($dir, 'tel:') && str_contains($dir, 'Copiar dirección')],
    ['dirección arriba en detalle', str_contains($detalle, 'Dirección de entrega') && str_contains($detalle, 'conCopia')],
    ['línea destinatario en tarjeta', str_contains($tarjetas, 'CP ')],
    ['capture + galería pesaje', str_contains($pesaje, 'capture="environment"') && str_contains($pesaje, 'Tomar foto') && str_contains($pesaje, 'Galería')],
    ['capture + galería apartado', str_contains($apartado, 'capture="environment"') && str_contains($apartado, 'Tomar foto') && str_contains($apartado, 'Galería')],
    ['confirmar pesaje', str_contains($pesaje, "confirmacion === 'registrar'") && str_contains($pesaje, 'Registrar pesaje')],
    ['confirmar quitar envío', str_contains($pesaje, 'quitar_envio')],
    ['overlay pesaje no cierra', str_contains($pesaje, 'pedirCerrar')
        && ! preg_match('/THEME_MODAL_OVERLAY[^>]*onClick=\{onClose\}/', $pesaje)],
    ['otro envío 44px', str_contains($pesaje, 'Otro envío') && str_contains($pesaje, 'w-full min-h-[44px]')],
    ['peso text-base', str_contains($pesaje, 'text-base') && str_contains($pesaje, 'scrollIntoView')],
    ['confirmar existencias detalle', str_contains($detalle, "confirmacion === 'existencias'")],
    ['quitar evidencia 44px', str_contains($pesaje, 'Quitar evidencia') && str_contains($pesaje, 'min-h-[44px]')],
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

<?php

/**
 * Self-check: responder pesaje pasa a PESAJE_RESPONDIDO + badges sin solape post pre-venta.
 * Uso: php tests/Unit/ControlPedidos/check_pesaje_labels_auto_borrador.php
 */
$fallos = 0;
$root = dirname(__DIR__, 3);

$servicio = file_get_contents($root.'/app/Services/ControlPedidos/ResponderPesajePedidoBmaService.php');
$styles = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js');
$tabla = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/TablaPedidos.jsx');
$cedis = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx');
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');

/** Espejo de esFasePreVenta + filtro pesaje en badgeEstatusEnvio. */
$mostrarBadgePesaje = static function (string $estatusEnvio, ?string $fase, bool $forzarPesaje = false): bool {
    if ($estatusEnvio === '' || $estatusEnvio === 'completo') {
        return false;
    }
    if (! in_array($estatusEnvio, ['pendiente_pesaje', 'pesaje_listo'], true)) {
        return true;
    }
    if ($forzarPesaje) {
        return true;
    }
    $preVenta = $fase === null || $fase === ''
        || in_array($fase, ['BORRADOR', 'PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO', 'RECHAZADO_VENDEDORA'], true);

    return $preVenta;
};

$checks = [
    ['servicio importa CatalogoEstatusPedido', str_contains($servicio, 'CatalogoEstatusPedido')],
    ['servicio pasa a PESAJE_RESPONDIDO', str_contains($servicio, 'FASE_PESAJE_RESPONDIDO')
        && str_contains($servicio, 'catalogo_estatus_pedido_id')],
    ['historial usa estatusNuevo', str_contains($servicio, '$estatusNuevo->id')],
    ['JS esFasePreVenta', str_contains($styles, 'esFasePreVenta')],
    ['JS badgeEstatusEnvio filtra pesaje', str_contains($styles, 'forzarPesaje')
        && str_contains($styles, "['pendiente_pesaje', 'pesaje_listo']")],
    ['TablaPedidos pasa faseCiclo', str_contains($tabla, 'faseCiclo:')],
    ['TablaPedidos obs solo pre-venta', str_contains($tabla, 'esFasePreVenta')],
    ['CEDIS forzarPesaje', str_contains($cedis, 'forzarPesaje: true')],
    ['form aviso sin Continuar obligatorio', str_contains($form, 'Ya puede cotizar')],
];

foreach ($checks as [$label, $ok]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fallos++;
    }
}

$logicChecks = [
    ['pesaje_listo + BORRADOR visible', $mostrarBadgePesaje('pesaje_listo', 'BORRADOR') === true],
    ['pesaje_listo + PESAJE_PENDIENTE visible', $mostrarBadgePesaje('pesaje_listo', 'PESAJE_PENDIENTE') === true],
    ['pesaje_listo + PESAJE_RESPONDIDO visible', $mostrarBadgePesaje('pesaje_listo', 'PESAJE_RESPONDIDO') === true],
    ['pesaje_listo + EN_CEDIS oculto', $mostrarBadgePesaje('pesaje_listo', 'EN_CEDIS') === false],
    ['pesaje_listo + PENDIENTE_AUXILIAR oculto', $mostrarBadgePesaje('pesaje_listo', 'PENDIENTE_AUXILIAR') === false],
    ['pendiente_pesaje + EN_CEDIS oculto', $mostrarBadgePesaje('pendiente_pesaje', 'EN_CEDIS') === false],
    ['pendiente_pesaje + EN_CEDIS forzado CEDIS', $mostrarBadgePesaje('pendiente_pesaje', 'EN_CEDIS', true) === true],
    ['pendiente_liberacion + EN_CEDIS visible', $mostrarBadgePesaje('pendiente_liberacion', 'EN_CEDIS') === true],
];

foreach ($logicChecks as [$label, $ok]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fallos++;
    }
}

exit($fallos > 0 ? 1 : 0);

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
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx');
$detalle = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis.jsx');

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
    if (! $preVenta) {
        return false;
    }
    if ($estatusEnvio === 'pendiente_pesaje' && $fase === 'PESAJE_PENDIENTE') {
        return false;
    }
    if ($estatusEnvio === 'pesaje_listo' && $fase === 'PESAJE_RESPONDIDO') {
        return false;
    }

    return true;
};

$checks = [
    ['servicio importa CatalogoEstatusPedido', str_contains($servicio, 'CatalogoEstatusPedido')],
    ['servicio pasa a PESAJE_RESPONDIDO', str_contains($servicio, 'FASE_PESAJE_RESPONDIDO')
        && str_contains($servicio, 'catalogo_estatus_pedido_id')],
    ['historial usa estatusNuevo', str_contains($servicio, '$estatusNuevo->id')],
    ['JS esFasePreVenta', str_contains($styles, 'esFasePreVenta')],
    ['JS oculta pesaje que duplica fase', str_contains($styles, 'duplicaFase')],
    ['TablaPedidos pasa faseCiclo', str_contains($tabla, 'faseCiclo:')],
    ['TablaPedidos obs solo pre-venta', str_contains($tabla, 'esFasePreVenta')],
    ['CEDIS forzarPesaje', str_contains($cedis, 'forzarPesaje: true')],
    ['form aviso sin Continuar obligatorio', str_contains($form, 'Ya puede capturar el total de mercancía')],
    ['JS mostrarNotaCompraCedis', str_contains($styles, 'mostrarNotaCompraCedis')],
    ['CEDIS nota compra condicionada', str_contains($cedis, 'mostrarNotaCompraCedis(fase)')],
    ['Detalle CEDIS nota condicionada', str_contains($detalle, 'mostrarNotaCompraCedis')],
];

/** Espejo de mostrarNotaCompraCedis. */
$mostrarNota = static function (?string $fase): bool {
    return in_array($fase, [
        'EN_CEDIS', 'INCIDENCIA_CEDIS', 'PENDIENTE_DE_GUIA', 'PENDIENTE_GUIA_CLIENTE',
        'PENDIENTE_DE_ENVIO', 'ENTREGADO', 'ENVIADO',
    ], true);
};

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
    ['pesaje_listo + PESAJE_RESPONDIDO oculto (duplica fase)', $mostrarBadgePesaje('pesaje_listo', 'PESAJE_RESPONDIDO') === false],
    ['pendiente_pesaje + PESAJE_PENDIENTE oculto (duplica fase)', $mostrarBadgePesaje('pendiente_pesaje', 'PESAJE_PENDIENTE') === false],
    ['pesaje_listo + EN_CEDIS oculto', $mostrarBadgePesaje('pesaje_listo', 'EN_CEDIS') === false],
    ['pesaje_listo + PENDIENTE_AUXILIAR oculto', $mostrarBadgePesaje('pesaje_listo', 'PENDIENTE_AUXILIAR') === false],
    ['pendiente_pesaje + EN_CEDIS oculto', $mostrarBadgePesaje('pendiente_pesaje', 'EN_CEDIS') === false],
    ['pendiente_pesaje + EN_CEDIS forzado CEDIS', $mostrarBadgePesaje('pendiente_pesaje', 'EN_CEDIS', true) === true],
    ['pendiente_liberacion + EN_CEDIS visible', $mostrarBadgePesaje('pendiente_liberacion', 'EN_CEDIS') === true],
    ['nota oculta en PESAJE_PENDIENTE', $mostrarNota('PESAJE_PENDIENTE') === false],
    ['nota oculta en PESAJE_RESPONDIDO', $mostrarNota('PESAJE_RESPONDIDO') === false],
    ['nota oculta en PENDIENTE_AUXILIAR', $mostrarNota('PENDIENTE_AUXILIAR') === false],
    ['nota visible en EN_CEDIS', $mostrarNota('EN_CEDIS') === true],
    ['nota visible en PENDIENTE_DE_ENVIO', $mostrarNota('PENDIENTE_DE_ENVIO') === true],
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

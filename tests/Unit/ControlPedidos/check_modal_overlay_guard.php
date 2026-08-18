<?php

/**
 * Self-check: helpers de cierre diferido y reglas de preserveState documentadas.
 * Uso: php tests/Unit/ControlPedidos/check_modal_overlay_guard.php
 */

$js = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js');
$form = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');
$alerta = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Partials/ModalAlertaPedido.jsx');
$confirmar = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Partials/ModalConfirmarAccion.jsx');
$index = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Index.jsx');

$fallos = 0;

$assert = static function (bool $ok, string $msg) use (&$fallos): void {
    if ($ok) {
        echo "OK: {$msg}\n";
        return;
    }
    fwrite(STDERR, "FAIL: {$msg}\n");
    $fallos++;
};

$assert(str_contains($js, 'export const deferModalAction'), 'deferModalAction exportado');
$assert(str_contains($alerta, 'deferModalAction'), 'ModalAlertaPedido usa defer');
$assert(str_contains($confirmar, 'deferModalAction'), 'ModalConfirmarAccion usa defer');
$assert(str_contains($form, 'preserveState: true'), 'ModalFormPedido preserveState en pesaje/archivos');
$assert(str_contains($form, 'ignoreOverlayCloseUntil'), 'ModalFormPedido guarda overlay');
$assert(str_contains($form, 'cerrarOverlayBorrador'), 'ModalFormPedido overlay protegido');
$assert(str_contains($index, 'if (modalForm.abierto) return'), 'Index no muestra flash sobre borrador abierto');
$assert(str_contains($form, 'WRAP_FALTANTE'), 'validación resalta contenedores, no modal');
$assert(! str_contains($form, 'import ModalAlertaPedido'), 'form sin ModalAlertaPedido');
$assert(! str_contains($form, 'control_pedidos.pdf_pedido.store') || str_contains($form, 'axios.post(route(\'control_pedidos.pdf_pedido.store\''), 'PDF se sube con axios');
$assert(! str_contains($form, "router.post(route('control_pedidos.pdf_pedido.store'"), 'PDF no usa router.post');
$assert(str_contains($js, 'claves'), 'validarCamposEnvioPedido expone claves');

exit($fallos > 0 ? 1 : 0);

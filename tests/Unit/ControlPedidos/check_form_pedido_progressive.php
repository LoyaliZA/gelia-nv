<?php

/**
 * Self-check: Cliente → tipo → PDF → pesaje → respuesta → dirección → paquetería → saldo a favor → cotización → pago → remisión.
 * Uso: php tests/Unit/ControlPedidos/check_form_pedido_progressive.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');

$checks = [
    ['sin cascada mostrarTrasTipo', ! str_contains($form, 'mostrarTrasTipo')],
    ['flag tieneTipo', str_contains($form, 'const tieneTipo')],
    ['gate pesaje exige tipo o pesaje en curso', str_contains($form, 'mostrarPesaje = (tieneTipo && requiereLogistica)')
        && str_contains($form, 'tienePesajeRespondido')],
    ['gate resto exige origen y cotización', str_contains($form, 'mostrarRestoPedido = Boolean(data.origen_id)')
        && str_contains($form, 'cotizacionHabilitada')],
    ['flag enfocadoEnPesaje', str_contains($form, 'enfocadoEnPesaje')],
    ['flag mostrarRestoPedido', str_contains($form, 'mostrarRestoPedido')],
    ['hint elegir tipo', str_contains($form, 'Seleccione Tipo de pedido')],
    ['flag puedeContinuarPedido', str_contains($form, 'puedeContinuarPedido')],
    ['boton Continuar pedido', str_contains($form, 'Continuar pedido')],
    ['sin Conservar como borrador UI', ! str_contains($form, 'Conservar como borrador')],
    ['sin Datos generales como sección', ! str_contains($form, 'Datos generales')],
    ['label Tipo de pedido', str_contains($form, 'Tipo de pedido')],
    ['pesaje gated', str_contains($form, '{mostrarPesaje && (')],
    ['resto gated', str_contains($form, '{mostrarRestoPedido && (')],
    ['logistica gated', str_contains($form, '{mostrarLogisticaPostPesaje && (')],
    ['mapa nSec', str_contains($form, 'const nSec = requiereLogistica') && str_contains($form, 'saf: 8')],
    ['pesaje listo cuenta como respondido', str_contains($form, "estatus_envio === 'pesaje_listo'")],
    ['bind pedido creado sin remount', str_contains($form, 'onPedidoCreado')],
    ['quién respondió el pesaje', str_contains($form, 'Respondió:')],
];

$titulos = [
    'Cliente y productos',
    'Tipo de entrega',
    'PDF o archivo del pedido',
    'Solicitud de pesaje',
    'Respuesta de cajas y pesos',
    'ArrowRight className="w-5 h-5" /> Continuar pedido',
    'Dirección de envío',
    'Paquetería y seguro',
    '{nSec.saf}. Saldo a favor',
    '{nSec.cot}. Cotización',
    '{nSec.pago}. Pago',
    '{nSec.rem}. Remisión',
];
$prev = -1;
$ordenOk = true;
foreach ($titulos as $t) {
    $i = strpos($form, $t);
    if ($i === false || ($prev >= 0 && $i <= $prev)) {
        $ordenOk = false;
        break;
    }
    $prev = $i;
}
$checks[] = ['orden captura según flujo real', $ordenOk];

$iContinuar = strpos($form, 'ArrowRight className="w-5 h-5" /> Continuar pedido');
$iResp = strpos($form, 'Respuesta de cajas y pesos');
$checks[] = [
    'continuar dentro de respuesta de cajas',
    $iContinuar !== false && $iResp !== false && $iResp < $iContinuar,
];
$checks[] = [
    'copy continuar dirección cotización pago',
    str_contains($form, 'dirección, cotización y pago'),
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

<?php

/**
 * Self-check RF-04: Tipo+cliente → pesaje → Continuar pedido → resto.
 * Uso: php tests/Unit/ControlPedidos/check_form_pedido_progressive.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');

$checks = [
    ['sin cascada mostrarTrasTipo', ! str_contains($form, 'mostrarTrasTipo')],
    ['flag tieneTipo', str_contains($form, 'const tieneTipo')],
    ['gate pesaje exige tipo', str_contains($form, 'mostrarPesaje = tieneTipo && requiereLogistica')],
    ['gate resto exige tipo', str_contains($form, 'mostrarRestoPedido = tieneTipo &&')],
    ['flag enfocadoEnPesaje', str_contains($form, 'enfocadoEnPesaje')],
    ['flag mostrarRestoPedido', str_contains($form, 'mostrarRestoPedido')],
    ['hint elegir tipo', str_contains($form, 'Seleccione Tipo de pedido')],
    ['flag puedeContinuarPedido', str_contains($form, 'puedeContinuarPedido')],
    ['boton Continuar pedido', str_contains($form, 'Continuar pedido')],
    ['sin Conservar como borrador UI', ! str_contains($form, 'Conservar como borrador')],
    ['un solo bloque Pesaje CEDIS', substr_count($form, 'Pesaje CEDIS') === 1],
    ['sin label Tipo CEDIS falso', ! str_contains($form, 'Tipo (CEDIS)')],
    ['label Tipo de pedido', str_contains($form, 'Tipo de pedido')],
    ['pesaje gated', str_contains($form, '{mostrarPesaje && (')],
    ['resto gated', str_contains($form, '{mostrarRestoPedido && (')],
    ['logistica gated', str_contains($form, '{mostrarLogisticaPostPesaje && (')],
];

$iTipo = strpos($form, '1. Tipo de pedido y cliente');
$iPesaje = strpos($form, '2. Pesaje CEDIS');
$iDatos = strpos($form, '3. Datos generales');
$iDir = strpos($form, '4. Dirección de envío');
$iContinuar = strpos($form, 'ArrowRight className="w-5 h-5" /> Continuar pedido');
$checks[] = [
    'orden tipo < pesaje < continuar < datos < dirección',
    $iTipo !== false && $iPesaje !== false && $iContinuar !== false && $iDatos !== false && $iDir !== false
        && $iTipo < $iPesaje && $iPesaje < $iContinuar && $iContinuar < $iDatos && $iDatos < $iDir,
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

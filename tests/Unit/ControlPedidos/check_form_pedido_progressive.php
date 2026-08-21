<?php

/**
 * Self-check: Cliente → tipo → PDF → consulta → respuesta → monto → dirección → …
 * Uso: php tests/Unit/ControlPedidos/check_form_pedido_progressive.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');

$checks = [
    ['sin cascada mostrarTrasTipo', ! str_contains($form, 'mostrarTrasTipo')],
    ['flag tieneTipo', str_contains($form, 'const tieneTipo')],
    ['gate pesaje exige tipo o pesaje en curso', str_contains($form, 'mostrarPesaje = tieneTipo')
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
    ['mapa nSec', str_contains($form, 'const nSec = requiereLogistica') && str_contains($form, 'saf: 9')],
    ['pesaje listo cuenta como respondido', str_contains($form, "estatus_envio === 'pesaje_listo'")],
    ['bind pedido creado sin remount', str_contains($form, 'onPedidoCreado')],
    ['quién respondió el pesaje', str_contains($form, 'Respondió:')],
    ['monto gated por mostrarMontoMercancia', str_contains($form, 'mostrarMontoMercancia')],
    ['cerrar consulta en UI', str_contains($form, 'Cerrar consulta')],
    ['gate consultaCerrada', str_contains($form, 'consultaCerrada')],
    ['copy continuar dirección cotización pago', str_contains($form, 'dirección, cotización y pago')],
    ['actualizar consulta (no solo re-pesaje)', str_contains($form, 'Actualizar consulta')],
    ['anexos multiple input', str_contains($form, 'multiple accept="application/pdf')],
    ['galeria anexos local', str_contains($form, 'anexoDocsLocal')],
    ['miniaturas anexo', str_contains($form, 'MiniaturaDocumento')],
    ['resguardo diferido aviso', str_contains($form, 'Envío (diferido)')],
    ['resguardo en cotizacionLista', str_contains($form, 'esResguardoAbierto')],
    ['pdf local gana a pedido stale', str_contains($form, 'const pdfPedidoDoc = pdfDocLocal')],
    ['folio wizerp temprano', str_contains($form, 'Folio generado por Wizerp')],
    ['paqueteria temprano en pdf', str_contains($form, 'Folio, paquetería y archivo del pedido')],
];

$titulos = [
    '{nSec.cliente}. Cliente',
    'Tipo de entrega',
    'Folio generado por Wizerp',
    '{nSec.solPesaje}. {labelConsulta}',
    'Continuar pedido',
    '{nSec.monto}. Total de mercancía',
    'Dirección de envío',
    'Guía y seguro',
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

$iCliente = strpos($form, '{nSec.cliente}. Cliente');
$iMonto = strpos($form, '{nSec.monto}. Total de mercancía');
$iResp = strpos($form, '{nSec.resp}.');
$checks[] = [
    'monto después de respuesta CEDIS',
    $iMonto !== false && $iResp !== false && $iResp < $iMonto,
];
$checks[] = [
    'monto no en bloque cliente',
    $iCliente !== false && $iMonto !== false && $iCliente < $iMonto,
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

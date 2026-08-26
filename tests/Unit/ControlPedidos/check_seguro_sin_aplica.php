<?php

/**
 * Self-check: Sin seguro no exige ni retiene monto de seguro.
 * Uso: php tests/Unit/ControlPedidos/check_seguro_sin_aplica.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$resuelve = file_get_contents($root.'/app/Services/ControlPedidos/ResuelveDatosPedidoBma.php');
$totales = file_get_contents($root.'/app/Services/ControlPedidos/CalcularTotalesEnvioPedidoService.php');
$cobertura = file_get_contents($root.'/app/Services/SaldosAFavor/CoberturaPagoPedidoBmaService.php');
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx');

$checks = [
    ['resolverSeguro costo 0 si no aplica', str_contains($resuelve, '$costo = $aplicaSeguro')
        && str_contains($resuelve, '? $calc->calcularCosto')
        && str_contains($resuelve, ': 0.0')],
    ['totales no OR por monto', str_contains($totales, '$aplicaSeguro = (bool) $pedido->aplica_seguro;')
        && ! str_contains($totales, '$aplicaSeguro = (bool) $pedido->aplica_seguro || $seguro > 0')],
    ['cobertura no OR por monto', str_contains($cobertura, '$aplicaSeguro = (bool) $pedido->aplica_seguro;')
        && ! str_contains($cobertura, '|| (float) $seguro > 0')],
    ['form UI solo con aplica_seguro', str_contains($form, '{((!guiaCliente && data.aplica_seguro) || data.envia_a_otra_persona) && (')
        && str_contains($form, '{!guiaCliente && data.aplica_seguro && (')],
    ['form effect respeta aplica_seguro', str_contains($form, 'if (!data.aplica_seguro)')
        && str_contains($form, 'setData(\'costo_seguro\', 0)')],
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

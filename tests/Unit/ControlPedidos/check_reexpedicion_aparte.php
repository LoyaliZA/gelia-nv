<?php

/**
 * Self-check: reexpedición como cargo aparte en cotización.
 * Uso: php tests/Unit/ControlPedidos/check_reexpedicion_aparte.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
$resolver = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/resolverReexpedicionForm.js');
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');

$checks = [
    ['resolver no mezcla en costoEnvio', ! str_contains($resolver, 'costoEnvio:')],
    ['helper separar', str_contains($resolver, 'separarCostoEnvioDeReexpedicion')],
    ['helper persistir', str_contains($resolver, 'costoEnvioParaPersistir')],
    ['UI linea Reexpedición', str_contains($form, '>Reexpedición</span>')],
    ['seguro sin reexpedición en base', str_contains($form, 'sin reexpedición')],
    ['estado costoReexpedicion', str_contains($form, 'const [costoReexpedicion, setCostoReexpedicion]')],
    ['persistir al guardar', str_contains($form, 'costoEnvioParaPersistir(d.costo_envio, costoReexpedicion)')],
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

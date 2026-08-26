<?php

/**
 * Self-check: PAQ. PENDIENTE en catálogo, form, guards y helper de modelo.
 * Uso: php tests/Unit/ControlPedidos/check_paq_pendiente.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$modelo = file_get_contents($root.'/app/Models/ControlPedidos/CatalogoPaqueteriaPedido.php');
$pedido = file_get_contents($root.'/app/Models/ControlPedidos/PedidoBma.php');
$seeder = file_get_contents($root.'/database/seeders/ControlPedidosCatalogosSeeder.php');
$migracion = file_get_contents($root.'/database/migrations/2026_08_26_180000_add_paq_pendiente_catalogo_paqueterias.php');
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx');
$asignar = file_get_contents($root.'/app/Services/ControlPedidos/AsignarGuiaPedidoBmaService.php');
$enviado = file_get_contents($root.'/app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php');

$checks = [
    ['constante NOMBRE_PENDIENTE', str_contains($modelo, "NOMBRE_PENDIENTE = 'PAQ. PENDIENTE'")],
    ['metodo esPendienteConfirmacion', str_contains($modelo, 'function esPendienteConfirmacion')],
    ['pedido tienePaqueteriaPendiente', str_contains($pedido, 'function tienePaqueteriaPendiente')],
    ['seeder PAQ. PENDIENTE', str_contains($seeder, 'PAQ. PENDIENTE') && str_contains($seeder, 'permite_costo_diferido')],
    ['migracion updateOrInsert', str_contains($migracion, 'PAQ. PENDIENTE') && str_contains($migracion, 'updateOrInsert')],
    ['migracion local_regional + diferido', str_contains($migracion, "'categoria' => 'local_regional'")
        && str_contains($migracion, "'permite_costo_diferido' => true")],
    ['form optgroup Por confirmar', str_contains($form, 'Por confirmar') && str_contains($form, 'paqueteriasPendientes')],
    ['form hint pendiente', str_contains($form, 'paqPendienteSeleccionada')],
    ['guard asignar guia', str_contains($asignar, 'tienePaqueteriaPendiente')],
    ['guard marcar enviado', str_contains($enviado, 'tienePaqueteriaPendiente')],
];

foreach ($checks as [$label, $ok]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fallos++;
    }
}

require $root.'/vendor/autoload.php';

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;

$pend = new CatalogoPaqueteriaPedido([
    'nombre' => CatalogoPaqueteriaPedido::NOMBRE_PENDIENTE,
    'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
    'permite_costo_diferido' => true,
]);
$runtime = [
    ['runtime pendiente true', $pend->esPendienteConfirmacion()],
    ['runtime espacios/case', (new CatalogoPaqueteriaPedido(['nombre' => '  paq. pendiente  ']))->esPendienteConfirmacion()],
    ['runtime fedex false', ! (new CatalogoPaqueteriaPedido(['nombre' => 'FEDEX']))->esPendienteConfirmacion()],
    ['runtime no rastreo', ! $pend->ofreceRastreo()],
    ['runtime costo diferido', $pend->permiteCostoDiferido()],
];

$pedidoModel = new PedidoBma();
$pedidoModel->setRelation('paqueteria', $pend);
$runtime[] = ['runtime pedido pendiente', $pedidoModel->tienePaqueteriaPendiente()];

foreach ($runtime as [$label, $ok]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fallos++;
    }
}

exit($fallos > 0 ? 1 : 0);

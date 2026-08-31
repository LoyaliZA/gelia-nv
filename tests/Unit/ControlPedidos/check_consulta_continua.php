<?php

/**
 * Self-check: consulta continua CEDIS (cierre, tienda, actualizar).
 * Uso: php tests/Unit/ControlPedidos/check_consulta_continua.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
require_once __DIR__.'/_routes_helper.php';

$checks = [];

$mig = glob($root.'/database/migrations/*consulta_cerrada*')[0] ?? '';
$checks[] = ['migración consulta_cerrada', $mig !== '' && str_contains(file_get_contents($mig), 'consulta_cerrada_at')];

$model = file_get_contents($root.'/app/Models/ControlPedidos/PedidoBma.php');
$checks[] = ['puedeCerrarConsulta', str_contains($model, 'function puedeCerrarConsulta')];
$checks[] = ['esConsultaMercancia', str_contains($model, 'function esConsultaMercancia')];
$checks[] = ['tienda puede solicitar consulta', ! str_contains($model, "if (! (\$this->origen?->requiere_logistica ?? true)) {\n            return false;\n        }")
    || ! preg_match('/function puedeSolicitarPesaje\(\)[\s\S]*?requiere_logistica[\s\S]*?return false;/', $model)];

// After our change, puedeSolicitarPesaje should NOT gate on requiere_logistica.
$checks[] = [
    'puedeSolicitarPesaje sin gate logística',
    preg_match(
        '/function puedeSolicitarPesaje\(\): bool\s*\{[\s\S]*?\/\/ Envío y Tienda/',
        $model
    ) === 1,
];

$cerrar = $root.'/app/Services/ControlPedidos/CerrarConsultaPedidoBmaService.php';
$checks[] = ['CerrarConsulta service', is_file($cerrar)];
$actualizar = $root.'/app/Services/ControlPedidos/ActualizarConsultaPedidoBmaService.php';
$checks[] = ['ActualizarConsulta service', is_file($actualizar)];

$repesaje = file_get_contents($root.'/app/Services/ControlPedidos/SolicitarRepesajePedidoBmaService.php');
$checks[] = ['repesaje es wrapper ActualizarConsulta', str_contains($repesaje, 'ActualizarConsultaPedidoBmaService')];

$responder = file_get_contents($root.'/app/Services/ControlPedidos/ResponderPesajePedidoBmaService.php');
$checks[] = ['responder soporta soloRevisiones', str_contains($responder, 'esConsultaMercancia')];
$checks[] = ['responder no wipe costo si pesos iguales', str_contains($responder, 'cambioPesos')];

$val = file_get_contents($root.'/app/Services/ControlPedidos/ValidacionCamposPedidoBma.php');
$checks[] = ['validación exige consulta cerrada', str_contains($val, 'requiereConsultaCerradaParaProceder')];

$routes = control_pedidos_routes_content($root);
$checks[] = ['ruta cerrar-consulta', str_contains($routes, 'cerrar-consulta')];
$checks[] = ['ruta reabrir-consulta', str_contains($routes, 'reabrir-consulta')];

$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx');
$checks[] = ['UI cerrar consulta', str_contains($form, 'cerrar_consulta') || str_contains($form, 'cerrarConsulta')];
$checks[] = ['UI puedeRegistrarPago', str_contains($form, 'puedeRegistrarPago')];
$checks[] = ['UI Actualizar consulta', str_contains($form, 'Actualizar consulta')];

$cedis = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx');
$checks[] = ['CEDIS soloRevisiones', str_contains($cedis, 'soloRevisiones')];
$checks[] = ['CEDIS precarga actualización', str_contains($cedis, 'Precarga: respuesta anterior')
    || str_contains($cedis, 'consulta_actualizacion_pendiente')];

$index = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Index.jsx');
$checks[] = ['tab Pendientes consulta', str_contains($index, 'Pendientes consulta')];
$checks[] = ['CEDIS resumen antes→después', str_contains($cedis, 'Antes → después')];
$checks[] = ['CEDIS baselineConsulta', str_contains($cedis, 'baselineConsulta')];
$checks[] = ['form monto post-consulta', str_contains($form, 'mostrarMontoMercancia')];
$checks[] = ['form sin monto en Cliente y productos', ! str_contains($form, 'Cliente y productos')];

foreach ($checks as [$label, $ok]) {
    if ($ok) {
        echo "OK: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fallos++;
    }
}

exit($fallos > 0 ? 1 : 0);

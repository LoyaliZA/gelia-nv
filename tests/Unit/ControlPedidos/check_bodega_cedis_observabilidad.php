<?php

/**
 * Self-check Fase 6 — Bodega, CEDIS y observabilidad.
 * Uso: php tests/Unit/ControlPedidos/check_bodega_cedis_observabilidad.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$checks = [
    ['migración fase 6 bodega', is_file($root.'/database/migrations/2026_10_02_210000_fase6_bodega_cedis_observabilidad.php')],
    ['conciliar traspaso', is_file($root.'/app/Services/ControlPedidos/ConciliarTraspasoTiendaCedisService.php')],
    ['conciliar grupo complemento', is_file($root.'/app/Services/ControlPedidos/ConciliarGrupoComplementoEnvioBodegaService.php')],
    ['eventos traspaso', is_file($root.'/app/Services/ControlPedidos/RegistrarEventoTraspasoPreparacionService.php')],
    ['cola estados cuenta', is_file($root.'/app/Services/ControlPedidos/ColaEstadosCuentaPreparacionService.php')],
    ['hook conciliar CEDIS', str_contains(
        file_get_contents($root.'/app/Services/Traspasos/ConfirmarTraspasoCedisService.php'),
        'ConciliarTraspasoTiendaCedisService'
    )],
    ['filtros origen tienda', str_contains(
        file_get_contents($root.'/app/Services/ControlPedidos/ListarTareasTiendaService.php'),
        'origen_solicitud'
    )],
    ['tab HISTORIAL', str_contains(
        file_get_contents($root.'/resources/js/Pages/ControlPedidos/Tienda/Partials/FiltrosTienda.jsx'),
        'HISTORIAL'
    )],
    ['banner cola cuenta', str_contains(
        file_get_contents($root.'/resources/js/Pages/ControlPedidos/Tienda/Index.jsx'),
        'cola_estados_cuenta'
    )],
    ['tipos evento traspaso', str_contains(
        file_get_contents($root.'/app/Models/ControlPedidos/PedidoBmaCumplimientoEvento.php'),
        'TIPO_TRASPASO_SALIDA'
    )],
];

foreach ($checks as [$label, $ok]) {
    if ($ok) {
        echo "OK  {$label}\n";
    } else {
        echo "FAIL {$label}\n";
        $fallos++;
    }
}

exit($fallos > 0 ? 1 : 0);

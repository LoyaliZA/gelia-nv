<?php

/**
 * Self-check Fase 5 — Municipio, carátula y empaque.
 * Uso: php tests/Unit/ControlPedidos/check_fase5_municipio_empaque.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
require_once __DIR__.'/_routes_helper.php';
$cpRoutes = control_pedidos_routes_content($root);
$requisitos = file_get_contents($root.'/app/Services/ControlPedidos/CalcularRequisitosPreparacionService.php');

$checks = [
    ['migración empaque municipal', is_file($root.'/database/migrations/2026_10_02_200000_fase5_municipio_empaque_salida.php')],
    ['validarDocumentosCaratulaMunicipio', str_contains($requisitos, 'validarDocumentosCaratulaMunicipio')],
    ['validarEmpaqueMunicipio', str_contains($requisitos, 'validarEmpaqueMunicipio')],
    ['TIPO_HOJA_SALIDA_INTERNA', str_contains(file_get_contents($root.'/app/Models/ControlPedidos/PedidoBmaTareaDocumento.php'), 'HOJA_SALIDA_INTERNA')],
    ['servicio hoja salida', is_file($root.'/app/Services/ControlPedidos/GenerarHojaSalidaInternaService.php')],
    ['servicio empaque', is_file($root.'/app/Services/ControlPedidos/ConfirmarEmpaqueMunicipioService.php')],
    ['servicio despacho', is_file($root.'/app/Services/ControlPedidos/ConfirmarDespachoMunicipioService.php')],
    ['observer invalidación', is_file($root.'/app/Observers/PedidoBmaTareaPreparacionCaratulaObserver.php')],
    ['ruta empacar', str_contains($cpRoutes, 'municipio/empacar')],
    ['ruta despachar', str_contains($cpRoutes, 'municipio/despachar')],
    ['UI empaque', is_file($root.'/resources/js/Pages/ControlPedidos/Tienda/Partials/EmpaqueMunicipioTienda.jsx')],
    ['ESTADO_EMPACADA', str_contains(file_get_contents($root.'/app/Models/ControlPedidos/PedidoBmaCumplimientoFisico.php'), 'ESTADO_EMPACADA')],
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

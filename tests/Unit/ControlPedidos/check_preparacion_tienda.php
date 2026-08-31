<?php

/**
 * Self-check Fase 4 — Preparación Tienda.
 * Uso: php tests/Unit/ControlPedidos/check_preparacion_tienda.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
require_once __DIR__.'/_routes_helper.php';

$checks = [
    ['migración fase 4', is_file($root.'/database/migrations/2026_08_24_180000_fase4_preparacion_tienda.php')],
    ['config PreparacionTiendaConfig', is_file($root.'/app/Services/ControlPedidos/PreparacionTiendaConfig.php')],
    ['máquina estados tarea', is_file($root.'/app/Support/ControlPedidos/MaquinaEstadosTareaPreparacion.php')],
    ['servicio crear tarea', is_file($root.'/app/Services/ControlPedidos/CrearTareaPreparacionService.php')],
    ['controller tienda', is_file($root.'/app/Http/Controllers/ControlPedidos/PedidoBmaTiendaController.php')],
    ['ruta solicitar preparacion', str_contains(control_pedidos_routes_content($root), 'solicitar-preparacion-tienda')],
    ['ruta corregir preparacion', str_contains(control_pedidos_routes_content($root), 'tareas-preparacion')],
    ['UI Index Tienda', is_file($root.'/resources/js/Pages/ControlPedidos/Tienda/Index.jsx')],
    ['UI Show Tienda', is_file($root.'/resources/js/Pages/ControlPedidos/Tienda/Show.jsx')],
    ['sidebar tienda', str_contains(file_get_contents($root.'/resources/js/config/sidebarNavigation.js'), 'control_pedidos_tienda')],
    ['permisos tienda', str_contains(file_get_contents($root.'/resources/js/utils/permisos.js'), 'control_pedidos.tienda.ver')],
    ['ventas solicitar preparacion', str_contains(file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx'), 'solicitarPreparacionTienda')],
    ['ventas modal corregir', is_file($root.'/resources/js/Pages/ControlPedidos/Partials/ModalCorregirPreparacionTienda.jsx')],
    ['job recordatorio', is_file($root.'/app/Console/Commands/ControlPedidos/RecordatorioVencimientoPreparacionTiendaCommand.php')],
    ['schedule recordatorio', str_contains(file_get_contents($root.'/routes/console.php'), 'recordatorio-vencimiento-preparacion-tienda')],
    ['PENDIENTE→CON_INCIDENCIA', str_contains(
        file_get_contents($root.'/app/Support/ControlPedidos/MaquinaEstadosTareaPreparacion.php'),
        'ESTADO_CON_INCIDENCIA'
    )],
    ['catalogos preparacion_config', str_contains(
        file_get_contents($root.'/app/Services/ControlPedidos/ObtenerCatalogosPedidoBmaService.php'),
        'preparacion_config'
    )],
    ['cedis excluye tarea tienda', str_contains(
        file_get_contents($root.'/app/Services/ControlPedidos/ListarPedidosCedisService.php'),
        'tareaPreparacionVigente'
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

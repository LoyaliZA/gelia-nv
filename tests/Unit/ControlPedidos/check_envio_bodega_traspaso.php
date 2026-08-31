<?php

/**
 * Self-check Fase 5 — Envío Bodega y Traspaso Tienda → CEDIS.
 * Uso: php tests/Unit/ControlPedidos/check_envio_bodega_traspaso.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
require_once __DIR__.'/_routes_helper.php';

$checks = [
    ['migración fase 5', is_file($root.'/database/migrations/2026_08_24_190000_fase5_envio_bodega_traspaso_cedis.php')],
    ['modelo matriz', is_file($root.'/app/Models/ControlPedidos/MatrizRequisitosPreparacion.php')],
    ['servicio crear traspaso desde tarea', is_file($root.'/app/Services/ControlPedidos/CrearTraspasoDesdeTareaPreparacionService.php')],
    ['servicio confirmar salida', is_file($root.'/app/Services/ControlPedidos/ConfirmarSalidaTrasladoTiendaService.php')],
    ['servicio sync traspaso', is_file($root.'/app/Services/ControlPedidos/SincronizarTareaDesdeTraspasoService.php')],
    ['comando reconciliar', is_file($root.'/app/Console/Commands/ControlPedidos/ReconciliarTrasladosPreparacionCommand.php')],
    ['schedule reconciliar', str_contains(file_get_contents($root.'/routes/console.php'), 'reconciliar-traslados-preparacion')],
    ['ruta confirmar salida', str_contains(control_pedidos_routes_content($root), 'confirmar-salida')],
    ['estados LISTA_PARA_TRASLADO', str_contains(file_get_contents($root.'/app/Models/ControlPedidos/PedidoBmaTareaPreparacion.php'), 'LISTA_PARA_TRASLADO')],
    ['CODIGOS_FASE5', str_contains(file_get_contents($root.'/app/Models/ControlPedidos/CatalogoModalidadPreparacionPedido.php'), 'CODIGOS_FASE5')],
    ['tabs LISTAS_TRASLADO', str_contains(file_get_contents($root.'/resources/js/Pages/ControlPedidos/Tienda/Partials/FiltrosTienda.jsx'), 'LISTAS_TRASLADO')],
    ['UI confirmar salida', str_contains(file_get_contents($root.'/resources/js/Pages/ControlPedidos/Tienda/Show.jsx'), 'confirmar_salida')],
    ['badge Gestión de pedido', str_contains(file_get_contents($root.'/resources/js/Pages/Traspasos/Cedis/Index.jsx'), 'Gestión de pedido')],
    ['progreso Ventas', str_contains(file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx'), 'progresoTraslado')],
    ['hook ConfirmarTraspasoCedis', str_contains(file_get_contents($root.'/app/Services/Traspasos/ConfirmarTraspasoCedisService.php'), 'SincronizarTareaDesdeTraspasoService')],
    ['permiso trasladar', str_contains(file_get_contents($root.'/resources/js/utils/permisos.js'), 'control_pedidos.tienda.trasladar')],
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

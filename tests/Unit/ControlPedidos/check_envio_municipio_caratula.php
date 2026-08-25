<?php

/**
 * Self-check Fase 6 — Envío municipio y carátulas.
 * Uso: php tests/Unit/ControlPedidos/check_envio_municipio_caratula.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$checks = [
    ['migración fase 6', is_file($root.'/database/migrations/2026_08_24_200000_fase6_envio_municipio_caratulas.php')],
    ['modelo PedidoBmaCaratula', is_file($root.'/app/Models/ControlPedidos/PedidoBmaCaratula.php')],
    ['servicio generar carátula', is_file($root.'/app/Services/ControlPedidos/GenerarCaratulaPedidoService.php')],
    ['servicio confirmar carátula', is_file($root.'/app/Services/ControlPedidos/ConfirmarCaratulaColocadaService.php')],
    ['servicio regenerar carátula', is_file($root.'/app/Services/ControlPedidos/RegenerarCaratulaPedidoService.php')],
    ['blade carátula', is_file($root.'/resources/views/control_pedidos/caratula.blade.php')],
    ['CODIGO_ENVIO_MUNICIPIO', str_contains(file_get_contents($root.'/app/Models/ControlPedidos/CatalogoModalidadPreparacionPedido.php'), 'ENVIO_MUNICIPIO')],
    ['LISTA_PARA_CARATULA', str_contains(file_get_contents($root.'/app/Models/ControlPedidos/PedidoBmaTareaPreparacion.php'), 'LISTA_PARA_CARATULA')],
    ['ruta generar carátula', str_contains(file_get_contents($root.'/routes/web.php'), 'caratula/generar')],
    ['ruta confirmar colocación', str_contains(file_get_contents($root.'/routes/web.php'), 'caratula/confirmar-colocacion')],
    ['tab LISTAS_CARATULA', str_contains(file_get_contents($root.'/resources/js/Pages/ControlPedidos/Tienda/Partials/FiltrosTienda.jsx'), 'LISTAS_CARATULA')],
    ['UI datos municipales Ventas', str_contains(file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx'), 'Datos de entrega municipal')],
    ['permiso generar_caratula', str_contains(file_get_contents($root.'/resources/js/utils/permisos.js'), 'control_pedidos.tienda.generar_caratula')],
    ['sin hardcode Jaguar', !preg_match('/Jaguar|TNT/i', file_get_contents($root.'/database/migrations/2026_08_24_200000_fase6_envio_municipio_caratulas.php'))],
    ['habilitado_envio_municipio default false', str_contains(file_get_contents($root.'/database/migrations/2026_08_24_200000_fase6_envio_municipio_caratulas.php'), "->default(false)")],
    ['paqueterias_municipio catalogo', str_contains(file_get_contents($root.'/app/Services/ControlPedidos/ObtenerCatalogosPedidoBmaService.php'), 'paqueterias_municipio')],
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

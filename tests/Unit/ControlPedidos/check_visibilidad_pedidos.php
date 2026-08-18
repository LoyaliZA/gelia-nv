<?php

/**
 * Self-check: permisos y visibilidad Control Pedidos.
 * Uso: php tests/Unit/ControlPedidos/check_visibilidad_pedidos.php
 */

$fallos = 0;
$assert = static function (bool $ok, string $msg) use (&$fallos): void {
    if ($ok) {
        echo "OK: {$msg}\n";

        return;
    }
    fwrite(STDERR, "FAIL: {$msg}\n");
    $fallos++;
};

$vis = file_get_contents(__DIR__.'/../../../app/Support/ControlPedidos/VisibilidadPedidoBma.php');
$listar = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/ListarPedidosBmaService.php');
$ctrl = file_get_contents(__DIR__.'/../../../app/Http/Controllers/ControlPedidos/PedidoBmaController.php');
$enviar = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/EnviarPedidoBmaService.php');
$cola = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/AvanzarColaErroresPedidoBmaService.php');
$doc = file_get_contents(__DIR__.'/../../../app/Models/ControlPedidos/PedidoBmaDocumento.php');
$routes = file_get_contents(__DIR__.'/../../../routes/web.php');
$tabla = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Partials/TablaPedidos.jsx');
$auditar = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Auditar/Index.jsx');
$cedis = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Cedis/Index.jsx');
$delegado = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Delegado/Partials/TablaDelegado.jsx');

$assert(str_contains($vis, 'idsVendedoresVisibles'), 'helper ids vendedores');
$assert(str_contains($vis, 'puedeMutarComoVendedora'), 'helper mutar vendedora');
$assert(str_contains($vis, 'puedeConsultar'), 'helper consultar');
$assert(str_contains($vis, 'colaboradores()'), 'gerente vía colaboradores');
$assert(str_contains($vis, 'excluirBorradoresAjenos'), 'listado oculta borradores ajenos');
$assert(str_contains($vis, 'esBorradorAjeno'), 'consulta rechaza borrador ajeno');

$assert(str_contains($listar, 'VisibilidadPedidoBma::aplicarAlcanceListadoBma'), 'listado usa helper');
$assert(str_contains($listar, 'puede_editar'), 'listado anexa puede_editar');
$assert(str_contains($listar, 'asegurarConsulta'), 'asegurarConsulta existe');

$assert(str_contains($ctrl, 'aplicarAlcanceListadoBma'), 'candidatosPrincipal filtrado');
$assert(str_contains($ctrl, 'function documento'), 'controller descarga documento');
$assert(str_contains($routes, 'documentos.show'), 'ruta documentos auth');

$assert(str_contains($enviar, 'puedeMutarComoVendedora'), 'enviar exige creador');
$assert(str_contains($cola, 'DUENO_VENDEDORA'), 'cola refuerza dueño vendedora');

$assert(str_contains($doc, 'control_pedidos.documentos.show'), 'url documento autenticada');
$assert(str_contains($tabla, 'puede_editar'), 'UI respeta puede_editar');

$assert(str_contains($auditar, 'ModalBitacoraPedido'), 'bitácora en auditar');
$assert(str_contains($cedis, 'ModalBitacoraPedido'), 'bitácora en cedis');
$assert(str_contains($delegado, 'ModalBitacoraPedido'), 'bitácora en delegado');

$cedisList = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/ListarPedidosCedisService.php');
$auditoriaList = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/ListarPedidosAuditoriaService.php');
$delegadoList = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/ListarPedidosDelegadoService.php');
$assert(str_contains($cedisList, 'historial.usuario'), 'CEDIS carga historial');
$assert(str_contains($vis, 'idsDepartamentos'), 'helper departamentos');
$assert(str_contains($auditoriaList, 'aplicarAlcanceListadoBma'), 'auditoría filtra por visibilidad');
$assert(str_contains($cedisList, 'FASE_PENDIENTE_GUIA_CLIENTE'), 'CEDIS incluye guía del cliente');
$assert(str_contains($cedisList, 'function queryTodos'), 'CEDIS TODOS une bandeja + pesaje');
$assert(str_contains($cedisList, 'ESTATUS_ENVIO_PENDIENTE_PESAJE'), 'CEDIS TODOS incluye pendiente_pesaje');
$assert(str_contains($cedisList, '+ $pendientesPesaje'), 'métrica total incluye pesaje');
$assert(str_contains($delegadoList, 'historial.usuario'), 'Delegado carga historial');

exit($fallos > 0 ? 1 : 0);

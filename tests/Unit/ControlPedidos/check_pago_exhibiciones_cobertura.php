<?php

/**
 * Self-check (sin DB): RF-01/02/03 pagos — cobertura vs revisión, requiere_banco, UI.
 * Uso: php tests/Unit/ControlPedidos/check_pago_exhibiciones_cobertura.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);
require_once __DIR__.'/_routes_helper.php';

$registrar = file_get_contents($root.'/app/Services/SaldosAFavor/RegistrarPagoPedidoBmaService.php');
$actualizar = file_get_contents($root.'/app/Services/SaldosAFavor/ActualizarPagoPedidoBmaService.php');
$eliminar = file_get_contents($root.'/app/Services/SaldosAFavor/EliminarPagoPedidoBmaService.php');
$revisar = file_get_contents($root.'/app/Services/SaldosAFavor/RevisarPagoPedidoBmaService.php');
$modelo = file_get_contents($root.'/app/Models/SaldosAFavor/PedidoBmaPago.php');
$pedido = file_get_contents($root.'/app/Models/ControlPedidos/PedidoBma.php');
$resuelve = file_get_contents($root.'/app/Services/ControlPedidos/ResuelveDatosPedidoBma.php');
$enviar = file_get_contents($root.'/app/Services/ControlPedidos/EnviarPedidoBmaService.php');
$validar = file_get_contents($root.'/app/Services/ControlPedidos/ValidarPagoPedidoBmaService.php');
$auditar = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Auditar/Partials/ModalRevisarPedido.jsx');
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');
$seccion = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/SeccionPagosExhibicion.jsx');
$styles = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js');
$tabla = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/TablaPedidos.jsx');
$detalle = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalDetallePedido.jsx');
$routes = control_pedidos_routes_content($root);
$controller = file_get_contents($root.'/app/Http/Controllers/ControlPedidos/PedidoBmaSaldosPagosController.php');
$acciones = file_get_contents($root.'/app/Support/ControlPedidos/AccionesHistorialPedidoBma.php');
$migracion = file_get_contents($root.'/database/migrations/2026_08_09_150000_migrate_pedido_bma_pagos_estado_revision.php');

$checks = [
    ['assertCubiertoParaEnviar en RegistrarPago', str_contains($registrar, 'function assertCubiertoParaEnviar')],
    ['assertPagoListoParaAvanzar', str_contains($registrar, 'function assertPagoListoParaAvanzar')],
    ['calcularResumenCobertura', str_contains($registrar, 'function calcularResumenCobertura')],
    ['comprobante obligatorio en handle', str_contains($registrar, 'Cada exhibición de pago debe incluir su comprobante')],
    ['generarExcedenteSiAplica en RegistrarPago', str_contains($registrar, 'function generarExcedenteSiAplica')],
    ['reconciliarExcedenteTrasExhibicion', str_contains($registrar, 'function reconciliarExcedenteTrasExhibicion')],
    ['mensajeMontoFaltante', str_contains($registrar, 'function mensajeMontoFaltante')],
    ['resumen emite cobertura', str_contains($registrar, "'cobertura'")],
    ['resumen emite revision', str_contains($registrar, "'revision'")],
    ['resumen emite total_a_cubrir', str_contains($registrar, "'total_a_cubrir'")],
    ['resumen emite excedente_generado', str_contains($registrar, "'excedente_generado'")],
    ['cobertura independiente de revisión (no mezcla en cálculo de cobertura)', str_contains($registrar, "\$cobertura = 'cubierto'")],
    ['Enviar llama assertCubierto', str_contains($enviar, 'assertCubiertoParaEnviar')],
    ['Enviar genera excedente', str_contains($enviar, 'generarExcedenteSiAplica')],
    ['ValidarPago llama assertPagoListo', str_contains($validar, 'assertPagoListoParaAvanzar')],
    ['Eliminar reconcilia excedente', str_contains($eliminar, 'reconciliarExcedenteTrasExhibicion')],
    ['Actualizar reconcilia excedente', str_contains($actualizar, 'reconciliarExcedenteTrasExhibicion')],
    ['Aprobar re-assert pago', str_contains(file_get_contents($root.'/app/Services/ControlPedidos/AprobarPedidoBmaService.php'), 'assertPagoListoParaAvanzar')],
    ['Aprobar mensaje pago validado', str_contains(file_get_contents($root.'/app/Services/ControlPedidos/AprobarPedidoBmaService.php'), 'Valide el pago antes de aprobar')],
    ['Aprobar mensaje remision', str_contains(file_get_contents($root.'/app/Services/ControlPedidos/AprobarPedidoBmaService.php'), 'Adjunte la remisión PDF antes de aprobar')],
    ['JS calcularResumenCoberturaPago', str_contains($styles, 'calcularResumenCoberturaPago')],
    ['JS mensajePagoFaltante', str_contains($styles, 'mensajePagoFaltante')],
    ['UI Total a cubrir', str_contains($seccion, 'Total a cubrir')],
    ['UI Total pagado por el cliente', str_contains($seccion, 'Total pagado por el cliente')],
    ['UI sin Saldo a favor generado', ! str_contains($form, 'Saldo a favor generado')],
    ['UI excedente este pedido', str_contains($seccion, 'Excedente generado (este pedido)') || str_contains($form, 'Excedente generado (este pedido)')],
    ['Auditar no registra exhibiciones', str_contains($auditar, 'puedeRegistrar={false}')],
    ['Auditar puede revisar exhibiciones', str_contains($auditar, 'puedeRevisar=')],
    ['Auditar no genera SAF', str_contains($auditar, 'puedeGenerarSaldo={false}')],
    ['sin store_auditoria', ! str_contains($routes, 'pagos.store_auditoria')],
    ['controller comprobante required en store', str_contains($controller, "'comprobante' => ['required'")],
    ['ruta pagos.update', str_contains($routes, "name('pagos.update')")],
    ['ruta pagos.destroy', str_contains($routes, "name('pagos.destroy')")],
    ['revisarPago bajo can auditar', preg_match("/can:control_pedidos\.auditar[\s\S]*pagos\.revisar/m", $routes) === 1],
    ['FORMA deposito', str_contains($modelo, "'deposito'")],
    ['REQUIERE_BANCO mapa', str_contains($modelo, 'REQUIERE_BANCO')],
    ['formaRequiereBanco helper', str_contains($modelo, 'function formaRequiereBanco')],
    ['estados revision extendidos', str_contains($modelo, 'REVISION_VERIFICADO') && str_contains($modelo, 'REVISION_RECHAZADO')],
    ['migracion confirmado→verificado', str_contains($migracion, "'verificado'")],
    ['Actualizar invalida revision material', str_contains($actualizar, 'REVISION_PENDIENTE') && str_contains($actualizar, 'EDICION_EXHIBICION_PAGO')],
    ['Eliminar servicio existe', str_contains($eliminar, 'BAJA_EXHIBICION_PAGO')],
    ['Revisar servicio historial', str_contains($revisar, 'REVISION_EXHIBICION_PAGO')],
    ['acciones historial exhibicion', str_contains($acciones, 'EDICION_EXHIBICION_PAGO')],
    ['fuentesPagoResumen en PedidoBma', str_contains($pedido, 'function fuentesPagoResumen')],
    ['fallback banco legacy en fuentes', str_contains($pedido, 'catalogo_banco_id') || str_contains($pedido, '$this->banco')],
    ['ResuelveDatos no persiste banco general', ! str_contains($resuelve, "'catalogo_banco_id' => \$datos['catalogo_banco_id']")],
    ['UI form sin banco general', ! preg_match('/label className=\{SECCION\}>Banco</', $form)],
    ['Seccion modo dividido toggle', str_contains($seccion, 'Dividir el pago entre diferentes métodos o bancos')],
    // iniciarEdicion ya no fuerza dividido; el único setDividido(true) al cargar/toggle queda fuera de esa función.
    ['Seccion editar no fuerza dividido', ! str_contains(
        preg_match('/const iniciarEdicion = \(p\) => \{[\s\S]*?\n    \};/m', $seccion, $m) ? ($m[0] ?? '') : 'FAIL',
        'setDividido(true)'
    )],
    ['Resuelve seguro sin reexpedicion', str_contains($resuelve, 'envioBaseSinReexpedicion')],
    ['Resumen pago recalcula seguro sin rex', str_contains($registrar, 'costoReexpedicion')],
    ['Seccion confirmacion colapsar', str_contains($seccion, 'Al volver a pago único')],
    ['Seccion badgeCoberturaPago', str_contains($seccion, 'badgeCoberturaPago')],
    ['styles badgeCoberturaPago', str_contains($styles, 'badgeCoberturaPago')],
    ['styles sin dependencia de etiqueta combinada en badges nuevos', str_contains($styles, 'LABELS_COBERTURA_PAGO')],
    ['tabla usa fuentes_pago', str_contains($tabla, 'fuentes_pago') || str_contains($tabla, 'textoFuentesPagoCompacto')],
    ['detalle usa fuentes_pago', str_contains($detalle, 'fuentes_pago')],
    ['UI gate pagoPendiente', str_contains($form, 'pagoPendiente')],
    ['Seccion menciona comprobante', str_contains($seccion, 'Comprobante')],
    ['ValidacionCampos sin banco general', ! str_contains(file_get_contents($root.'/app/Services/ControlPedidos/ValidacionCamposPedidoBma.php'), "faltantes[] = 'banco'")],
    ['validarCamposEnvio sin banco general', ! str_contains($styles, "faltantes.push('banco')")],
    ['auditoria usa fuentes_pago', str_contains(file_get_contents($root.'/resources/js/Pages/ControlPedidos/Auditar/Partials/TablaAuditoria.jsx'), 'textoFuentesPagoCompacto')],
];

// Unit-ish: requiere_banco sin cargar Laravel
require_once $root.'/vendor/autoload.php';

use App\Models\SaldosAFavor\PedidoBmaPago;

$checks[] = ['transferencia requiere banco', PedidoBmaPago::formaRequiereBanco('transferencia') === true];
$checks[] = ['deposito requiere banco', PedidoBmaPago::formaRequiereBanco('deposito') === true];
$checks[] = ['efectivo no requiere banco', PedidoBmaPago::formaRequiereBanco('efectivo') === false];

use App\Services\ControlPedidos\CalcularSeguroPedidoService;

$seguro = new CalcularSeguroPedidoService();
$checks[] = [
    'reexpedicion 150 no entra al seguro (delta 3.75)',
    abs($seguro->calcularCosto('FEDEX', 350, 1000) - $seguro->calcularCosto('FEDEX', 200, 1000) - 3.75) < 0.001,
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

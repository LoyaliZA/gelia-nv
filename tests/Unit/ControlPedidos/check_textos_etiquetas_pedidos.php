<?php

/**
 * Self-check: copys de cliente (nota de compra, origen de guía, Envío N, Revisión).
 * Uso: php tests/Unit/ControlPedidos/check_textos_etiquetas_pedidos.php
 */
$fallos = 0;
$root = dirname(__DIR__, 3);

$styles = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js');
$form = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx');
$publico = file_get_contents($root.'/resources/js/Pages/Clientes/Direcciones/FormularioPublico.jsx');
$errorDatos = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalReportarErrorDatos.jsx');
$camposPhp = file_get_contents($root.'/app/Support/ControlPedidos/CamposIncorrectosPedidoBma.php');
$maquina = file_get_contents($root.'/app/Support/ControlPedidos/MaquinaEstadosPedidoBma.php');
$listarBma = file_get_contents($root.'/app/Services/ControlPedidos/ListarPedidosBmaService.php');
$listarAud = file_get_contents($root.'/app/Services/ControlPedidos/ListarPedidosAuditoriaService.php');
$tablaAud = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Auditar/Partials/TablaAuditoria.jsx');
$tablaPed = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/TablaPedidos.jsx');
$detalle = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalDetallePedido.jsx');
$cedis = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis.jsx');
$pesaje = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx');
$revisar = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Auditar/Partials/ModalRevisarPedido.jsx');
$liberar = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Auditar/Partials/ModalLiberarResguardoAbierto.jsx');

$pregunta = '¿Deseas que la nota de compra vaya dentro de tu envío?';

$checks = [
    ['constante pregunta nota de compra', str_contains($styles, "LABEL_NOTA_COMPRA_PREGUNTA = '{$pregunta}'")],
    ['form pedido usa la pregunta', str_contains($form, 'LABEL_NOTA_COMPRA_PREGUNTA')],
    ['formulario público usa la pregunta', str_contains($publico, $pregunta)],
    ['UI sin Anexar remisión', ! str_contains($errorDatos, 'Anexar remisión') && ! str_contains($form, 'Anexar remisión')],
    ['PHP anexar_remision label nota de compra', str_contains($camposPhp, "'anexar_remision' => 'Nota de compra en el envío'")],
    ['label guía empresa', str_contains($styles, "LABEL_GUIA_EMPRESA = 'Guía generada por la empresa'")],
    ['label guía cliente', str_contains($styles, "LABEL_GUIA_CLIENTE = 'Guía proporcionada por el cliente'")],
    ['form radio guía empresa/cliente', str_contains($form, 'LABEL_GUIA_EMPRESA') && str_contains($form, 'LABEL_GUIA_CLIENTE') && str_contains($form, 'type="radio"')],
    ['PHP cliente_proporciona_guia label larga', str_contains($camposPhp, "'cliente_proporciona_guia' => 'Guía proporcionada por el cliente'")],
    ['helper etiquetaEnvio', str_contains($styles, 'export const etiquetaEnvio') && str_contains($styles, '`Envío ${n}`')],
    ['detalle usa etiquetaEnvio', str_contains($detalle, 'etiquetaEnvio(idx, c)')],
    ['CEDIS usa etiquetaEnvio', str_contains($cedis, 'etiquetaEnvio(idx, c)')],
    ['pesaje usa etiquetaEnvio', str_contains($pesaje, 'etiquetaEnvio(')],
    ['revisar usa etiquetaEnvio', str_contains($revisar, 'etiquetaEnvio(idx, c)')],
    ['liberar usa etiquetaEnvio', str_contains($liberar, 'etiquetaEnvio(idx, c)')],
    ['sin fallback Caja en detalle', ! str_contains($detalle, "|| 'Caja'")],
    ['semántico PENDIENTE_AUXILIAR es Pendiente de auditoría', str_contains($styles, "PENDIENTE_AUXILIAR: { hex: '#EAB308', label: 'Pendiente de auditoría' }")],
    ['badge Revisión', str_contains($styles, "label: 'Revisión'") && str_contains($styles, 'badgePendienteRevision')],
    ['badge Nueva revisión', str_contains($styles, "label: 'Nueva revisión'") && ! str_contains($styles, 'Revisar nuevamente')],
    ['badgeAuditoriaRevision usa en_revision_ahora', str_contains($styles, 'en_revision_ahora')],
    ['auditoría usa badgeAuditoriaRevision', str_contains($tablaAud, 'badgeAuditoriaRevision(pedido)')],
    ['tabla BMA usa badgeAuditoriaRevision', str_contains($tablaPed, 'badgeAuditoriaRevision(pedido)')],
    ['listado BMA setea en_revision_ahora', str_contains($listarBma, 'en_revision_ahora')],
    ['auditoría setea en_revision_ahora', str_contains($listarAud, 'en_revision_ahora')],
    ['PHP esPendienteReRevision extraído', str_contains($maquina, 'function esPendienteReRevision')],
    ['listado BMA setea pendiente_re_revision', str_contains($listarBma, 'pendiente_re_revision')],
    ['auditoría reusa MaquinaEstados', str_contains($listarAud, 'MaquinaEstadosPedidoBma::esPendienteReRevision')],
    ['consulta origen de guía', str_contains($detalle, 'etiquetaOrigenGuia') && str_contains($cedis, 'etiquetaOrigenGuia')],
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

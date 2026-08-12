<?php

/**
 * Self-check: cards colapsadas, fotos lote/envío, escaneo continuo, backend envio_caja, detalle separado.
 * Uso: php tests/Unit/ControlPedidos/check_cedis_pesaje_productos_ux.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$modal = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx');
$escaner = file_get_contents($root.'/resources/js/Components/Escanner/ModalEscanearCodigo.jsx');
$input = file_get_contents($root.'/resources/js/Components/Escanner/InputConEscanner.jsx');
$request = file_get_contents($root.'/app/Http/Requests/ControlPedidos/ResponderPesajePedidoBmaRequest.php');
$service = file_get_contents($root.'/app/Services/ControlPedidos/ResponderPesajePedidoBmaService.php');
$docModel = file_get_contents($root.'/app/Models/ControlPedidos/PedidoBmaDocumento.php');
$detalle = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/ModalDetallePedido.jsx');
$detalleCedis = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis.jsx');

$detalleStyles = file_get_contents($root.'/resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js');

$checks = [
    ['cards <details> colapsadas', str_contains($modal, '<details') && str_contains($modal, 'expandido')],
    ['estado inicial bueno', str_contains($modal, "estado_fisico: 'bueno'")],
    ['fotos lote por envío', str_contains($modal, 'Fotos del lote — envío') && str_contains($modal, 'evidenciasPorEnvio')],
    ['sin fotos lote globales', ! str_contains($modal, 'Fotos del lote (todos los perfumes)') && ! str_contains($modal, 'evidenciasGenerales')],
    ['form evidencias_envios', str_contains($modal, 'evidencias_envios[')],
    ['escaneoContinuo en pesaje', str_contains($modal, 'escaneoContinuo')],
    ['ModalEscanearCodigo continuo', str_contains($escaner, 'continuo = false') && str_contains($escaner, 'continuoRef')],
    ['debounce continuo', str_contains($escaner, 'DEBOUNCE_ENTRE_ESCANEOS_MS') || str_contains($escaner, 'DEBOUNCE_CONTINUO_MS')],
    ['bip confirmación escaneo', str_contains($escaner, 'bip_scanner.mp3') && str_contains($escaner, 'reproducirBipConfirmacion')],
    ['InputConEscanner no cierra en continuo', str_contains($input, 'escaneoContinuo') && str_contains($input, 'if (!escaneoContinuo)')],
    ['request evidencias_envios', str_contains($request, 'evidencias_envios')],
    ['const RELACION_ENVIO_CAJA', str_contains($docModel, "RELACION_ENVIO_CAJA = 'envio_caja'")],
    ['servicio guarda envio_caja', str_contains($service, 'RELACION_ENVIO_CAJA')],
    ['valida foto por cada envío', str_contains($service, 'foto del contenido del envío') && ! str_contains($service, 'count($lineas) >= 2')],
    ['envíos después de revisión', strpos($modal, 'Revisión física de productos') < strpos($modal, '>Envíos<')
        || (strpos($modal, 'Revisión física de productos') !== false
            && strpos($modal, 'Revisión física de productos') < strpos($modal, 'Envíos'))],
    ['detalle Productos con detalle', str_contains($detalle, 'Productos con detalle')],
    ['detalle Productos OK', str_contains($detalle, 'Productos OK')],
    ['detalle Evidencias del lote', str_contains($detalle, 'Evidencias del lote')],
    ['detalle Foto por envío', str_contains($detalle, 'Foto por envío')],
    ['detalle filtra revision_producto', str_contains($detalle, "relacion_tipo === 'revision_producto'")],
    ['detalle filtra envio_caja', str_contains($detalle, "relacion_tipo === 'envio_caja'")],
    ['CEDIS detalle espejo secciones', str_contains($detalleCedis, 'Productos con detalle') && str_contains($detalleCedis, 'Evidencias del lote')],
    ['PDF pedido siempre visible al inicio', str_contains($modal, 'PDF o foto del pedido')
        && ! str_contains($modal, 'soporteAbierto')
        && ! str_contains($modal, 'Ver PDF / foto del pedido')],
    ['PDF embebido con iframe', str_contains($modal, '<iframe') && ! str_contains($modal, 'Tocar para ver PDF')],
    ['sin Estado general en UI', ! str_contains($modal, 'Estado general')],
    ['estado general derivado', str_contains($modal, 'estadoGeneralDerivado')],
    ['catálogo caja bloqueado', str_contains($modal, "campo !== 'peso_real_kg'") && str_contains($modal, 'Datos del catálogo')],
    ['productos compactos >3', str_contains($modal, 'MAX_PRODUCTOS_ABIERTOS') && str_contains($modal, 'productosCompactos')],
    ['autoguardado IndexedDB', str_contains($modal, 'guardarBorradorPesaje') && str_contains($modal, 'leerBorradorPesaje')],
    ['estado sin existencias', str_contains($detalleStyles, "sin_existencia: 'Sin existencias'")
        && str_contains($modal, 'requiereComentario')],
    ['búsqueda por almacen_id', str_contains($modal, 'almacen_id') && str_contains($modal, 'Buscando en:')],
    ['sin texto fallback catálogo', ! str_contains($modal, 'fallback catálogo')],
    ['permite duplicar productos', ! str_contains($modal, 'Ese producto ya está en la lista.')],
    ['etiqueta instancia 1/N', str_contains($modal, 'etiquetasInstanciaRevision')],
    ['flag permite_busqueda_productos', str_contains(
        file_get_contents($root.'/app/Models/Almacen.php'),
        'permite_busqueda_productos'
    )],
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

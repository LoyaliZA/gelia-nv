<?php

/**
 * Self-check: bitácora enriquece schema/writer/UI.
 * Uso: php tests/Unit/ControlPedidos/check_bitacora_pedido.php
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

$migration = file_get_contents(__DIR__.'/../../../database/migrations/2026_08_07_124800_enrich_pedido_bma_historial_estados.php');
$model = file_get_contents(__DIR__.'/../../../app/Models/ControlPedidos/PedidoBmaHistorialEstado.php');
$writer = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/RegistrarHistorialPedidoService.php');
$acciones = file_get_contents(__DIR__.'/../../../app/Support/ControlPedidos/AccionesHistorialPedidoBma.php');
$modal = file_get_contents(__DIR__.'/../../../resources/js/Pages/ControlPedidos/Partials/ModalBitacoraPedido.jsx');
$remision = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/GestionarRemisionPedidoBmaService.php');
$guia = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/GestionarGuiaPdfPedidoBmaService.php');
$cola = file_get_contents(__DIR__.'/../../../app/Services/ControlPedidos/AvanzarColaErroresPedidoBmaService.php');

$assert(str_contains($migration, "'accion'"), 'migración añade accion');
$assert(str_contains($migration, "'rol'"), 'migración añade rol');
$assert(str_contains($migration, "'departamento'"), 'migración añade departamento');
$assert(str_contains($migration, "'evidencia_ruta'"), 'migración añade evidencia_ruta');

$assert(str_contains($model, "'accion'"), 'modelo fillable accion');
$assert(str_contains($model, 'accion_etiqueta'), 'modelo appends accion_etiqueta');

$assert(str_contains($writer, 'snapshotActor'), 'writer toma snapshot de actor');
$assert(str_contains($writer, 'evidencia'), 'writer acepta evidencia');

$assert(str_contains($acciones, 'CREACION_BORRADOR'), 'constantes de acción');
$assert(str_contains($acciones, 'CARGA_REMISION'), 'acción carga remisión');
$assert(str_contains($acciones, 'CORRECCION'), 'acción corrección');
$assert(str_contains($acciones, 'REABRIR_ENVIO'), 'acción reabrir envío');

$assert(str_contains($modal, 'accion_etiqueta') || str_contains($modal, 'accionEtiqueta'), 'modal muestra acción');
$assert(str_contains($modal, 'estatus_anterior') || str_contains($modal, 'estatusAnterior'), 'modal muestra estado anterior');
$assert(str_contains($modal, 'departamento'), 'modal muestra departamento/rol');
$assert(str_contains($modal, 'evidencia_ruta') || str_contains($modal, 'evidenciaRuta'), 'modal link evidencia');

$assert(str_contains($remision, 'CARGA_REMISION'), 'remisión escribe historial');
$assert(str_contains($guia, 'CARGA_GUIA_PDF'), 'PDF guía escribe historial');
$assert(str_contains($cola, 'CORRECCION'), 'cola errores escribe corrección');

exit($fallos > 0 ? 1 : 0);

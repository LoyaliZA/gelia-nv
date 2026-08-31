<?php

declare(strict_types=1);

/**
 * DomPDF 3 ejecuta page_script al registrarlo; la paginación debe ir en end_document.
 */
$path = dirname(__DIR__, 3).'/app/Services/Reportes/PagosPedidos/GenerarReportePagosPedidosPdfService.php';
$src = file_get_contents($path);

assert(is_string($src));
assert(str_contains($src, 'setCallbacks'), 'registrarPiePagina debe usar setCallbacks (DomPDF 3)');
assert(str_contains($src, 'end_document'), 'registrarPiePagina debe engancharse a end_document');
assert(! str_contains($src, 'getCanvas()->page_script'), 'no registrar page_script antes del render');

echo "check_pdf_pagination_callback: OK\n";

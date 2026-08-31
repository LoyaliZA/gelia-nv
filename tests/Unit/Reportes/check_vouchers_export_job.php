<?php

declare(strict_types=1);

/**
 * ponytail: verificación estática del job de exportación vouchers.
 */
$path = dirname(__DIR__, 3).'/app/Jobs/GenerarReportePagosPedidosPdfJob.php';
$src = file_get_contents($path);

assert(is_string($src));
assert(str_contains($src, "['vouchers', 'csv_resumen']"), 'job debe ramificar vouchers CSV');
assert(str_contains($src, "['vouchers', 'pdf']"), 'job debe ramificar vouchers PDF');
assert(str_contains($src, 'ESTADO_PENDING'), 'job debe contemplar estado pending');

$solicitar = file_get_contents(dirname(__DIR__, 3).'/app/Services/Reportes/PagosPedidos/SolicitarExportacionReportePagosPedidosService.php');
assert(is_string($solicitar));
assert(str_contains($solicitar, 'tipo_reporte'), 'solicitar debe guardar tipo_reporte');
assert(str_contains($solicitar, 'ESTADO_PENDING'), 'solicitar debe crear en pending');

echo "check_vouchers_export_job: OK\n";

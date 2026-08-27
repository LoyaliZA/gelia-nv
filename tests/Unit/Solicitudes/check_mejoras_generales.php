<?php

/**
 * Self-check sin DB/Sail: flags tienda + scope de vencimiento + labels.
 * Ejecutar: php tests/Unit/Solicitudes/check_mejoras_generales.php
 */
require __DIR__.'/../../../vendor/autoload.php';

$fails = 0;
$ok = function (string $label, bool $pass) use (&$fails): void {
    echo ($pass ? '[OK] ' : '[FAIL] ').$label.PHP_EOL;
    if (! $pass) {
        $fails++;
    }
};

use App\Services\Solicitudes\CrearSolicitudService;

$ok('esFlujoTienda compra_en_tienda', CrearSolicitudService::esFlujoTienda(['compra_en_tienda' => true]));
$ok('esFlujoTienda solo_tag', CrearSolicitudService::esFlujoTienda(['compra_en_tienda_solo_tag' => true]));
$ok('esFlujoTienda normal false', ! CrearSolicitudService::esFlujoTienda([
    'compra_en_tienda' => false,
    'compra_en_tienda_solo_tag' => false,
]));

$tagModel = file_get_contents(__DIR__.'/../../../app/Models/SolicitudTag.php');
$ok('scope excluye compra_en_tienda', str_contains($tagModel, "->where('compra_en_tienda', false)"));
$ok('scope excluye solo_tag', str_contains($tagModel, "->where('compra_en_tienda_solo_tag', false)"));

$ctrl = file_get_contents(__DIR__.'/../../../app/Http/Controllers/Solicitudes/SolicitudController.php');
$ok('aprueba flujo tienda auto pago', str_contains($ctrl, '$aprobarFlujoTienda'));
$ok('setea pago_confirmado en flujo tienda', preg_match(
    '/if \(\$aprobarFlujoTienda\) \{\s*\$datosUpdate\[[\'"]pago_confirmado[\'"]\] = true;/s',
    $ctrl
) === 1);

$facturaCtrl = file_get_contents(__DIR__.'/../../../app/Http/Controllers/Facturas/SolicitudFacturaController.php');
$ok('enlace fiscal permite Respondida', str_contains($facturaCtrl, "idDe('Respondida')"));
$ok('destroy permite borrador propio', str_contains($facturaCtrl, '$esBorradorPropio'));

$aplicar = file_get_contents(__DIR__.'/../../../app/Services/Facturas/AplicarDatosFiscalesPublicosDesdeEnlaceService.php');
$ok('form publico acepta Respondida', str_contains($aplicar, "idDe('Respondida')"));
$ok('notifica formulario_corregido', str_contains($aplicar, 'formularioCorregido'));

$label = 'Compra Realizada: Solicitar tag';
foreach ([
    'resources/js/Pages/Solicitudes/Index.jsx',
    'resources/js/Pages/Solicitudes/Partials/ModalFormSolicitud.jsx',
    'resources/js/utils/alertasPrefs.js',
    'app/Notifications/AlertaSolicitud.php',
] as $rel) {
    $path = __DIR__.'/../../../'.$rel;
    $ok("label en {$rel}", str_contains(file_get_contents($path), $label));
}

$tarjeta = file_get_contents(__DIR__.'/../../../resources/js/Pages/CancelacionesCotizaciones/Partials/TarjetaOperativa.jsx');
$ok('chip departamento en TarjetaOperativa', str_contains($tarjeta, 'deptoLabel'));

$ok('modal corregir fiscales existe', is_file(__DIR__.'/../../../resources/js/Pages/Facturas/Partials/ModalCorregirDatosFiscales.jsx'));

echo PHP_EOL.($fails === 0 ? 'ALL CHECKS PASSED' : "{$fails} CHECK(S) FAILED").PHP_EOL;
exit($fails === 0 ? 0 : 1);

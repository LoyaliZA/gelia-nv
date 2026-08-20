<?php

/**
 * Self-check: host form, nginx, Permissions-Policy camera=(self) para evidencias CEDIS.
 * Uso: php tests/Unit/ControlPedidos/check_cedis_evidencia_publica.php
 */

$fallos = 0;
$root = dirname(__DIR__, 3);

$restrict = file_get_contents($root.'/app/Http/Middleware/RestrictFormHostname.php');
$harden = file_get_contents($root.'/app/Http/Middleware/HardenSolicitudDireccionPublica.php');
$nginx = file_get_contents($root.'/default.conf');
$formUrl = file_get_contents($root.'/app/Support/FormPublicUrl.php');
$bootstrap = file_get_contents($root.'/resources/js/bootstrap.js');
$routes = file_get_contents($root.'/routes/web.php');

$checks = [
    ['RestrictFormHostname allowlist evidencias', str_contains($restrict, "str_starts_with(\$routeName, 'cedis_evidencia.publicas.')")],
    ['nginx location cedis-evidencia', str_contains($nginx, 'location ^~ /cedis-evidencia')],
    ['Permissions-Policy camera self', str_contains($harden, "cedis_evidencia.publicas.")
        && str_contains($harden, "'(self)'")],
    ['FormPublicUrl cedisEvidenciaShow', str_contains($formUrl, 'function cedisEvidenciaShow')
        && str_contains($formUrl, '/cedis-evidencia/')],
    ['Echo skip en /cedis-evidencia', str_contains($bootstrap, "/cedis-evidencia")],
    ['rutas públicas evidencias', str_contains($routes, "name('cedis_evidencia.publicas.show')")
        && str_contains($routes, "name('cedis_evidencia.publicas.fotos')")],
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

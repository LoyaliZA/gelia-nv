<?php

// Borra exclusivamente los binarios ficticios identificados de esta auditoría.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'codex_m01_audit_20261114') {
    throw new RuntimeException('Base exclusiva de auditoría requerida.');
}
$privados = App\Models\ClienteDocumento::withTrashed()->where('nombre_original', 'identificacion-ficticia.png')
    ->whereHas('consignatario.cliente', fn ($q) => $q->where('numero_cliente', 'QA-M01-BROWSER'))->get();
$publicos = App\Models\ControlPedidos\PedidoBmaDocumento::where('nombre_original', 'conjunto-ficticio.png')
    ->whereHas('pedido', fn ($q) => $q->where('folio', 'QA-M01-NAVEGADOR'))->get();
$hashes = [];
foreach ($publicos as $doc) {
    $contenido = Illuminate\Support\Facades\Storage::disk('public')->get($doc->ruta_archivo);
    if ($contenido !== null) { $hashes[] = hash('sha256', $contenido); }
}
$eliminados = 0;
foreach ($privados as $doc) {
    $eliminados += (int) Illuminate\Support\Facades\Storage::disk('local')->delete($doc->ruta);
}
foreach ($publicos as $doc) {
    $eliminados += (int) Illuminate\Support\Facades\Storage::disk('public')->delete($doc->ruta_archivo);
}
// Primer recorrido: base aislada reconstruida después; mismo canvas ficticio.
// No borrar por fecha, extensión ni por el id del pedido operativo.
foreach (Illuminate\Support\Facades\Storage::disk('public')->files('pedidos_bma/14/disponibilidad') as $ruta) {
    $contenido = Illuminate\Support\Facades\Storage::disk('public')->get($ruta);
    if ($contenido !== null && in_array(hash('sha256', $contenido), $hashes, true)) {
        $eliminados += (int) Illuminate\Support\Facades\Storage::disk('public')->delete($ruta);
    }
}
echo json_encode(['archivos_ficticios_eliminados' => $eliminados, 'capturas_conservadas' => true], JSON_PRETTY_PRINT).PHP_EOL;

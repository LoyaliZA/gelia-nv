<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'codex_m01_audit_20261114') {
    throw new RuntimeException('Se requiere la base exclusiva de auditoría.');
}
$f = json_decode(file_get_contents('/tmp/m01-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
$p = App\Models\ControlPedidos\PedidoBma::where('vendedor_id', $f['usuario_id'])->where('folio_remision', 'WIZ-CONTINUIDAD-'.$f['usuario_id'])->firstOrFail();
$soporte = $p->documentos()->where('nombre_original', 'm01-qa.png')->where('tipo', 'pdf_pedido')->firstOrFail();
$disk = Illuminate\Support\Facades\Storage::disk('public');
if (!$disk->exists($soporte->ruta_archivo)) throw new RuntimeException('El soporte de comparación ya fue eliminado.');
$hash = hash('sha256', $disk->get($soporte->ruta_archivo));
$publicos = App\Models\ControlPedidos\PedidoBmaDocumento::where('nombre_original', 'm01-qa.png')
    ->whereHas('pedido', fn ($q) => $q->where('folio_remision', 'like', 'WIZ-CONTINUIDAD%')
        ->whereHas('vendedor', fn ($u) => $u->where('email', 'like', 'navegador-m01-%@example.test')))->get();
$privados = App\Models\ClienteDocumento::withTrashed()->where('nombre_original', 'm01-qa.png')
    ->whereHas('consignatario', fn ($q) => $q->whereHas('versiones', fn ($v) => $v->where('nombre', 'Persona ficticia de continuidad'))
        ->whereHas('cliente.vendedor', fn ($u) => $u->where('email', 'like', 'navegador-m01-%@example.test')))->get();
$eliminados = 0; $omitidos = 0;
foreach ([['public', $publicos, 'ruta_archivo'], ['local', $privados, 'ruta']] as [$disco, $documentos, $campo]) {
    $storage = Illuminate\Support\Facades\Storage::disk($disco);
    foreach ($documentos as $doc) {
        $ruta = $doc->{$campo};
        if (!$storage->exists($ruta)) continue;
        if (!hash_equals($hash, hash('sha256', $storage->get($ruta)))) { $omitidos++; continue; }
        $eliminados += (int) $storage->delete($ruta);
    }
}
echo json_encode(['archivos_ficticios_eliminados' => $eliminados, 'archivos_de_otro_hash_conservados' => $omitidos], JSON_PRETTY_PRINT).PHP_EOL;

<?php

// Solo datos ficticios en la base exclusiva. Ejecutar mediante Sail.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'codex_m01_audit_20261114') {
    throw new RuntimeException('Base exclusiva de auditoría requerida.');
}
config(['filesystems.disks.local.root' => '/tmp/m01-auditoria-documentos', 'cache.default' => 'array']);
$servicio = app(App\Services\Clientes\Expediente\GestionarExpedienteClienteService::class);
if (($argv[1] ?? '') === 'worker') {
    $consignatario = App\Models\ClienteConsignatario::findOrFail($argv[2]);
    $usuario = App\Models\User::findOrFail($argv[3]);
    for ($i = 0; $i < 3; $i++) {
        $servicio->editarDatos($consignatario, $usuario, 'Persona '.getmypid().'-'.$i, '3311111111');
        $servicio->guardarDocumento($consignatario, $usuario, Illuminate\Http\UploadedFile::fake()->create('identificacion-ficticia.pdf', 2, 'application/pdf'));
    }
    exit(0);
}
$usuario = App\Models\User::factory()->create(['email' => 'concurrencia-m01@example.test']);
foreach (['clientes.ver', 'clientes.expediente.editar', 'clientes.expediente.documentos'] as $permiso) {
    $usuario->givePermissionTo(Spatie\Permission\Models\Permission::findOrCreate($permiso, 'web'));
}
$lista = App\Models\CatalogoListaDescuento::firstOrCreate(['nombre' => 'AUDITORIA M01'], ['monto_requerido' => 0, 'activo' => true]);
$cliente = App\Models\Cliente::create(['numero_cliente' => 'QA-M01-CONCURRENCIA', 'nombre' => 'Cliente ficticio de concurrencia', 'lista_actual_id' => $lista->id, 'vendedor_id' => $usuario->id]);
$consignatario = $servicio->crear($cliente, $usuario, 'Persona inicial', '3311111111');
$procesos = [];
for ($i = 0; $i < 4; $i++) {
    $proceso = new Symfony\Component\Process\Process([PHP_BINARY, __FILE__, 'worker', (string) $consignatario->id, (string) $usuario->id], '/var/www/html');
    $proceso->setTimeout(30)->start();
    $procesos[] = $proceso;
}
foreach ($procesos as $proceso) {
    $proceso->wait();
    if (! $proceso->isSuccessful()) { throw new RuntimeException($proceso->getErrorOutput().$proceso->getOutput()); }
}
$datos = $consignatario->versiones()->orderBy('version')->pluck('version')->all();
$docs = $consignatario->documentos()->orderBy('version')->pluck('version')->all();
if ($datos !== range(1, 13) || $docs !== range(1, 12) || $consignatario->versiones()->where('vigente', true)->count() !== 1 || $consignatario->documentos()->where('vigente', true)->count() !== 1) {
    throw new RuntimeException('Versiones duplicadas, perdidas o múltiples vigentes.');
}
echo json_encode(['resultado' => 'aprobado', 'procesos' => 4, 'versiones_datos' => count($datos), 'versiones_documentos' => count($docs), 'vigente_por_tipo' => 1], JSON_PRETTY_PRINT).PHP_EOL;

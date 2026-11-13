<?php

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ErroresImportacionCliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\ImporteProvisionalSolicitud;
use App\Models\User;
use App\Services\Clientes\ImportarClientesWizerpService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$host = (string) config('database.connections.mysql.host');
$db = (string) config('database.connections.mysql.database');
if (config('database.default') !== 'mysql' || $db !== 'gelia_wizerp_test' || $host !== 'gelia-wizerp-mysql-test') {
    fwrite(STDERR, "Destino incorrecto: ".config('database.default')." {$host} {$db}\n");
    exit(2);
}

Artisan::call('migrate', ['--force' => true]);

$usuario = User::factory()->create();
$lista = CatalogoListaDescuento::create(['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0, 'activo' => true]);
CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01, 'activo' => true]);
$cliente = Cliente::create([
    'numero_cliente' => '8815',
    'nombre' => 'Cliente ficticio',
    'lista_actual_id' => $lista->id,
    'monto_venta_actual' => '5000.00',
    'es_inactivo' => false,
    'vendedor_id' => $usuario->id,
]);

$importar = function (string $monto, ?string $fecha, string $modalidad) use ($usuario, $cliente) {
    $csv = "numero_cliente,nombre,codigo_lista,monto_venta_actual,vendedor_id\n{$cliente->numero_cliente},Cliente,,{$monto},\n";
    $ruta = 'importaciones_clientes/verificar-'.md5($modalidad.$fecha.$monto).'.csv';
    Storage::disk('local')->put($ruta, $csv);
    $importacion = ImportacionCliente::create([
        'usuario_id' => $usuario->id,
        'nombre_archivo_original' => 'clientes.csv',
        'ruta_almacenamiento' => $ruta,
        'modalidad_carga' => $modalidad,
        'fecha_corte' => $fecha,
        'estado' => 'previsualizada',
    ]);
    $archivo = new UploadedFile(Storage::disk('local')->path($ruta), 'clientes.csv', 'text/csv', null, true);

    return app(ImportarClientesWizerpService::class)->ejecutar($archivo, $importacion);
};

$fallos = [];
$primero = $importar('5000', '2026-11-12 10:00:00', ImportacionCliente::MODALIDAD_CORTE);
$cliente->refresh();
if ((string) $cliente->monto_venta_actual !== '5000.00') {
    $fallos[] = 'monto inicial '.$cliente->monto_venta_actual;
}
if (HistorialMontoCliente::query()->where('cliente_id', $cliente->id)->count() !== 0) {
    $fallos[] = 'quedó historial sin cambio de monto';
}
if (ImporteProvisionalSolicitud::query()->where('cliente_id', $cliente->id)->count() !== 0) {
    $fallos[] = 'quedó un provisional';
}
if ($cliente->fecha_ultimo_corte_administrativo?->format('Y-m-d H:i:s') !== '2026-11-12 10:00:00') {
    $fallos[] = 'corte '.$cliente->fecha_ultimo_corte_administrativo;
}
if (($primero['stats']['errores'] ?? -1) !== 0) {
    $fallos[] = 'primer corte '.json_encode($primero['errores_detalle'] ?? [], JSON_UNESCAPED_UNICODE);
}

$segundo = $importar('9000', '2026-11-12 08:00:00', ImportacionCliente::MODALIDAD_CORTE);
$cliente->refresh();
if ((string) $cliente->monto_venta_actual !== '5000.00') {
    $fallos[] = 'el corte de las 8:00 escribió '.$cliente->monto_venta_actual;
}
if ($cliente->fecha_ultimo_corte_administrativo?->format('Y-m-d H:i:s') !== '2026-11-12 10:00:00') {
    $fallos[] = 'el corte de las 8:00 reemplazó la fecha';
}
if (ErroresImportacionCliente::query()->where('campo', 'fecha_corte')->count() !== 1) {
    $fallos[] = 'rechazo '.json_encode($segundo['errores_detalle'] ?? [], JSON_UNESCAPED_UNICODE);
}

$normal = $importar('7000', null, ImportacionCliente::MODALIDAD_NORMAL);
$cliente->refresh();
if ((string) $cliente->monto_venta_actual !== '7000.00') {
    $fallos[] = 'la actualización normal no escribió '.$cliente->monto_venta_actual;
}
if ($cliente->fecha_ultimo_corte_administrativo?->format('Y-m-d H:i:s') !== '2026-11-12 10:00:00') {
    $fallos[] = 'la actualización normal movió el corte';
}
if (($normal['stats']['errores'] ?? -1) !== 0) {
    $fallos[] = 'normal '.json_encode($normal['errores_detalle'] ?? [], JSON_UNESCAPED_UNICODE);
}

if ($fallos !== []) {
    fwrite(STDERR, implode("\n", $fallos)."\n");
    exit(1);
}

echo "MYSQL_OK\n";

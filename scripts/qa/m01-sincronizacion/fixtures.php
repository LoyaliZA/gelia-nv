<?php

// Reutiliza el guard y los catálogos ficticios de la base exclusiva; nunca migra ni borra filas.
ob_start();
require __DIR__.'/../m01-continuidad/fixtures.php';
$fixture = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

$ventas = User::findOrFail($fixture['usuario_id']);
$ventas->update(['name' => 'Ventas ficticias sincronización']);
foreach (['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos', 'control_pedidos.cedis.responder_pesaje'] as $permiso) {
    $ventas->revokePermissionTo($permiso);
}
$cedis = User::factory()->create([
    'name' => 'CEDIS ficticio sincronización',
    'email' => 'cedis-sinc-'.$ventas->id.'@example.test',
    'departamento_id' => $ventas->departamento_id,
    'password' => Illuminate\Support\Facades\Hash::make('M01-solo-pruebas-2026'),
]);
foreach (['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos', 'control_pedidos.cedis.responder_pesaje'] as $permiso) {
    $cedis->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
}
$cedis->concederAccesoSucursal($ventas->sucursalesOperables()->first());
$pedido = PedidoBma::findOrFail($fixture['pedido_id']);
$pedido->update(['folio_remision' => 'WIZ-SINC-'.$ventas->id, 'departamento_operacion_id' => $ventas->departamento_id]);
$imagen = imagecreatetruecolor(800, 1000);
$blanco = imagecolorallocate($imagen, 255, 255, 255);
$negro = imagecolorallocate($imagen, 0, 0, 0);
imagefill($imagen, 0, 0, $blanco);
foreach (['PEDIDO WIZERP - FICTICIO PARA QA', $pedido->folio_remision, 'Cliente ficticio de sincronizacion', 'SIN PAGO - DOCUMENTO SINTETICO', 'Pieza ficticia A - 1 unidad', 'Pieza ficticia B - 1 unidad'] as $i => $texto) {
    imagestring($imagen, 5, 40, 60 + $i * 60, $texto, $negro);
}
ob_start(); imagepng($imagen); $png = ob_get_clean();
$ruta = 'qa/m01-sincronizacion/'.$pedido->id.'/wizerp.png';
Storage::disk('public')->put($ruta, $png);
$pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => $ruta, 'nombre_original' => 'wizerp-sinc-ficticio.png', 'mime_type' => 'image/png', 'tamano_bytes' => strlen($png), 'orden' => 0, 'activo' => true]);
$consignatario = app(App\Services\Clientes\Expediente\GestionarExpedienteClienteService::class)->crear($pedido->cliente, $ventas, 'Consignatario ficticio de sincronización', '3311111111', $pedido);
app(App\Services\ControlPedidos\Municipal\RegistrarSolicitudMunicipalService::class)->ejecutar($pedido, $ventas, [
    'catalogo_paqueteria_id' => $fixture['transporte_id'], 'consignatario_id' => $consignatario->id,
    'almacen_id' => $fixture['almacen_cedis_id'], 'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'diferido',
]);
app(App\Services\ControlPedidos\Municipal\EnviarConsultaMunicipalService::class)->ejecutar($pedido->fresh(), $ventas, $fixture['almacen_cedis_id']);
$fixture['cedis_email'] = $cedis->email;
$fixture['cedis_id'] = $cedis->id;
$fixture['folio'] = $pedido->folio_remision;
$fixture['pedidos_antes_alta_vacia'] = PedidoBma::count();
echo json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

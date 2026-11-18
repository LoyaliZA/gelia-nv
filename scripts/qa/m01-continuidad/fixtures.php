<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'codex_m01_audit_20261114') {
    throw new RuntimeException('Base exclusiva de auditoría requerida.');
}
$sufijo = now()->format('His');
$departamento = App\Models\Departamento::create(['nombre' => 'Departamento ficticio '.$sufijo, 'codigo' => $sufijo, 'activo' => true]);
$usuario = App\Models\User::factory()->create(['name' => 'Auditoría municipal', 'email' => 'navegador-m01-'.$sufijo.'@example.test', 'departamento_id' => $departamento->id, 'password' => Illuminate\Support\Facades\Hash::make('M01-solo-pruebas-2026')]);
foreach (['clientes.ver', 'clientes.expediente.ver', 'clientes.expediente.editar', 'clientes.expediente.documentos', 'clientes.expediente.ver_identificacion', 'clientes.expediente.eliminar', 'clientes.expediente.ver_historico', 'control_pedidos.crear', 'control_pedidos.editar', 'control_pedidos.ver_detalle', 'control_pedidos.ver_listado', 'control_pedidos.municipal.responder_consulta'] as $permiso) {
    $usuario->givePermissionTo(Spatie\Permission\Models\Permission::findOrCreate($permiso, 'web'));
}
foreach (['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos', 'control_pedidos.cedis.responder_pesaje'] as $permiso) $usuario->givePermissionTo(Spatie\Permission\Models\Permission::findOrCreate($permiso, 'web'));
$lista = App\Models\CatalogoListaDescuento::firstOrCreate(['nombre' => 'AUDITORIA M01'], ['monto_requerido' => 0, 'activo' => true]);
$cliente = App\Models\Cliente::create(['numero_cliente' => '990000'.$sufijo, 'nombre' => 'Cliente ficticio de auditoría', 'lista_actual_id' => $lista->id, 'vendedor_id' => $usuario->id]);
$tipo = App\Models\CatalogoTipoAlmacen::firstOrCreate(['nombre' => 'PDV']);
$sucursal = App\Models\Sucursal::create(['codigo' => 'M01-QA-'.$sufijo, 'nombre' => 'Sucursal ficticia', 'activo' => true]);
$usuario->concederAccesoSucursal($sucursal);
$almacen = App\Models\Almacen::create(['codigo' => 'M01-QA-PDV-'.$sufijo, 'nombre' => 'Almacén ficticio', 'tipo_almacen_id' => $tipo->id, 'sucursal_id' => $sucursal->id, 'activo' => true, 'visible_en_pedidos' => true]);
$tipoCedis = App\Models\CatalogoTipoAlmacen::firstOrCreate(['nombre' => 'CEDIS']);
$almacenCedis = App\Models\Almacen::create(['codigo' => 'QA-CEDIS-'.$sufijo, 'nombre' => 'CEDIS ficticio '.$sufijo, 'tipo_almacen_id' => $tipoCedis->id, 'sucursal_id' => $sucursal->id, 'activo' => true, 'visible_en_pedidos' => true]);
$estatus = App\Models\ControlPedidos\CatalogoEstatusPedido::firstOrCreate(['codigo_interno' => 'M01_QA_BORRADOR'], ['nombre_visual' => 'Borrador', 'fase_ciclo' => 'BORRADOR', 'color_hex' => '#64748B', 'orden' => 1, 'activo' => true]);
foreach (['PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO'] as $fase) {
    App\Models\ControlPedidos\CatalogoEstatusPedido::firstOrCreate(['codigo_interno' => 'M01_QA_'.$fase], ['nombre_visual' => $fase, 'fase_ciclo' => $fase, 'color_hex' => '#64748B', 'orden' => 2, 'activo' => true]);
}
$pedido = App\Models\ControlPedidos\PedidoBma::create(['folio' => 'QA-M01-NAVEGADOR-'.$sufijo, 'fecha' => now()->toDateString(), 'cliente_id' => $cliente->id, 'vendedor_id' => $usuario->id, 'catalogo_estatus_pedido_id' => $estatus->id, 'total_mercancia' => 100, 'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal']]);
$transporte = App\Models\ControlPedidos\CatalogoPaqueteriaPedido::create(['nombre' => 'Transporte ficticio Jaguar '.$sufijo, 'sin_logistica' => false, 'categoria' => 'local_regional', 'activo' => true, 'habilitado_envio_municipio' => true, 'permite_costo_diferido' => true, 'reglas_municipio_pendientes' => false]);
$comercial = App\Models\ControlPedidos\CatalogoPaqueteriaPedido::create(['nombre' => 'Comercial ficticia '.$sufijo, 'categoria' => 'comercial', 'sin_logistica' => true, 'activo' => true]);
$inactiva = App\Models\ControlPedidos\CatalogoPaqueteriaPedido::create(['nombre' => 'Inactiva ficticia '.$sufijo, 'categoria' => 'local_regional', 'activo' => false]);
echo json_encode(['email' => $usuario->email, 'numero_cliente' => $cliente->numero_cliente, 'departamento' => $departamento->nombre, 'comercial' => $comercial->nombre, 'inactiva' => $inactiva->nombre, 'usuario_id' => $usuario->id, 'cliente_id' => $cliente->id, 'pedido_id' => $pedido->id, 'almacen_id' => $almacen->id, 'almacen_cedis_id' => $almacenCedis->id, 'transporte_id' => $transporte->id], JSON_PRETTY_PRINT).PHP_EOL;

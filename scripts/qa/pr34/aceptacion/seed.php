<?php
// Solo fixtures locales persistentes; nunca ejecutar en la conexión operativa.
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('qa-pr34') || config('database.connections.mysql.database') !== 'gelia_pr34_qa' || config('database.default') !== 'mysql') {
    throw new RuntimeException('Se requiere la base exclusiva gelia_pr34_qa.');
}
use App\Models\{Almacen, CatalogoTipoAlmacen, CatalogoListaDescuento, CatalogoBanco, Cliente, Departamento, Sucursal, User, ConfiguracionSistema};
use App\Models\ControlPedidos\{PedidoBma, PedidoBmaDocumento, CatalogoEstatusPedido, CatalogoModalidadPreparacionPedido, CatalogoOrigenPedido};
use Illuminate\Support\Facades\{DB, Hash, Storage};
use Spatie\Permission\Models\{Permission, Role};

$archivo = storage_path('app/qa-pr34-fixtures.json');
if (is_file($archivo)) { echo file_get_contents($archivo); exit; }
// Catálogo de nombres de permisos solamente: sin usuarios, roles administrativos ni claves externas.
foreach (Database\Seeders\PermisosActualesSeeder::nombres() as $nombre) Permission::findOrCreate($nombre, 'web');
$depto = Departamento::firstOrCreate(['nombre' => 'QA PR34 Ventas'], ['activo' => true]);
$sucursal = Sucursal::firstOrCreate(['codigo' => 'QA34'], ['nombre' => 'QA PR34 Tienda', 'activo' => true]);
$tipo = CatalogoTipoAlmacen::firstOrCreate(['nombre' => 'PDV'], ['activo' => true]);
$tienda = Almacen::firstOrCreate(['codigo' => 'QA34-PDV'], ['nombre' => 'QA PR34 Tienda', 'sucursal_id' => $sucursal->id, 'tipo_almacen_id' => $tipo->id, 'activo' => true, 'visible_en_pedidos' => true, 'permite_salida_pedidos' => true]);
$tipoCedis = CatalogoTipoAlmacen::firstOrCreate(['nombre' => 'CEDIS'], ['activo' => true]);
$cedis = Almacen::firstOrCreate(['codigo' => 'QA34-CEDIS'], ['nombre' => 'QA PR34 CEDIS', 'tipo_almacen_id' => $tipoCedis->id, 'activo' => true, 'visible_en_pedidos' => true, 'permite_salida_pedidos' => true]);
$permisos = [
    'ventas' => ['control_pedidos.crear','control_pedidos.editar','control_pedidos.ver_listado','control_pedidos.ver_detalle','control_pedidos.pagos.ver','control_pedidos.pagos.adjuntar'],
    'auxiliar' => ['control_pedidos.auditar','control_pedidos.auditar.aprobar','control_pedidos.pagos.ver','control_pedidos.pagos.validar'],
    'tienda' => ['control_pedidos.tienda.ver','control_pedidos.tienda.tomar','control_pedidos.tienda.responder','control_pedidos.tienda.apartado.separar','control_pedidos.tienda.evidencias','control_pedidos.tienda.reportar_error'],
    'cedis' => ['control_pedidos.cedis','control_pedidos.cedis.empacar','control_pedidos.cedis.enviar'],
];
$usuarios = [];
foreach ($permisos as $actor => $lista) {
    $rol = Role::findOrCreate('QA PR34 '.ucfirst($actor), 'web');
    foreach ($lista as $permiso) Permission::findOrCreate($permiso, 'web');
    $rol->syncPermissions([]);
    $usuario = User::firstOrCreate(['email' => $actor.'@pr34.example.test'], ['name' => 'QA PR34 '.ucfirst($actor), 'username' => 'qa34_'.$actor, 'password' => Hash::make(env('QA_LOGIN_PASSWORD')), 'email_verified_at' => now(), 'departamento_id' => $depto->id]);
    $usuario->assignRole($rol); $usuario->syncPermissions($lista); $usuario->departamentos()->syncWithoutDetaching([$depto->id]);
    $usuario->concederAccesoSucursal($sucursal, true);
    $usuarios[$actor] = $usuario;
}
app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
foreach (['BORRADOR','PESAJE_PENDIENTE','PESAJE_RESPONDIDO','PENDIENTE_AUXILIAR','EN_CEDIS','PENDIENTE_DE_ENVIO','ENVIADO','ENTREGADO','RECHAZADO_VENDEDORA','INCIDENCIA_CEDIS'] as $n => $fase) {
    CatalogoEstatusPedido::firstOrCreate(['fase_ciclo' => $fase], ['codigo_interno' => $fase, 'nombre_visual' => str_replace('_',' ',$fase), 'color_hex' => '#777777', 'orden' => $n, 'activo' => true]);
}
foreach (['RECOGE_TIENDA','RECOGE_TIENDA_TRANSFERENCIA','RECOGE_TIENDA_CREDITO'] as $codigo) CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => $codigo], ['nombre' => str_replace('_',' ',$codigo), 'activo' => true]);
$origen = CatalogoOrigenPedido::firstOrCreate(['nombre' => 'QA PR34 Tienda'], [ 'requiere_logistica' => true, 'activo' => true]);
$lista = CatalogoListaDescuento::firstOrCreate(['nombre' => 'QA PR34 Lista'], ['activo' => true]);
$cliente = Cliente::firstOrCreate(['numero_cliente' => 'QA340001'], ['nombre' => 'Cliente ficticio PR34', 'lista_actual_id' => $lista->id, 'vendedor_id' => $usuarios['ventas']->id]);
$banco = CatalogoBanco::firstOrCreate(['nombre' => 'QA PR34 Banco ficticio'], ['activo' => true, 'compartido' => true]);
app(App\Services\ControlPedidos\BancosGestionPedidosConfig::class)->guardar([['id' => $banco->id, 'habilitado' => true, 'compartido' => true, 'departamento_ids' => [$depto->id]]]);
$flag = ['activo' => true, 'modalidades_habilitadas' => ['RECOGE_TIENDA','RECOGE_TIENDA_TRANSFERENCIA','RECOGE_TIENDA_CREDITO'], 'departamentos_habilitados' => [$depto->id], 'almacenes_habilitados' => [$tienda->id], 'usuarios_piloto' => array_map(fn($u) => $u->id, $usuarios)];
ConfiguracionSistema::updateOrCreate(['clave' => 'control_pedidos.preparacion_tienda'], ['valor' => json_encode($flag), 'tipo' => 'json', 'grupo' => 'ControlPedidos']);
app(App\Services\ControlPedidos\PrioridadAtencionPedidosConfig::class)->guardar(['mismo_dia','municipal','tienda','resguardo','nacional']);
$pedidos = [];
foreach (['consulta','transferencia','archivos','archivos-exceso','lectura-ajena'] as $caso) {
    $pedido = PedidoBma::firstOrCreate(['folio' => 'QA-PR34-'.strtoupper($caso)], [
        'folio_remision' => 'QA34-'.strtoupper($caso), 'fecha' => now()->toDateString(), 'vendedor_id' => $usuarios['ventas']->id, 'cliente_id' => $cliente->id, 'departamento_operacion_id' => $depto->id, 'origen_id' => $origen->id, 'almacen_id' => $cedis->id, 'sucursal_destino_id' => $sucursal->id,
        'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('BORRADOR')->id, 'total_mercancia' => 100, 'costo_envio' => 0, 'saldo_a_favor' => 0, 'costo_seguro' => 0, 'total_a_cobrar' => 100, 'aplica_seguro' => false, 'es_resguardo' => false, 'prioridad_md' => false,
        'solicitud_preparacion_json' => ['version' => 1, 'modalidad_entrega' => 'tienda', 'area_revision' => 'preparacion_tienda', 'almacen_revision_id' => $tienda->id],
    ]);
    $ruta = 'pedidos_bma/qa-pr34/'.$pedido->id.'/pedido.pdf';
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n4 0 obj<</Length 65>>stream\nBT /F1 18 Tf 40 740 Td (PEDIDO FICTICIO QA PR34) Tj ET\nendstream endobj\n5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    Storage::disk('public')->put($ruta, $pdf);
    $doc = $pedido->documentos()->firstOrCreate(['tipo' => 'pdf_pedido'], ['ruta_archivo' => $ruta, 'nombre_original' => 'QA-PR34-pedido.pdf', 'mime_type' => 'application/pdf', 'tamano_bytes' => strlen($pdf), 'orden' => 1, 'activo' => true]);
    $pedidos[$caso] = ['id' => $pedido->id, 'folio' => $pedido->folio, 'documento_id' => $doc->id, 'revision_esperada' => $pedido->revision_registro, 'base_captura' => App\Support\ControlPedidos\RevisionCapturaPedido::base($pedido)];
}
$salida = ['usuarios' => array_map(fn($u) => ['id' => $u->id,'email' => $u->email], $usuarios), 'pedidos' => $pedidos, 'banco_id' => $banco->id, 'tienda_id' => $tienda->id, 'cedis_id' => $cedis->id, 'sucursal_id' => $sucursal->id];
file_put_contents($archivo, json_encode($salida, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($salida, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;

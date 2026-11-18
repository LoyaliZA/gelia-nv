<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'codex_m01_audit_20261114') {
    throw new RuntimeException('Base exclusiva de auditoría requerida.');
}
$archivo = '/tmp/m01-sinc-fixtures.json';
$fixture = json_decode(file_get_contents($archivo), true, flags: JSON_THROW_ON_ERROR);
foreach (['tienda', 'municipal'] as $tipo) {
    if (in_array('--renovar-'.$tipo, $argv, true) && isset($fixture['regresion_pedidos'][$tipo])) {
        $anterior = App\Models\ControlPedidos\PedidoBma::findOrFail($fixture['regresion_pedidos'][$tipo]['id']);
        if ($tipo === 'tienda' && ! empty($anterior->solicitud_preparacion_json['anexo_sin_remision'][0]['respondida_at'])) {
            $fixture['tienda_verificada'] = $fixture['regresion_pedidos'][$tipo];
        }
        $fixture['regresion_historial'][] = ['tipo' => $tipo, ...$fixture['regresion_pedidos'][$tipo]];
        unset($fixture['regresion_pedidos'][$tipo]);
    }
}
$base = App\Models\ControlPedidos\PedidoBma::findOrFail($fixture['pedido_id']);
$ventas = App\Models\User::findOrFail($fixture['usuario_id']);
if ($base->vendedor_id !== $ventas->id || $ventas->name !== 'Ventas ficticias sincronización') {
    throw new RuntimeException('Se requieren las identidades sintéticas de este recorrido.');
}
$documento = $base->documentos()->where('nombre_original', 'wizerp-sinc-ficticio.png')->firstOrFail();
$sucursal = $ventas->sucursalesOperables()->firstOrFail();
$estatus = App\Models\ControlPedidos\CatalogoEstatusPedido::porFase('PESAJE_PENDIENTE');
$nacional = App\Models\ControlPedidos\CatalogoPaqueteriaPedido::firstOrCreate(['nombre' => 'Paquetería nacional ficticia '.$ventas->id], ['categoria' => 'comercial', 'sin_logistica' => false, 'requiere_peso' => true, 'requiere_caja' => true, 'activo' => true]);
foreach (['tienda', 'nacional', 'municipal'] as $modalidad) {
    if (isset($fixture['regresion_pedidos'][$modalidad])) continue;
    $origen = $modalidad === 'municipal' ? null : App\Models\ControlPedidos\CatalogoOrigenPedido::firstOrCreate(['nombre' => $modalidad === 'tienda' ? 'TIENDA' : 'Envío nacional ficticio'], ['requiere_logistica' => $modalidad === 'nacional', 'activo' => true]);
    $folio = 'WIZ-REGRESION-'.$ventas->id.'-'.strtoupper($modalidad);
    if ($modalidad !== 'nacional' && ! empty($fixture['regresion_historial'])) $folio .= '-R'.count($fixture['regresion_historial']);
    $json = ['modalidad_entrega' => $modalidad, 'traslado_interno_pdv' => false];
    if ($modalidad === 'tienda') $json += ['area_revision' => 'control_pedidos', 'codigo_modalidad' => 'RECOGE_TIENDA', 'caso_tienda' => 'consulta_cedis'];
    $pedido = App\Models\ControlPedidos\PedidoBma::create([
        'folio' => 'QA-'.$folio, 'folio_remision' => $folio, 'fecha' => now()->toDateString(),
        'cliente_id' => $base->cliente_id, 'vendedor_id' => $ventas->id, 'departamento_operacion_id' => $ventas->departamento_id,
        'catalogo_estatus_pedido_id' => $estatus->id, 'origen_id' => $origen?->id, 'almacen_id' => $modalidad === 'municipal' ? null : $fixture['almacen_cedis_id'],
        'sucursal_destino_id' => $modalidad === 'municipal' ? null : $sucursal->id, 'catalogo_paqueteria_id' => $modalidad === 'nacional' ? $nacional->id : null,
        'estatus_envio' => 'pendiente_pesaje', 'pesaje_solicitado_at' => now(), 'total_mercancia' => 100, 'costo_envio' => 0,
        'solicitud_preparacion_json' => $json,
    ]);
    $imagen = imagecreatefromstring(Illuminate\Support\Facades\Storage::disk('public')->get($documento->ruta_archivo));
    imagefilledrectangle($imagen, 30, 110, 790, 140, imagecolorallocate($imagen, 255, 255, 255));
    imagestring($imagen, 5, 40, 120, $folio, imagecolorallocate($imagen, 0, 0, 0));
    ob_start(); imagepng($imagen); $png = ob_get_clean();
    $ruta = 'qa/m01-sincronizacion/'.$pedido->id.'/wizerp.png';
    Illuminate\Support\Facades\Storage::disk('public')->put($ruta, $png);
    $pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => $ruta, 'nombre_original' => 'wizerp-sinc-ficticio.png', 'mime_type' => 'image/png', 'tamano_bytes' => strlen($png), 'orden' => 0, 'activo' => true]);
    if ($modalidad === 'municipal') {
        app(App\Services\ControlPedidos\Municipal\RegistrarSolicitudMunicipalService::class)->ejecutar($pedido, $ventas, [
            'catalogo_paqueteria_id' => $fixture['transporte_id'], 'consignatario_id' => $base->solicitud_preparacion_json['expediente']['consignatario_id'],
            'almacen_id' => $fixture['almacen_cedis_id'], 'leyenda_terminal' => 'PAGADO', 'costo_transporte' => 'diferido',
        ]);
        app(App\Services\ControlPedidos\Municipal\EnviarConsultaMunicipalService::class)->ejecutar($pedido->fresh(), $ventas, $fixture['almacen_cedis_id']);
    }
    $fixture['regresion_pedidos'][$modalidad] = ['id' => $pedido->id, 'folio' => $folio];
}
file_put_contents($archivo, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($fixture['regresion_pedidos'], JSON_PRETTY_PRINT).PHP_EOL;

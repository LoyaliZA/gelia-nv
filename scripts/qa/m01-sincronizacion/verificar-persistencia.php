<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'codex_m01_audit_20261114') {
    throw new RuntimeException('Base exclusiva de auditoría requerida.');
}
$f = json_decode(file_get_contents('/tmp/m01-sinc-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
$exigir = static function (bool $ok, string $mensaje): void { if (! $ok) throw new RuntimeException($mensaje); };
$pedido = App\Models\ControlPedidos\PedidoBma::with('documentos', 'revisionesProducto')->findOrFail($f['pedido_id']);
$exigir($pedido->vendedor_id === $f['usuario_id'], 'El pedido debe pertenecer al fixture.');
$revision = $pedido->solicitud_preparacion_json['revision_disponibilidad'];
$exigir(count($revision['historial_respuestas'] ?? []) >= 2, 'Falta historial de ciclos.');
$ids = $pedido->documentos->pluck('id')->all();
foreach ([...($revision['historial_respuestas'] ?? []), $revision] as $respuesta) {
    foreach ($respuesta['evidencia_actual_ids'] ?? [] as $id) $exigir(in_array($id, $ids, true), 'Una evidencia anterior se perdió.');
}
$exigir($pedido->documentos->where('tipo', 'pdf_pedido')->count() === 1, 'Se debe conservar el Wizerp original.');
$exigir($pedido->revisionesProducto->contains('resolucion', 'retirar'), 'El retiro del faltante no quedó registrado.');
$exigir($pedido->pago_validado_at === null && $pedido->numero_remision === null && $pedido->empacado_at === null, 'La consulta no debe crear hechos financieros o físicos.');
$exigir($pedido->tareasPreparacion()->count() === 0, 'Municipal no debe crear tarea física.');
$exigir($pedido->costo_envio === null && $pedido->solicitud_preparacion_json['finanzas_municipales']['costo_transporte'] === 'diferido', 'El costo diferido fue alterado.');
$esperados = $f['pedidos_antes_alta_vacia'] + count($f['regresion_pedidos']) + count($f['regresion_historial'] ?? []);
$exigir(App\Models\ControlPedidos\PedidoBma::count() === $esperados, 'Se crearon pedidos fuera de los fixtures explícitos.');
$tienda = App\Models\ControlPedidos\PedidoBma::with('documentos')->findOrFail(($f['tienda_verificada'] ?? $f['regresion_pedidos']['tienda'])['id']);
$anexos = $tienda->solicitud_preparacion_json['anexo_sin_remision'] ?? [];
$exigir(count($anexos) === 1 && ! empty($anexos[0]['respondida_at']), 'Falta el anexo Tienda respondido una sola vez.');
$exigir($tienda->documentos->where('tipo', 'pdf_pedido')->count() === 1, 'El anexo reemplazó el Wizerp.');
$exigir($tienda->pago_validado_at === null && $tienda->numero_remision === null && $tienda->empacado_at === null, 'El anexo creó hechos operativos.');
$nacional = App\Models\ControlPedidos\PedidoBma::findOrFail($f['regresion_pedidos']['nacional']['id']);
$exigir($nacional->pesaje_respondido_at === null, 'El navegador no debía registrar un pesaje.');
echo json_encode([
    'resultado' => 'aprobado', 'pedido_municipal' => $pedido->id,
    'documentos_conservados' => count($ids), 'historial_respuestas' => count($revision['historial_respuestas']),
    'pedidos_esperados' => $esperados, 'pedidos_en_base' => App\Models\ControlPedidos\PedidoBma::count(),
    'tienda' => $tienda->id, 'anexos_respondidos' => count($anexos), 'nacional_sin_pesaje_registrado' => $nacional->id,
    'sin_pago_remision_empaque_o_tarea_municipal' => true,
], JSON_PRETTY_PRINT).PHP_EOL;

<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'codex_m01_audit_20261114') {
    throw new RuntimeException('Se requiere la base exclusiva de auditoría.');
}
$f = json_decode(file_get_contents('/tmp/m01-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
$p = App\Models\ControlPedidos\PedidoBma::where('vendedor_id', $f['usuario_id'])->where('folio_remision', 'WIZ-CONTINUIDAD-'.$f['usuario_id'])->firstOrFail();
$j = $p->solicitud_preparacion_json;
$documentoId = $j['expediente']['documento_id'];
$identificacion = App\Models\ClienteDocumento::findOrFail($documentoId);
$checks = [
    'cliente' => $p->cliente_id === $f['cliente_id'],
    'departamento' => $p->departamento_operacion_id === $p->vendedor->departamento_id,
    'modalidad' => $j['modalidad_entrega'] === 'municipal',
    'terminal' => $p->catalogo_paqueteria_id === $f['transporte_id'],
    'almacen_guardado' => $j['almacen_revision_id'] === $f['almacen_cedis_id'] && $j['consulta_municipal']['almacen_id'] === $f['almacen_cedis_id'],
    'expediente_versionado' => $identificacion->version === 2 && $j['expediente']['version_datos_id'] !== null,
    'soporte_comercial' => $p->documentos()->where('tipo', 'pdf_pedido')->where('activo', true)->exists(),
    'consulta_cerrada' => $p->consulta_cerrada_at !== null && $j['consulta_municipal']['estado'] === 'cerrada',
    'revision_final_sin_excepciones' => $j['revision_disponibilidad']['estado'] === 'sin_excepciones',
    'transporte_diferido_intacto' => $p->costo_envio === null && $j['finanzas_municipales']['costo_transporte'] === 'diferido' && $p->estatus_envio === 'pendiente_regularizacion',
    'sin_pago_remision_empaque_entrega' => !$p->pago_validado_at && !$p->numero_remision && !$p->empacado_at && !$p->entregado_at,
    'sin_tarea_tienda' => $p->tareasPreparacion()->count() === 0,
    'sin_municipio_inventado' => !array_key_exists('municipio_destino', $j),
];
echo json_encode(['pedido_id' => $p->id, 'folio_wizerp' => $p->folio_remision, 'comprobaciones' => $checks], JSON_PRETTY_PRINT).PHP_EOL;
if (in_array(false, $checks, true)) throw new RuntimeException('Persistencia incompleta.');

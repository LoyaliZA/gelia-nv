# Fix wave Multienvío nacional Fase 1

Status: DONE (sin commit; sqlite solamente)

## Archivos
- app/Services/ControlPedidos/Nacional/Multienvio/DetectarHechosPreviosMultienvio.php (I-1)
- app/Services/ControlPedidos/Nacional/Multienvio/EvaluarMultienvioNacional.php (I-2: miembro, activo, regularización)
- app/Services/ControlPedidos/Nacional/Multienvio/RegistrarDecisionMultienvio.php (I-3 refrescarConfiguracion)
- app/Services/ControlPedidos/Nacional/Multienvio/ConfirmarMultienvio.php (I-4 snapshot al_confirmar + historial; M-2)
- app/Http/Controllers/ControlPedidos/MultienvioNacionalController.php (M-4 revision required)
- docs/ai/modulos/REGISTRO_PEDIDOS.md (M-5, M-10)
- Tests: HechosPrevios, Evaluar, Decision, Confirmar, Http (MultienvioNacional*Test)

## Evidencia (RED -> GREEN)
- I-1: RED "remisión como PDF vigente sin número": esperaba regularizacion_pendiente, obtuvo activo. Casos añadidos que ya pasaban (guía PDF, guia_subida_at, rastreo de caja, clave JSON, aplicación SAF con `PRAGMA defer_foreign_keys` y fila sin crédito real). GREEN: HechosPrevios 20 passed.
- I-2: RED 3 fallos (miembro elegible; Decision creaba grupo; motivo activo = "dos cajas"). GREEN. Motivos: miembro -> 'El pedido ya es un envío de un multienvío.'; origen activo -> 'El multienvío ya está activo.'; regularización -> 'El multienvío está pendiente de regularización.' (antes de la regla de cajas; terminal sigue cortocircuitando en Decision/Confirmar).
- I-3: RED 3 fallos (revisión no se refrescaba; revisionEsperada vieja no lanzaba). GREEN: refrescar revisión/decidido_*, poda de borrador por uuid activo; revisionEsperada vieja -> revisionObsoleta; si ya no es elegible -> noElegible. Si la revisión no cambió, se devuelve el grupo como antes.
- I-4: la prueba se escribió junto con el fix (primer run ya verde); se verificó RED revirtiendo solo la línea del snapshot (falla en `al_confirmar`) y se restauró. snapshot_origen_json['al_confirmar'] = {financiero (SnapshotHistorialPedidoBma::financiero), cotizacion_nacional, cajas[uuid]{costo_envio,costo_seguro,costo_adicional}} y fila de historial del origen con financiero_previo, cotizacion_nacional_previa, cajas_previas.
- M-2: RED (listener PedidoBma::created + DB::afterCommit que lanza; copias borradas con grupo activo). GREEN: eliminarCopias solo si el grupo no quedó activo/regularizacion_pendiente (re-lectura fresca).
- M-4: RED (decision sin revision -> 200). GREEN; tests HTTP existentes actualizados para enviar revision.
- M-5/M-10: REGISTRO_PEDIDOS.md actualizado (hechos previos exactos, regularizacion_pendiente sin salida, etc.).

## Comandos / resultados finales
- `--filter='MultienvioNacional|MobileEspejoRutasTest'`: 113 passed (796 assertions).
- Regresión (Ola3Cierre, LogisticaCobro, VistaPreviaCotizacion, PesajeNacionalRespuesta20, AnexoExtraordinario, Fase22, ActualizarGuias, RemisionAuditoria): 100 passed (635 assertions).
- Sync: rsync de git diff + untracked a /home/ti-admin/gelianv; ejecución con docker exec en gelianv-laravel.test-1.

## Notas
- pago_capturado y contenido (M-1) intactos. No se tocó la guía operativa V3.1 §15 (no solicitado; el usuario decide pago_capturado aparte).
- Aviso inocuo: PHPUnit no puede escribir .phpunit.result.cache en el contenedor.

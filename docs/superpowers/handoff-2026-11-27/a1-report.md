# Task A1 — Datos de grupo y de cajas para la UI (backend)

Status: DONE (sin commit; MySQL no usado)

## Archivos
- Nuevo `app/Support/ControlPedidos/Nacional/VisibilidadMiembrosMultienvio.php`: única regla de visibilidad de miembros (extraída de ProyeccionMultienvio; memo por vendedor|estatus|estatus_envio solo si modalidad nacional, resto pedido por pedido).
- Nuevo `app/Support/ControlPedidos/Nacional/ResumenGrupoMultienvio.php`: `paraPagina(Collection, User)` y `de(PedidoBma, User)`.
- `ProyeccionMultienvio.php`: usa la visibilidad compartida; añade `cajas_origen`, `cajas_resumen`, y por envío `pendiente` y `saf_aplicado` (pagado/estado_pago ya existían).
- `LecturaConsultaNacional.php`: clave `multienvio` (usuario = request()->user() ?? auth()->user()).
- `ListarPedidosBmaService.php`: `ejecutar` (paginado: sobre `$paginador->getCollection()` antes de `through`; sin paginar) y `presentar` llaman al resumen.
- Test nuevo `tests/Feature/ControlPedidos/MultienvioNacionalResumenTest.php` (12 pruebas).

## TDD
- RED: `--filter=MultienvioNacionalResumenTest` -> 13 fallos (clase/clave inexistentes) antes de implementar.
- GREEN: 12 passed (169 assertions) (se retiró una prueba de conteo sobre la ruta /listado: ese listado ya es N+1 por pedido por `anexarFlagsVista`, preexistente; el conteo constante se prueba sobre el resumen: 7 consultas con 2 o 6 grupos).
- Regresión (`MultienvioNacional|MobileEspejoRutasTest|NacionalOla3CierreTest|NacionalLogisticaCobroTest|NacionalVistaPreviaCotizacionTest|PesajeNacionalRespuesta20Test|NacionalAnexoExtraordinarioTest|BandejasOperativasTest`): 189 passed, 2 skipped, 0 fallos.
- Comandos: rsync a /home/ti-admin/gelianv + `docker exec -u sail -e APP_CONFIG_CACHE=... gelianv-laravel.test-1 php artisan test --filter=...`.

## Decisiones
- `n_total` = miembros VISIBLES para el usuario; con ocultos solo se expone `parcial=true` (no se revela cuántos hay). El front debe mostrar "Envío n" sin "de N" cuando `parcial`.
- `hermanos` incluye a todos los miembros visibles (también el propio); un pedido sin grupo no recibe atributo. El origen en `configurando/activo/regularizacion_pendiente` recibe resumen (hermanos vacío mientras configura).
- Miembros no nacionales no se resumen (columnas ligeras no bastan para evaluar su visibilidad; por construcción todos son nacionales).
- `cajas_origen`: null salvo dueña (vendedor_id) + estado `configurando` + se lee el propio origen; solo cajas activas del origen (las movidas ya no pertenecen); evidencias `evidencia_condicion` activas por `pedido_bma_caja_id` o `relacion_tipo=envio_caja/relacion_id`, URL por `PedidoBmaDocumento::url` (ruta `documentos.show`), solo si `puedeConsultarDocumentos`. La evidencia general (revision_general) no se incluye.
- `cajas_resumen` {n,tipos,peso_total_kg}: solo dueña, `evaluacion.elegible` y grupo inexistente o `pendiente_decision`; si no, null.
- `saf_aplicado`: suma de `saf_pedido_aplicaciones` con estado `aplicado` (reservado/liberado no cuentan), lectura sola, no altera `pendiente` (= max(total_a_cobrar - pagado, 0)).
- Aviso: `multienvio` es también una relación de PedidoBma; para pedidos sin grupo `getAttribute('multienvio')` dispararía carga perezosa de la relación (null). El resumen se lee de `getAttributes()`.

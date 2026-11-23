# Anexo extraordinario (Nacional) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que una vendedora pueda pedir un anexo de piezas a un pedido nacional ya pagado o remisionado mediante un modal corto que crea un minipedido hijo, con mini consulta a CEDIS, pago/remisión propios y guía y caja aparte.

**Architecture:** Se reutiliza `RegistrarComplementoNacional`, pero el hijo nace como pedido nacional **independiente** (`pedido_principal_id = null`, vinculado solo por JSON) para que CEDIS lo empaque y guíe aparte, sin pago prevalidado y con sus piezas en `piezas_nacionales` para que corran la consulta, el cierre, la continuación y el pago ya existentes. Una ruta nueva crea el hijo y abre su mini consulta; un modal nuevo y una etiqueta «Anexo» completan la interfaz.

**Tech Stack:** Laravel 12 (PHP 8.4) + PHPUnit (SQLite en memoria) y React 18 + Inertia + Vitest, todo vía `./vendor/bin/sail`.

**Spec:** `docs/superpowers/specs/2026-11-23-anexo-extraordinario-nacional-design.md` (aprobado 23-nov-2026). Contrato de negocio: guía V3.1 §6 N-F04, §7.1.

## Global Constraints

- Todo comando por `./vendor/bin/sail`; PHPUnit con `-e APP_CONFIG_CACHE=/tmp/gelia-test-config-anexo.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes-anexo.php laravel.test php artisan test --filter=...`. Vitest con `./vendor/bin/sail npm test -- <ruta>`.
- **No hacer commit ni push** (AGENTS.md); dejar los cambios en el árbol de trabajo. Esto sustituye los pasos de commit de la plantilla.
- Compilar con `./vendor/bin/sail npm run build` solo en la Tarea 6; sin `public/hot`.
- Permiso para solicitar: `control_pedidos.crear` (sin permiso nuevo). Todo permiso nuevo iría en migración y `permisos_actuales.php`.
- Interfaz: `GUIA_DISENO_GELIA.md`, `.ux-profile.md`, 375 px y 1280 px; reutilizar tokens y `pedidosBmaStyles.js`; verbos en botones; etiqueta visible en cada campo; nunca solo color.
- Ventas **nunca** valida su propio pago: el hijo nace con `pago_validado_at = null`.
- Archivos: PDF, JPG, PNG, WEBP; reglas de tamaño existentes.
- Nacional solo desde CEDIS; no tocar Tienda ni Municipal.

## Review Focus

- Doble clic o reintento con el mismo `solicitud_id` → un solo hijo, sin duplicar piezas ni documentos (Tarea 1 y 2).
- Principal sin pago ni remisión, cancelado, o no nacional de captura compartida → rechazo claro con 422 (Tarea 2).
- Pieza sin SKU y sin archivo, lista de piezas vacía, importe ≤ 0 o falta del folio Wizerp → rechazo con mensaje por campo (Tarea 1 y 3).
- Principal ya enviado: el anexo se crea como envío nuevo y la caja recolectada del principal queda intacta (Tarea 1).
- El anexo no frena ni invalida la guía o la salida del principal (Tarea 5).
- Modal usable a 375 px con teclado y sin desbordar (Tarea 3 y 6).

---

### Task 1: El hijo nace independiente, sin pago prevalidado y con sus piezas vigentes

**Files:**
- Modify: `app/Services/ControlPedidos/Nacional/RegistrarComplementoNacional.php:34-125`
- Test: `tests/Feature/ControlPedidos/NacionalAnexoExtraordinarioTest.php` (nuevo)

**Interfaces:**
- Consumes: `GenerarFolioPedidoBmaService::ejecutarComplemento(PedidoBma $principal): string`; `RevisionCapturaPedido::comprobar`; `CapturaNacional::piezasVigentes`.
- Produces: `RegistrarComplementoNacional::ejecutar(PedidoBma $pedido, User $usuario, array $datos, array $archivos): PedidoBma` con la misma firma. `$datos` exige además `folio_wizerp` (string, ≤ 60) y acepta `nota` (string, ≤ 500) y `total_mercancia_nueva` (> 0). Devuelve el **hijo** con: `pedido_principal_id = null`, `folio` = `ejecutarComplemento($pedido)`, `folio_remision = folio_wizerp`, `pago_validado_at = null`, `pago_validado_por_id = null`, `cliente_direccion_id` copiado del principal, `solicitud_preparacion_json.complemento_nacional = {solicitud_id, pedido_origen_id, pedido_origen_folio, antes_de_salida, nota, piezas}` y `solicitud_preparacion_json.piezas_nacionales = [{id, sku, descripcion, cantidad, documento_ids}]` (mismo `id` UUID que la revisión). El archivo general se guarda como `PedidoBmaDocumento::TIPO_PDF_PEDIDO`.

- [ ] **Step 1: Escribir las pruebas rojas** en `NacionalAnexoExtraordinarioTest` (reutilizar el armado de pedidos de `NacionalLogisticaCobroTest::pedido()/caja()`):
  - `test_el_hijo_nace_independiente_sin_pago_y_con_su_folio_wizerp`: principal con `numero_remision='REM-1'`, `pago_validado_at=now()`; tras `ejecutar(...)` con 2 piezas y `folio_wizerp='WIZ-777'`, aserciones: `hijo->pedido_principal_id === null`; `hijo->pago_validado_at === null`; `hijo->folio_remision === 'WIZ-777'`; `hijo->folio` termina en `-C1`; `piezas_nacionales` tiene 2 elementos y `CapturaNacional::piezasVigentes($hijo)` los devuelve; el principal conserva `numero_remision`, `pago_validado_at` y sus cajas.
  - `test_el_reintento_con_la_misma_solicitud_no_duplica`: dos llamadas con el mismo `solicitud_id` → `PedidoBma::count()` aumenta en 1 y las revisiones del hijo son 2.
  - `test_pieza_sin_sku_ni_archivo_y_sin_folio_wizerp_se_rechazan`: `InvalidArgumentException` con «Adjunte la captura…» y con «folio» respectivamente.
  - `test_si_el_principal_ya_salio_el_anexo_es_envio_nuevo_y_no_toca_la_caja`: principal en `FASE_ENVIADO` con caja recolectada; el hijo se crea, `antes_de_salida === false` y la caja del principal sigue `recolectada` con su `numero_rastreo`.
- [ ] **Step 2: Ejecutar y ver fallar.** Run: `... --filter=NacionalAnexoExtraordinarioTest`. Expected: FAIL (el hijo tiene `pago_validado_at` y `pedido_principal_id`, no existe `folio_wizerp`).
- [ ] **Step 3: Implementar en `RegistrarComplementoNacional::ejecutar`:** exigir `folio_wizerp`; crear siempre con `pedido_principal_id => null` y `folio => $this->folios->ejecutarComplemento($pedido)`; quitar `pago_validado_at`/`pago_validado_por_id` y el `'WIZ-'.uniqid()`; copiar `cliente_direccion_id`; guardar el archivo general como `TIPO_PDF_PEDIDO` (cambiar el tipo en `persistir()` cuando `$revision === null`); escribir `piezas_nacionales` con el `id` UUID de cada revisión y `pedido_origen_folio` en `complemento_nacional`. Mantener la lista `complementos_nacionales` y el `increment('revision_registro')` del principal.
- [ ] **Step 4: Ejecutar y ver pasar.** Expected: PASS (4 pruebas).

### Task 2: Ruta que crea el anexo y abre su mini consulta

**Files:**
- Modify: `app/Http/Controllers/ControlPedidos/PedidoBmaController.php` (método nuevo junto a `ajusteNacional`, línea ~931), `routes/web/modules/control-pedidos/core.php:84-93`, `app/Services/ControlPedidos/Nacional/RegistrarAjusteConsultaNacional.php:43-51`
- Create: `app/Http/Requests/ControlPedidos/AnexoExtraordinarioNacionalRequest.php`
- Test: `tests/Feature/ControlPedidos/NacionalAnexoExtraordinarioTest.php`

**Interfaces:**
- Consumes: `RegistrarComplementoNacional::ejecutar` (Tarea 1); `SolicitarPesajePedidoBmaService::ejecutar(PedidoBma $pedido, int $usuarioId, ?array $baseCaptura = null): PedidoBma`.
- Produces: `POST /control-pedidos/{pedidoBma}/anexo-extraordinario` (nombre `control_pedidos.anexo_extraordinario`), `PedidoBmaController::anexoExtraordinario(AnexoExtraordinarioNacionalRequest, PedidoBma $pedidoBma, ListarPedidosBmaService, RegistrarComplementoNacional, SolicitarPesajePedidoBmaService): JsonResponse`. Respuesta 200: `{ok: true, anexo: {id, folio, folio_principal, estatus_fase}}`; 422 `{message}` si el principal no es nacional de captura compartida, no tiene pago ni remisión, está cancelado, o faltan campos. Campos del request: `solicitud_id` (uuid), `revision_esperada`, `base_captura`, `folio_wizerp`, `total_mercancia_nueva`, `nota` (opcional), `documento` (archivo obligatorio), `piezas[]` con `sku`, `descripcion`, `cantidad`, `evidencia`.

- [ ] **Step 1: Pruebas rojas.** `test_http_crea_el_anexo_y_deja_su_consulta_pendiente` (sin `Notificar`: mockear como en `NacionalLogisticaCobroTest::setUp`): con permiso `control_pedidos.crear`, `POST` con archivo y 1 pieza SKU → 200, `anexo.estatus_fase === 'PESAJE_PENDIENTE'`, el hijo existe con `pago_validado_at` nulo; `test_http_rechaza_principal_sin_pago_ni_remision` → 422; `test_http_sin_permiso_responde_403`; `test_el_ajuste_ordinario_con_remision_ya_no_crea_complemento` → 422 con «Anexo extraordinario» en el mensaje.
- [ ] **Step 2: Ver fallar.** Expected: FAIL (404 de ruta).
- [ ] **Step 3: Implementar** el request (reglas del Interfaces; `documento` `file|mimes:pdf,jpg,jpeg,png,webp`), el método del controlador (`Gate::authorize('control_pedidos.crear')`, `$listarService->asegurarAcceso`, crear con el servicio y luego `solicitarPesaje` sobre el hijo; si solicitar falla, devolver 422 con el mensaje y dejar el hijo en borrador), la ruta, y en `RegistrarAjusteConsultaNacional` sustituir la llamada a `RegistrarComplementoNacional` por `InvalidArgumentException('Con remisión o pago, use «Anexo extraordinario».')`.
- [ ] **Step 3b: Rechazar principal cancelado o sin pago/remisión** en el controlador antes de crear.
- [ ] **Step 4: Ver pasar.** Expected: PASS.

### Task 3: Modal «Anexo extraordinario»

**Files:**
- Create: `resources/js/Pages/ControlPedidos/Nacional/ModalAnexoExtraordinario.jsx`, `resources/js/Pages/ControlPedidos/Nacional/ModalAnexoExtraordinario.test.jsx`
- Modify: `resources/js/Pages/ControlPedidos/Nacional/FormularioRegistrarNacional.jsx:80,257-300,529-...` (reemplazar el panel «Agregar mercancía al pedido pagado»)

**Interfaces:**
- Consumes: `POST /control-pedidos/{id}/anexo-extraordinario` (Tarea 2); `uuidSolicitud()`.
- Produces: `ModalAnexoExtraordinario({abierto, onClose, pedido, onCreado})` donde `pedido` aporta `id`, `folio_remision|folio`, `revision_registro`, `base_captura`, `solicitud_preparacion`; `onCreado({id, folio})` se invoca tras 200. Campos con etiqueta visible: «Folio Wizerp del anexo», «Importe de mercancía del anexo», «Documento del anexo (PDF o captura)», piezas (SKU, descripción, cantidad, archivo por pieza) y «Nota para la auxiliar». Botones «Cancelar» y «Enviar a consulta».

- [ ] **Step 1: Pruebas rojas (Vitest, axios simulado):** (a) muestra los datos heredados y no pide cliente ni paquetería; (b) sin folio, sin documento o con una pieza sin SKU ni archivo no hace `POST` y muestra qué falta; (c) con datos válidos hace `POST` multipart a `/control-pedidos/9/anexo-extraordinario` con `solicitud_id`, `folio_wizerp`, `piezas[0][sku]` y llama `onCreado`; (d) dos clics seguidos envían una sola petición; (e) un 422 muestra `message` sin cerrar el modal.
- [ ] **Step 2: Ver fallar.** Expected: FAIL (módulo inexistente).
- [ ] **Step 3: Implementar** el modal con `createPortal`, `role="dialog"` y `aria-labelledby`, mismo armazón que `ModalAgregarDireccionNacional.jsx`; en `FormularioRegistrarNacional` reemplazar el panel viejo por un botón «Anexo extraordinario» visible cuando `conComplemento && !cambio` (ya sin la condición `!yaSalio`: tras la salida es envío nuevo) y retirar el estado `importeComplemento`/`soporteComplemento` y su envío a `ajuste-nacional`.
- [ ] **Step 4: Ver pasar.** Run: `./vendor/bin/sail npm test -- resources/js/Pages/ControlPedidos/Nacional`. Expected: PASS y sin nuevas rojas respecto a la línea base (2 de `modalElegibilidadCedisPdv` ya conocidas en `Partials`).

### Task 4: Etiqueta «Anexo» en todas las bandejas

**Files:**
- Create: `resources/js/Pages/ControlPedidos/Partials/EtiquetaAnexo.jsx`, `EtiquetaAnexo.test.jsx`
- Modify: `Partials/TablaPedidos.jsx` (fila de Ventas), `Cedis/Partials/TarjetasCedis.jsx` y `ModalDetalleCedis.jsx`, bandeja del auxiliar (`Auditar/` — localizar la fila y el encabezado del modal de revisión), `Delegado/Partials/TablaDelegado.jsx`

**Interfaces:**
- Produces: `datosAnexo(pedido): {principalId, folioPrincipal} | null` (lee `pedido.solicitud_preparacion?.complemento_nacional ?? pedido.solicitud_preparacion_json?.complemento_nacional`) y `<EtiquetaAnexo pedido={...} />` que muestra «Anexo» con icono y «de {folioPrincipal}» con borde y texto propios, `null` si no aplica.

- [ ] **Step 1: Pruebas rojas:** con `complemento_nacional: {pedido_origen_id: 5, pedido_origen_folio: 'PBMA-58'}` renderiza «Anexo» y «de PBMA-58»; sin la clave renderiza nada; acepta `solicitud_preparacion_json`.
- [ ] **Step 2: Ver fallar.** Expected: FAIL.
- [ ] **Step 3: Implementar** el componente reutilizando tokens (`theme-text-info`, bordes del sistema) y colocarlo junto al folio en las cinco superficies.
- [ ] **Step 4: Ver pasar.** Expected: PASS.

### Task 5: Contrato actualizado en pruebas existentes y barreras del principal

**Files:**
- Modify: `tests/Feature/ControlPedidos/NacionalOla3Test.php:73-170`, `tests/Feature/ControlPedidos/NacionalOla3CierreTest.php:71-190`
- Test: `tests/Feature/ControlPedidos/NacionalAnexoExtraordinarioTest.php`

**Interfaces:**
- Consumes: Tareas 1 y 2.

- [ ] **Step 1: Pruebas rojas nuevas:** `test_el_anexo_pendiente_no_frena_guia_ni_salida_del_principal` (principal empacado con guía vigente + hijo en consulta → `MarcarEnviadoPedidoBmaService::ejecutar` del principal concluye) y `test_el_hijo_aparece_como_pedido_propio_en_el_listado_de_cedis` (`ListarPedidosCedisService` lo devuelve, porque `pedido_principal_id` es nulo).
- [ ] **Step 2: Ver cuáles pruebas existentes fallan** tras Tareas 1-2 y actualizarlas al contrato nuevo: hijo sin pago prevalidado, `pedido_principal_id` nulo y creación por `anexoExtraordinario` en lugar de `ajuste-nacional`; no cambiar aserciones de custodia ni recepción. Anotar cada cambio en el informe de auditoría (V3.1 prevalece sobre un test verde).
- [ ] **Step 3: Verificar que `complemento_nacional` y `piezas_nacionales` sobreviven a `FusionarSolicitudPreparacionPedido`** (lista de llaves en `:381`); si no, añadirlas con una prueba que guarde el borrador del hijo y relea el JSON.
- [ ] **Step 4: Ejecutar** `--filter='NacionalOla3|NacionalAnexoExtraordinario|NacionalLogisticaCobro|NacionalAltaConsulta'`. Expected: todo verde.

### Task 6: Publicar, verificar en navegador y documentar

**Files:**
- Modify: `docs/ai/modulos/REGISTRO_PEDIDOS.md`, `docs/superpowers/specs/2026-11-23-nacional-auditoria-informe.md`

- [ ] **Step 1:** `./vendor/bin/sail npm run build`. Expected: «Compilación publicada» y sin `public/hot`.
- [ ] **Step 2: Recorrido en el entorno de pruebas** (datos `AUD-NAC-*`): en `AUD-NAC-001` (pagado y enviado) abrir «Anexo extraordinario» a 1280 px y a 375 px (iframe), crear el anexo con una pieza SKU y un documento; ver la etiqueta «Anexo de …» en Ventas y en CEDIS; responder la mini consulta, cerrar, cotizar con la dirección heredada, pagar y verificar como Auxiliar; ver que CEDIS lo muestra como tarjeta propia y que el principal no cambia. Registrar `PASS/FAIL` por relevo y capturas.
- [ ] **Step 3:** Documentar el flujo en `REGISTRO_PEDIDOS.md` (regla de mantenimiento de contexto) y marcar el resultado en el informe.
- [ ] **Step 4:** Reportar al usuario lo no verificado (otros roles, teléfono real) sin hacer commit.

---

## Autorevisión

- **Cobertura del spec:** modal corto con datos heredados (T3), hijo independiente con mini consulta, pago y remisión propios (T1-T2), guía y caja aparte y principal intacto (T1, T5), etiqueta distinta en bandejas (T4), tras salida = envío nuevo (T1, T3), sin validación automática de pago (T1), retiro fuera de alcance. Sin brechas.
- **Consistencia de tipos:** `RegistrarComplementoNacional::ejecutar` conserva firma; `folio_wizerp` y `complemento_nacional.pedido_origen_folio` se definen en T1 y se consumen en T2-T4 con el mismo nombre.
- **Proporción:** seis tareas; las firmas y pruebas fijan lo que el constructor no puede decidir solo y se deja a su criterio el armado visual interno del modal.

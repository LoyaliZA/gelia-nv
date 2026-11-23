# Auditoría de Envíos Nacionales 2.0 — Plan de ejecución

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Producir un informe priorizado de hallazgos sobre Envíos Nacionales (registro, pesaje, paqueterías con logística, endpoints, regresiones, interfaz) contrastado con la guía operativa V3.1, sin modificar código de producto.

**Architecture:** Tres pasadas (línea base ejecutable → trazado por relevos → transversal con navegador) que alimentan un único informe Markdown con tabla de hallazgos. Cada tarea agrega filas con evidencia; nada se corrige en esta fase.

**Tech Stack:** Laravel/PHPUnit y Vitest vía `./vendor/bin/sail`; Claude in Chrome contra `http://100.110.75.115:9080` (superadmin de prueba); `git` para diffs.

**Spec:** `docs/superpowers/specs/2026-11-23-nacional-auditoria-design.md` · Contrato de negocio: `gestion-pedidos-docs/GELIA_GESTION_PEDIDOS_GUIA_OPERATIVA_V3.1_2026-11-21.md`

## Global Constraints

- Solo lectura sobre código de producto: no editar `app/`, `resources/`, `routes/`, `database/`; no migrar, no `npm run build`, sin commit ni push (AGENTS.md).
- Todo comando por `./vendor/bin/sail`; PHPUnit con `-e APP_CONFIG_CACHE=/tmp/gelia-test-config-aud.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes-aud.php`.
- Pruebas dirigidas y proporcionales; no repetir suites por rutina.
- Navegador: sin pagos, guías ni mensajería reales; datos sintéticos con prefijo `AUD-NAC-`; sin acciones destructivas.
- Estados permitidos: `PASS`, `FAIL`, `BLOCKED`, `NOT_RUN`. Un test verde no es `PASS` de relevo (V3.1 §11.2). El superadmin no acredita separación de roles.
- Severidades: Crítica / Alta / Media / Baja (definidas en el spec).
- Cifras de los reportes de Cursor se tratan como afirmaciones a reverificar.

## Review Focus

- Pesaje de CEDIS en 375 px: controles inalcanzables o desbordamiento horizontal de `ModalResponderPesaje.jsx` → debe poder completarse con una mano (Tarea 4 y 7).
- Paquetería DHL/Estafeta/FedEx con catálogo `requiere_peso` desactivado → debe cotizar igual por caja, con reexpedición fuera de la base del seguro (Tarea 5).
- Doble envío del mismo formulario/`solicitud_id` → no duplica pedido, anexo, cobro ni pesaje (Tarea 3).
- Respuesta de pesaje con caja sin peso o peso obsoleto tras anexo → rechazo claro, sin cierre (Tarea 4).
- Endpoint nacional con pedido ya salido o histórico → 422/403 explicativo, sin cambiar estado (Tarea 6).

---

### Task 1: Esqueleto del informe y línea base de rutas

**Files:**
- Create: `docs/superpowers/specs/2026-11-23-nacional-auditoria-informe.md`
- Read: `routes/web/modules/control-pedidos/{core,cedis,delegado}.php`

**Interfaces:**
- Produces: formato de fila de hallazgo `| ID | Sev | Área | Descripción | Evidencia | Regla V3.1 | Lote |` con IDs `NAC-001…`; secciones `## Resumen`, `## Línea base`, `## Matriz de relevos`, `## Hallazgos`, `## Regresiones`, `## No verificado`, `## Lotes propuestos`. Las tareas 2–9 solo agregan filas/secciones.

- [ ] **Step 1: Crear el informe con las secciones vacías y la tabla de hallazgos con encabezado.**
- [ ] **Step 2: Listar rutas nacionales.** Run: `./vendor/bin/sail artisan route:list --path=control-pedidos --json` filtrando por `nacional|pesaje|paqueteria|remision-caja|custodia|cancelacion|repesaje|guia|salida`. Expected: tabla en `## Línea base` con método, URI, controlador@acción, middleware/permiso.
- [ ] **Step 3: Marcar rutas sin acción en controlador, sin permiso o sin consumidor en `resources/js`.** Run: `grep -rn "<nombre_ruta>" resources/js` por cada ruta. Expected: columna "consumidor UI" con archivo o "ninguno" (candidato a hallazgo de §11.2).

### Task 2: Línea base de pruebas (PHPUnit y Vitest)

**Files:**
- Modify: informe, sección `## Línea base`

**Interfaces:**
- Consumes: formato de fila de Task 1.
- Produces: lista de fallas con clasificación `preexistente | introducida por Cursor | sin clasificar`.

- [ ] **Step 1: PHPUnit nacional.** Run: `./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config-aud.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes-aud.php laravel.test php artisan test --filter='Nacional|MarcaNacionalCaptura|OrigenEnvioNacional|CalcularSeguroPedidoService|ControlPedidosPaqPendiente|CandadoCostoEnvio|SnapshotDireccionPedido|ControlPedidosActualizarGuias|ElegibilidadPesaje'`. Expected: conteos reales registrados; cada falla con mensaje.
- [ ] **Step 2: Vitest.** Run: `./vendor/bin/sail npm test -- resources/js/Pages/ControlPedidos/Nacional resources/js/Pages/ControlPedidos/Cedis`. Expected: conteos reales; fallas con archivo y mensaje.
- [ ] **Step 3: Clasificar cada falla.** Leer el código bajo prueba y `git log -p` de sus archivos; para las dos conocidas (`SnapshotDireccionPedidoTest::test_enviar_crea_snapshot_y_proyecta_domicilio`, `MunicipalOrdinarioM01Test::test_resolver_faltante_y_responder_actualizacion_permite_cierre_sin_pisar_flete`) decidir si el cambio de regla fue de Cursor. Expected: una fila de hallazgo por falla no explicada, o nota "preexistente" con evidencia.

### Task 3: Relevo A — Registro del pedido nacional (N-F01 pasos 1–3)

**Files:**
- Read: `resources/js/Pages/ControlPedidos/Nacional/FormularioRegistrarNacional.jsx`, `Partials/{ModalFormPedido.jsx,flujoCapturaPedido.js,FormularioRegistrarTienda.jsx}`, `app/Support/ControlPedidos/Nacional/CapturaNacional.php`, `app/Services/ControlPedidos/Nacional/GuardarMercanciaNacional.php`, `app/Http/Controllers/ControlPedidos/PedidoBmaController.php`, `PedidoBmaRequestBase.php`

**Interfaces:**
- Produces: filas de matriz para: alta con PDF, importe preliminar único, paquetería o `PAQ. PENDIENTE`, SKU opcional, guardado idempotente por `solicitud_id`, envío de consulta a CEDIS.

- [ ] **Step 1: Contrastar con V3.1 N-F01 pasos 1–3 y §11.3** (importe preliminar y paquetería en la misma fila; SKU opcional; sin avisos de guía/costo al alta). Expected: cada punto marcado cumple/incumple con `archivo:línea`.
- [ ] **Step 2: Verificar idempotencia y concurrencia** leyendo `solicitud_id` y el 409 `vista_atrasada` en controlador y servicio. Expected: hallazgo si un doble envío puede duplicar.
- [ ] **Step 3: Comparar contra capacidades de la 1.0** en `Partials/ModalFormPedidoLegado.jsx` (campos que el nuevo formulario ya no captura: domicilio, destinatario, seguro, referencias). Expected: lista "capacidad perdida / diferida a continuación / intencional" con regla V3.1 que lo justifica.

### Task 4: Relevo B — Pesaje y respuesta de CEDIS (N-F01 pasos 4–7), incluido móvil

**Files:**
- Read: `resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx` (1,982 líneas), `Cedis/Index.jsx`, `TarjetasCedis.jsx`, `app/Services/ControlPedidos/{SolicitarPesajePedidoBmaService,ResponderPesajePedidoBmaService}.php`, `Nacional/LecturaConsultaNacional.php`, `PedidoBmaCedisController.php`

**Interfaces:**
- Produces: filas de matriz para solicitar pesaje, abrir solicitud en bandeja, responder por unidad, capturar cajas/peso real/peso a cobrar/medidas, ajuste con retiro/anexo y re-pesaje, cierre por Ventas.

- [ ] **Step 1: Trazar el recorrido solicitar → bandeja CEDIS → responder → Ventas cierra** con rutas de Task 1. Expected: cada eslabón con archivo:línea o hallazgo "sin superficie" (§2 regla de bandejas).
- [ ] **Step 2: Validar reglas de servidor:** caja sin peso, peso a cobrar ≥ real y redondeo (3.8→4), peso obsoleto tras anexo, respuesta que no cierra la consulta. Expected: hallazgo por regla no impuesta.
- [ ] **Step 3: Revisar `ModalResponderPesaje.jsx` para móvil:** anchos fijos, grids sin colapso, tablas, targets < 44 px, pie fijo, `overflow`. Expected: lista de puntos de falla a 375 px con línea y su costo de arreglo (ajuste CSS vs. rediseño por pasos); contrastar con `GUIA_DISENO_GELIA.md` y `.ux-profile.md`.

### Task 5: Relevo C — Paqueterías con logística (FedEx, Estafeta, DHL)

**Files:**
- Read: `CatalogoPaqueteriaPedido.php`, `Nacional/CambiarPaqueteriaNacional.php`, `Nacional/CoberturaEnvioNacional.php`, `Nacional/ContinuacionEnvioNacional.jsx`, servicio de seguro y reexpedición, `Admin/Partials/Catalogos/TablaPaqueteriasPedido.jsx`, seeders/migraciones del catálogo

**Interfaces:**
- Produces: filas de matriz para cotización por caja, flete, reexpedición separada, seguro (AC-09: reutilizar fórmulas, sin cálculo duplicado en cliente), pago de solo lo pendiente, `PAQ. PENDIENTE` → elegir empresa.

- [ ] **Step 1: Leer cómo se decide "paquetería con logística"** (flags `requiere_peso`, tipo, catálogo) y qué hace el sistema con DHL/Estafeta/FedEx en cada flag. Expected: tabla empresa × flag × comportamiento real.
- [ ] **Step 2: Contrastar cálculo con el ejemplo del propio reporte** (DHL, flete 200, mercancía 1000, reexpedición 150 → seguro 75, costo de envío 350; dos cajas 10.10 y 10.25 → 20.35) ejecutando la prueba existente `NacionalLogisticaCobroTest`. Expected: resultado real vs. esperado.
- [ ] **Step 3: Buscar cálculo duplicado en JS** (`grep -n "seguro\|reexpedic" resources/js/Pages/ControlPedidos/Nacional`). Expected: hallazgo por cada fórmula en cliente.

### Task 6: Relevo D — Cajas, complementos, bifurcaciones y barreras (N-F03, N-F04, §6 bifurcaciones)

**Files:**
- Read: `app/Services/ControlPedidos/Nacional/*`, `app/Support/ControlPedidos/Nacional/Barrera*.php`, `AsignarGuiaPedidoBmaService.php`, `MarcarEnviadoPedidoBmaService.php`, `GestionarRemisionPedidoBmaService.php`, `ModalCambiarDireccion`, `PedidoBmaController` acciones nacionales, migración `2026_11_20_191500_create_pedido_bma_caja_remisiones_table`

**Interfaces:**
- Produces: filas de matriz para remisión por caja con seguro (N-11, AC-12), complemento con remisión (N-06), cambio de paquetería, dirección con guía, Nacional→Tienda, cancelación, crédito bloqueado, guía válida y cobertura antes de salida.

- [ ] **Step 1: Por cada ruta de Task 1, verificar con la matriz: ¿tiene botón en bandeja del rol correcto, validación de servidor, estado final y mensaje cuando no procede?** Expected: ruta clasificada `completa | solo backend | UI sin efecto`.
- [ ] **Step 2: Comprobar barreras §1.1.10** (sin despacho con crédito bloqueado, complemento sin pagar, recepción pendiente, por cotizar, guía inválida) en `MarcarEnviadoPedidoBmaService` y `AsignarGuia…`. Expected: hallazgo por barrera ausente.
- [ ] **Step 3: Verificar que la migración de cajas/remisiones exista en el esquema de la base de pruebas del navegador** (consultar con `sail artisan migrate:status`, solo lectura). Expected: nota G-02 resuelta o `BLOCKED`.

### Task 7: Navegador — recorrido con datos sintéticos y revisión visual

**Files:**
- Modify: informe (`## Matriz de relevos`, `## Hallazgos`); capturas en `docs/superpowers/specs/nacional-auditoria-evidencia/`

**Interfaces:**
- Consumes: matriz de Tasks 3–6 (qué esperar en cada pantalla).
- Produces: estado `PASS/FAIL/BLOCKED` por relevo observable y hallazgos de interfaz/red.

- [ ] **Step 1: Cargar herramientas de Chrome** (skill `anthropic-skills:chrome-browser`; un solo ToolSearch) y abrir pestaña nueva en `http://100.110.75.115:9080/dashboard`. Confirmar sesión y versión visible del entorno. Expected: sesión activa o `BLOCKED` con motivo.
- [ ] **Step 2: Registrar un pedido nacional `AUD-NAC-001`** con PDF de prueba, `PAQ. PENDIENTE` y otro con DHL; reabrir el modal. Capturar 1280 px y 375 px (`resize_window`). Expected: campos conservados; hallazgos de diseño frente a la guía.
- [ ] **Step 3: Enviar consulta, abrir la bandeja CEDIS y responder pesaje** con 2 cajas; repetir a 375 px. Leer consola y red. Expected: cada 4xx/5xx de endpoint nacional anotado con URL, payload y respuesta.
- [ ] **Step 4: Cerrar consulta como Ventas, abrir Continuación** y probar FedEx/Estafeta/DHL. Expected: cotización, flete y saldo observados vs. Task 5.
- [ ] **Step 5: Documentar lo no comprobable con superadmin** (separación por rol, bandejas por permiso) como `NOT_RUN` con la referencia a la prueba de permisos que lo cubriría.

### Task 8: Regresiones transversales (Tienda y Municipal)

**Files:**
- Read: `git show` de `b5be2bb5`, `f410db92`, `1ac45c5f`, `63d6c6d5`, `0d04de9c` sobre archivos compartidos: `FormularioRegistrarTienda.jsx`, `PedidoBma.php`, `adaptadoresConsulta.js`, `FusionarSolicitudPreparacionPedido.php`, `AjustePiezas.jsx`, `flujoCapturaPedido.js`, rutas

**Interfaces:**
- Produces: sección `## Regresiones` con cambio, riesgo para Tienda/Municipal y prueba que lo demuestra o la falta de ella.

- [ ] **Step 1: Listar los cambios de comportamiento en archivos compartidos** (ramas `if nacional`, cambios de contrato) con `git show --stat` y lectura de hunks. Expected: tabla archivo → cambio → modalidad afectada.
- [ ] **Step 2: Correr regresión dirigida de Tienda y Municipal.** Run: `./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config-aud.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes-aud.php laravel.test php artisan test --filter='MunicipalOrdinarioM01Test|RegistrarPedidosTiendaTest|CorteMismoDiaTiendaTest|SolicitudRegistrarTiendaTest|ConcurrenciaCapturaPedidoTest'`. Expected: fallas listadas; las cuatro de `f410db92` reverificadas.
- [ ] **Step 3: Abrir en navegador un alta de Tienda y una Municipal** (datos `AUD-NAC-`) para ver que el formulario compartido sigue operable. Expected: `PASS/FAIL` por modalidad.

### Task 9: Consolidación y lotes de reparación

**Files:**
- Modify: informe, `## Resumen`, `## Matriz de relevos`, `## No verificado`, `## Lotes propuestos`

- [ ] **Step 1: Deduplicar hallazgos y asignar severidad** según definición del spec. Expected: ningún hallazgo sin evidencia ni regla V3.1 (o "regla de interfaz/guía de diseño").
- [ ] **Step 2: Agrupar en lotes ordenados por dependencia** (p. ej. endpoints rotos/regresiones → pesaje móvil → paqueterías → modal/diseño), con riesgo y prueba de aceptación por lote. Expected: 3–6 lotes.
- [ ] **Step 3: Escribir el resumen ejecutivo** (conteo por severidad, qué se puede dar por sano, qué quedó sin verificar y por qué) y presentar el informe al usuario. Expected: usuario elige el primer lote a reparar; no se corrige nada sin esa decisión.

---

## Autorevisión

- **Cobertura del spec:** línea base (T1–T2), relevos y V3.1 (T3–T6), paqueterías (T5), pesaje y móvil (T4, T7), endpoints (T1, T6, T7), regresiones (T8), interfaz/navegador (T7), entregable y lotes (T9). Sin brechas.
- **Consistencia:** el formato de fila y los IDs `NAC-###` se definen en T1 y no cambian.
- **Proporción:** el plan es más corto que la suma de las áreas que cubre; no transcribe código porque no escribe código de producto.

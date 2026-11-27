# Multienvío nacional — Fases A y B (operación completa en las vistas) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax. **Cadencia pedida por el usuario:** menos fases, urgente, mismo rigor en dinero/permisos/pagos. Un implementador por bloque y una revisión por bloque (no por tarea); revisor con modelo fuerte solo en pagos, permisos y visibilidad.

**Goal:** Que vendedoras, auxiliares, CEDIS y encargado de guías puedan operar un Multienvío de punta a punta en las vistas existentes de Gestión de pedidos, con rutas de datos listas para saldos a favor y reportes.

**Architecture:** Cada envío ya es un `PedidoBma` ordinario de una sola caja (Fase 1), así que Auditar, CEDIS y Delegado lo procesan con sus flujos actuales. Se añade (1) un resumen de grupo calculado por lote para las listas, (2) la UI de Ventas dentro del modal nacional existente (panel antes de la cotización), (3) contexto de grupo («Envío 2 de 3», hermanos, búsqueda por cualquier folio/remisión/rastreo) en Auditar/CEDIS/Delegado, (4) señalización de comprobante compartido, y (5) pruebas de recorrido completo.

**Tech Stack:** Laravel 12 / PHP 8.4 (PHPUnit SQLite + MySQL de dev), React (Inertia) + Vitest/happy-dom, build con `npm run build` en el contenedor de desarrollo.

**Spec:** `docs/superpowers/specs/2026-11-27-multienvio-nacional-design.md` (+ R1 `…-r1-hallazgos.md`); Fase 1 hecha: `docs/superpowers/plans/2026-11-27-multienvio-nacional-fase1.md`. Mapas de integración (resumen en las tareas): Ventas `FormularioRegistrarNacional.jsx:442`, Auditar `ListarPedidosAuditoriaService.php`, CEDIS `TarjetasCedis.jsx`/`ListarPedidosCedisService.php`, Delegado `TablaDelegado.jsx`/`ListarPedidosDelegadoService.php`.

## Global Constraints

- Backend de Fase 1 intacto salvo extensiones aditivas listadas aquí; no tocar barreras (`BarreraSeguroCajaNacional`, `AsignarRemisionCajaNacional`), fórmulas de seguro ni el flujo de guías PDF por caja de la co-desarrolladora (`GuiasPdfPorCaja`, solo `aplica_seguro=false`).
- **Nunca** poner `pedido_principal_id` a un miembro (el empaque/CEDIS lo trataría como complemento). Los miembros son raíces.
- El grupo no concede permisos: toda lista/proyección incluye solo los miembros que el actor ya puede ver (`VisibilidadPedidoBma`); sin fuga de folios/importes de miembros ocultos.
- Sin N+1: resumen de grupo por lote (una consulta por página, `whereIn multienvio_nacional_id`), columnas ligeras, nunca `withRelations` pesado para hermanos.
- UI: respetar `DESIGN.md` (tokens `theme-*`, `var(--color-*)`, lucide, píldoras con icono+una palabra, objetivos de 44 px, verbos primero, nada de hex); textos de usuario sin códigos de ficha; copy en español.
- Errores 422 por campo (`folio_remision`, `total_mercancia`, `revision`, `envios`, `multienvio`); `revision` se reenvía siempre; confirmar es idempotente.
- Rutas web nuevas se espejan en `routes/api/mobile-control-pedidos.php` (o se excluyen con motivo en `config/api_paridad.php`); `MobileEspejoRutasTest` en verde.
- Importes en centavos; mismo comprobante en varios envíos permitido (sin deduplicación global). Reportes suman por pago.
- No hacer commit ni push. Pruebas: `rsync` a `/home/ti-admin/gelianv` + `docker exec -u sail … gelianv-laravel.test-1 php artisan test` / `npm test`; build con `docker exec -u sail gelianv-laravel.test-1 npm run build` (solo al cerrar un bloque visual). MySQL: base `gelianv` de `gelianv-mysql-1`, solo filas sintéticas marcadas y limpiadas, solo mis migraciones.
- Baseline conocido: `NacionalAltaConsultaTest > alta mixta` falla antes de cualquier cambio.

## Review Focus

1. Un miembro oculto para el actor (CEDIS/Delegado/Auxiliar/otro vendedor) no aparece en chips, contadores ni búsqueda por hermano (Tareas A1, B1-B3).
2. Doble clic / dos pestañas / revisión obsoleta en el panel de Ventas no duplican ni pierden captura (A3).
3. Tres envíos con el mismo comprobante: el ingreso bancario verificado = 2040.00 (no 6120) y el pendiente de cada envío no se contamina (B4).
4. Un miembro avanza solo por Auditar→CEDIS→guía→salida sin quedar acoplado a otro, y sin que `consolidar` lo funda en una operación (B2, B3).
5. Pedido con hechos previos queda en «regularización pendiente», visible y sin acciones destructivas (A3).

---

## Fase A — Ventas

### Task A1: Datos de grupo y de cajas para la UI (backend)

**Files:** Create `app/Support/ControlPedidos/Nacional/ResumenGrupoMultienvio.php`; Modify `ProyeccionMultienvio.php`, `app/Support/ControlPedidos/Nacional/LecturaConsultaNacional.php`, `ListarPedidosBmaService.php`; Test `tests/Feature/ControlPedidos/MultienvioNacionalResumenTest.php`.

**Interfaces:** Produces `ResumenGrupoMultienvio::paraPagina(Collection $pedidos, User $usuario): void` (una consulta por lote; hace `setAttribute('multienvio', ['id','orden','n_total','estado','hermanos'=>[{id,orden,folio,folio_remision,estatus_nombre,estatus_fase,pago_validado,tiene_remision,tiene_guia}]])` solo con hermanos visibles al usuario y `parcial` si hay ocultos) — lo consumen B1-B3. `ProyeccionMultienvio` añade `cajas_origen: [{uuid_operativo, orden, tipo, peso_cobrado_kg, largo, ancho, alto, evidencias:[{id,url}]}]` solo para la vendedora dueña en estados `configurando` (y antes de existir miembros) — insumo de la UI de configuración. `LecturaConsultaNacional` incluye `multienvio` (resumen del propio pedido) para que `adoptar` lo conserve.

- [ ] Test: resumen por lote con N+1 acotado (`DB::enableQueryLog`, constante entre 2 y 6 grupos), hermanos ocultos no aparecen y `parcial=true`, `cajas_origen` solo para la dueña y no incluye cajas ya movidas.
- [ ] Implementar lo anterior; en `ListarPedidosBmaService::ejecutar` llamar al resumen sobre la página (antes de `through`).
- [ ] Correr `MultienvioNacional|MobileEspejoRutasTest` + regresión Nacional.

### Task A2: Etiqueta de grupo y refresco en vivo (frontend)

**Files:** Create `resources/js/Pages/ControlPedidos/Partials/EtiquetaMultienvio.jsx` (+ `.test.jsx`); Modify `Partials/EncabezadoFolioPedido.jsx`, `Index.jsx` (comparación `mismo` de refresco en vivo, ~l.168-175: añadir `multienvio_nacional_id`, `multienvio_orden`, `multienvio.estado`), `Partials/detalle-pedido/CuerpoDetalleNacional.jsx` (sección «Multienvío» con hermanos).

**Interfaces:** `<EtiquetaMultienvio multienvio={pedido.multienvio} />` píldora «Envío n de N» (icono `Layers`/`PackagePlus` según DESIGN) con tono neutral; no depende del tope `MAX_BADGES_LISTADO`.

- [ ] Tests Vitest (patrón `ContinuacionEnvioNacional.test.jsx`): render con/sin grupo, `parcial`, no rompe `EncabezadoFolioPedido` existente.
- [ ] Implementar y correr los tests de `Partials/` afectados.

### Task A3: Panel «Multienvío» en el modal nacional (frontend)

**Files:** Create `resources/js/Pages/ControlPedidos/Nacional/PanelMultienvioNacional.jsx` (+ `.test.jsx`), `Nacional/useMultienvioNacional.js` (axios GET/POST/PUT, mapeo de errores 422 por campo); Modify `Nacional/FormularioRegistrarNacional.jsx` (insertar justo antes del bloque que renderiza `ContinuacionEnvioNacional`, ~l.442; mantener el panel de cotización oculto mientras la decisión esté pendiente o el grupo `configurando`; tras `sin_seguro` o fin de la configuración mostrar la cotización; añadir su suciedad a `onDirtyChange`).

**Interfaces:** Consumes `GET /control-pedidos/{id}/multienvio` y las rutas `decision|borrador|confirmar` (siempre con `revision`). El panel muestra:
  1. **Aviso** (usar `AvisoOperativoPedido`): «Multienvío detectado — CEDIS determinó N cajas. Si el cliente requiere seguro, cada caja necesita su propio pedido, pago y remisión.» Acciones: **Activar multienvío con seguro** (principal) / **Continuar sin seguro**; texto de apoyo: «Podrás activarlo después si el cliente cambia de opinión». No aparece con 1 caja, consulta abierta o decisión ya tomada.
  2. **Configuración** (estado `configurando`): una fila por caja (`cajas_origen`: n.º, tipo, peso, medidas, miniaturas de evidencia de CEDIS en solo lectura) con campos *Folio de pedido Wizerp*, *Valor de mercancía* (`formatoMoneda`, regex de monto del panel de cotización), *Contenido* (opcional); resumen de totales con **diferencia** contra el total del pedido (en centavos) y bloqueo del botón **Confirmar multienvío** hasta que cuadre; autoguardado del borrador (debounce/blur, `PUT borrador`), errores por campo (`aria-invalid`/`aria-describedby`), retiro de seguro posible con «Continuar sin seguro» mientras no haya hechos.
  3. **Activo**: lista de envíos (folio GELIA, folio Wizerp, estado) y progreso `x_de_n`; cada envío abre el flujo normal del pedido (cotización/pagos) — no se copian formularios.
  4. **Regularización pendiente**: banner con los hechos detectados (`regularizacion.hechos`: «ya hay pago capturado/validado, remisión, guía…») y el texto de que Auxiliar/Ventas deben regularizar; sin botones destructivos.
  5. Acción posterior en pedido estándar multicaja: «Activar multienvío con seguro» siempre disponible mientras `evaluacion.elegible` (ME-06), con `revision` vigente (si cambió, recargar y avisar).
- [ ] Tests Vitest: aviso solo cuando `elegible && decisión pendiente`; no aparece con 1 caja; `sin_seguro` muestra la cotización; configurar 3 cajas con 600/500/400 sobre 1500 habilita confirmar, 1 centavo de diferencia lo bloquea y muestra la diferencia; error 422 `folio_remision` marca el campo; `revision` obsoleta recarga; doble clic en confirmar envía una vez; banner de regularización; el borrador reabierto restaura valores; foco/teclado básicos.
- [ ] Implementar y correr `Nacional/*.test.jsx` y `Partials/ModalFormPedido.seleccion.test.jsx`.

### Task A4: Recorrido de un miembro hasta Auditar (backend, prueba de integración)

**Files:** Test `tests/Feature/ControlPedidos/MultienvioNacionalRecorridoVentasTest.php`.

- [ ] Prueba HTTP: escenario de 3 cajas con DHL: decisión → borrador → confirmar → cada miembro cotiza con la ruta existente (vista previa y guardado coinciden; seguro DHL = 2% + $51 por caja, p. ej. 765.00/683.40/591.60 con los fletes 100/120/130 del ejemplo del spec) → se envía a Auxiliar; el pedido original y los dos nuevos aparecen en la lista de Auditar y solo se puede auditar cada uno por separado. Si algo del flujo existente rechaza al miembro (estatus, `pesaje_solicitado_at`, soporte), corregir en `MaterializarEnvioMultienvio` (aditivo) y documentarlo.
- [ ] **Revisión de bloque A** (modelo fuerte): permisos/visibilidad de `cajas_origen`, N+1, estados del panel, idempotencia y pérdida de captura.

## Fase B — Auditar, CEDIS, Delegado, pago compartido

### Task B1: Auditar con contexto de grupo y comprobante compartido

**Files:** Modify `ListarPedidosAuditoriaService.php` (+ `PedidoBmaAuditoriaController` filtro `multienvio`), `Auditar/Partials/TablaAuditoria.jsx`, `Auditar/Partials/ModalRevisarPedido.jsx`, `Auditar/Partials/FiltrosAuditoria.jsx` si hace falta; Test PHP + Vitest.

- [ ] Fila/tarjeta: `EtiquetaMultienvio` + chips de hermanos (folio Wizerp, estado de pago/remisión con icono y texto), clic abre el hermano; búsqueda `q` también por `numero_remision` y por folio de cualquier hermano del mismo grupo (solo visibles); filtro por grupo.
- [ ] En `SeccionPagosExhibicion`/modal: **aviso de comprobante compartido** cuando la `referencia` de una exhibición coincide con exhibiciones de otros miembros del mismo grupo (una consulta por página con `whereIn referencia` y `multienvio_nacional_id`): «Esta referencia también está en: Envío 2, Envío 3. Suma de las partes: $2,040.00» (solo informativo; no bloquea, no concilia).
- [ ] Observación sugerida al capturar pago en un miembro: texto prellenable «Transferencia compartida, multienvío X, caja n/N» (editable); sin asistente de reparto.
- [ ] Remisión: confirmar con prueba que cada miembro registra su `numero_remision` y PDF sin chocar con los hermanos y que `GestionarRemisionPedidoBmaService` no los acopla.
- [ ] Contadores de Auditar no cambian (cuentan envíos); añadir `operaciones_agrupadas` solo si es barato (`COUNT(DISTINCT COALESCE(multienvio_nacional_id, -id))`).

### Task B2: CEDIS

**Files:** Modify `ListarPedidosCedisService.php` (resumen por lote, búsqueda por `numero_remision`, `numero_rastreo`, rastreo de caja y por folio de hermano visible; `parcial`), `Cedis/Partials/TarjetasCedis.jsx` (píldora «Envío n de N» + bloque de hermanos con icono+texto, clic abre el hermano mediante `listado?q=` como `recargarDetalleGuias`), `Cedis/Partials/ModalDetalleCedis.jsx` (sección de grupo, remisión del propio envío), `app/Services/ControlPedidos/ConsolidarPedidosEmpaqueService.php` (**rechazar** consolidar miembros: `assertConsolidable`); Tests PHP + Vitest.

- [ ] Verificar que ningún miembro pasa por `pedido_principal_id`/`complementosDe` (prueba), que cada uno aparece en CEDIS solo cuando tiene su `pago_validado_at` + remisión, y que `MarcarEmpacado`/`MarcarEnviado` operan por miembro con su guía individual (con seguro → camino `AsignarGuiaPedidoBmaService`, no PDF por caja).
- [ ] Revisar (grep) agrupaciones por `cliente_id` en `RegistrarBultosEmpaquePedidoBmaService`, `SesionEvidenciaCedis`, `AtencionMismoDiaTienda`, `AssertPedidoNoBloqueadoFase7`, `GestionarGuiaPdfPedidoBmaService`, `PrioridadAtencionPedidosConfig` y documentar/arreglar cualquier acoplamiento.

### Task B3: Encargado de guías

**Files:** Modify `ListarPedidosDelegadoService.php`, `BusquedaDelegadoPedidoBma.php` (campo `folio` y general también por folio de hermano visible), `Delegado/Partials/TablaDelegado.jsx`, `ModalDetalleDelegado.jsx` (píldora + hermanos), exportación (`ExportarPlantillaGuiasPedidoService`: añadir columnas informativas `Envio` «2/3» y `Folio_Wizerp`; **`ID_Pedido` sigue siendo la clave individual**); Tests PHP + Vitest (`remisionBusqueda.test.jsx` patrón).

- [ ] Prueba de importación: 3 miembros, 3 filas con `ID_Pedido` distinto → cada uno recibe su guía/rastreo y PDF propios; el ID del grupo nunca asigna la misma guía a todos.
- [ ] El miembro con seguro entra por `AsignarGuiaPedidoBmaService`, nunca por `GuiasPdfPorCaja`.

### Task B4: Recorrido completo y reportes

**Files:** Test `tests/Feature/ControlPedidos/MultienvioNacionalRecorridoCompletoTest.php`.

- [ ] Prueba end-to-end (SQLite): 3 envíos con seguro DHL → un solo comprobante de 2040.00 registrado como 3 exhibiciones (765.00/683.40/591.60, misma referencia y archivo, observación) → Auxiliar verifica solo el 1.º: ingreso verificado = 765.00, no 2040/6120 → verifica los otros: `CalcularIngresoBancarioValidadoService` = 2040.00; `vouchers_ingreso_bancario` cuenta 3 exhibiciones (documentar que no equivale a 3 transferencias); cierres sin duplicar; remisión por miembro; cada miembro aparece en CEDIS, se empaca, recibe su guía por `ID_Pedido` y sale; un miembro en otra fase no esconde a los demás; con un miembro enviado el grupo no se marca completo.
- [ ] Caso «pago ya capturado antes de activar» → `regularizacion_pendiente` visible en Ventas y Auxiliar, sin cambiar pagos.
- [ ] Rutas de datos para saldos a favor/reportes: confirmar que `GET /multienvio` expone por envío `pagado/pendiente/estado_pago` y `saf_aplicado` (solo lectura) para esa consulta; sin lógica nueva de SAF.

### Task B5: Regresión, documentación, build y evidencia

- [ ] Suites dirigidas: `MultienvioNacional|MobileEspejoRutasTest|BandejasOperativasTest|BusquedaRemisionDelegadoTest|EmpaqueBultosCedisTest|ControlPedidosFase22Test|ControlPedidosActualizarGuiasTest|RemisionAuditoriaTest|ImportesPagoTiendaTest|Nacional*` y Vitest de `Nacional/`, `Partials/`, `Auditar/`, `Cedis/`, `Delegado/`; MySQL: repetir `MultienvioNacionalConcurrenciaMysqlTest` con las dos migraciones aplicadas y revertirlas al final.
- [ ] `npm run build` en el contenedor de desarrollo; revisión visual (escritorio/móvil, claro/oscuro) con `GUIA_EVIDENCIA_VISUAL.md`: capturas del aviso, la configuración, Auditar con chips y aviso de voucher compartido, tarjeta CEDIS y detalle de Delegado.
- [ ] Documentación: `docs/ai/modulos/{REGISTRO_PEDIDOS,AUDITORIA_PAGOS,CEDIS_EMPAQUE,DELEGADO_GUIAS}.md`, `MAPA.md`, `PRUEBAS.md`, guía V3.1 (recorrido de los pasos 11-12 y tabla §15) — solo hechos verificados; marcar pendiente lo no probado con cuentas reales.
- [ ] **Revisión final de bloque B y del conjunto** (modelo fuerte): permisos/visibilidad en las cuatro listas, N+1, acoplamientos, reportes.

---

## Fuera de esta entrega (pendiente explícito)

Reasignación de pagos ya capturados/validados con voucher compartido a envíos creados después (regularización automática), cambio de seguro por caja (seguros mixtos), despacho parcial entre miembros con política propia, conteo «operaciones agrupadas» en todas las métricas, repesaje con reconciliación de grupo existente y la prueba con cuentas reales de cada rol.

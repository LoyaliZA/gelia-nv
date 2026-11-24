# Vista única de respuesta de disponibilidad Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que Ventas vea en Tienda, Municipal y Nacional una sola tabla «Respuesta de disponibilidad» con filas verdes/rojas/amarillas, sin el panel «Cambios del pedido».

**Architecture:** Una función pura convierte los cambios de `cambiosDisponibilidad()` en filas con tono (`exito` | `peligro` | `aviso`). `RespuestaDisponibilidadPedido` recibe esas filas por una prop nueva, las pinta tras las piezas y pliega las anteriores en un `<details>`. Los tres seguimientos dejan de renderizar `ResumenCambiosDisponibilidad` (que sigue vivo en CEDIS y Ajustes de Tienda).

**Tech Stack:** React (JSX), Vitest + Testing Library, CSS de GELIA (`control-pedidos-tienda.css`), tokens `--color-exito/--color-peligro/--color-aviso`.

**Spec:** `docs/superpowers/specs/2026-11-24-vista-unica-respuesta-disponibilidad-design.md`

## Global Constraints

- Color por estado: verde = OK / confirmado con el cliente; rojo = sin existencias / retiro; amarillo = pendiente (anexo por revisar, avance pendiente, con detalle sin decisión). El amarillo nunca se usa para «confirmado».
- Cada fila lleva texto de estado; el color nunca es el único canal.
- No tocar backend, `unidad_id`, claves de solicitud ni `PanelRespuestaDisponibilidad` («Cambios por revisar»).
- `ResumenCambiosDisponibilidad` se conserva en `ModalResponderPesaje`, `AlternativasDisponibilidadCedis` y `AjustesSolicitadosTienda`.
- Reutilizar tokens y clases `tienda-respuesta-*`; probar a 375 px y 1280 px.
- Nunca mostrar códigos de ficha (T-01…) en textos de usuario.
- Comandos mediante `./vendor/bin/sail` (hoy `vendor/bin/sail` no existe en este servidor y vitest en el host falla con EACCES: diagnosticar antes de sustituir). No commit/push sin que el usuario lo pida.
- Actualizar la guía operativa V3.1 §15 en el mismo trabajo.

## Review Focus

1. Anexo solicitado mientras aún no hay respuesta (no `respondida`): hoy la tabla no se pinta y el anexo desaparecería; debe verse como fila amarilla.
2. Decisión de Ventas (`confirmar`/`retirar`) sobre una pieza que ya está en la tabla: no debe duplicarse como fila aparte; la fila de la pieza toma el color de la decisión.
3. Reapertura de consulta (`reapertura_disponibilidad`): el aviso «Consulta reabierta por …» no se pierde.
4. Solicitud sin cambios: la tabla queda idéntica a la actual (sin `<details>` vacío).
5. Anexo con `avance_pendiente` (Tienda, ciclo sin enviar): amarillo «Pendiente de revisión», no verde aunque la unidad tenga resultado guardado.

---

### Task 1: Filas con tono a partir de los cambios

**Files:**
- Modify: `resources/js/Pages/ControlPedidos/Partials/cambiosDisponibilidad.js` (al final; y en la ficha de retiro por pieza, añadir `revision_id: pieza.revision_id`)
- Test: `resources/js/Pages/ControlPedidos/Partials/cambiosDisponibilidadFilas.test.js` (nuevo)

**Interfaces:**
- Produces:
  - `tonoDePieza(fila: {estado_fisico: string, resolucion?: {accion: string}}): 'exito'|'peligro'|'aviso'` — decisión `confirmar`/`aceptar_condicion` → exito; `retirar` → peligro; `sustituir` → aviso; sin decisión: `bueno` → exito, `sin_existencia` → peligro, otro → aviso.
  - `filasDeCambios(solicitud: object, documentos: object[], opciones: {revisionIdsEnTabla?: Set<number>}): {actuales: FilaCambio[], anteriores: FilaCambio[]}`
  - `FilaCambio = {clave: string, descripcion: string, sku: string, cantidad: number|null, tono: 'exito'|'peligro'|'aviso', etiqueta: string, documentos: object[], tipo: 'anexo'|'retiro'|'confirmacion'}`
- Etiquetas: confirmacion → «Confirmado con el cliente» (exito); retiro → «Retiro solicitado» (peligro); anexo pendiente o `avance_pendiente` → «Por revisar» (aviso); anexo con `resultado === 'correcto'` → «Todo OK» (exito); anexo con `incidencia` → «Con piezas reportadas» (aviso).
- Partición: reutiliza `particionarCambiosConsulta(cambios, solicitud, 'ventas')`. Se excluyen de ambas listas los cambios cuyo `revision_id` esté en `revisionIdsEnTabla`.

- [ ] **Step 1: Escribir las pruebas** en `cambiosDisponibilidadFilas.test.js`: `tonoDePieza` (cinco casos de arriba); `filasDeCambios` con un anexo pendiente → una fila `{tono:'aviso', etiqueta:'Por revisar'}`; con `avance_pendiente` y unidad `correcto` → sigue `aviso`; una decisión `confirmar` con `revision_id: 5` y `revisionIdsEnTabla = new Set([5])` → lista vacía; la misma sin el set → fila `exito` «Confirmado con el cliente»; solicitud vacía → `{actuales: [], anteriores: []}`.
- [ ] **Step 2: Ejecutar** `./vendor/bin/sail npm test -- cambiosDisponibilidadFilas` — Expected: FAIL («tonoDePieza is not a function»).
- [ ] **Step 3: Implementar** `tonoDePieza` y `filasDeCambios` en `cambiosDisponibilidad.js` y añadir `revision_id` a las fichas de retiro por pieza, para que la exclusión del Review Focus 2 funcione.
- [ ] **Step 4: Ejecutar** el test anterior y `cambiosDisponibilidadIdentidad.test.js` — Expected: PASS ambos.

### Task 2: La tabla pinta filas con tono y las de cambios

**Files:**
- Modify: `resources/js/Pages/ControlPedidos/Partials/RespuestaDisponibilidadPedido.jsx`
- Modify: `resources/css/gelia/features/control-pedidos-tienda.css` (junto a `.tienda-respuesta-estado`, ~1483)
- Test: `resources/js/Pages/ControlPedidos/Partials/RespuestaDisponibilidadPedido.test.jsx` (nuevo)

**Interfaces:**
- Consumes: `tonoDePieza`, `FilaCambio` (Task 1).
- Produces: props nuevas de `RespuestaDisponibilidadPedido`: `cambios?: {actuales: FilaCambio[], anteriores: FilaCambio[]}`, `avisoReapertura?: string`. Cada `.tienda-respuesta-fila` lleva `data-tono`. Las filas de cambios llevan `data-origen="cambio"`, miniatura (`MiniaturaDocumento` de `documentos[0]`), `etiqueta` visible y, en la columna Cantidad, `cantidad ?? '—'`. Las anteriores van en `<details className="tienda-respuesta-movimientos"><summary>Movimientos ({n})</summary>…`. La tabla se pinta si hay `filas` o `cambios.actuales`.

- [ ] **Step 1: Escribir pruebas**: pieza `bueno` → `data-tono="exito"`; `sin_existencia` → `peligro`; con `resolucion.accion==='retirar'` → `peligro`; fila de cambio «Por revisar» → `data-tono="aviso"` y texto visible «Por revisar»; `anteriores` de 2 → `summary` «Movimientos (2)»; sin `cambios` ni anteriores → sin `<details>`; solo `cambios.actuales` y `filas=[]` → la tabla se pinta.
- [ ] **Step 2: Ejecutar** `./vendor/bin/sail npm test -- RespuestaDisponibilidadPedido` — Expected: FAIL.
- [ ] **Step 3: Implementar** en el componente (usa `tonoDePieza` para el `data-tono` de cada pieza y el del chip `tienda-respuesta-estado`) y el CSS: `.tienda-respuesta-fila[data-tono]` aplica borde izquierdo y fondo con `color-mix` de `--color-exito`, `--color-peligro`, `--color-aviso` (mismo patrón que `.tienda-respuesta-estado`).
- [ ] **Step 4: Ejecutar** el test y `./vendor/bin/sail npm run lint:style-css` — Expected: PASS y sin errores de estilo.

### Task 3: Seguimientos de Tienda, Municipal y Nacional

**Files:**
- Modify: `Partials/SeguimientoSolicitudTienda.jsx` (~110 y 293), `Municipal/SeguimientoSolicitudMunicipal.jsx` (~93 y 115), `Nacional/SeguimientoSolicitudNacional.jsx` (~186; hoy no usa el resumen: solo añadir `cambios`)
- Test: `Partials/respuestaAnteriorDisponibilidad.test.jsx`, `Partials/AccionesRapidasDisponibilidad.test.jsx`, `Municipal/SeguimientoSolicitudMunicipal.test.jsx`, `Nacional/SeguimientoSolicitudNacional.test.jsx`

**Interfaces:**
- Consumes: `cambiosDisponibilidad`, `filasDeCambios(solicitud, documentos, {revisionIdsEnTabla})` y las props de Task 2.
- Cada seguimiento calcula `revisionIdsEnTabla = new Set(piezas.map(p => Number(p.id)))`, pasa `cambios` y `avisoReapertura` (de `solicitud.reapertura_disponibilidad.por`) y **elimina** `<ResumenCambiosDisponibilidad … vista="ventas">`. Si `!respondida` pero hay `cambios.actuales`, renderiza la tabla con `titulo="Cambios solicitados"` y sin responsable (Review Focus 1).

- [ ] **Step 1: Actualizar las pruebas existentes** que buscan `.consulta-cambio*`, «Cambios del pedido» o «Movimientos (n)» en estos seguimientos para que afirmen las filas `[data-origen="cambio"]`, su `data-tono` y el texto de estado; añadir: anexo pendiente sin respuesta → fila amarilla visible; decisión `confirmar` sobre pieza de la tabla → una sola fila, tono `exito`; reapertura → texto «Consulta reabierta por».
- [ ] **Step 2: Ejecutar** `./vendor/bin/sail npm test -- respuestaAnteriorDisponibilidad AccionesRapidasDisponibilidad SeguimientoSolicitudMunicipal SeguimientoSolicitudNacional SeguimientoSolicitudTienda` — Expected: FAIL.
- [ ] **Step 3: Implementar** el cableado en los tres seguimientos.
- [ ] **Step 4: Ejecutar** el mismo comando más `PanelRespuestaDisponibilidad` y `AccionesRapidasDisponibilidad` — Expected: PASS (el panel interactivo y CEDIS siguen usando `ResumenCambiosDisponibilidad`).

### Task 4: Guía y comprobación visual

**Files:**
- Modify: `gestion-pedidos-docs/GELIA_GESTION_PEDIDOS_GUIA_OPERATIVA_V3.1_2026-11-21.md` (nueva fila en §15)

- [ ] **Step 1: Añadir la fila de §15** (fecha 24-nov-2026; regla: respuesta de disponibilidad única con filas verde/rojo/amarillo, anexo pendiente en amarillo, panel «Cambios del pedido» retirado de Tienda/Municipal/Nacional; regla sustituida: dos paneles con información repetida; casos: respuesta OK, con detalle + confirmado, retiro, anexo pendiente, reapertura, sin cambios).
- [ ] **Step 2: Compilar y revisar** con `./vendor/bin/sail npm run build` y revisar los tres seguimientos con datos ficticios a 375 px y 1280 px (según `GUIA_EVIDENCIA_VISUAL.md`); correr `ux-audit`. Expected: filas con color y texto de estado, sin desbordamiento horizontal.

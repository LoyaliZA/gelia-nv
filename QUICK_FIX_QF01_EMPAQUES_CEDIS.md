# QF-01 — Terminología contextual de empaques en preparación CEDIS

Fecha: 16 de octubre de 2026 (America/Mexico_City).

## Alcance

Corrección puntual de etiquetas en la consulta de mercancía CEDIS (modal «Responder consulta de mercancía», tarjetas y detalle). No altera persistencia, pesaje, logística, finanzas, evidencias, migraciones ni F01/Q8/Q9.

## Condiciones utilizadas

Helper compartido: `terminologiaEmpaqueConsultaCedis` en `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js`.

Prioridad (sin comparar textos visibles):

1. **Bolsas** si `es_traslado_interno_pdv` **o** `requiere_bultos_empaque` **o** `requiere_sucursal_destino`.
2. **Cajas** si `pedido.paqueteria.requiere_caja === true` (y no cayó en el punto 1).
3. **Bultos** (genérico) en cualquier otro caso sin clasificación segura.

No se usa solo `origen.requiere_logistica === false` como señal de envío a sucursal (hay paqueterías regionales sin Logística que no son traslados internos).

## Campo y backend

- El valor sigue capturándose y persistiendo en `numero_cajas`.
- En revisión de mercancía, `numero_cajas` continúa siendo cantidad estimada reutilizada (comentario existente en `ResponderPesajePedidoBmaService`: resguardos PDV vía `CantidadBultosEsperadaResguardoPdv`).
- No se generan cajas/bolsas físicas, pesos ni medidas definitivas.
- Mensajes de validación PHP y semántica de servicios: sin cambios.

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js` | Helper `terminologiaEmpaqueConsultaCedis` |
| `resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx` | Paso, CTA, campo, ayuda, intro y resumen contextuales |
| `resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx` | Etiqueta de cantidad en tarjeta |
| `resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis.jsx` | Etiqueta en datos operativos (consulta mercancía) |
| `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.validacion.test.js` | Casos QF-01 del helper |
| `resources/js/Pages/ControlPedidos/Cedis/Partials/q81Interfaz.test.jsx` | Espera «Bolsas» en traslado |
| `resources/js/Pages/ControlPedidos/Cedis/Partials/trasladosQ6Presentacion.test.jsx` | Espera «Bolsas» en detalle interno |
| `tests/Unit/ControlPedidos/check_revision_fisica_pesaje.php` | Self-check UI contextual |
| `tests/Unit/ControlPedidos/check_cedis_responder_pesaje_ui.php` | Self-check UI contextual |

## Dependencias detectadas (sin modificar)

- `numero_cajas` en consulta de mercancía → esperado en resguardo PDV (`CantidadBultosEsperadaResguardoPdv`).
- `paqueteria.requiere_caja` ya exige tipo de caja sin kilogramos (`exigeCajaSinPeso` en el modal).
- Flujo de pesaje completo (`!soloRevisiones`) ya usa «Cajas»/envíos; fuera de este QF.
- Excepciones (traslado interno empacado en cajas): aplazadas a propósito.

## Pruebas realizadas

- Vitest: `pedidosBmaStyles.validacion.test.js`, `q81Interfaz.test.jsx`, `trasladosQ6Presentacion.test.jsx` (24 OK).
- Self-checks PHP: `check_revision_fisica_pesaje.php`, `check_cedis_responder_pesaje_ui.php` (OK).
- Browser (16 oct 2026):
  - **App real** `http://100.75.11.59:9080/control-pedidos/cedis`: pedido **5625859** (traslado a CTC - CITY CENTER) muestra **Bolsas a preparar** en tarjeta y **Bolsas a preparar (aprox.)** = 1 en detalle → Datos operativos. Valor recuperado correctamente. Pedidos de paquetería con pesaje completo conservan **Envíos / Guía** (fuera del flujo de consulta de mercancía).
  - **Vista aislada** `scripts/qa/qf01-empaques/` (Vite :5179): tarjetas y modal con los tres casos — Bolsas / Cajas / Bultos; paso, campo, ayuda y resumen del modal coherentes. Viewport móvil 390×844: mismas etiquetas en tarjetas.

## Validación operativa

| Caso | Etiqueta | Evidencia browser |
|------|----------|-------------------|
| Traslado interno / envío a sucursal | Bolsas | App real 5625859 + preview SUCURSAL-BOLSAS |
| Paquetería con `requiere_caja` | Cajas | Preview PAQ-CAJAS (paso/campo/ayuda) |
| Sin clasificación segura | Bultos | Preview GEN-BULTOS |
| Persistencia `numero_cajas` | Sin cambio | Detalle real muestra 1 |

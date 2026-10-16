# F01 — Correcciones funcionales y visuales del flujo de pedidos

Intervención independiente de Q8 y Q9. No cambia objetivos, estados ni documentación histórica de esos checkpoints.

Modalidad revisada: **Envío con paquetería mediante Logística**.

**F01 no está terminado.** El bloqueo del modal de dirección quedó resuelto en escritorio y móvil. La persistencia de un cambio nuevo (guardar, cerrar, reabrir) y un conflicto real de concurrencia no se ejecutaron sobre pedidos reales.

## Estado por ítem

| Ítem | Estado |
| --- | --- |
| F01.4 Modal de enlaces de dirección | RESUELTO |
| F01.1 Separación de etapas (formulario de ventas) | PARCIALMENTE RESUELTO |
| F01.2 Cálculo automático de seguro | PARCIALMENTE RESUELTO |
| F01.3 Guardado y alertas | PARCIALMENTE RESUELTO |
| F01.5 Visor de PDF | PARCIALMENTE RESUELTO |
| F01.6 Acciones y navegación | PARCIALMENTE RESUELTO |
| F01.7 Regresión completa | PARCIALMENTE RESUELTO |

No hay ítems BLOQUEADOS. Quedan comprobaciones que el entorno no permitió sin alterar pedidos reales.

---

## F01.4 — Superposición del modal de dirección — RESUELTO

### Problema

El generador de enlaces aparecía detrás del modal del pedido y no se podía usar.

### Causa raíz

`ModalGenerarLinkDireccion` usaba `z-index: calc(var(--gelia-z-modal) + 20)` (9520). El pedido, al portarse a `body`, recibe `.gelia-modal-overlay` con `calc(var(--gelia-z-toast) + 1)` (10001). El diálogo hijo quedaba debajo y `usePedidoDialog` del pedido seguía siendo la capa superior (foco y Escape). El componente también se montaba en el portal aunque estuviera cerrado.

### Corrección

Misma capa que los diálogos anidados ya existentes (`ModalVistaPreviaDocumento`, `ModalConfirmarAccion`): overlay en `calc(var(--gelia-z-toast) + 2)`, `usePedidoDialog` propio y desmontaje cuando está cerrado.

### Archivos

- `resources/js/Pages/ControlPedidos/Partials/ModalGenerarLinkDireccion.jsx`

### Comportamiento

- Antes: el enlace quedaba detrás del pedido.
- Ahora: el enlace queda encima, se puede buscar cliente y cerrar para volver al pedido.

### Evidencia en navegador

- Escritorio, pedido 864556 abierto: el diálogo «Link de dirección» quedó al frente, con cliente 8611 y la acción «Registrar primera dirección». No se pulsó «Generar enlace» para no crear un enlace real.
- Al cerrar, el pedido seguía abierto.
- Móvil (390×844): el mismo diálogo se abrió desde «Más acciones», con campo de búsqueda usable, y se cerró de vuelta al listado.

---

## F01.1 — Separación de etapas — PARCIALMENTE RESUELTO

### Problema

Pesaje, respuesta de mercancía y cotización mezclaban dirección, costos, seguro y la foto del envío.

### Causa raíz

- `ModalResponderPesaje` renderizaba `DireccionPedidoResumen` antes de que existiera dirección.
- En `FASE_RESPUESTA`, `TarjetaEnvioPedido` en modo costos pedía costo de envío y seguro por caja. Cualquier monto en la línea hace que `ActualizarPedidoBmaService` ignore los totales del pedido y sume las cajas.
- `SeccionRevisionFisicaPedido` incluía «Foto por envío» dentro de la revisión de productos.

### Corrección

- Pesaje CEDIS: se quitó el bloque de dirección de entrega. Permanecen sucursal destino (solo revisiones internas), cajas, cantidades y pesos.
- Respuesta CEDIS: las tarjetas de envío quedan en lectura (pesos y documentos). La foto del envío se muestra al pie del envío. Las fotos de mercancía no se movieron.
- Cotización: costo de envío y adicional por caja. El seguro deja de capturarse por caja. El costo de envío del pedido, con desglose de cajas, es la suma (solo lectura).

Los campos del modelo no se eliminaron. Otras modalidades siguen viendo fotos de envío en revisión (`ocultarFotosEnvio` por defecto en falso) y el seguro por caja en auditoría (`mostrarSeguro` por defecto en verdadero).

### Archivos

- `resources/js/Pages/ControlPedidos/Partials/ModalResponderPesaje.jsx`
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`
- `resources/js/Pages/ControlPedidos/Partials/TarjetaEnvioPedido.jsx`
- `resources/js/Pages/ControlPedidos/Partials/SeccionRevisionFisicaPedido.jsx`

### Evidencia en navegador (pedido 864556, ESTAFETA, consulta cerrada)

- Respuesta CEDIS: revisión de productos intacta (3 piezas, una en mal estado con su foto). Envío 1 CAJA #21 con largo/ancho/alto y pesos real 5 kg, volumétrico 5.39 kg, cobrado 6 kg. Documento `kenzo-homme-edt-hombre.jpeg` bajo el envío. Sin campos de costo ni seguro en esa etapa.
- Cotización: texto «Costo de envío por caja. El seguro se calcula con la paquetería, no se captura aquí.» Campos de costo de envío, costo adicional y concepto. Costo de envío del pedido en solo lectura como suma. Casilla «Sin seguro».

### Pendiente

La bandeja CEDIS tenía 0 pendientes de pesaje. No se abrió «Responder pesaje» en el navegador. La ausencia de dirección y la cabecera de acciones se verificaron en el componente, no en pantalla.

---

## F01.2 — Seguro automático — PARCIALMENTE RESUELTO

### Problema

El seguro se tecleaba a mano por caja.

### Causa raíz

La fórmula ya existía y no se había borrado. Con `detalle_cajas`, un monto en la línea (incluido un seguro en 0) hace que el servidor descarte `costo_seguro` del pedido y sume las cajas. La captura manual de la etapa de respuesta anulaba `CalcularSeguroPedidoService`.

### Reglas recuperadas (no inventadas)

Origen: `app/Services/ControlPedidos/CalcularSeguroPedidoService.php` y `calcCostoSeguro` / `paqueteriaTieneCobertura` en `pedidosBmaStyles.js`.

Cobertura solo si el nombre de la paquetería, en mayúsculas y coincidencia exacta, es FEDEX, ESTAFETA o DHL.

| Paquetería | Fórmula |
| --- | --- |
| FEDEX, ESTAFETA | (flete sin reexpedición + mercancía) × 0.025 |
| DHL | (flete sin reexpedición + mercancía) × 0.02 + 51 |
| Cualquier otra | 0, sin cobertura |

La reexpedición de zona no entra en la base (`envioBaseSinReexpedicion`).

Si mercancía o flete vienen en blanco, no se calcula: DHL no debe inventar los $51. Un cero explícito sí entra en la fórmula (ESTAFETA 0, DHL 51).

Pedidos ya guardados con `aplica_seguro` en falso se tratan como decisión explícita al reabrir. Cambiar la paquetería en la sesión quita esa exclusión. Un pedido nuevo con paquetería cubierta y ambos montos capturados marca el seguro solo.

El importe del pedido se reparte en la primera línea de caja completa y 0 en el resto, para que la suma de cajas coincida con la fórmula del pedido. Si el desglose de flete está incompleto, no se escribe seguro en 0 (eso activaría el desglose y borraría totales heredados).

### Archivos

- `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js` (`distribuirSeguroEnLineas`)
- `resources/js/Pages/ControlPedidos/Partials/distribuirSeguroEnLineas.test.js`
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`
- `app/Services/ControlPedidos/ResuelveDatosPedidoBma.php`

### Pruebas automatizadas

- `CalcularSeguroPedidoServiceTest`: 5 pruebas, incluidas FEDEX 100+400 = 12.5, DHL 100+400 = 61, ESTAFETA 0+0 = 0, y reexpedición fuera de la base.
- `distribuirSeguroEnLineas.test.js`: FEDEX 30, DHL 61, paquetería sin cobertura 0; líneas incompletas omiten `costo_seguro`; líneas completas `[30, 0]`.
- `check_seguro_sin_aplica.php`: el resolver sigue usando `aplica_seguro` como condición y pone 0 cuando está apagado.

### Evidencia en navegador

En 864556 (ESTAFETA, mercancía y envío en $0, seguro ya desactivado) la cotización muestra «Sin seguro» y no pide el importe por caja. No se guardó un importe distinto: el pedido es de una clienta real y la fórmula con base 0 para ESTAFETA es 0, así que no demuestra el porcentaje.

### Pendiente

Falta un pedido de prueba, no productivo, con FEDEX o DHL, mercancía y flete distintos de cero, guardar, cerrar y reabrir para ver el importe calculado.

---

## F01.3 — Guardado y alertas — PARCIALMENTE RESUELTO

### Problema

«El servidor actualizó este pedido» y «Pedido final» ocupaban la captura. No estaba claro si el autoguardado persistía.

### Causa raíz

1. El listado reemplaza el pedido al cambiar `updated_at`. El efecto de sincronización tomaba el eco del propio autoguardado, con el formulario aún distinto del último fingerprint, como edición de otra sesión. Además copiaba `updated_at` del listado a la versión esperada y un autoguardado posterior podía pisar lo que CEDIS acababa de guardar.
2. «Pedido final» era `AvisoOperativoPedido` fijo en la sección de mercancía, no un estado de concurrencia.

El autoguardado sí hace POST a `control_pedidos.autoguardar` y solo entonces el encabezado pasa a «Guardado». Un 409 detiene el autoguardado. Eso ya existía; la alerta falsa venía del eco.

### Corrección

- La versión esperada vive en un ref que solo avanza al abrir y al recibir `updated_at` de un guardado exitoso.
- Mientras `autoguardando` es verdadero, el eco del listado no se consume.
- Si `updated_at` coincide con la versión confirmada, es el propio guardado y no hay aviso.
- Si la versión difiere y el formulario local no coincide con lo último persistido, se marca conflicto, se detiene el autoguardado y permanece el aviso con «Conservar copia y recargar».
- Aviso ordinario: una línea (Guardando, Guardado, Cambios pendientes, Error al guardar, Cambio en otra sesión). En error no concurrente hay «Reintentar».
- «Pedido final» pasó a una línea: «Capture el monto cuando la mercancía ya está confirmada».

No se quitó la validación de `updated_at_esperado`.

### Archivos

- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`
- `resources/js/Pages/ControlPedidos/Partials/EncabezadoPedido.jsx`

### Evidencia en navegador

Pedido 864556 reabierto: encabezado compacto «Guardado», sin el aviso grande. Los pesos, la caja y la revisión coinciden con lo ya guardado. No se modificó el pedido, así que no hay una escritura nueva comprobada de ida y vuelta.

### Pendiente

- Editar, esperar confirmación del servidor, cerrar y reabrir.
- Forzar un fallo de red y usar «Reintentar».
- Abrir el mismo pedido en dos sesiones y comprobar el 409.

Hasta eso, F01.3 sigue parcial y F01 no se da por cerrado.

---

## F01.5 — PDF — PARCIALMENTE RESUELTO

### Problema

El PDF abría en otra pestaña.

### Corrección

Se reutilizó `ModalVistaPreviaDocumento` (misma carga, sin duplicar). El visor usa hasta `min(72rem, 100vw - 1.5rem)` y el iframe `min(82dvh, 100dvh - 8rem)`. Siguen «Descargar» y «Abrir en pestaña».

En pesaje CEDIS, junto al almacén, «Ver PDF del pedido» abre ese mismo visor.

### Archivos

- `resources/js/Pages/ControlPedidos/Partials/ModalVistaPreviaDocumento.jsx`
- `resources/js/Pages/ControlPedidos/Partials/ModalResponderPesaje.jsx`

### Evidencia en navegador

Desde el pedido 864556, «Ver Pedido_20328.pdf» abrió el visor en la misma URL (no cambió de pestaña), con título, Descargar y Abrir en pestaña. El cuerpo del PDF se vio oscuro en el navegador embebido; la barra del visor mostraba página. No se distinguió si el archivo o el visor embebido dejaron la página en blanco.

### Seguimiento

El recuadro de Pedido compartido se veía vacío porque el PDF iba en un `iframe` y el visor del navegador no pinta dentro del modal. Ahora se dibujan las páginas con el mismo visor de páginas (`VisorPdfPaginas`). En el pedido 864556 se leyeron las 2 páginas del archivo `Pedido_20328.pdf`.

La evidencia del envío en la tarjeta de cajas era un enlace. Ahora usa la misma miniatura que el resto de evidencias. Al pulsarla se abre el visor con acercar, alejar y girar. Comprobado con `kenzo-homme-edp-hombre.jpeg`.

### Pendiente

Abrir «Ver PDF del pedido» dentro de Responder pesaje (bandeja CEDIS en cero).

---

## F01.6 — Acciones y navegación — PARCIALMENTE RESUELTO

### Corrección

- Responder pesaje: Atrás, avance de paso y Registrar consulta/pesaje están en la cabecera, con salto de línea si no caben. El pie fijo se quitó. El mismo bloque y el resumen de estado quedan al final del contenido, no ocupando altura durante el escaneo.
- En la revisión de ventas, la foto del envío ya no alarga la lista de productos.

### Evidencia

Revisión de productos del 864556 sin barra inferior fija de Atrás/Continuar de pesaje. La cabecera del modal CEDIS no se abrió en pantalla.

---

## Pruebas (F01.7)

| Comprobación | Resultado |
| --- | --- |
| 1. Pesaje sin dirección | Código. Cola CEDIS vacía, sin recorrido en pantalla. |
| 2. Cajas y pesos | Visto en 864556: CAJA #21, 5 / 5.39 / 6 kg. |
| 3. Respuesta de productos | Vista: 3 piezas, una en mal estado con foto propia. |
| 4. Foto en el envío | Vista: documento bajo Envío 1, no en la lista general. |
| 5. Costos en cotización | Vista: envío y adicional por caja; seguro no se captura ahí. |
| 6. Seguro automático con cobertura | Fórmula en pruebas PHP y JS. En pantalla, ESTAFETA con base $0 y seguro ya desactivado. |
| 7. Paquetería sin seguro | Prueba JS (importe 0). No recorrido en pantalla. |
| 8. Autoguardado real | Encabezado «Guardado» al reabrir. Sin escritura nueva. |
| 9. Alertas compactas | Vista: sin banner «Pedido final» ni «El servidor actualizó…». Conflicto real no provocado. |
| 10. Enlace de dirección | Escritorio encima del pedido; móvil usable. Enlace no generado. |
| 11. PDF amplio | Visor en la misma página. Contenido oscuro en el navegador embebido. |
| 12. PDF en revisión física | Botón en el modal de pesaje. No abierto (cola vacía). |
| 13. Móvil | Listado y modal de enlace. Pesaje y PDF no. |

Automatizadas en esta intervención:

- `./vendor/bin/sail artisan test --filter=CalcularSeguroPedidoServiceTest` — 5 passed.
- `./vendor/bin/sail php` sobre `check_seguro_sin_aplica.php` — OK.
- Vitest: `distribuirSeguroEnLineas.test.js`, `q81Interfaz.test.jsx`, `usePedidoDialog.test.js` — 14 passed.
- `./vendor/bin/sail npm run build` — publicación correcta.

No se ejecutó `migrate`, `migrate:fresh` ni `ALLOW_DESTRUCTIVE_DB`. No hay commit ni push.

## Abierto

1. Persistencia de una edición nueva y recuperación ante fallo o 409.
2. Importe de seguro distinto de cero, guardado y reabierto, en un pedido que no sea productivo.
3. Recorrido visual de Responder pesaje (dirección ausente, acciones arriba, PDF, navegación al final del scroll) cuando exista un pesaje pendiente de prueba.
4. Cuerpo del PDF en un visor con plugin, escritorio y móvil.

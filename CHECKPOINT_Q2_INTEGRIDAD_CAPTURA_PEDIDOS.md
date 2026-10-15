# CHECKPOINT Q2 — Integridad de captura y recuperación de pedidos

Fecha: 14 de octubre de 2026. Alcance: borrador de Registrar Pedidos. No incluye concurrencia (Q3), ni cambios de Q1 o Q7.

## 1. Defectos reproducidos

Antes de la corrección, un autoguardado HTTP 200 de un borrador con modalidad, entrega municipal, prioridad y folio adicional dejaba `solicitud_preparacion_json` en null. El modal, al montarse con esa intención en el pedido, mostraba el selector de modalidad vacío: el valor no estaba en el estado que pinta los controles.

La misma reproducción separó la pérdida real de una diferencia de presentación. Municipio, destinatario, teléfono, dirección, transporte y cobro no se recuperaban. Almacén, sucursal (si la clave no se reenvía), observaciones, montos y saldos ya vivían en `useForm` y en columnas del pedido.

Un guardado que solo enviaba comentarios recalculaba el pedido con defaults de alta y podía anular costo, mercancía, saldo y domicilio. `false` y `0` sí sobrevivían cuando la clave viajaba en el request; lo que faltaba era no tratar la ausencia como borrado.

Guardar no creaba tarea ni carátula. Ese comportamiento ya se cumplía y se conservó.

## 2. Matriz de campos

| Campo | Estado React | Persistencia anterior | Recuperación anterior | Corrección |
|---|---|---|---|---|
| Modalidad de preparación | `codigoModalidadPreparacion`, fuera de `useForm` | Solo al solicitar la tarea | Desde `tarea_preparacion.modalidad`; vacía en borrador | `solicitud_preparacion_json.codigo_modalidad`. Si hay tarea, la modalidad de la tarea sigue siendo la que se muestra |
| Almacén seleccionado | `data.almacen_id` | Columna `almacen_id` | `formDefaults` | Sin segunda copia. Un update parcial que omite la clave no la vacía |
| Sucursal destino | `data.sucursal_destino_id` | Columna, solo si la clave viene en el request | `formDefaults` | Igual. Omitir la clave no borra. `null` sigue siendo limpieza cuando el pedido admite destino. Si el control no aplica, el formulario conserva la regla actual de no enviarlo como destino de un envío |
| Prioridad mismo día | `prioridadMd` | Columna `prioridad_md`, escrita al crear la tarea | Siempre arrancaba en false | La columna se escribe en el borrador cuando la clave viene. `false` se guarda. Si se omite, no se apaga |
| Folio y tipo de referencia adicional | `folioReferencia`, `tipoReferencia` | `pedido_bma_referencias` solo al solicitar la tarea | Controles vacíos / tipo visual Cotización | Intención en JSON. No crea referencia oficial ni evento. Si ya existe una referencia vigente de cotización, pedido u otro, el control muestra esa, no el folio interno ni la remisión |
| Municipio destino | `entregaMunicipal`, fuera de `useForm` | Solo en la tarea, al solicitar | No volvía al borrador | JSON `entrega_municipal.municipio_destino` |
| Destinatario es el cliente | Mismo estado | Igual | El control volvía a marcado | Booleano en JSON. `false` se conserva |
| Destinatario alternativo y teléfono | Mismo estado | Igual | Se perdían | JSON. No se copian a `contacto_*_snapshot` ni a la tarea durante el autoguardado |
| Dirección o referencia municipal | Mismo estado | Igual | Se perdía | JSON |
| Paquetería municipal | `entregaMunicipal.catalogo_paqueteria_id` | La paquetería del pedido es otra columna | No volvía | JSON. No sustituye `pedidos_bma.catalogo_paqueteria_id` |
| Cobro municipal | `entregaMunicipal.modalidad_cobro` | `envio_por_cobrar` del pedido es otra decisión | El control volvía a Pagado | JSON `PAGADO` o `POR_COBRAR` |
| Observaciones | `data.comentarios_drive` | Columna `comentarios_drive` | `formDefaults` | Sin copia nueva. Un parcial que no trae la clave no la borra |
| Cliente, dirección de catálogo, montos, seguro, SAF | `useForm` | Columnas y aplicaciones SAF | `formDefaults` y servicios ya existentes | El update parcial ya no rellena estos campos con defaults de alta |
| Soporte PDF o foto | Documento del servidor; el binario no entra al borrador | Tabla de documentos | Servicios de descarga ya existentes | El guardado de la intención no borra el documento ni mete la ruta en el JSON |
| Borrador local v1/v2 | `localStorage` global | Solo `data` | Ignoraba modalidad y entrega | v2 nuevo incluye `usuario_id` y `captura_preparacion`. v1 y v2 sin esa captura no inventan municipio |

## 3. Contrato persistente

Se reutilizan columnas que ya eran la fuente de verdad: `almacen_id`, `sucursal_destino_id`, `prioridad_md`, `comentarios_drive`, cliente, dirección, montos y SAF.

Lo que no tenía representación antes de la tarea queda en `pedidos_bma.solicitud_preparacion_json` (nullable, versión 1):

- `codigo_modalidad`
- `tipo_referencia`, `folio_referencia`
- `entrega_municipal` con destinatario, teléfono, municipio, referencia, paquetería y cobro

No se duplica ahí la prioridad: sigue en `prioridad_md`.

Semántica de actualización:

- Clave ausente: se conserva el valor anterior.
- `null` en una clave presente: limpia solo esa clave.
- `false` y `0` se escriben.
- El merge no usa los defaults de `atributosPedidoBase` para rellenar lo omitido.
- Guardar la intención no crea tarea, carátula, traspaso, resguardo ni referencia oficial, y no cambia el estatus.
- El autoguardado no modifica filas de `pedido_bma_tareas_preparacion` ni sus snapshots.

La lectura de un histórico con JSON null devuelve `solicitud_preparacion: null`. No se fabrican municipio, teléfono ni folio.

## 4. Archivos

Nuevos:

- `database/migrations/2026_10_14_231500_add_solicitud_preparacion_json_to_pedidos_bma.php`
- `app/Services/ControlPedidos/FusionarSolicitudPreparacionPedido.php`
- `app/Services/ControlPedidos/PersistirIntencionPreparacionPedido.php`
- `resources/js/Pages/ControlPedidos/Partials/capturaPreparacionPedido.js`
- `tests/Feature/ControlPedidos/BorradorPreparacionContratoTest.php`
- `resources/js/Pages/ControlPedidos/Partials/modalPersistenciaContrato.test.jsx`

Modificados:

- `app/Models/ControlPedidos/PedidoBma.php`
- `app/Http/Requests/ControlPedidos/PedidoBmaRequestBase.php`
- `app/Services/ControlPedidos/ActualizarPedidoBmaService.php`
- `app/Services/ControlPedidos/CrearPedidoBmaService.php`
- `app/Services/ControlPedidos/ListarPedidosBmaService.php`
- `app/Http/Controllers/ControlPedidos/PedidoBmaController.php` (la respuesta de autoguardado añade `prioridad_md` y `solicitud_preparacion`)
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`
- `resources/js/Pages/ControlPedidos/Index.jsx` (el aviso de borrador local respeta el usuario)

No se reescribieron Q1 ni Q7. El controlador solo sumó dos campos a la respuesta JSON del autoguardado.

## 5. Frontend

El modal actual sigue igual de estructura. Los controles de modalidad, prioridad, folio y entrega municipal leen y escriben un estado que ahora se serializa con el borrador.

Al abrir un pedido, ese estado sale de `solicitud_preparacion` y de `prioridad_md`. Si hay tarea municipal, la entrega mostrada sale de los campos completos de la tarea (nombre, teléfono, municipio, referencia, paquetería y cobro), no del resumen recortado.

El autoguardado y el guardado manual envían la intención. Si el borrador nunca tuvo preparación y los controles siguen vacíos, la clave no se manda: así un histórico no recibe un municipio inventado.

La copia local se actualiza con `captura_preparacion` y `usuario_id`. No se borra antes de una respuesta correcta del servidor. Un borrador con otro `usuario_id`, o con otro `pedido_id` cuando se está editando un pedido distinto, no se aplica. v1 y v2 antiguos sin `captura_preparacion` restauran solo los campos que ya traían.

Cambiar de fase deja montada la fase visitada; el estado vive en el modal, no dentro del panel. Cambiar de modalidad oculta la entrega municipal y la vuelve a mostrar con los mismos valores. Los archivos no entran al JSON ni a `localStorage`.

El texto «Local · guardado» es solo la copia del navegador. «Servidor · {folio}» aparece después de una respuesta correcta, que incluye la intención confirmada. Un 409 sigue diciendo conflicto y no marca el borrador como guardado.

## 6. Backend

`PersistirIntencionPreparacionPedido` escribe prioridad y JSON. `FusionarSolicitudPreparacionPedido` hace el merge. `ActualizarPedidoBmaService` siembra el pedido actual y solo vuelca al `UPDATE` las columnas cuyas claves vinieron en el request. Crear un pedido nuevo sigue usando los defaults de alta y, si el alta trae intención, la guarda igual.

El listado anexa `referencia_captura` solo cuando hay una referencia vigente de captura, y completa `entrega_municipal` de la tarea con los campos almacenados. `transporte` del resumen se mantiene.

## 7. Migración

Aditiva: `solicitud_preparacion_json` JSON nullable. No borra ni renombra columnas. Pedidos anteriores quedan en null.

No se ejecutó en una base operativa. Las pruebas corrieron sobre sqlite `:memory:` con el guard de entorno `testing`.

Despliegue: aplicar la migración antes de depender de la columna. Si el código nuevo convive un momento con el esquema viejo, la escritura de la intención se omite cuando la columna no existe y la lectura devuelve null. Después de migrar, los borradores viejos siguen siendo válidos.

Para revertir el código, la columna puede quedarse. Bajar la migración elimina solo esa columna y, con ella, las intenciones guardadas después del despliegue. No toca tareas, documentos ni referencias oficiales.

## 8. Pruebas

Reproducción previa: el autoguardado respondía 200 y la modalidad persistida era null. El modal montado mostraba el selector de modalidad en blanco.

Después:

`./vendor/bin/sail artisan test tests/Feature/ControlPedidos/BorradorPreparacionContratoTest.php`

6 pruebas, 75 aserciones, en verde. Cubren borrador municipal completo, destinatario alternativo y `false`, prioridad, folio sin referencia oficial, almacén y sucursal conservados, parcial sin borrar montos ni domicilio, `null` / `false` / `0` / clave omitida, histórico sin JSON, tarea y carátula intactas, documento que sigue en su tabla y no dentro del JSON, estatus de borrador.

`npx vitest run resources/js/Pages/ControlPedidos/Partials/modalPersistenciaContrato.test.jsx`

3 pruebas en verde, con el modal montado. Comprueban los controles al reabrir, el cambio de modalidad ida y vuelta, el salto a Cotización y el regreso a Datos, el desmonte y un segundo montaje, la recuperación local v2 del mismo usuario, que v1 no inventa municipio, y que un local de otro usuario no pisa lo que vino del servidor.

También en verde, en la misma sesión: `DestinoSucursalPedidoBmaTest`, `Fase3FormularioProgresivoTest`, `ControlPedidosComplementoFase5Test`, `ConfirmarCaratulaIdentidadTest`, `destinoSucursalPedido.test.js`, `remisionBusqueda.test.jsx`.

`BusquedaRemisionDelegadoTest` no llegó a sus aserciones: `Permission denied` al abrir `storage/framework/testing/disks/public/pedidos_bma`. Es un fallo de disco de prueba, no una aserción de remisión. No se cambió Q7 para ocultarlo.

## 9. Antes y después

Antes, cerrar o recargar un borrador perdía modalidad, prioridad, folio adicional y entrega municipal, aunque la interfaz hubiera dicho que el servidor guardó. Un request incompleto podía poner costo, saldo o domicilio en el valor de un alta vacía.

Después, esa captura vuelve a los controles al reabrir el mismo pedido. Un request que solo trae comentarios deja montos, domicilio, prioridad e intención como estaban. La tarea ya creada no se reescribe.

## 10. Limitaciones

Q2 no hace seguro el guardado simultáneo. Siguen pendientes, para Q3: revisión monotónica, un solo escritor, idempotencia durable, resolución de 409, cola única de guardado manual y automático, y barrera antes de solicitar preparación.

Un borrador local v1 o v2 sin `usuario_id` todavía puede ofrecerse en «recuperar borrador» de un pedido nuevo en ese navegador. No se aplica encima de otro pedido abierto ni encima de un usuario distinto cuando el sello sí existe.

Si ya hay una referencia oficial, el control muestra esa y no una intención JSON distinta. El autoguardado no crea una segunda referencia.

La paquetería del pedido y la paquetería municipal siguen siendo dos controles. Unificarlas es Q9.

Cuando el control de sucursal no aplica, el guardado manual sigue pudiendo limpiar un destino incompatible. Eso es la regla actual de envío, no una recuperación de borrador.

## 11. Preparación para Q3

La intención ya tiene un lugar estable, el merge distingue omisión de null, y la respuesta de autoguardado devuelve la intención confirmada. Q3 puede colgar de ese contrato la revisión, la idempotencia y la barrera previa a solicitar, sin volver a inventar dónde viven estos campos.

No se inició Q3.

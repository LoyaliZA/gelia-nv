# Vista única de respuesta de disponibilidad (diseño corto)

Fecha: 24-nov-2026. Estado: pendiente de aprobación.

## Intención y por qué
Ventas espera la respuesta de Tienda/CEDIS. Hoy ve dos paneles con información repetida: la tabla «Respuesta de disponibilidad» y «Cambios del pedido» (tarjetas de anexo, retiro y estado confirmado). Las acciones «Estado confirmado con el cliente» y «Retiro registrado» aparecen en ambos. Se unifica en **una sola tabla** donde el color de cada fila dice de un vistazo qué salió bien y qué no.

## Objetivos
- Una sola vista (`RespuestaDisponibilidadPedido`) con respuesta, resultados y acciones por pieza.
- Color por estado de la fila: **verde** = OK / estado confirmado con el cliente; **rojo** = sin existencias / retiro; **amarillo** = pendiente (anexo por revisar, avance pendiente, con detalle sin decisión). El amarillo no se reutiliza para confirmado.
- Aplica igual a Tienda, Municipal y Nacional (mismo componente en `SeguimientoSolicitudTienda`, `...Municipal`, `...Nacional`).
- Historial de movimientos anteriores y la reapertura se conservan, plegados al pie de la misma tabla.

## No objetivos / anti-objetivos
- No cambiar el panel interactivo de CEDIS/Tienda (`PanelRespuestaDisponibilidad`, «Cambios por revisar» con Todo OK / Con detalle / Sin existencias); es otra pantalla.
- No cambiar backend, claves de solicitud, ni identidades de unidad (`unidad_id`); no perder la trazabilidad de movimientos.
- No comunicar el estado solo con color: cada fila lleva texto de estado (accesibilidad).
- No reintroducir el panel «Decisión del cliente» ni códigos de ficha visibles.

## Enfoque elegido (opción 1)
`ResumenCambiosDisponibilidad` deja de renderizarse en los seguimientos de Ventas. Los datos que ya calcula `cambiosDisponibilidad()` entran como filas de la tabla:
- anexo solicitado pendiente → fila amarilla «Por revisar», con miniatura de la captura;
- retiro solicitado → fila roja «Retiro solicitado»;
- estado confirmado → fila verde «Confirmado con el cliente»;
- piezas de la respuesta con su acción (Estado confirmado / Retiro registrado / Sustitución) reciben el color de esa acción.
Movimientos anteriores (`particionarCambiosConsulta().anteriores`) van en un `<details>` al pie.
Descartadas: (2) tabla + banda de movimientos (siguen dos lugares donde mirar); (3) solo colorear (no cumple el objetivo).

## Restricciones
- Reutilizar tokens de color y primitivas existentes (`tienda-respuesta-estado[data-tono]`, `--color-peligro`, tono de aviso/éxito); respetar tema y acento.
- Pasar viewports 375 px y 1280 px; en móvil las filas ya colapsan por `data-label`.
- `ResumenCambiosDisponibilidad` sigue usándose en CEDIS (`ModalResponderPesaje`, `AlternativasDisponibilidadCedis`) y `AjustesSolicitadosTienda`; solo se retira de los tres seguimientos.
- Actualizar guía operativa V3.1 §15 y las pruebas que buscan «Cambios del pedido».

## Para el implementador
Estructura exacta de la fila combinada, nombre de los tonos CSS y la plegado del historial; verificar con los casos: respuesta OK, con detalle + confirmado, retiro, anexo pendiente, reapertura, y sin cambios.

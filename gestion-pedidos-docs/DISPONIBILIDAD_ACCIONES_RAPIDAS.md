# Disponibilidad: acciones rápidas y claridad de Mismo día

Decisión e implementación del 5 de noviembre de 2026. Esta instrucción posterior del usuario sustituye el cierre anterior que permitía aceptar excepciones en conjunto sin resolver cada detalle. No se restaura el antiguo panel «Decisión del cliente»: las acciones están en la propia pieza de la respuesta. Las piezas correctas del conjunto no requieren recaptura individual.

## Comportamiento

- Una pieza con detalle ofrece **Confirmar estado**, **Retirar** y **Sustituir**. Sin existencias ofrece exclusivamente **Retirar** y **Sustituir**. La confirmación reconoce el estado reportado al cliente, no crea inventario ni cambia el resultado físico de la revisión.
- El servidor bloquea el cierre de Tienda/CEDIS mientras exista un faltante abierto, un detalle sin decisión o un ajuste pendiente. La separación de Tienda también consume ese bloqueo. El recorrido de transferencia no habilita preparación/salida con faltantes abiertos.
- La sustitución conserva la revisión original y registra la alternativa por producto del catálogo o por una captura/PDF pegado o adjuntado. No se reemplaza el documento original. La nueva alternativa espera revisión de quien respondió; Ventas debe cerrar la consulta después de esa respuesta.
- En una consulta documental sin separación ni compromisos activos, retirar/sustituir es una modificación documental: no libera stock. Si hay separación o compromisos físicos, el retiro continúa por el servicio existente y requiere la actuación del auxiliar. No se permite cambiar informalmente un pedido con pago verificado o remisión; esos casos mantienen su gestión documentada. Los importes, pagos y remisiones no se recalculan ni borran por una acción rápida.
- CEDIS ve las decisiones gráficamente y puede identificar/responder la alternativa pendiente. Una alternativa nuevamente sin existencias crea una revisión faltante y mantiene bloqueado el cierre. Tienda ve el mismo resumen y atiende el anexo con su servicio actual, productos, foto y facultades del auxiliar asignado. Responder disponibilidad no confirma separación física.
- **Modificar pedido** despliega la captura detallada de anexo/retiro. Su historial y las evidencias enviadas permanecen en **Cambios guardados del pedido**, aun con esa captura plegada.
- Un pedido anterior cerrado con un faltante muestra arriba **Consulta cerrada con piezas sin existencias**, en rojo de contraste verificado. **Reabrir consulta** opera en el mismo modal y notifica a Tienda/CEDIS. Conserva respuesta, documentos, cobertura y reporte original de Mismo día. La reapertura se permite antes de preparar/trasladar; no reabre pedidos entregados o cancelados. Si hay pago verificado/remisión, reabrir no habilita un retiro informal ni modifica importes.
- Reabrir una consulta CEDIS devuelve el pedido a su bandeja de consulta; cerrar de nuevo lo marca como consulta respondida. Un nuevo anexo documental CEDIS también vuelve a esa bandeja. No se activa pesaje de envíos en el flujo de Tienda.

## Persistencia

Se corrigió el callback del anexo: enviaba una función a un padre que esperaba la solicitud completa; el estado local podía dejar de representar lo guardado. El endpoint ahora devuelve la solicitud conservada y el formulario admite correctamente ambos tipos de actualización.

Registrar pedido consulta el estado actual al abrirse. Mantiene los ajustes deshabilitados hasta leerlo, ofrece reintento y evita sustituir una lectura más reciente por una fila antigua del listado. CEDIS también actualiza su lectura al abrir disponibilidad. Anexos/retiros detallados y acciones rápidas incluyen claves de solicitud para que un reintento no duplique registros o archivos. Se añadió una generación UUID compatible con HTTP de la red local, comprobada en navegador. Las claves se conservan al normalizar/guardar la operación.

## Presentación de Mismo día

La tarjeta prioriza el pendiente actual y la sucursal/fecha de recolección. Procedencia, ingreso, remisión y primer reporte quedan en **Datos y documentos del recorrido**; historial y gestiones tienen su propio desplegable. La captura de seguimiento, el cambio de destino y el acuerdo posterior se abren mediante acciones explícitas. Los botones secundarios usan la primitiva GELIA con borde e iconos. Traslado entre sucursales aparece en custodia o cuando tiene sentido en su etapa; no se ofrece como paso inicial de Mismo día.

Se mantienen corte, días configurados, primer reporte inmutable, verificación del ingreso, remisión, bolsas, salida, recepción, custodia y entrega. Reabrir consulta o plegar información no vuelve oportuno un reporte tardío ni reprograma el pedido.

Referencia utilizada: Registrar pedidos, paneles de respuesta y galería GELIA. Se aplicó la skill `frontend-design` dentro de la dirección del repositorio, conservando fuente, tema y acento configurados. No se añadieron librerías ni movimiento.

## Comprobación y límites

- Regresión dirigida de SolicitudRegistrarTienda y TransferenciaTienda: 64 pruebas aprobadas; se detectó un test cuyo contrato anterior permitía cerrar un detalle sin decisión y se actualizó a la nueva regla solicitada. El cierre corregido y las ampliaciones se comprobaron después de forma dirigida, sin repetir la suite general.
- Lote final de ocho escenarios de servidor aprobado, 113 aserciones: cierre con faltante, confirmación de detalle, retirada idempotente, sustituciones por SKU/captura, nueva falta de disponibilidad, respuesta de Tienda sin liberación indebida, conservación del reporte MD y reintento de anexo sin documentos duplicados. Dos escenarios de reapertura/Tienda se verificaron además tras el último refuerzo de etapas. Pruebas con SQLite en memoria y configuración/rutas de prueba aisladas, mediante Sail.
- 25 pruebas de componentes aprobadas: acciones por pieza, cierre/reapertura, captura detallada plegada, historial persistente, UUID por HTTP y regresiones de respuesta, Tienda y transferencia.
- Ocho capturas del build publicado con datos ficticios: alerta cerrada, sustitución por captura, resumen Mismo día y cambios vistos por CEDIS, en escritorio oscuro 1440×1000 y móvil claro 390×844. Sin errores JavaScript ni desbordamiento; contraste del texto de la alerta al menos 4.5:1 en ambos temas; controles secundarios de MD con borde e iconos. Inspección visual y corrección del texto de resultado en móvil, sin dividir «existencias» letra por letra.
- [Capturas y reporte](../resources/design-references/tienda-disponibilidad-acciones-2026-11-05/) y [guion de comprobación](../scripts/qa/tienda-recorrido/verificar-disponibilidad-acciones.mjs). La red se intercepta: no es una sesión autenticada ni acredita recepción real de notificaciones. La persistencia se comprueba con endpoints/pruebas de servidor, no se deduce de una captura.
- Pendiente: recorrido conjunto con vendedora, auxiliar y CEDIS reales. No se modificó el pedido real de la captura del usuario ni se reabrió automáticamente. La recuperación se inicia al pulsar Reabrir consulta. Tampoco se alteró el catálogo de City Center.

Build publicado con Sail, assets anteriores conservados, sin `public/hot`; rutas publicadas al finalizar. Sin migraciones, commit ni push. Conservar el trabajo previo de traslados que sigue en el árbol local.

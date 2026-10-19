# SOLICITUD DE MEJORA - AUDITORÍA DE PEDIDOS - CUBIERTO

## Solicitud inicial
Para los pedidos que son solo de tienda se pidió una ficha más sencilla, parecida a la referencia enviada. Hoy quien registra y quien audita ven datos de envío que no usan en ese tipo de pedido. La información debía mostrarse según el tipo de pedido, también en la revisión de la persona auxiliar.

## Acciones
Se revisó la revisión de pagos y la captura de pedidos de tienda. Se dejó un prototipo aparte, con dos presentaciones. La recomendada es una ficha de una sola columna: pedido, clientela, comprobante, total de la compra y archivo de tienda. La otra conserva un panel más ancho, solo como contraste. Los botones simulan validar, rechazar y aprobar; no guardan nada en el sistema.

## Cambios
La pantalla en uso no cambió. La propuesta está en `docs/visual-refresh/auditoria-tienda/prototipo.html`, con el detalle de campos en `brief.md`. Recoge hoy, envíos y crédito sin comprobante quedan fuera de esa ficha, como ya funciona el proceso.

## Puntos a revisar
Abrir el prototipo y recorrer un pedido de tienda pendiente, uno pagado, un rechazo con motivo y uno sin archivo. Confirmar que no aparecen paquetería, cajas ni pesos. Elegir si esa ficha corta es la que debe pasar a la revisión real.

## Mejoras posibles
Si se elige la ficha corta, el siguiente paso es aplicarla en la revisión de la persona auxiliar y en la captura de pedidos de tienda, sin cambiar permisos ni montos.

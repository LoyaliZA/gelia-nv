# SOLICITUD DE MEJORA - CONTROL DE PEDIDOS - CUBIERTO

## Solicitud inicial
Un guardado dejó el envío de un pedido con pesaje en $0.00 sobre un costo ya capturado, sin confirmación ni historial. Al enviar, ese cero se aceptaba y el pedido parecía cubierto aunque la paquetería comercial tuviera peso cobrado. Se pidió impedir esa pérdida, dejar constancia del cambio y poder corregir el mismo pedido sin eliminarlo.

## Acciones
Se revisó el guardado del encabezado, el autoguardado del formulario y la validación al enviar. Se agregó un candado que conserva el monto anterior, un historial del cambio y la misma regla en el envío del pedido. Se cubrió el comportamiento con pruebas del módulo.

## Cambios
- Guardar o enviar con el envío en vacío o en cero, cuando ya había un costo mayor a cero en un pedido con pesaje y paquetería comercial, pide confirmación y un motivo de al menos 5 caracteres. Sin eso, el monto anterior permanece.
- El autoguardado ya no borra ese costo si el campo queda vacío o en cero. El resto del avance sí se guarda.
- Cada cambio real del costo de envío del encabezado queda en el historial, con monto anterior, monto nuevo, quien guardó y el motivo si hubo anulación.
- Si el pago ya estaba validado, anular o cambiar ese costo reabre la validación con el motivo indicado.
- Al enviar, una paquetería comercial con pesaje respondido y peso cobrado no avanza si el costo que entra a la cobertura queda en cero. Un desglose de cajas que sí suma un costo positivo sí puede avanzar. Guía del cliente y pedido sin pesaje pueden seguir sin costo.
- El pedido afectado no se elimina. Se corrige capturando de nuevo el envío y ajustando la exhibición al total que corresponda.

## Puntos a revisar
- En un pedido con pesaje, paquetería comercial y envío ya capturado, dejar el costo en cero y guardar: debe pedir motivo y no borrar el monto si se cancela.
- Confirmar la anulación y revisar que el historial muestre el monto anterior, el nuevo y el motivo.
- Enviar un pedido comercial con peso cobrado y envío en cero: debe detenerse hasta capturar un costo mayor a cero.
- Si el pago ya estaba validado, comprobar que el cambio de costo deja la validación pendiente de repetirse.

## Mejoras posibles
El desglose por caja conserva su propio registro de costos. Esta solicitud no cambia esa vía ni corrige por sí sola el pedido que ya quedó en cero: esa corrección se hace volviendo a capturar el envío y la exhibición.

# SOLICITUD DE MEJORA - REGISTRO DE PEDIDOS - CUBIERTO

## Solicitud inicial
En el registro de pedidos, función Envío, el seguro de DHL no coincidía con la fórmula: total de la compra + guía + 2% + $51. El importe quedaba $3 por debajo y ese faltante cambiaba el total a pagar.

## Acciones
La fórmula de DHL ya era (total de la compra + guía) × 2% + $51. El desfase venía de restar otra vez el cargo de reexpedición sobre una guía que ya no lo incluía. Con un cargo de zona de $150, el 2% de esa resta son exactamente $3.

El cálculo del seguro usa ahora la guía que se ve en la cotización, sin volver a descontar la reexpedición.

## Cambios
- Cotización del pedido con DHL: el seguro y el total a pagar suben esos $3 cuando hay reexpedición de $150.
- FedEx y Estafeta usan la misma guía. Con el mismo cargo de $150, su seguro deja de quedar $3.75 abajo.
- Un pedido ya guardado toma el importe corregido al reabrirlo y guardarlo, antes de subir el pago.

## Puntos a revisar
Reabrir el pedido con DHL y reexpedición, confirmar que el seguro coincide con (compra + guía) × 2% + $51 y que el total a pagar subió $3. Después sí subir el comprobante.

## Mejoras posibles
No quedan pendientes de esta solicitud.

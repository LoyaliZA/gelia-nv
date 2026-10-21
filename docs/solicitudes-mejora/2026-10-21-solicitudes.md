# SOLICITUD DE MEJORA - SOLICITUDES - CUBIERTO

## Solicitud inicial
Al cotizar una compra para pasar a MAYOREO PLATA, el registro de solicitudes indicaba que el monto sí alcanzaba esa lista, pero no permitía seleccionarla. En el ejercicio de escalonamiento el mismo caso sí quedaba en PLATA.

## Acciones
Se comparó el cálculo del formulario con el del ejercicio, usando el acumulado ya pagado y la cotización del caso reportado. El ejercicio toma lo ya pagado más la cotización con el descuento adicional de la lista destino. El formulario comparaba la suma bruta contra un mínimo que también inflaba lo ya pagado, y por eso bloqueaba PLATA y dejaba MAYOREO BRONCE.

## Cambios
El registro de solicitudes y el guardado usan ahora la misma regla que el ejercicio. Si el neto proyectado cubre el mínimo de la lista, esa lista se puede elegir y queda propuesta. Lo ya pagado no se vuelve a descontar.

## Puntos a revisar
Conviene repetir una cotización de ascenso a PLATA, ORO y DIAMANTE cuando ya existe un acumulado pagado, y confirmar que la lista propuesta se guarda en la solicitud.

## Mejoras posibles
No quedan pendientes de esta solicitud.

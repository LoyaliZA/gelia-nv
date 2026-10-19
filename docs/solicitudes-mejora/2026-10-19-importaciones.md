# SOLICITUD DE MEJORA - IMPORTACIONES - CUBIERTO

## Solicitud inicial
Después de simular una importación de inventario, al indicar aplicar en segundo plano el sistema respondía que había que simular antes de aplicar, aunque la simulación ya se había ejecutado.

## Acciones
Se revisó la pantalla de la importación. El aviso usaba el estado con el que se había abierto la página y no el estado actualizado al terminar la simulación.

## Cambios
Al completar la simulación, la pantalla reconoce ese resultado y permite aplicar los cambios sin exigir una simulación nueva. Si el mapeo cambia después de simular, se mantiene el aviso de volver a simular.

## Puntos a revisar
Abrir un lote de importación, simular y aplicar en segundo plano en la misma visita, sin recargar la página. Confirmar que, si se modifica el mapeo, el sistema vuelve a pedir la simulación.

## Mejoras posibles
La revisión general de cómo explicar el almacén destino y cómo evitar productos duplicados quedó fuera de esta corrección. Conviene atenderla en una solicitud aparte de importación de inventario.

# SOLICITUD DE MEJORA - IMPORTACIONES DE ALMACÉN - CUBIERTO

## Solicitud inicial

Hacer más clara la carga de productos e inventarios: distinguir altas y actualizaciones, usar el folio como identificador que no cambia, y vincular el archivo a un almacén elegido en pantalla.

## Acciones

Se reemplazaron las casillas combinables por cuatro casos: Productos, Costos y precios, Existencias y Carga completa. La fila se reconoce solo por folio. La simulación muestra altas, cambios y errores antes de aplicar.

## Cambios

En Importaciones se elige un caso, la sucursal y el almacén, y se descarga la plantilla de ese caso. Un folio existente actualiza; un folio vacío o nuevo crea el producto en Productos y en Carga completa. Costos y existencias no crean productos. Una celda vacía no borra el dato guardado. El acceso desde productos, costos e inventarios abre el caso correspondiente.

## Puntos a revisar

Conviene probar un archivo pequeño de cada caso: un folio conocido, un folio vacío, un folio nuevo y un folio que no existe en costos. Confirmar que el SKU de otro producto no se mezcla y que el vínculo queda en el almacén elegido. La pantalla nueva se verá cuando se publique el build del frontend.

## Mejoras posibles

Quedó fuera el rediseño de las pantallas de productos, costos e inventarios, y el asistente antiguo de catálogo maestro, que sigue identificando por SKU.

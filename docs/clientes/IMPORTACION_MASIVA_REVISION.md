# Revisión de la carga masiva de clientes

La pantalla de Clientes conserva su diseño. La persona administradora elige el CSV y pulsa «Revisar archivo». Esa revisión no escribe clientes. Después confirma solo esa revisión.

## Qué muestra la revisión

Filas, clientes nuevos, actualizaciones, sin cambios, filas con error, cambios de lista, descensos, activaciones, inactivaciones propuestas y cambios de monto. Lista hasta 25 clientes afectados y el motivo. El resto queda contado.

## Qué detiene la escritura

- Un `codigo_lista` vacío no cambia estado ni lista. Un monto cero no es una baja.
- El archivo no puede inactivar. GELIA todavía no define una señal explícita de baja.
- No se reemplaza una lista protegida.
- Un descenso de lista y una diferencia de monto de 1000 o más quedan avisados. No se corrigen solos. Si la revisión no está bloqueada, confirmar aplica exactamente esos cambios.
- Un archivo con cabeceras, columnas o filas contradictorias se rechaza antes de escribir clientes.
- Un archivo ya importado, completo o parcial, no se vuelve a escribir.
- Si el archivo trae `fecha_corte` o `fecha_exportacion` y esa fecha es anterior al último historial de monto, no se escribe. Sin una fecha legible no hay evidencia de antigüedad.
- La confirmación usa el identificador y el token de esa revisión. Si el archivo o el estado de esos clientes cambió, hay que revisar de nuevo. La ruta anterior de importación directa ya no escribe.

## Resultado

Al confirmar se ven procesadas, omitidas, errores con causa y los cambios. El historial de la carga se conserva. Cada fila sigue confirmándose por separado. Si la carga se interrumpe, el resultado indica las filas ya confirmadas y las que no se escribieron.

Las 95 diferencias del 12 de noviembre de 2026 siguen pendientes. Esta revisión no inventa montos ni llama a la conciliación documental.

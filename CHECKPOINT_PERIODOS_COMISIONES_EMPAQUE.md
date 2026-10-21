# Comisiones de empaque: reporte y reparto permanente

Fecha: 20 de octubre de 2026, America/Mexico_City.

Este documento describe la implementación final y sustituye la propuesta anterior que capturaba colaboradores y porcentajes al crear cada periodo.

## Contrato operativo

Cada caja aporta la tarifa fija del área a un fondo común. El fondo representa el 100%. La encargada asigna porcentajes permanentes, por ejemplo 50/30/10/10, que se conservan en todas las operaciones y periodos del área hasta que se cambian. Quién empacó cada caja es un dato de trazabilidad; no determina la comisión individual.

Los periodos solo definen nombre y fechas. Crear otro periodo no duplica ni solicita nuevamente el reparto. El equipo permanente vigente se aplica al acumulado abierto. Guardar otro reparto cambia las estimaciones abiertas y conserva versiones anteriores; un corte confirmado conserva sus porcentajes e importes.

Si alguien sale el 13: cambiar el fin del periodo al 13 con motivo, revisar y confirmar el corte anterior, actualizar el equipo permanente y sus porcentajes, y crear el siguiente periodo desde el 14. Cerrar y crear son acciones separadas. El saldo de quien salió permanece por pagar hasta registrar su pago.

La tarifa general por caja se mantiene por área. No se implementaron tarifas por tipo de empaque; esa ampliación futura es independiente del reparto entre colaboradores.

## Interfaz

- Empaques es el inicio: tabla de reporte, Todos / Por revisar / Revisados, búsqueda por cliente y remisión. Los demás filtros quedan desplegables.
- Área y periodo del reporte están a la vista. Los filtros del reporte, conteos y fondo abierto comparten el mismo contexto. Los importes por pagar representan saldos de cortes y se consultan por separado.
- La tabla presenta fecha, cliente y remisión, quién empacó, cajas, envío, aportación al fondo y revisión. Cada empaque abre su detalle y las acciones existentes de revisión o clasificación. La captura manual se abre bajo demanda.
- Equipo y reparto permite buscar cuentas, agregar o retirar personas, ajustar sus porcentajes y registrar el motivo. No exige dar de alta participantes previamente: el guardado vincula la cuenta a su registro de comisiones. No hay distribución equitativa automática.
- Periodos permite crear fechas, cambiarlas con motivo y cerrar mediante la revisión del cálculo. Los periodos abiertos muestran el reparto permanente; los cerrados muestran las asignaciones efectivamente confirmadas.
- Por pagar e Historial de cortes respetan el área seleccionada. Los pagos y sus exportaciones conservan el alcance por área, incluso cuando un pago tiene aplicaciones de distintas áreas.
- El modal general se retiró. Tarifa y administración abre una página; permisos, importación, áreas y cuentas se encuentran en Administración avanzada.
- La búsqueda de usuarios limpia selecciones anteriores, descarta respuestas antiguas y muestra fallos. El editor del equipo advierte al navegar con cambios sin guardar. La edición incluye versión para rechazar un guardado atrasado sobre un reparto que otra persona ya cambió.

## Datos y compatibilidad

La migración `2026_10_20_110000_add_periodos_to_comisiones_empaque_repartos.php`, ya aplicada en la primera iteración local, añade metadatos opcionales a los repartos.

La migración `2026_10_20_130000_separar_periodos_y_equipo_comisiones_empaque.php` separa:

- `comisiones_empaque_periodos`: fechas, nombre, motivo y referencia al reparto anterior si proviene de la primera implementación.
- `comisiones_empaque_repartos.permanente`: versiones del equipo permanente. Sus líneas siguen usando los participantes y porcentajes existentes.

La migración copia los periodos con nombre y genera un equipo permanente inicial con las líneas del último reparto registrado de cada área. No elimina ni modifica las asignaciones o los importes históricos. La prueba de migración comprueba la conservación de un corte y la copia de su configuración anterior.

Los cortes nuevos usan el reparto permanente vigente sobre el fondo completo. Las versiones antiguas y sus asignaciones permanecen disponibles. El cálculo incluye la versión y los porcentajes en su huella; cambiar de equipo invalida una revisión anterior. Editar el equipo, crear o cambiar periodos y confirmar cortes se serializa por área. Los periodos no pueden solaparse ni cambiar fechas una vez confirmado su corte. El envío de porcentajes al endpoint de periodos se rechaza.

Los formularios usan transformación y envío separados, compatibles con la versión instalada de Inertia. Los pagos limpian la selección y renuevan su clave de idempotencia después del éxito.

## Validación

- Backend: **44 pruebas, 227 aserciones**, pasan con PHP 8.4 y SQLite en memoria.
- Interfaz: **8 pruebas**, pasan.
- Compilación del frontend: termina; conserva el aviso general sobre tamaño de archivos compilados.
- Formato PHP y comprobación de espacios del cambio: pasan.
- La comprobación global de estilos falla por literales anteriores en `Profile/Edit.jsx` y `Solicitudes/Partials/ModalFormSolicitud.jsx`, fuera del módulo. No se cambiaron esas páginas ni la línea base.

Las pruebas cubren fondo colectivo aunque una sola persona empaca todas las cajas; reparto permanente entre periodos; 50/30/10/10 y cambio a 60/30/10; conservación del saldo de quien sale; suma exacta de 100%; cuentas repetidas; permisos y separación por área; versión atrasada del editor; huella del cálculo; solapes; periodos sin porcentajes; conversión del esquema anterior; filtros y conteos; exportación de un pago compartido entre áreas por el importe que corresponde a cada área.

## Recorrido autenticado

Se usó Chromium con una base aislada `/tmp/ce-permanente-qa.sqlite`, cuentas y pedidos de ejemplo. Capturas y auxiliares en `tmp/ce-permanente-qa/`.

Se comprobó:

1. Reporte sin captura manual permanente y navegación por pendientes.
2. Equipo de cuatro con 50/30/10/10 sobre el fondo común.
3. Cambio de fecha final al 13 con motivo y confirmación de $1,000: $500, $300, $100 y $100.
4. Retirar a la cuarta persona y guardar 60/30/10 como reparto permanente.
5. Crear el periodo del 14 al 15 sin capturar porcentajes.
6. Confirmar $900: $540, $270 y $90.
7. Conservar ambos repartos en los periodos cerrados.
8. Saldo inicial de la primera colaboradora de $1,040; pagos parciales de $100 y $50; saldo final de $890.
9. Consultar Tienda sin arrastrar los saldos de CEDIS.
10. Administración en página sin modal, escritorio de 1440 px y móvil de 390 px sin desborde horizontal.

Una primera comprobación de crear el siguiente periodo detectó que el encabezado podía reiniciar un formulario en edición. Se ocultó ese botón mientras existe un editor; el recorrido posterior completó el guardado. La revisión final del navegador no registró errores de JavaScript.

## Entrega local

Se aplicaron las nuevas migraciones locales de este cambio por ruta, se regeneraron las rutas de Sail y se compilaron los recursos. No se ejecutaron otras migraciones pendientes. La migración crea la versión inicial del equipo conservando la configuración existente; no se crearon cortes o pagos de prueba en la base de operación.

No hubo commit, push ni despliegue de producción. El despliegue debe incluir ambas nuevas migraciones, rutas y recursos compilados. El navegador y el servidor de comprobación aislados se cierran al finalizar.

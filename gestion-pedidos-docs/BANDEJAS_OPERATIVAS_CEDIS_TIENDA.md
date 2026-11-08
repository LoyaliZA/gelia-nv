# Bandejas operativas de CEDIS y Tienda

Decisión del usuario e implementación del 8 de noviembre de 2026. Alcance: dashboard, indicadores, filtros y orden del listado. Se conserva el trabajo local previo; no se modifican los servicios de consulta, respuesta, apartado, pago, remisión, traslado, custodia ni entrega.

## Presentación y filtros

- CEDIS: una sola selección por tipo con contador: **Todos**, **Para tienda**, **Mismo día**, **Municipales** y **Nacionales**. Sustituye los KPI y pestañas anteriores por etapas. Los resguardos se incluyen en Para tienda; Mismo día es excluyente para no duplicar pedidos entre contadores. Se reutiliza la clasificación de la jerarquía operativa existente, incluyendo su compatibilidad con pedidos legados.
- Tienda: **Todos**, **Pendientes**, **En atención** y **Atendidos**, sin una segunda fila de bandejas. Encabezado compacto, tarjetas como vista inicial y selector de tabla/tarjetas solo en escritorio. Una preferencia de escritorio guardada se conserva; móvil siempre presenta tarjetas.
- Ambas inician en Todos, sin límite de fechas y con los más antiguos primero dentro de la jerarquía de atención configurada. Cambiar a recientes invierte la fecha dentro de cada prioridad, sin sustituir la jerarquía.
- Búsqueda por folio/cliente; vendedora en campo propio; rango Desde/Hasta por fecha de solicitud; accesos **Hoy**, **Todas las fechas** y **Más antiguos**. Hoy usa la fecha del servidor en la zona operativa configurada. CEDIS usa solicitud de consulta/pesaje, con creación como respaldo; Tienda usa `solicitada_at`.
- En móvil, búsqueda y accesos rápidos quedan visibles; **Fechas, vendedora y orden** despliega los controles detallados. Los indicadores sustituyen métricas duplicadas. Se conservan fuente, tema, acento y cristal configurados.
- Filtros y orden se conservan al paginar, actualizar y recibir cambios. Las búsquedas diferidas se cancelan al elegir otro filtro para evitar recuperar una bandeja anterior. Limpiar vuelve a Todos.
- Los conteos de las nuevas categorías respetan búsqueda, vendedora y rango; no se limitan a la categoría seleccionada, de modo que permiten comparar las demás. El total del listado corresponde a la selección actual.

## Agrupación de Tienda y compatibilidad

Estos grupos son filtros de lectura; no crean ni sustituyen estados operativos:

- Pendientes: estado PENDIENTE.
- Atendidos: RESPONDIDA, RECIBIDA_CEDIS, LIBERADA, CANCELADA y SUSTITUIDA. Incluye el historial cerrado/cancelado para conservar acceso.
- En atención: los demás estados, incluyendo errores, rechazos, preparación, traslado y solicitud de liberación. Ninguna etapa queda fuera de Todos.

Se conservan los filtros antiguos en servidor por compatibilidad con enlaces existentes y otras consultas. No se alteran las restricciones por actor, departamento, sucursal ni los permisos de Bitácora. CEDIS conserva el acceso separado a Liberaciones pendientes, su permiso y su acción; no se convierte en una sexta categoría de pedidos.

## Comprobación y límites

- Sail, SQLite en memoria y cachés de configuración/rutas aisladas: 11 pruebas de servidor aprobadas, 77 aserciones. Incluyen clasificación excluyente/legados, fechas hasta fin del día, vendedora, orden antes de paginar, fechas inválidas, partición de las 13 etapas de Tienda, coherencia de contadores, jerarquía y visibilidad CEDIS por departamento.
- Interfaz: aprobadas las tres pruebas nuevas de filtros (fecha operativa, conservación al cambiar bandeja/página, búsqueda diferida y limpieza), conexión del listado con el modal de Tienda y nueve escenarios del modal de preparación. También pasan dos comprobaciones de almacén y ocho de las diez de tarjetas CEDIS.
- Dos pruebas existentes de `q81Interfaz.test.jsx` siguen fallando porque esperan `Folio Pedido: PED-81`, mientras el componente versionado ya utiliza `etiquetaPrincipal="Folio"`. No se modificó ese componente ni se cambiaron las expectativas para ocultar el fallo.
- Build publicado mediante Sail, archivos y manifiesto verificados, assets anteriores conservados y sin `public/hot`. PHP de ambos controladores sin errores de sintaxis; `git diff --check` aprobado.
- El guard de estilo JSX no pudo ejecutarse por ausencia de `docs/style-system/baseline/jsx-hex-allowlist.tsv`. No se regeneró esa lista. Los componentes nuevos consumen tokens/primitivas y no incorporan colores hexadecimales.
- [Guion reproducible](../scripts/qa/tienda-recorrido/verificar-bandejas-operativas.mjs): ocho escenarios de las páginas reales del build, CEDIS/Tienda a 390×844 y 1440×1000, claro/oscuro con cristal. 24 capturas de cabecera, filtros desplegados y pedidos; sin errores JavaScript ni desbordamiento horizontal. Se ejercitaron Hoy, Más antiguos y cambio de tipo/estado mediante listados JSON interceptados. Inspección visual de ambas áreas en móvil y de escritorio oscuro; se compactó la cabecera de Tienda y se corrigió la alineación de Vendedora.
- [Capturas y reporte](../resources/design-references/bandejas-operativas-2026-11-08/). Datos y transporte ficticios: no acreditan un recorrido autenticado entre cuentas ni acciones de negocio reales. Ese recorrido continúa pendiente. No se escribieron pedidos operativos, ni se ejecutaron migraciones, commit o push.

Referencias de diseño: encabezado operativo de Punto de venta, paneles, controles y tarjetas GELIA existentes. Se mantuvieron las acciones y los modales de cada listado.

# Consultas compartidas — corte de implementación de etapas 1 y 2

Fecha: 17 de noviembre de 2026, zona de referencia México. Repositorio `/home/ti-admin/gelianv`, rama `develop`, HEAD `08d240a7`.

**Etapas 1 y 2 implementadas y comprobadas en el entorno aislado. Trabajo detenido antes de las etapas 3 a 6 para la revisión solicitada.** El [diagnóstico rector](M01_AJUSTES_FINALES_CEDIS_ACTUALIZACION.md) conserva el orden y alcance posteriores. No se extrajo ni se conectó el editor compartido a Municipal/Nacional.

El árbol ya contenía M-01 y numerosos cambios de otras áreas, incluidos archivos sin seguimiento. Se trabajó sobre ellos sin pull, merge, reset, rebase, commit, push ni migraciones. No se modificaron bases operativas ni se eliminaron pedidos o borradores anteriores.

## Resultado funcional

- Ventas y CEDIS reciben los cambios de consulta manteniendo sus modales abiertos. Se comprobaron respuesta con faltante sin SKU, retiro documental, segunda respuesta, cierre, reapertura y tercera respuesta municipal; también consulta, anexo documental y segunda respuesta de Tienda.
- El servidor devuelve una lectura canónica después de responder. Si Tienda sale de la etapa accesible para CEDIS, el mismo mecanismo detecta su regreso mediante la bandeja autorizada. No se amplió el permiso del GET ni el acceso a documentos fuera de etapa.
- Abrir un alta o seleccionar únicamente Municipal, Tienda o Nacional no crea un pedido. La transición a Nacional puede abrir el legado sin ID. Capturar información significativa y Enviar consulta conservan el guardado necesario.
- Las tarjetas CEDIS usan la composición existente y la ruta Origen → Destino. Envíos muestran **Envío: MUNICIPAL** y **Envío: NACIONAL**; Tienda mantiene sus convenciones. Municipal usa el almacén consultado y la terminal registrados.
- En móvil, la cabecera coloca el departamento en una segunda fila para conservar legibilidad y el tipo centrado. No cambia fuente, acento, tema ni estilos globales.

## 1. Causas verificadas y solución

| Hallazgo | Corrección |
| --- | --- |
| CEDIS pausaba las actualizaciones cuando el panel estaba abierto y conservaba el objeto del listado leído al abrir | El consumidor existente alterna entre pedido abierto y bandeja. Solo se pausa para proteger captura/envío; el pedido tiene respaldo real de 15 segundos |
| El hook descartaba eventos durante una lectura y podía dejar pendientes sin recuperar si `pausado` era una función | Encola una lectura posterior, evalúa la pausa actual y reanuda por el mismo camino. Retira pendientes al cambiar de pedido/desactivar |
| El respaldo podía suprimir su propio siguiente intervalo y contar errores como actualizaciones satisfactorias | Solo una lectura exitosa por evento evita una lectura redundante; el respaldo no suprime su siguiente ciclo. Reintento, reconexión y retorno a visible conservan el hook existente |
| Ventas tenía lectores simultáneos en bandeja y formulario actual | `ModalFormPedido` comunica quién posee la lectura. La bandeja deja la actualización al formulario actual; Nacional legado conserva su lector anterior. Cerrar el formulario actualiza la bandeja |
| Municipal fusionaba solo parte del seguimiento y comparaba únicamente fechas de respuesta | Regla común de vigencia y fusión operativa: consulta, revisiones, ajustes, decisiones, evidencias y bloqueo de cierre. Conserva datos editables y financieros locales |
| Cambios de un mismo segundo podían confundirse | `version_consulta` deriva de la última entrada del historial existente antes de filtrar su contenido por permiso. UUID identifica; no ordena. GET y respuesta canónica usan instantánea bajo bloqueo de lectura. Sin migración |
| El panel conservaba «enviada» y archivos del ciclo anterior | Marca de envío por ciclo. Una nueva actualización vuelve a habilitar la respuesta y exige captura nueva. Las capturas no enviadas se conservan |
| **Navegador:** retirar un faltante desmontaba la acción y dejaba el pedido ocupado para siempre | La acción libera su pausa al desmontarse. El seguimiento municipal agrega la ocupación por pieza para conservar otras capturas abiertas |
| **Navegador:** la segunda respuesta municipal era visible, pero el indicador de cierre seguía esperando un pesaje nacional | El indicador del modelo consume la política municipal que ya usa el cierre. Conserva titularidad, editabilidad y bloqueos de revisión; no introduce pesaje ni cambia finanzas |
| **Navegador:** después de responder Tienda, CEDIS perdía acceso por etapa y el GET posterior recibía 403 | El POST autorizado devuelve `pedido` canónico y `puede_consultar`, conservando sus campos anteriores. Fuera de etapa, el mismo hook consulta el listado autorizado por folio y vuelve al GET solamente cuando el pedido reaparece |
| **Navegador:** sustituir la vista local por miniaturas remotas después de terminar la etapa generaba otro 403 | Conserva las vistas locales enviadas y evita nuevas lecturas documentales fuera de alcance. La siguiente revisión recupera el acceso normal y limpia la captura anterior. No se modifica la autorización de documentos |
| Autoguardado consideraba modalidad/área como contenido; la transición a Nacional guardaba incondicionalmente | Modalidad/área solas no disparan persistencia. Nacional sin captura continúa al legado sin ID; con captura conserva su guardado previo |

El 403 de etapa no se ocultó ampliando permisos. La prueba de servidor comprueba expresamente que el POST devuelve la respuesta y que el GET posterior sigue siendo **403** para el actor CEDIS fuera de esa etapa. El navegador final terminó sin esos errores.

## 2. Arquitectura y límites operativos conservados

Se mantienen `useActualizacionesPedidos`, `.pedido.actualizado`, `control-pedidos.operaciones`, los observers y las rutas existentes. No se incorporaron canales, temporizadores por componente hijo ni servicios externos paralelos.

La sincronización de CEDIS tiene un solo dueño. Mientras el pedido es accesible, usa la lectura por ID; si salió de la etapa, consulta el endpoint de listado existente por su folio, con su filtrado de permisos. Esa comprobación no cambia búsqueda, página ni URL visibles. Al reingresar, obtiene la lectura canónica y continúa el mismo ciclo.

La lectura operativa se separa de la captura editable. Se conserva `revision_registro`/`base_captura` para el guardado común y financiero; el nuevo ordinal de consulta no los reemplaza. Las lecturas viejas no revierten reapertura, respuesta o cierre de una operación documentada. La bitácora continúa filtrada por su permiso; el ordinal no expone su contenido.

`presentacionPedidoCedis` centraliza exclusivamente etiqueta y ruta. Reconoce las marcas persistidas, incluida la municipal histórica; no convierte operaciones. La modalidad nacional histórica usa el origen configurado con logística y excluye traslados internos. No se clasifica por nombres comerciales de almacenes o paqueterías.

`ConsultaMunicipal::hidratarAlmacen` proyecta el almacén de la consulta tanto en listado como en lectura canónica, incluso cuando existe otro almacén operativo en el pedido. No escribe `almacen_id`, `origen_id` ni `sucursal_destino_id`. La ruta representa referencia de consulta/destino operativo; no acredita salida, traslado CEDIS → PDV → Terminal, recepción o entrega.

Municipal muestra la terminal de la relación `paqueteria`. Si faltan origen o destino, indica Por confirmar; no inventa almacén ni municipio. Nacional mantiene sus referencias de sucursal, dirección, paquetería y formularios de pesaje. Los controles financieros, remisiones, inventario, preparación, logística física y expediente continúan en sus servicios actuales.

## 3. Pruebas dirigidas

Se ejecutaron mediante Sail. PHP usó SQLite en memoria con **ambas** cachés aisladas mediante `sail exec -e APP_CONFIG_CACHE=... -e APP_ROUTES_CACHE=...`. Vitest se ejecutó como `sail`, con `NODE_OPTIONS=--no-experimental-webstorage`. No se ejecutó la suite general.

Los conteos siguientes son **casos distintos por clase/archivo**, no la suma de repeticiones. Las correcciones detectadas en navegador motivaron nuevas pasadas de los consumidores afectados.

| Servidor | Casos comprobados |
| --- | ---: |
| `MunicipalOrdinarioM01Test` | 29: lote inicial de 28, nueva proyección de almacén y repaso dirigido del cierre/segunda respuesta |
| `SolicitudRegistrarTiendaTest` | 8 seleccionados: consulta CEDIS, bitácora, actualización documental, respuesta obsoleta, idempotencia, cupo y respuesta canónica sin ampliar etapa |
| `ElegibilidadPesajePedidoTest` | 16 |
| `ControlPedidosCedisVisibilidadTest` | 5 |
| **Total de casos distintos de servidor** | **58** |

| Interfaz | Casos comprobados |
| --- | ---: |
| `useActualizacionesPedidos` | 8 |
| `PanelRespuestaDisponibilidad` | 24 |
| `FormularioRegistrarTienda.confirmacion` | 4 |
| `ModalFormPedido.seleccion` | 4 |
| `AccionesRapidasDisponibilidad` | 9 |
| `Municipal/SeguimientoSolicitudMunicipal` | 2 |
| `Municipal/vigenciaConsultaMunicipal` | 5 |
| `Municipal/envioDirectoMunicipal` | 4 |
| `Municipal/continuidadRegistro` | 2 |
| `Cedis/Partials/q81Interfaz` | 11 |
| `presentacionPedidoCedis` | 4 |
| **Total de casos distintos de interfaz** | **77** |

[Logs conservados](evidencias-m01-sincronizacion/). El primer lote completo municipal (28 casos/356 aserciones) y el primer lote frontend (41 casos) se observaron en la salida de herramientas; no se guardó su salida íntegra ni se repitieron solamente para crear un log. Los logs posteriores identifican sus lotes y conteos reales. No se presenta la clase completa de Tienda como aprobada.

## 4. Navegador real y persistencia

Laravel temporal **como sail** en 9193, MySQL exclusivo `codex_m01_audit_20261114`, Chromium/Selenium, React/CSS del build publicado y documentos ficticios. Dos cuentas distintas: Ventas **45**, CEDIS **46**; cookies independientes en `laravel.test` y `gelianv-laravel.test-1`. No se usaron los pedidos operativos.

Se desconectó Echo y se aisló broadcast con `BROADCAST_CONNECTION=null`. Los scripts no falsean visibilidad, no disparan eventos remotos y no ejecutan mutaciones por API directa: actúan sobre los controles de la aplicación. Los observadores comprueban que los cambios de cada ventana aparecieron **antes de reenfocarla**.

| Recorrido aprobado | Evidencia |
| --- | --- |
| Municipal 48: primera respuesta con faltante sin SKU; retiro; segunda respuesta; cierre; reapertura; tercera respuesta, sin cerrar modales | [Resultado completo](../resources/design-references/m01-sincronizacion/sincronizacion.json) |
| Tarjetas, documento autorizado y detalle de las tres modalidades; Nacional conserva formulario con peso real, sin registrar pesaje | [Resultado de integración/presentación](../resources/design-references/m01-presentacion/presentacion.json) |
| Tienda 55: respuesta automática; anexo documental desde el editor existente; reingreso a CEDIS por bandeja autorizada; segunda respuesta sin reabrir modal | Mismo resultado de integración; [respuesta y anexo](../resources/design-references/m01-presentacion/tienda-anexo-respondido-1440-dark.png) |
| Recepción municipal con el contrato canónico final y dos sesiones | Mismo resultado de integración |
| Apertura vacía y elección de Municipal, Tienda y Nacional; espera del autoguardado legado sin POST | [Resultado de alta vacía](../resources/design-references/m01-alta-vacia/alta-vacia.json) |
| Cabeceras finales después de corregir el texto partido observado en móvil: tres modalidades × tres anchos × dos temas, sin mutaciones | [18 comprobaciones finales](../resources/design-references/m01-cabeceras/presentacion.json), [Municipal móvil](../resources/design-references/m01-cabeceras/tarjeta-municipal-390-light.png) |
| Consulta municipal y visor dentro del modal completo | [Consulta móvil](../resources/design-references/m01-presentacion/consulta-municipal-390-light.png), [Wizerp](../resources/design-references/m01-presentacion/wizerp-municipal-1440-dark.png) |

La integración/presentación terminó con **28 capturas/mediciones** en su alcance; la comprobación final de cabeceras tiene 18, y el alta vacía tres. Consola final vacía, sin desbordamiento de página/diálogos en las superficies medidas; cabeceras finales sin palabras partidas. Se inspeccionaron las imágenes de tarjeta municipal en escritorio/tablet/móvil, panel municipal móvil y respuesta/anexo Tienda. Las capturas de tarjetas de `m01-presentacion` preceden al ajuste móvil de cabecera: la referencia final para las tarjetas es `m01-cabeceras`.

La [lectura final de persistencia](evidencias-m01-sincronizacion/persistencia-final.json) comprueba:

- Municipal 48 conserva cuatro documentos, dos respuestas históricas y la vigente; el retiro sigue registrado y el Wizerp no fue reemplazado.
- Tienda 55 conserva su Wizerp y un único anexo respondido. Nacional 50 no tiene pesaje registrado por esta comprobación.
- No se crearon pago validado, remisión, empaque o tarea municipal. El flete diferido sigue nulo/diferido.
- La base exclusiva tiene **33 pedidos**, exactamente los anteriores más los fixtures y renovaciones intencionales documentados. No hubo pedidos fuera de esos casos explícitos ni borradores por abrir/seleccionar modalidad.

Los casos usados para reintentar comprobaciones se conservaron; no se reiniciaron respuestas ni se eliminaron filas. Las identidades, documentos y binarios de estos recorridos son sintéticos y permanecen identificados en la base de auditoría.

## 5. Inventario de ajustes propios

Los archivos que ya estaban modificados o sin seguimiento conservan ese trabajo previo. Esta tabla describe solo la intervención de este corte.

| Grupo | Archivos |
| --- | --- |
| Lectura y respuesta canónica | `app/Http/Controllers/ControlPedidos/DisponibilidadTiendaController.php`, `PedidoBmaCedisController.php`; `app/Services/ControlPedidos/ListarPedidosBmaService.php`, `ListarPedidosCedisService.php`; `app/Support/ControlPedidos/LecturaFinancieraPedido.php`, `ConsultaMunicipal.php`; indicador municipal de `app/Models/ControlPedidos/PedidoBma.php` |
| Dueño de lectura, pausas y ciclos | `resources/js/Pages/ControlPedidos/Index.jsx`, `Cedis/Index.jsx`, `Partials/ModalFormPedido.jsx`, `FormularioRegistrarTienda.jsx`, `useActualizacionesPedidos.js`, `SeguimientoSolicitudTienda.jsx`, `AccionesRapidasDisponibilidad.jsx`, `PanelRespuestaDisponibilidad.jsx`; `Municipal/SeguimientoSolicitudMunicipal.jsx`, `vigenciaConsultaMunicipal.js` |
| Utilidades nuevas de lectura/presentación | `Partials/vigenciaConsultaPedido.js`, `Partials/presentacionPedidoCedis.js` y su prueba |
| Tarjetas, detalle, ruta y acceso documental | `Cedis/Partials/TarjetasCedis.jsx`, `ModalDetalleCedis.jsx`; `Partials/RutaPedidoTienda.jsx`, `AlternativasDisponibilidadCedis.jsx`, `ResumenCambiosDisponibilidad.jsx`; regla móvil local de `resources/css/gelia/features/control-pedidos-gestion-bma.css` |
| Pruebas ampliadas | `tests/Feature/ControlPedidos/MunicipalOrdinarioM01Test.php`, `SolicitudRegistrarTiendaTest.php`; pruebas JSX/JS de hook, panel, acciones, selección, envío directo, vigencia y tarjetas enumeradas arriba |
| QA nuevo | `scripts/qa/m01-sincronizacion/`: fixtures, fixtures de regresión, navegador de ciclos, presentación, alta vacía, lectura de persistencia y README |
| Documentación/evidencias | Este corte; enlace desde el diagnóstico rector; `evidencias-m01-sincronizacion`; referencias `m01-sincronizacion`, `m01-presentacion`, `m01-cabeceras` y `m01-alta-vacia` |

No se agregaron dependencias, migraciones, permisos, rutas nuevas ni infraestructura de actualización. Los cambios de expedientes, finanzas, reportes y otros módulos que ya estaban en el árbol no se atribuyen a esta intervención.

## 6. Límites y punto de revisión

- La actualización automática se acreditó con el respaldo real y dos sesiones autenticadas. **La entrega real por Reverb/Echo y los avisos a cuentas operativas no se comprobaron**; el canal/observer existente se inspeccionó y el entorno QA aisló su transporte.
- El Wizerp de navegador es un PNG ficticio legible. No se ejerció un PDF binario, cámara física ni escáner físico en este lote.
- Nacional se comprobó en presentación y navegación de su pesaje, además de regresión de servidor; no se registró un pesaje nacional en navegador ni se completó su recorrido financiero/logístico.
- No se ejecutó la suite general ni se revalidaron por rutina los fallos previos de Tienda ajenos a este corte documentados en los reportes anteriores.
- **Municipal y Nacional todavía no tienen el editor completo compartido de anexos/retiros.** La extracción de `AjustePiezas`, núcleo documental y conexión incremental corresponden a las etapas 3–6, pendientes de esta revisión.

Build final publicado por Sail con retención de assets y manifiesto verificado; no hay Vite ni `public/hot`. Los builds adicionales respondieron a defectos concretos encontrados por las comprobaciones, no a cada edición. `git diff --check` aprobado. Se cerraron únicamente Laravel QA 9193 y sus workers; Selenium quedó sin sesiones y el servidor compartido del puerto 80 siguió activo. Sin commit ni push.

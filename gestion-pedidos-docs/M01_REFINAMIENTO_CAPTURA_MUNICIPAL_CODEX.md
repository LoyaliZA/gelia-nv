# Refinamiento de captura municipal — intervención Codex

Fecha: 17 de noviembre de 2026. Trabajo sobre los cambios locales existentes, incluidos archivos sin seguimiento. Sin pull, merge, reset, rebase, commit ni push.

## 1. Decisión operativa y causa del selector vacío

**Decisión posterior del usuario, durante esta intervención:** las paqueterías municipales vigentes son las marcadas **Sin logística**. Categoría Local / Regional no equivale a Municipal. Las banderas `habilitado_envio_municipio` y `reglas_municipio_pendientes` pertenecen al recorrido anterior y no deben condicionar esta captura. Esta precisión prevalece sobre la petición inicial de admitir variantes con y sin logística.

Flujo comprobado:

1. Tabla `catalogo_paqueterias_pedido`, modelo `CatalogoPaqueteriaPedido`, booleanos con casts.
2. Administración usa `TablaPaqueteriasPedido` y `CatalogoController`, que ya permiten guardar `sin_logistica` y `activo`.
3. `ObtenerCatalogosPedidoBmaService::ejecutar` entrega `paqueterias` activas con `sin_logistica`, costo diferido y POR COBRAR.
4. También existe `paqueterias_municipio`, filtrado por banderas de la implementación anterior. Se conserva para sus consumidores legados.
5. El formulario nuevo tomaba primero `paqueterias` y filtraba `habilitado_envio_municipio !== false`; por ello excluía las entradas con la bandera antigua apagada, aunque ese atributo ya no determina Municipal. El backend exigía igualmente la política antigua.
6. La captura vigente usa `terminalesMunicipales`: activas y `sin_logistica === true`. La validación servidor usa `habilitadaParaRegistroMunicipal()` en guardado y primer envío de consulta. No se infiere nada por nombre, categoría, tarifa o capacidad de diferir costo.

**Situación operativa pendiente:** la lectura de solo consulta del catálogo de este servidor encontró **28 paqueterías activas y 0 marcadas Sin logística**. También tenían apagada la habilitación municipal antigua. No se alteraron esas filas ni se asignaron marcas por nombre/categoría. Por tanto, en esta base el selector seguirá sin opciones hasta marcar las terminales correspondientes como Sin logística mediante el administrador existente. La interfaz ahora explica esta situación. En la base exclusiva se verificaron terminales ficticias correctamente configuradas.

No se creó otro catálogo ni se añadieron controles para las banderas obsoletas. Los cambios administrativos explorados antes de la precisión del usuario se retiraron; ambos archivos administrativos estaban limpios al inicio y quedan sin cambios de esta intervención.

## 2. Destino persistente y compatibilidad

La referencia persistente es **`pedidos_bma.catalogo_paqueteria_id`**. El nombre operativo se obtiene de la relación `paqueteria`, y los endpoints municipales devuelven su id y nombre actual. Renombrar una terminal en el catálogo se refleja al volver a consultar el pedido; no se mantiene una copia comercial competidora en `municipio_destino`.

Se retiró Municipio o destino del formulario, del payload y de la obligatoriedad del endpoint y servicio municipales nuevos. Enviar consulta comprueba la referencia de paquetería y la solicitud guardada, sin requerir municipio.

`municipio_destino` no se elimina, rellena ni sobrescribe. Un valor histórico existente permanece intacto incluso al guardar otra vez desde esta captura. No hay migraciones nuevas.

Dependencias inspeccionadas mediante búsqueda y código:

| Dependencia | Tratamiento |
| --- | --- |
| `SolicitudMunicipalController`, `RegistrarSolicitudMunicipalService`, `EnviarConsultaMunicipalService` | Sin captura obligatoria de municipio en el registro vigente. |
| `SolicitarPreparacionTiendaRequest`, `CrearTareaPreparacionService` | Conservan municipio en el recorrido legado. |
| `PedidoBmaRequestBase`, `FusionarSolicitudPreparacionPedido`, `capturaPreparacionPedido.js`, `ModalFormPedidoLegado` | Conservan sus contratos y datos históricos. |
| Tareas de preparación, `VisibilidadTareaPreparacion`, `ListarPedidosBmaService`, tarjetas y detalle de Tienda | Conservan la información municipal histórica asociada a las tareas. |
| `PedidoBmaCaratula`, observer de carátula, `GenerarCaratulaPedidoService`, `GenerarHojaSalidaInternaService` | Mantienen municipio geográfico y snapshots existentes; no se cambia la generación documental. |
| Listado, detalles y seguimiento del registro nuevo | Leen la terminal desde `paqueteria`. No usan municipio como nombre de terminal. |

La función histórica `habilitadaParaEnvioMunicipio()` conserva su comportamiento. La política nueva tiene nombre y uso específicos, para no cambiar la elegibilidad del flujo físico anterior.

## 3. Hallazgo adicional de reapertura

El navegador reveló que `PedidoBma::getSolicitudPreparacionAttribute()` normalizaba el JSON con el contrato compartido y omitía `consulta_municipal`, `finanzas_municipales` y `expediente`. La persistencia era correcta, pero al recargar el listado el formulario perdía visualmente condiciones, consignatario y estado de consulta.

Se agregó una proyección de **lectura** limitada a pedidos con `modalidad_entrega = municipal`, incluyendo esas claves y el municipio histórico cuando existe. No se amplió el contrato de escritura/autoguardado compartido ni se permitieron estados operativos desde una entrada alternativa.

El almacén seleccionado también se guarda como intención en `almacen_revision_id`, con validación de existencia, actividad y visibilidad para pedidos. Enviar consulta continúa siendo un acto explícito separado: guardar no crea `consulta_municipal`, no notifica ni registra movimiento físico. El navegador verifica que el almacén persista tras recargar y reabrir.

## 4. Componentes y presentación

Referencia inspeccionada: `FormularioRegistrarTienda`, sus estilos efectivos en `control-pedidos-gestion-bma.css`, las primitivas, tokens y constantes GELIA. Se reutilizan la cabecera con icono, paneles sólidos, título, controles y pie de acciones. No hay CSS global nuevo ni cambios de fuente/acento/tema.

Cambios de esta intervención:

- `Municipal/FormularioRegistrarMunicipal.jsx`: Terminal destino y procedencia en columnas; consignatario y condiciones en paralelo en escritorio, apilados en móvil; documentos reconocibles con acceso al visor compartido; acciones persistentes, mensajes de validación y explicación del bloqueo de envío.
- `Municipal/ExpedienteConsignatario.jsx`: en modo compacto, selector de persona, ficha de la elegida y alta contextual secundaria; edición/cancelación sin desmontar la captura del pedido. Administración conserva su modo completo. Se limitó el ancho del input de archivo para eliminar el scroll horizontal observado.
- `Municipal/terminalMunicipal.js`: selección por Sin logística, detección del registro nuevo y nombre desde la relación.
- `Municipal/SeguimientoSolicitudMunicipal.jsx` y `ConsultasTienda.jsx`: modalidad y terminal consistentes; la cola obtiene el nombre mediante relación precargada en `ConsultaMunicipalController`.
- `Partials/TablaPedidos.jsx`: Municipal muestra Modalidad: Envío municipal y Terminal destino. El encabezado es Tipo de pedido cuando hay registros municipales; una lista sin ellos conserva Logística. Las tarjetas móviles también muestran modalidad y terminal.
- `Partials/ModalDetallePedido.jsx`: modalidad y terminal para el registro municipal.
- Detalles de CEDIS, Auditoría y Delegado: sustitución condicionada de Origen de la guía por Terminal destino para este registro.
- `Partials/pedidosBmaStyles.js`: el resumen compartido de guía devuelve Envío municipal únicamente para registros marcados con esa modalidad. Otros pedidos y municipales históricos sin la marca conservan sus etiquetas.

El borrador no guardado se conserva en `sessionStorage`, separado por usuario y pedido. Guarda ids, condiciones e importe; no almacena documentos ni copia nombres/identificaciones del expediente. Se limpia al guardar o cuando la consulta ya fue enviada. No es una sincronización de borradores entre dispositivos.

Después de enviar, los campos de solicitud quedan bloqueados. Se mantienen actualización de respuesta, acciones documentales de las piezas y seguimiento existentes. La modalidad y el costo no se confunden con pago del cliente.

## 5. Backend y seguridad

Archivos propios modificados: modelos `CatalogoPaqueteriaPedido` y `PedidoBma`; controladores municipales `SolicitudMunicipalController` y `ConsultaMunicipalController`; servicios municipales de registrar y enviar.

Se conservan permisos, alcance del pedido, bloqueo de fila, imposibilidad de modificar solicitudes enviadas/cerradas/pagadas/remisionadas, restricciones de consignatario y referencias históricas del expediente. Se mantienen las validaciones de costo conocido positivo, costo diferido según catálogo y POR COBRAR solo cuando se permite. El primer envío revalida que la terminal continúe activa y Sin logística; un reenvío idéntico conserva la idempotencia anterior.

No se modificó en esta intervención la política financiera, verificación de pagos, remisiones, descarga privada, versionado del expediente ni lógica de respuesta/cierre/reapertura de consulta. Las modificaciones anteriores de auditoría siguen presentes en el árbol local.

## 6. Pruebas ejecutadas

Evidencias de consola: [directorio de logs](evidencias-m01-refinamiento/).

| Verificación | Resultado y alcance |
| --- | --- |
| SQLite municipal inicial | 22 escenarios, 260 aserciones; tras corregir proyección, 263. |
| MySQL exclusivo `codex_m01_audit_20261114` | 22 escenarios, 260 aserciones. Esta pasada precede al ajuste final de proyección/almacén; esos ajustes se verificaron con SQLite final y el navegador MySQL real. |
| Backend final: Municipal + ElegibilidadPesajePedido + ControlPedidosEnvioDiferidoFase1 | **43 aprobadas, 349 aserciones**. SQLite en memoria, ambos caches aislados. |
| Vitest municipal + persistencia + concurrencia del modal compartido | **17 aprobadas**. Último ajuste de composición: se repitieron las 4 municipales, aprobadas. |
| Build | Publicado, manifiesto y assets verificados; advertencias de tamaño de chunks. Sin Vite ni `public/hot`. |
| `git diff --check` | Aprobado. |
| `npm run check:style` | **No completado**: falta `docs/style-system/baseline/jsx-hex-allowlist.tsv`. No se regeneró baseline ni se ocultó la salida. La fase CSS no se ejecutó porque el script usa `&&`. |

Los casos nuevos comprueban terminal Sin logística válida aunque las banderas antiguas no la habiliten; rechazo de con logística, inactiva e inexistente; guardado sin municipio; nombre actualizado desde catálogo; municipio histórico intacto; procedencia guardada sin enviar consulta; conservación de costo diferido y POR COBRAR; rechazo de esas condiciones cuando no se permiten; rechazo del primer envío si se desactiva la terminal después de guardar.

El primer intento de regresión Vitest tuvo 13 fallos antes de ejecutar las interacciones: `localStorage` nativo de Node no estaba disponible para las pruebas existentes. Se reejecutó con `NODE_OPTIONS=--no-experimental-webstorage`, dejando a happy-dom suministrar Web Storage; las 17 pruebas pasaron. No se modificaron las pruebas existentes para ocultar esos fallos. Permanecen avisos de `act(...)` en pruebas compartidas.

Comandos reproducibles:

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test --filter='MunicipalOrdinarioM01Test|ElegibilidadPesajePedidoTest|ControlPedidosEnvioDiferidoFase1Test'
./vendor/bin/sail exec --user sail -e NODE_OPTIONS=--no-experimental-webstorage laravel.test npm test -- resources/js/Pages/ControlPedidos/Municipal/registroMunicipal.test.jsx resources/js/Pages/ControlPedidos/Partials/modalPersistenciaContrato.test.jsx resources/js/Pages/ControlPedidos/Partials/modalConcurrenciaContrato.test.jsx
./vendor/bin/sail npm run build
```

El proxy `sail npm` ejecuta como `APP_USER=sail` en este entorno; los comandos npm ejecutados mediante `sail exec` al cierre usan explícitamente `--user sail`. Los assets publicados pertenecen a sail. No se cambiaron permisos generales ni el mecanismo de retención de assets.

## 7. Navegador real y evidencia visual

Se ejecutó GELIA real: Laravel temporal en 9193, React y CSS del build publicado, Chromium/Selenium, sesión autenticada y datos ficticios en la base MySQL exclusiva. Sin sesión ajena de Selenium; el guion elimina únicamente su propia sesión. No se usó un HTML simplificado.

Resultado final: [JSON de interacciones](../resources/design-references/m01-refinamiento/resultado.json).

Interacciones comprobadas con controles del formulario:

1. Iniciar sesión y editar una solicitud municipal desde el listado real.
2. Seleccionar terminal Sin logística y costo diferido.
3. Crear consignatario desde el contexto del pedido; abrir y cancelar edición sin perder terminal ni costo.
4. Cerrar y reabrir para recuperar borrador sin guardar.
5. Guardar solicitud con su botón, recargar el listado y reabrir: terminal, consignatario, condiciones y almacén conservados.
6. Ver listado con Envío municipal, sin Guía generada por la empresa como modalidad.
7. Enviar consulta con su botón al almacén elegido; observar estado enviada y bloqueo de captura.
8. Recorrer el cuerpo con scroll hasta Documentos y conservar las acciones visibles.

Resoluciones verificadas: **1440, 1024 y 390 px**, claro y oscuro. Sin scroll horizontal de página o cuerpo del diálogo; acciones dentro del área visible; consola final vacía. Se inspeccionaron visualmente capturas de escritorio y móvil. Se conservan seis capturas de registro y una adicional del área inferior móvil en [evidencia](../resources/design-references/m01-refinamiento/).

Durante la preparación hubo fallos del guion por esperar el JSON Inertia antes de la hidratación, permisos de edición ausentes en el usuario ficticio y comillas de un selector. Se corrigieron el guion y el fixture. La ejecución real también descubrió los problemas funcionales de proyección y desbordamiento descritos arriba; no se consideraron aprobados hasta repetir con éxito.

Esta comprobación no incluye carga/descarga de identificación ni apertura de documento adjunto en el visor: el fixture no adjunta binarios. Se conservó su código/permisos y las pruebas existentes del expediente. Los detalles de Auditoría, CEDIS y Delegado se revisaron en código y compilación, no se recorrieron con cuentas de esos actores. No se acredita la logística física municipal ni M-01 completo.

## 8. Entorno, pendientes y cierre

- Se preservan los cambios previos, incluidos los no rastreados; los reportes originales no se sobrescriben.
- Ninguna base operativa se reconstruyó ni recibió fixtures. Solo se leyó su catálogo. No se modificaron clientes, documentos ni terminales reales.
- Los fixtures de esta intervención permanecen únicamente en la base exclusiva. No se cargaron binarios ficticios nuevos, por lo que no se necesita limpieza de archivos documentales.
- Se cerró únicamente el Laravel temporal 9193 y sus workers. Los servicios compartidos siguen activos.
- Falta configurar qué terminales del catálogo operativo se marcan Sin logística: actualmente hay cero. Se debe usar el control existente en Catálogos → Paqueterías; no convertir automáticamente Local / Regional.
- Falta el baseline para completar el verificador general de estilos.
- No se implementaron carátula definitiva, preparación/empaque municipal, traslado, entrega a terminal, P-01 ni nuevas modalidades.

El código permite registrar, guardar, reabrir y enviar una consulta municipal sin municipio manual, con terminal de catálogo y presentación coherente. El recorrido fue acreditado con datos ficticios y la configuración válida; la base operativa necesita las marcas Sin logística indicadas arriba antes de disponer de opciones seleccionables.

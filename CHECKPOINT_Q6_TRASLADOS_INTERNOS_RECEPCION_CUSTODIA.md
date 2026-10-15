# CHECKPOINT Q6 — Traslados internos, recepción y custodia

## Resultado y punto de partida

Implementados y probados los traslados **CEDIS → PDV** y **PDV Carmen → PDV City**, con revisión física, cantidades, evidencia y bultos operativos, sin kilogramos, caja comercial, volumétrico, paquetería ni guía comercial. El recorrido probado llega hasta custodia, incluida su confirmación parcial. Se conserva el circuito comercial de pago, revisión Auxiliar y remisión.

**No está completo el abastecimiento de una preparación existente en destino PDV.** El vínculo actual de tareas y solicitudes de traspaso representa recepción en CEDIS. Tampoco hay una captura de llegadas parciales en la confirmación de gerencia actual: esa acción confirma todos los bultos esperados. Ambos límites se detallan abajo; no se sustituyen por recepción completa, entrega al cliente ni duplicación del pedido.

Partida: rama `main`, commit `99ff3b0d5bb545c5d3ad37990c1d1167cd1bd3e8`. El árbol ya tenía **41 archivos modificados**, los checkpoints Q5.1/Q5.2 y `DefinicionesOperativasQ52Test.php` sin seguimiento. Ese trabajo se conservó. No hay commit, push, despliegue ni migraciones sobre bases operativas.

Fuentes: reglas técnicas de `.cursor/rules` (Sail, modularidad, reutilización y estilos existentes; sin build de producción), checkpoints Q5.1/Q5.2, Q4/Q5 y los de preparación/PDV, código vigente y pruebas. No hay `AGENTS.md` en este repositorio. Las definiciones del encargo Q6 prevalecen sobre los pendientes históricos.

## Contrato aplicado y clasificación

- `PedidoBma::esTrasladoInternoAPdv()` concentra la clasificación. Requiere almacén físico y `sucursal_destino_id`. La sucursal del cliente, vendedor o almacén por sí sola no es un destino.
- Una modalidad vigente, respondida o guardada como intención de envío bodega/municipio **no** obtiene exención por tener una sucursal asociada. Esa combinación conserva los requisitos externos y la validación de destino incompatible.
- Para salida PDV se usa el vínculo real `almacenes.sucursal_id`: debe existir y ser distinto del destino. Recoger en el mismo PDV conserva su circuito. Un PDV sin sucursal vinculada no se reclasifica por inferencia; la captura explica el vínculo faltante.
- Se mantiene `esTrasladoInternoCedisAPdv()` para compatibilidad, limitado a CEDIS. Sus consumidores operativos pasan a la política general.
- La clasificación funciona en modelos sin ID y en captura antes de guardar. Se serializan `es_traslado_interno_pdv` y `requiere_bultos_empaque`; los catálogos incluyen la sucursal del almacén. La interfaz calcula el recorrido de los datos que se están editando, sin reutilizar un flag guardado desactualizado.
- Solicitud, respuesta, cierre, envío a Auxiliar, aprobación, empaque y salida vuelven a leer el pedido dentro de transacción y bloqueo. La respuesta evalúa el almacén efectivamente seleccionado, tanto en el Request como en el servicio. Se conserva la restricción de almacenes habilitados para salida/búsqueda.
- `requiere_logistica` conserva su valor y significado. La exención pertenece al recorrido; no se modifica el catálogo de origen ni se selecciona transporte interno ficticio.

**Ambigüedad histórica concreta:** sin modalidad ni otro propósito registrado, `sucursal_destino_id` es el único campo destinado a expresar la sucursal física receptora. Si fue usado históricamente como referencia comercial de un envío externo, no existe un indicador adicional fiable para identificar ese uso. Q6 no reescribe esos datos. Las modalidades externas explícitas sí impiden la exención.

## Matriz de diferencias y verificación

| Escenario | Esperado Q6 | Antes, comprobado en Q5.2/código | Corrección y verificación |
|---|---|---|---|
| CEDIS → PDV, origen con logística | Revisión y evidencia, sin peso/caja comercial/guía | Eximía paquetería y guía, pero exigía kilos salvo O02; dependía de tener ID | Política interna precede a logística y transporte. Request, respuesta, validación, captura, progreso y detalle coinciden. Prueba de recorrido hasta custodia |
| PDV Carmen → City | Mismo contrato | Destino interno prohibido para salida PDV con logística | Se permite destino físico distinto y se usa el mismo circuito de bultos/recepción. Prueba de recorrido hasta custodia |
| PDV → destinatario externo | Peso, caja y guía según transporte | Recorrido externo existente | Sin destino físico interno conserva pesaje y rastreo. Controles de Q5/Q6 y detalle externo |
| Sucursal asociada + modalidad externa | No eximir envío externo | Clasificación CEDIS miraba solo almacén y sucursal | Se evalúa modalidad vigente/respondida/intención. Control de bodega con sucursal y prueba JS municipal |
| PDV sin vínculo de sucursal o misma sucursal | No inferir traslado entre PDV | No distinguía ese caso al extender el recorrido | No clasifica como traslado a otro PDV; aviso en captura y prueba de backend |
| Bultos con origen logístico | Identidad y fotos de bultos operativos | El registro fotográfico de bultos se activaba solo por `requiereSucursalDestino()` | `requiereBultosEmpaque()` integra traslados internos en Request, servicio y modal existentes |
| Cantidades y faltantes | Solicitadas/encontradas verificables | La revisión CEDIS tenía identidad/condición, pero no cantidades explícitas por renglón | Dos columnas nuevas anulables; captura, validación de servidor y visualización. Faltantes parciales quedan abiertos en el mecanismo de resolución vigente |
| Salida y reintento | Una recepción esperada; no llegada automática | Handoff idempotente existente; salida validaba el objeto recibido antes del bloqueo | Salida relee y bloquea el pedido; reintento de pedido ya enviado no duplica historial/recepción. Prueba con el mismo objeto anterior a la salida |
| Estado de sucursal con origen logístico | En tránsito → recibido → recepción/custodia | Sincronización limitada a destino obligatorio | Se extiende a traslados internos. Se comprueban tránsito, recibido, custodia parcial y completa |
| O02, local con peso y comercial | Mantener contrato Q5.2 | Ya implementado | Regresiones conservadas: caja O02 se evalúa aparte; una paquetería guardada no vuelve a exigir caja/peso en traslado interno |

Responsables principales: modelo `PedidoBma`; servicios de solicitar/responder/cerrar/enviar/aprobar/empacar/marcar enviado; `ValidacionCamposPedidoBma`; máquina de estados; bultos y recepción esperada PDV; utilidades de destino/requisitos y formulario de Ventas; detalle/tarjetas/respuesta CEDIS.

## Revisión física y evidencia

La respuesta interna exige al menos un producto identificado, condición válida, cantidades enteras solicitadas/encontradas coherentes, bultos positivos y evidencia final del lote. Encontradas no puede exceder solicitadas; `sin_existencia` exige encontradas = 0. Un faltante exige observación. Daño conserva comentario y evidencia propia; siguen disponibles «Única pieza» y «Mejor ejemplar».

Se guardan responsable y fecha de respuesta. Cantidades y diferencias se muestran en Ventas/Auxiliar y CEDIS. El snapshot de recepción esperada conserva la revisión y su responsable/fecha. Un cambio de cantidades invalida una resolución anterior de ese renglón; una nueva observación no queda autorizada por una resolución obsoleta.

`PedidoBmaRevisionProducto::estaSinExistenciaAbierta()` y su consulta incluyen faltantes parciales observados. Se reutilizan las resoluciones existentes; no se crean existencias. Empaque/envío a Auxiliar siguen bloqueados por faltantes abiertos.

El registro no fabrica pesos, cajas ni respuestas vacías. `numero_cajas` conserva su uso vigente de cantidad de bultos; `PedidoBmaBultoEmpaque` conserva número, fotos de bulto/ticket, responsable y fecha. No se insertan cajas comerciales para representar bultos.

## Salida, recepción y custodia

Recorrido automático probado, en ambos orígenes:

1. Solicitar revisión; responder productos, cantidades y evidencia sin pesos.
2. Cerrar consulta por Ventas.
3. Registrar cobertura de pago; enviar a Auxiliar; validar pago, adjuntar remisión y aprobar con los servicios existentes.
4. Empacar con fotos e identidad de dos bultos. No se crea recepción en este paso.
5. Confirmar salida: estado enviado y una recepción **pendiente**, sin fecha de recepción física. No se crea tarea Tienda por el destino.
6. Reintentar salida y creación de recepción: no duplica pedido, recepción, evento ni historial.
7. Confirmar llegada mediante el servicio PDV: recibido, sin entrega al cliente.
8. Pasar a recepción y confirmar custodia primero de un bulto y luego del restante. Se conserva almacén real de custodia, condición, piezas, fechas y responsables.

La clave y restricción de unicidad de recepción esperada existentes se conservan. Los servicios PDV mantienen permisos por sucursal, versión, idempotencia e incidencias. Las pruebas nuevas de servicios aíslan notificaciones y alcance PDV; las regresiones HTTP de PDV verifican permisos, aislamiento, versiones e incidencias.

### Límites comprobados que permanecen abiertos

**Abastecer una preparación en PDV (criterio 11):** `SolicitudTraspaso` enlaza `tarea_preparacion_id`, productos y `pedido_bma_origen_id`, pero no declara el almacén/tarea receptora PDV. `CrearTraspasoDesdeTareaPreparacionService`, `ConfirmarSalidaTrasladoTiendaService` y `SincronizarTareaDesdeTraspasoService` llevan a `RECIBIDA_CEDIS`. La recepción de resguardo PDV no consume unidades contra una tarea destinataria. `pedido_principal_id` es una relación comercial, no acredita ese propósito. No se infiere abastecimiento ni se crea un pedido comercial o entrega para suplirlo. Falta extender el traspaso existente con destino físico y tarea receptora explícitos, conciliación de unidades recibidas/faltantes y un cierre de preparación condicionado a esa conciliación. No se creó una máquina paralela.

**Llegadas parciales de gerencia (parte del criterio 12):** `RegistrarRecepcionFisicaPdvService` confirma el conjunto esperado completo; el Request y formulario actuales no capturan un subconjunto de llegada. Existen utilidades/etiquetas para parcialidad, pero no completan ese contrato. Sí funcionan custodia parcial e incidencias por daño/faltante, verificadas en pruebas. Si falta mercancía, debe registrarse la incidencia; confirmar todos los bultos no representa una llegada parcial. Para completar ese recorrido hacen falta selección de folios realmente llegados, recepción complementaria y revisión de la UI/contrato del servicio existente. Q6 no simula la llegada de los faltantes.

## Inventario y anaqueles

La sincronización de inventario **permanece desactivada en estos recorridos**. No se añade un interruptor nuevo ni se cambian importaciones/sincronizaciones de otros módulos. Se conservan los eventos de apartado/retorno con `inventario_sincronizado = false`; la recepción esperada también registra esa condición.

La búsqueda por almacén filtra pertenencia al catálogo, sin exigir saldo positivo. La respuesta comprueba la identidad y pertenencia al catálogo, no la cantidad de stock. Una prueba registra tres piezas observadas de un producto cuyo saldo de catálogo es cero y verifica que la fila de inventario queda idéntica. Los servicios afectados no descuentan, reintegran ni mueven stock.

Se mantiene la retirada Q5.2 de anaqueles en preparación, apartado y retorno. No se añadieron ubicaciones/anaqueles a traslado o recepción. La custodia conserva el almacén real y sus evidencias. Estados técnicos e históricos de retorno no se renombran ni se borran.

## Esquema, archivos y configuración

Migración nueva y aditiva: `database/migrations/2026_10_15_190000_add_cantidades_to_pedido_bma_revisiones_producto.php`. Añade `cantidad_solicitada` y `cantidad_encontrada` anulables; los históricos no se rellenan por inferencia. `down()` retira únicamente esas dos columnas. **Solo se ejecutó por las pruebas en SQLite en memoria; no se aplicó a la base de la aplicación.** No hay cambios de configuración persistente.

Archivos Q6 (respecto al árbol inicial con Q5.1/Q5.2):

- Modelos: `PedidoBma.php`, `PedidoBmaRevisionProducto.php`.
- Servicios ControlPedidos: `SolicitarPesajePedidoBmaService.php`, `ResponderPesajePedidoBmaService.php`, `CerrarConsultaPedidoBmaService.php`, `EnviarPedidoBmaService.php`, `AprobarPedidoBmaService.php`, `MarcarEmpacadoPedidoBmaService.php`, `MarcarEnviadoPedidoBmaService.php`, `RegistrarBultosEmpaquePedidoBmaService.php`, `ValidacionCamposPedidoBma.php`, `CalcularProgresoPedidoBmaService.php`, `ObtenerCatalogosPedidoBmaService.php`, `ListarPedidosCedisService.php`.
- Requests ControlPedidos: `ResponderPesajePedidoBmaRequest.php`, `MarcarEmpacadoPedidoBmaRequest.php`.
- Soportes: `MaquinaEstadosPedidoBma.php`, `CantidadBultosEsperadaResguardoPdv.php`.
- Servicios PDV: `CrearRecepcionEsperadaPdvService.php`, `SincronizarEstatusSucursalPedidoBmaService.php`.
- Interfaz de pedidos: `destinoSucursalPedido.js`, `pedidosBmaStyles.js`, `ModalFormPedidoLegado.jsx`, `SeccionRevisionFisicaPedido.jsx`; CEDIS: `ModalResponderPesaje.jsx`, `ModalDetalleCedis.jsx`, `TarjetasCedis.jsx`.
- Pruebas nuevas: `TrasladosInternosQ6Test.php`, `trasladosQ6Presentacion.test.jsx`. Pruebas ajustadas/ampliadas: `DestinoSucursalPedidoBmaTest.php`, `ElegibilidadCedisPdvPedidoTest.php`, `destinoSucursalPedido.test.js`, `pedidosBmaStyles.validacion.test.js`, `modalElegibilidadCedisPdv.test.jsx`.

## Pruebas y resultados reales

Todos los comandos de aplicación se ejecutaron por Sail. PHPUnit fuerza SQLite `:memory:`. No se utilizó `ALLOW_DESTRUCTIVE_DB`. Para las pasadas finales se evitó el archivo de configuración cacheada mediante `APP_CONFIG_CACHE=/tmp/gelia-q6-no-config`; no se borró ni regeneró configuración de la aplicación.

| Comprobación | Resultado |
|---|---|
| Línea base antes de editar: Q5.2, elegibilidad, pesaje, destino, Q4, flujo paralelo, Recoge y workspace Tienda | **84/84** |
| Línea base JS: destino, validación de envío y apartado Recoge | **33/33** |
| Regresión final relacionada: los anteriores + Q6, handoff, recepción física, custodia parcial, incidencias, traspasos y alertas/plazos | **141/141, 897 aserciones** |
| JS de todo ControlPedidos, incluida captura y detalle Q6 | **122/122, 25 archivos** |
| Ampliación completa ControlPedidos + PDV/traspasos seleccionados | **442 pruebas: 416 pasan, 26 fallan**. Las 26 se reprodujeron en una copia aislada del árbol inicial con Q5.1/Q5.2: 86 pruebas seleccionadas, 60 pasan, 8 fallos de aserción y 18 errores |
| JS adicional `recepcionFisicaUtils.test.js` | **9/10**; mismo fallo reproducido en copia inicial: espera entrega total solo por recepción completa. El contrato vigente exige custodia completa. No se relajó esa protección |

La comprobación de sintaxis de los 22 archivos PHP Q6 (aplicación, migración y prueba nueva) pasó; `git diff --check` también.

Las 26 incidencias previas están en `ControlPedidosCandidatosPrincipalTest`, `ControlPedidosFase2Test`, `ControlPedidosMejoras21Test`, `ControlPedidosPermisosCierreTest`, `EnvioMunicipioCaratulaTest`, `Fase3FormularioProgresivoTest`, `ControlPedidosErroresPorDuenoTest`, `ControlPedidosFase22Test`, `ControlPedidosFase7Test`, `ControlPedidosHandoffAlertasTest`, `ControlPedidosMejoras28Test`, `ControlPedidosResguardoApartadoTest`, `SnapshotDireccionPedidoTest` y `EnvioBodegaTraspasoTest`. Incluyen expectativas anteriores al cierre de consulta/bultos, contratos de respuesta antiguos y pruebas sin tablas/contenedor inicializado. No se presenta la suite ampliada como verde.

Evidencia temporal local, ignorada por Git: `storage/framework/testing/q6-targeted-result.txt`, `q6-js-result.txt`, `q6-expanded-result.txt` y `q6-baseline-result.txt`. La copia inicial se reconstruyó con el commit de partida y el parche local previo; las pruebas usaron su código/clases y base SQLite en memoria.

**Comprobación manual:** no se recorrió navegador autenticado. No había una sesión de navegador autenticada disponible en las herramientas de esta ejecución y no se aplicó la migración a una base operativa para crear pedidos. La inspección estática, las pruebas de DOM y las pruebas HTTP/servicios anteriores se reportan por separado; no sustituyen esa comprobación. Siguen pendientes los tres recorridos manuales con datos de prueba: CEDIS → PDV, PDV → PDV y externo de control.

## Compatibilidad, riesgos y reversión

Se mantienen O02 (caja independiente del peso), pesaje local obligatorio y comercial, matriz efectiva Tienda para envíos externos, ausencia de modalidad documental, consulta pendiente bloqueando empaque/salida, avance paralelo de guía/empaque externo y alertas distintas de retraso. No se modifican crédito, SAF, calendario/ancla 24/48 h ni clasificación general municipal.

Antes de usar la nueva respuesta en un entorno desplegado debe aplicarse la migración aditiva mediante su proceso habitual. Desplegar el código sin esas columnas impediría guardar revisiones. No se ejecutó ese paso por instrucción expresa.

Los históricos sin cantidades conservan valores nulos. Los datos de sucursal usados con otro propósito sin modalidad siguen siendo una ambigüedad de datos, no una autorización para eximir un envío externo. Los PDV sin vínculo de sucursal deben corregirse por su mantenimiento de catálogo antes de identificar un traslado entre sucursales.

Reversión: retirar únicamente los cambios Q6 tomando como referencia el árbol inicial con Q5.1/Q5.2, **no** restaurar todo a `HEAD`, que perdería ese trabajo. Las columnas anulables pueden conservarse al revertir código. Retirarlas después perdería las nuevas cantidades, por lo que requiere preservar esos datos. No hay que revertir movimientos de inventario porque Q6 no los genera.

Pendientes concretos: integración de abastecimiento a tarea receptora PDV, captura real de llegadas parciales de gerencia, revisión manual autenticada y saneamiento separado de las pruebas previas fallidas. No quedan abiertas las definiciones de negocio de transporte interno, exención de pesaje o inventario sin sincronizar.

### Reproducción de las comprobaciones principales

```bash
./vendor/bin/sail exec -T laravel.test env APP_CONFIG_CACHE=/tmp/gelia-q6-no-config php artisan test --compact --filter='TrasladosInternosQ6Test|DefinicionesOperativasQ52Test|ElegibilidadPesajePedidoTest|ControlPedidosPesajeTest|DestinoSucursalPedidoBmaTest|ElegibilidadCedisPdvPedidoTest|ControlPedidosFlujoParaleloTest|RecogeTiendaFlujoTest|PreparacionTiendaWorkspaceTest|HandoffRecepcionEsperadaPdvTest|RecepcionFisicaResguardoPdvTest|RecepcionParcialResguardoPdvTest|IncidenciaResguardoPdvTest|SolicitudTraspasoFlujoTest|ControlPedidosPlazosRetrasoTest|ControlPedidosEvaluarRetrasosTest'
./vendor/bin/sail npm test -- resources/js/Pages/ControlPedidos
```

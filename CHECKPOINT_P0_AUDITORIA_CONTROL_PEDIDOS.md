# CHECKPOINT P0 — Auditoría funcional integral de Control de Pedidos

Fecha: 14 de octubre de 2026. Alcance: registro de Ventas, borradores, preparación en Tienda, envío municipal y enlaces con seguimiento operativo.

## Dictamen y límites

Los reportes tienen causas funcionales verificables. No conviene empezar eliminando campos: la modalidad municipal está acoplada al preparador Tienda y a su selección de almacén; existen dos selecciones independientes para la misma paquetería; parte de la captura de preparación no pertenece al borrador persistido; y las sustituciones de tareas pierden datos municipales.

Este checkpoint contiene diagnóstico y propuesta. **No modifica código de aplicación, catálogos, configuración, contratos ni datos operativos.** Había cambios previos de Punto de Venta en el árbol de trabajo; no se editaron. Se revisó el código de este árbol, no una versión desplegada certificada.

Evidencia utilizada:

- Código React/Laravel, rutas, requests, modelos, migraciones, seeders y pruebas existentes.
- Consulta de solo lectura a la BD configurada en el contenedor local de desarrollo `gelianv-laravel.test-1`. Sus resultados no acreditan el estado de producción.
- Pruebas existentes con SQLite en memoria y frontend. Los defectos señalados como estáticos no cuentan con una reproducción completa en navegador o en operación real.

Prioridades: P1 = pérdida/inconsistencia de información o bloqueo operativo; P2 = claridad, consistencia de validaciones o mantenibilidad. «P0» identifica este checkpoint, no la severidad de todos los hallazgos.

## 1. Arquitectura y fuentes de verdad

| Capa | Implementación existente | Responsabilidad real |
| --- | --- | --- |
| Entrada de Ventas | `resources/js/Pages/ControlPedidos/Index.jsx:290` | Abre/desmonta el modal; instancia nueva al abrir y actualizaciones del pedido vivo. |
| Formulario público | `Partials/ModalFormPedido.jsx:18` | Siempre delega al legado con `modoProgresivo=true`; no es otra implementación de captura. |
| Captura/orquestación | `Partials/ModalFormPedidoLegado.jsx` (4,247 líneas) | `useForm`, estados de preparación, cliente/dirección, pagos, archivos, borradores, fases, solicitud CEDIS/Tienda y sincronización. |
| Presentación por fases | `Partials/FormularioProgresivo/*`, `usePedidoDialog`, `useActualizacionesPedidos`, `useListadoDiscreto` | Presentación, foco y refresco. La lógica de dominio todavía reside mayormente en el legado. |
| Registro/API web | `routes/web/modules/control-pedidos/core.php`, `PedidoBmaController` | Endpoints Inertia/JSON; acceso al pedido y delegación a servicios. |
| Validación inicial | `PedidoBmaRequestBase`, `StorePedidoBmaRequest`, `UpdatePedidoBmaRequest` | Captura parcial de borrador, tipos, relaciones existentes y montos no negativos. |
| Persistencia | `CrearPedidoBmaService`, `ActualizarPedidoBmaService`, `ResuelveDatosPedidoBma` | Pedido, totales, logística, documentos, SAF y dirección. |
| Habilitación | `ObtenerCatalogosPedidoBmaService`, `PreparacionTiendaConfig`, `CatalogoPaqueteriaPedido` | Catálogos activos, pilotos/departamentos, almacenes y reglas de transporte. |
| Preparación | `CrearTareaPreparacionService`, `PedidoBmaTiendaController`, `Tienda/Show.jsx` y sus parciales | Solicitud, toma, piezas, evidencias, respuesta, incidencias, carátula. |
| Requisitos | `CalcularRequisitosPreparacionService`, `MatrizRequisitosPreparacion` | Combina requisitos de modalidad, matriz por departamento/almacén y transporte. |
| Seguimiento | `ListarPedidosBmaService`, `ListarTareasTiendaService`, `ListarPedidosCedisService` | Serializaciones y bandejas según permisos, filtros y estado. |
| Transiciones | `MaquinaEstadosPedidoBma`, `MaquinaEstadosTareaPreparacion`, `MaquinaEstadosCumplimientoFisico` | Tres ciclos distintos: pedido, tarea y custodia/salida física. |
| Dependencias externas al módulo | Clientes/direcciones, almacenes/inventarios, Traspasos, Saldos a Favor/pagos y PDV/resguardos | Reutilización de entidades y trazabilidad; no son campos de libre eliminación. |

En la tabla, `Partials/*` es relativo a `resources/js/Pages/ControlPedidos/`; servicios, modelos y requests pertenecen a sus directorios `ControlPedidos` bajo `app/`.

### Entidades que no deben confundirse

| Concepto | Representación | Uso comprobado |
| --- | --- | --- |
| Tipo/origen comercial del pedido | `origen_id` → `origenes_pedido.requiere_logistica` | Abre/cierra ramas de logística. No representa el almacén. |
| Origen logístico | `pedidos_bma.almacen_id`, tarea `almacen_id` | Selección de inventario, matriz de requisitos y origen del traspaso. |
| Sucursal destino | `sucursal_destino_id` → `sucursales` | Custodia/entrega PDV según modalidades; validación propia. |
| Municipio/destino municipal | Tarea `municipio_destino`, texto; snapshot en carátula | Destino impreso y seguimiento municipal. **No hay un selector de catálogo de municipios en este formulario**. |
| Dirección del cliente/pedido | `cliente_direccion_id`, `PedidoBmaDireccion`, texto/CP | Dirección verificada o excepción manual y snapshot histórico. |
| Transporte/paquetería | `catalogo_paqueterias_pedido` | Es la misma entidad en ambos selectores; cambia el conjunto elegible. |
| Modalidad de preparación | `catalogo_modalidades_preparacion_pedido` | Decide quién prepara, traslado, apartado y carátula. |
| Tipo de operación de envío | `catalogo_tipos_operacion_envio` | Normal, municipio diferido, resguardos. No equivale a modalidad de preparación. |

No se encontró en la ruta municipal auditada una relación municipio→transportista, un catálogo de rutas municipales ni una regla que permita deducir el destino solo por la paquetería. Eso no prueba que no existan catálogos geográficos en otras áreas; prueba que esta captura no los utiliza.

## 2. Recorrido funcional reconstruido

La UI efectiva usa Datos del cliente y pedido → Respuesta Tienda/CEDIS → Cotización → Pago y remisión. El contrato de progreso del servidor representa Solicitud → Consulta → Confirmación → Cotización → Pago. Confirmación se presenta dentro del recorrido de respuesta; no son once pantallas independientes.

| Etapa / responsable | Entradas | Salidas y validaciones | Endpoint / dependencia |
| --- | --- | --- | --- |
| Nuevo / Ventas | Captura limpia o borrador local | Defaults y elección de recuperación; desmontaje al cerrar | `Index.jsx`, `formDefaults`, almacenamiento local |
| Cliente y contexto / Ventas | Cliente, departamento de operación, origen, almacén | Direcciones disponibles y contexto para bancos/SAF; almacenes activos/visibles | Catálogos, Clientes, `ContextoDepartamentoPedido` |
| Modalidad/destino / Ventas | Modalidad habilitada, almacén, sucursal cuando aplica, entrega municipal | Subflujo Tienda o CEDIS; filtro municipal y requisitos de transportista | `PreparacionTiendaConfig`, validadores de almacén y sucursal |
| Soporte / Ventas | Folio documental, paquetería, PDF o foto | Archivo asociado a pedido persistido; no se guarda el binario en localStorage | `POST /control-pedidos/{id}/pdf-pedido`; `GestionarPdfPedidoBmaService` |
| Borrador / Ventas y autoguardado | Estado `data` del formulario | Pedido BORRADOR, folio interno, id, timestamp y progreso; guardar no equivale a enviar | `POST /control-pedidos`, `POST /control-pedidos/autoguardar`, `PUT /control-pedidos/{id}` |
| Recuperar / Ventas | Pedido del listado o v2/v1 local | Hidratación parcial; archivos se recuperan desde entidades del servidor, no desde la copia local | `ListarPedidosBmaService`; helpers locales |
| Solicitar / Ventas | Cliente y soporte; CEDIS exige paquetería en UI para logística; Tienda exige modalidad/almacén y entrega municipal cuando aplica | Tarea PENDIENTE, pedido en consulta pendiente; habilitación por usuario/departamento/modalidad y almacén | `POST .../solicitar-pesaje` o `.../solicitar-preparacion-tienda` |
| Preparar / Tienda o CEDIS | Tarea, documento original y productos | Toma por responsable; cantidades, condición física, evidencias y, según reglas, pesos/caja; controles de versión | Rutas `tienda.php` / `cedis.php`; `ResponderPreparacionTiendaService` |
| Municipal / Tienda | Datos de tarea y requisitos del transporte | EN_ATENCION → LISTA_PARA_CARATULA → generación PDF → confirmación colocada → RESPONDIDA | Servicios de carátula; snapshot y versiones |
| Confirmar / Ventas | Respuesta y evidencias revisadas con cliente | Cierre de consulta; desbloquea monto, dirección, cotización y pago. Traslado debe haberse recibido; carátula municipal debe estar colocada | `POST .../cerrar-consulta`; `PedidoBma::puedeCerrarConsulta` |
| Cotizar/pagar / Ventas | Monto final, logística según variante, exhibiciones, SAF | Cálculos de flete/seguro/reexpedición y cobertura de pago; la regla final del servidor vuelve a validar | `ResuelveDatosPedidoBma`, `CalcularTotalesEnvioPedidoService`, servicios SAF/pagos |
| Enviar a revisión / Ventas | Pedido editable, consulta cerrada y campos finales | PENDIENTE_AUXILIAR; valida cobertura, genera snapshot, avanza cola de errores | `PUT .../enviar`; `EnviarPedidoBmaService`, `ValidacionCamposPedidoBma` |
| Seguimiento/salida / Auxiliar, Tienda, CEDIS, Delegado | Pedido revisado, pago o permiso por cobrar, preparación y documentos | Auditoría y guía según variante; salida municipal: confirmar pago → autorizar salida → empacar → despachar con receptor | Servicios de revisión y `ConfirmarPagoSalidaPreparacionService`, `AutorizarSalidaPreparacionService`, `ConfirmarEmpaqueMunicipioService`, `ConfirmarDespachoMunicipioService` |

Variantes comprobadas: Recoge hoy termina con apartado confirmado y no se envía a pago/auditoría (`EnviarPedidoBmaService:34`); Tienda→Bodega crea Traspaso y espera recepción CEDIS; complementarios heredan logística del principal; resguardo abierto difiere dirección/costo mediante Completar envío. No se deben imponer al resto los requisitos particulares de una variante.

## 3. Los cuatro reportes

### H1. Municipio y transporte: ambigüedad de etiquetas y habilitación incompleta — P1/P2

**Confirmado:** `ModalFormPedidoLegado.jsx:2598` muestra un input libre `entregaMunicipal.municipio_destino`; `:2605` muestra Transporte con `entregaMunicipal.catalogo_paqueteria_id`. No se capturan dos entidades de transporte: ese id apunta al catálogo general de paqueterías.

`ObtenerCatalogosPedidoBmaService:84` construye `paqueterias_municipio` con tres condiciones simultáneas: `activo=true`, `habilitado_envio_municipio=true`, `reglas_municipio_pendientes=false`. `CatalogoPaqueteriaPedido::habilitadaParaEnvioMunicipio` aplica el mismo criterio en backend. No filtra esta lista por municipio, usuario o permiso de transportista.

**Datos locales observados:** 23 paqueterías, 23 activas, cero elegibles y 23 con reglas pendientes. Por tanto, el mensaje vacío corresponde al resultado real de habilitación en este entorno; no a la inexistencia de un catálogo general. La migración `2026_08_24_200000_fase6_envio_municipio_caratulas.php:52` introduce los flags con defaults false/true.

**Causa adicional:** `TablaPaqueteriasPedido.jsx` y `CatalogoController::reglasPaqueteriaPedido` (`:770`) permiten configurar costo diferido, tarifa y peso, pero no exponen ni aceptan los flags de habilitación municipal ni el conjunto completo de sus reglas. Crear una paquetería local/regional desde ese administrador no basta para que aparezca en Transporte. Hace falta completar esa administración reutilizando la misma entidad, no otra tabla.

**Propuesta:** un selector «Paquetería / transporte» con el catálogo existente y elegibilidad municipal explícita; mantener destino como dato independiente, autocompletado desde la dirección cuando exista correspondencia comprobada y editable antes de solicitar. Si no hay elegibles, mostrar causa y ruta de configuración a quien tenga permiso; al resto indicar a qué área solicitar habilitación. No habilitar todas automáticamente: las reglas definen documentos, cobro y empaque.

**No retirar municipio:** `GenerarCaratulaPedidoService:104` lo copia al snapshot y a `pedido_bma_caratulas`; el PDF lo imprime y las serializaciones de Ventas/Tienda lo muestran. Su responsabilidad actual sí es operativa, aunque no sea un catálogo de rutas.

### H2. Preparación en Tienda solicita o representa datos ya existentes — P1/P2

Hay que distinguir duplicación de captura de simple consulta:

| Dato | Estado actual / evidencia | Tratamiento propuesto |
| --- | --- | --- |
| Folio interno | Generado como `PBMA-año-secuencia`, estable; complementos `-Cn` | Heredado, solo lectura. |
| Folio inicial | `data.folio_remision`, rotulado Folio, `:2928` | Heredar en la solicitud con tipo documental explícito. |
| Folio adicional | `folioReferencia`/`tipoReferencia`, otra captura opcional `:3173`; se registra en `PedidoBmaReferencia` | Mostrar referencia vigente; agregar/cambiar otra solo como acción explícita. No exigir volver a escribir el mismo folio. |
| Departamento | La solicitud muestra un texto calculado desde `departamentoContexto`, `:3169` | Ya es heredado de solo lectura en ese bloque. No afirmar que allí se captura dos veces. |
| Prioridad del mismo día | Estado local `prioridadMd`; backend la guarda en pedido al solicitar y ajusta plazo | Dato propio de planificación; editable con persistencia, no eliminar sin validar su efecto en vencimientos. |
| Soporte del pedido | `tieneSoporteSolicitud()` acepta PDF/foto de pedido o cotización; `DocumentoSolicitudTienda` consulta el documento original | Ya existe reutilización. Mantener visor, no pedir una segunda carga del mismo archivo. |
| Piezas/evidencias | Tienda registra SKU, cantidades encontradas y estado físico | Información propia de ejecución y confirmación; no confundir con duplicación de la solicitud original. |
| Destinatario/dirección | Datos en tarea separados del cliente y del snapshot de dirección | Heredar una propuesta visible desde dirección/contacto; confirmar diferencias antes de congelar snapshot. |

**Duplicación funcional confirmada:** con `requiereLogistica`, el bloque del archivo muestra `data.catalogo_paqueteria_id` (`:2937`), incluso cuando también se muestra Transporte municipal. Son dos estados para la misma entidad, sin sincronización. Al solicitar, `CrearTareaPreparacionService:193` copia la paquetería municipal al pedido y puede reemplazar la elegida en el bloque general. También transforma `modalidad_cobro` en `pedido.envio_por_cobrar`. Una edición posterior del pedido usa el estado general, mientras la tarea conserva su propio valor.

**Riesgo de divergencia:** `ActualizarPedidoBmaService` actualiza pedido, no la entrega municipal de la tarea ni sus carátulas. No hay en esa ruta un comando que sincronice ambos snapshots o invalide las carátulas por cambios de esos datos generales. Separar edición previa a preparación de una corrección posterior versionada.

**Folios distintos no implican corrupción:** `PedidoBmaReferencia::visible` prioriza la referencia vigente distinta de REMISION y, sin ella, usa `folio_remision` o el folio interno. La remisión operativa también tiene manejo propio. Esta precedencia explica que cambie el folio mostrado sin alterar el interno. Unificar etiquetas y herencia, conservando tipos e historial.

### H3. «Costo diferido»: no es un importe adicional del pedido — P2

No se encontró un input de importe `costo_diferido` en la captura auditada. Existen:

1. `catalogo_paqueterias_pedido.permite_costo_diferido`, booleano editable en el administrador, etiqueta «Permite costo de envío diferido (municipio)» (`TablaPaqueteriasPedido.jsx:184`). Se inicia true para nuevas locales/regionales y se modifica al cambiar categoría.
2. El sufijo informativo «· costo diferido» de opciones locales (`ModalFormPedidoLegado.jsx:2961`).
3. `pedidos_bma.costo_envio`, importe nullable, y `tipo_operacion_envio_id`; el tipo MUNICIPIO_DIFERIDO se deriva de la paquetería en `ResuelveDatosPedidoBma::resolverTipoOperacionEnvioId`.

No es obligatorio marcar el booleano para registrar un pedido. El costo puede omitirse según variante: costo diferido, resguardo, guía cliente, por cobrar y algunas condiciones de tarifa/peso. `ValidacionCamposPedidoBma:17` controla la omisión y `:191` convierte diferido sin costo en pendiente de regularización. `PAQ. PENDIENTE` también utiliza esta capacidad; el seeder y la migración de agosto lo configuran así. Por tanto, no representa exclusivamente envíos municipales.

**Consumidores comprobados:** resolución del tipo, validación al enviar, cálculo de totales por cajas, progreso/cotización y presentación del registro; tests `ControlPedidosEnvioDiferidoFase1Test`, `ControlPedidosPaqPendienteTest`, `ControlPedidosTarifaLocalTest`. Las consultas que seleccionan/exportan el tipo o el costo seguirían recibiendo datos distintos si se cambiara esa derivación. La búsqueda del flag en sí no demuestra un uso directo en todos los reportes.

**Recomendación:** retirar el tecnicismo de la captura cotidiana o renombrar el indicador informativo; conservar columna, tipo y capacidad mientras se define su política. No esconder el único control administrativo sin una política alternativa: sus defaults seguirían aplicándose. Si el reporte se refiere al costo normal de envío, ese importe interviene en totales y cobertura y no es eliminable por este diagnóstico. La evidencia permite simplificar la exposición; no autoriza afirmar que la capacidad no se usa ni borrar históricos.

### H4. Municipio sobrescribe CEDIS con PDV — P1

Cadena causal exacta:

1. Seleccionar `ENVIO_MUNICIPIO` cambia `codigoModalidadPreparacion` (`ModalFormPedidoLegado.jsx:2537`).
2. Esa modalidad vuelve verdadero `enFlujoTienda` (`:498`).
3. `almacenesPreparacion` usa `preparacion_config.almacenes_habilitados`; si está vacío, solo acepta almacenes tipo PDV (`:482`).
4. El efecto `:577` comprueba si el almacén elegido pertenece a ese conjunto. Si no pertenece y hay una única opción, **sobrescribe** `data.almacen_id`; si hay cero o varias, lo vacía. No existe un id fijo de PDV: existe un filtro de tipo/configuración y selección automática de la única opción.
5. El selector `:2487` también se limita al mismo conjunto. `CrearTareaPreparacionService:65` exige `almacenPermitido`; `PreparacionTiendaConfig:158` rechaza CEDIS por defecto. Finalmente el servicio escribe el almacén solicitado en tarea y pedido (`:155`, `:184`).

**Datos locales:** preparación activa, ENVIO_MUNICIPIO habilitado, `almacenes_habilitados=[]`; almacenes visibles de salida CEDIS (VTA, id 1) y CARMEN CADENA (PDV, id 2). Esta combinación explica el reemplazo por la única opción PDV. La suite contiene incluso `PreparacionTiendaWorkspaceTest::test_tienda_no_utiliza_cedis_por_defecto_y_respeta_almacenes_configurados`, que protege la restricción actual.

**Borradores:** la modalidad seleccionada todavía no enviada no se guarda con `data`; al reabrir se pierde. Al volver a elegir municipio, se ejecuta de nuevo el reemplazo. Para tarea existente, el efecto general sale por `usaPreparacionTienda`, pero el selector continúa filtrado; no asumir que ese guardado resuelve todas las ediciones históricas. El update preserva un almacén vacío, pero **sí acepta otro id no vacío válido**: no evita que el autoguardado convierta el reemplazo visual en persistente.

**Restricciones reales:** `permite_salida_pedidos` impide un nuevo origen marcado solo destino, con excepción para conservar el id histórico. Activo/visible controlan el catálogo; habilitación de preparación es otra regla. «Recoge hoy solo PDV» es una restricción explícita distinta, que no debe desaparecer al corregir municipio.

**Propuesta, sin aplicarla:** separar origen, destino, transportista y área preparadora. Conservar el almacén cuando cambie la modalidad; ante incompatibilidad, explicar y requerir una selección explícita. Para CEDIS→municipio, definir el preparador y reutilizar preparación/requisitos/carátula/salida existentes. No basta con quitar el efecto React: backend y bandejas seguirían restringiendo el caso. Tampoco basta con agregar CEDIS a la lista global: ampliaría otras modalidades y no determina responsabilidades. Establecer elegibilidad por combinación modalidad/origen y probar inventario, matriz, asignación y salida antes de habilitarla.

## 4. Hallazgos transversales adicionales

### T1. Captura de preparación fuera del borrador — P1, confirmado por trazado estático

`ModalFormPedidoLegado.jsx:346` declara modalidad, prioridad, folio/tipo de referencia y `entregaMunicipal` en estados independientes. `serializarBorrador` (`:132`) y los autoguardados (`:1097`, `:1210`) solo reciben `data`. Las reglas del borrador tampoco aceptan esos atributos municipales ni la prioridad/referencia adicional. Solo se envían al crear la tarea (`:1976`).

Consecuencia: cerrar/desmontar o recargar antes de solicitar pierde municipio, transporte municipal, destinatario alternativo, referencia, modalidad de cobro, prioridad y referencia adicional, aunque aparezca «guardado». La recuperación local mezcla `data`, no esos estados; el servidor tampoco puede reconstruirlos desde un borrador sin tarea. La copia local es una sola clave global v2, con fallback v1, sin partición por pedido/usuario. No se probó cambio de sesión; esta última característica es un riesgo de mezcla de recuperación, no una incidencia demostrada.

Propuesta: un estado serializable de solicitud, versionado, con persistencia parcial en servidor y copia local por identidad/pedido; restaurar antes de aplicar cualquier default. Separar metadatos de archivos de binarios y comunicar qué está efectivamente guardado. Mantener compatibilidad con v1/v2 y borradores antiguos sin datos municipales.

### T2. La sustitución de tarea descarta entrega municipal — P1, confirmado por listas de atributos

`CorregirTareaPreparacionService:73` crea la sustituta con modalidad, almacén, plazo y observaciones, pero omite destinatario, teléfono, municipio, dirección/referencia, transportista, cobro y `requiere_traslado_cedis`. `CambiarModalidadPreparacionService:128` tampoco captura ni copia entrega municipal al entrar en ENVIO_MUNICIPIO. Tener esos datos en el pedido no basta: `GenerarCaratulaPedidoService` los toma de la tarea.

Propuesta: comando común de creación/sustitución que herede datos compatibles, valide los nuevos requeridos y conserve vínculo `tarea_anterior_id`. Si cambia modalidad, solicitar solo los datos que falten. No reutilizar una carátula antigua como si describiera la nueva tarea. Pruebas necesarias para incidencia municipal→corrección y otra modalidad→municipal.

### T3. Confirmar carátula utiliza una variable no capturada — P1, defecto estático

`ConfirmarCaratulaColocadaService:33` abre una closure con `use ($tarea, $usuario, $versionEsperada)`, pero en `:47` evalúa `$caratulaId`, parámetro que no fue incluido. En PHP la variable externa no se incorpora automáticamente a esa closure. Puede generar error bajo el manejador de Laravel e impide que el id solicitado se procese correctamente. Bloquea una etapa necesaria para responder municipio. La suite municipal disponible no demuestra esta ejecución transaccional; requiere prueba de integración con id vigente, obsoleto y omitido antes de corregir.

### T4. Protección de concurrencia de borrador incompleta — P1, riesgo respaldado por código

`PedidoBmaController:143` compara `updated_at_esperado` opcional, usa segundos y tolera diferencia ≤1; comprueba antes de actualizar, sin escritura condicional por versión en esa ruta. Dos escrituras concurrentes pueden superar la comparación. Además, al recibir 409, `ModalFormPedidoLegado.jsx:1168` adopta el timestamp del servidor sin reconciliar el contenido local; una edición posterior puede reintentar con ese token y datos locales antiguos. No se midió una colisión real.

Propuesta: revisión monotónica, actualización atómica y rechazo persistente hasta recargar/reconciliar. Un 409 debe ser un estado visible de conflicto, no una oportunidad de avanzar la revisión sin resolverlo. `estadoGuardadoProg` (`:773`) trata cualquier texto de servidor sin «guardando» o «error» como guardado, incluyendo «Servidor · conflicto»: inconsistencia visual comprobable.

### T5. Tener id no garantiza que la captura más reciente esté guardada — P1, riesgo de orden de operaciones

`asegurarPedidoEnBd` (`ModalFormPedidoLegado.jsx:1189`) devuelve inmediatamente un id existente; no persiste cambios pendientes. Solicitar preparación llama a ese helper y luego envía solo el payload de preparación. Cambiar cliente/datos de pedido y solicitar antes del autoguardado puede hacer que backend use el cliente o soporte anteriores; la entrega con destinatario=cliente se resuelve desde el pedido persistido.

Propuesta: guardar y esperar la revisión vigente antes de toda acción que consuma datos; serializar guardado manual, automático y solicitud. Evitar dos creaciones simultáneas si aún no hay id. El booleano `autoguardandoBd` limita el timer, pero no constituye por sí solo una cola compartida para todos los disparadores.

### T6. Preparación municipal no equivale a pesaje CEDIS — P1, incompatibilidad condicional

`ResponderPreparacionTiendaService::aplicarSincronizacionPedido` (`:326`) marca `pesaje_respondido_at` al completar Tienda. `PedidoBma::tienePesajeRespondido` solo evalúa esa fecha. Sin embargo, `ValidacionCamposPedidoBma:105` exige peso real, caja, número de cajas y detalle de cajas cuando hay logística y pesaje respondido, salvo modo revisión de mercancía. Este último depende de `origen.requiere_logistica=false`, no de modalidad municipal.

Por ello, una preparación municipal legítima que no exija peso/caja según su transporte puede completar su respuesta y quedar bloqueada al enviarse a auxiliar por requisitos propios de CEDIS. La omisión de costo diferido no evita ese bloque. No se reprodujo el recorrido completo porque el entorno local tiene cero transportes municipales elegibles; el conflicto se fundamenta en las condiciones de código, no en un pedido afectado observado.

Propuesta: validar capacidades de la operación y requisitos efectivos; separar el hito «preparación respondida» del supuesto «existe pesaje detallado». Reutilizar matriz y políticas existentes; no introducir excepciones por nombres de paquetería.

### T7. Validaciones/progreso no describen siempre la misma condición — P2

- El frontend comprueba municipio no vacío; el servicio exige longitud 2–255. El teléfono alternativo no se valida en el disparador municipal, pero el servicio lo exige. Los contactos faltantes del cliente se descubren al solicitar, sin edición visible cuando «destinatario es cliente» está marcado.
- `CalcularProgresoPedidoBmaService::solicitudCompleta` acepta soporte **o** folio; solicitar Tienda exige soporte, y el envío final exige también folio. Puede marcar solicitud completa antes de que la acción sea ejecutable.
- `CalcularProgresoPedidoBmaService::cotizacionLista` exige costo salvo resguardo/guía cliente/por cobrar, mientras `ValidacionCamposPedidoBma` contempla además costo diferido y ciertas tarifas locales sin peso. El progreso puede no coincidir con la validación final.
- Los requests generales aceptan ids existentes; el catálogo lista activos y la validación de habilitación municipal vive en otro servicio. No extender la afirmación «id existe» a «es elegible para cualquier operación».

Propuesta: publicar requisitos y bloqueos ejecutables por etapa desde una política compartida; mantener validaciones tempranas de formato en React y reglas autoritativas en servidor. Diferir algunos datos a etapas posteriores es correcto cuando se comunica y se conserva el borrador.

### T8. Idempotencia y exclusión de tarea activa — P1, riesgo de concurrencia pendiente de reproducción

La UI genera `prep-id-Date.now()` por intento. El servicio busca por clave y comprueba tarea activa antes de la transacción de creación, sin bloquear el pedido en esa sección. La clave no es estable entre reintentos. La migración `2026_08_24_180000_fase4_preparacion_tienda.php:95` garantiza unicidad de la clave de idempotencia, pero el índice `(pedido_bma_id, estado)` es normal, no único. Dos intenciones con claves distintas no quedan excluidas por ese índice. Hace falta una prueba concurrente antes de afirmar duplicados observados en producción.

Propuesta: misma clave por intención y bloqueo/revalidación de tarea activa dentro de la transacción. No confundir el soporte parcial de idempotencia con garantía probada de unicidad operativa.

## 5. Modelo de simplificación propuesto

Un pedido conserva origen logístico, destino y medio de entrega como conceptos separados. La tarea ejecuta una solicitud de preparación asociada; sus snapshots tienen una razón histórica, no deben ser copias editables sin coordinación.

1. **Solicitud única persistida:** cliente, contexto, folio/documento, almacén y modalidad. Usar los campos y relaciones actuales antes de introducir otros. Añadir persistencia parcial solo donde hoy no existe y con contrato explícito de borrador.
2. **Entrega municipal integrada:** un único valor canónico de paquetería, destino propuesto desde dirección y contacto confirmado. Mostrar requisitos efectivos del transporte. No volver a preguntar paquetería en el bloque documental.
3. **Preparación:** mostrar cliente, departamento, referencias y soporte heredados; capturar únicamente prioridad/indicaciones nuevas o cambios explícitos. Tienda registra cantidades y evidencias propias de ejecución.
4. **Confirmación y cambios:** mantener confirmación del cliente y colocación física de carátula, pues representan hechos diferentes. Cambiar destino/transportista después de solicitar debe corregir/versionar la tarea y revisar documentos derivados.
5. **Origen independiente:** conservar selección en cambios de modalidad; parametrizar qué área puede preparar cada combinación, con CEDIS→municipio como caso explícito. El almacén sigue determinando inventario y origen de traspaso.
6. **Seguimiento coherente:** presentar por separado consulta, validación comercial/pago y cumplimiento físico. No reutilizar un indicador de pesaje como prueba de todos los requisitos físicos.

Reutilizar `CatalogoPaqueteriaPedido`, `PedidoBmaReferencia`, snapshot de direcciones, `CalcularRequisitosPreparacionService`, matrices, políticas de salida y máquinas de estados. Reutilizar también el visor de soporte, bandejas y carátulas con versión/hash. No construir un segundo catálogo municipal, un segundo registro de pedidos ni otra máquina paralela.

## 6. Secuencia de implementación recomendada y compatibilidad

| Entrega propuesta | Alcance | Criterio de aceptación |
| --- | --- | --- |
| P1 — Integridad de captura | Persistencia de solicitud, restauración, guardado antes de acciones y conflictos reales | Cerrar/reabrir recupera toda entrega; no sobrescribe cambios concurrentes ni usa cliente anterior. |
| P1 — Integridad de preparación | Clonado validado, carátula y unicidad de tarea activa | Corrección conserva entrega, id de carátula se valida, reintento no duplica tareas. |
| P1 — Origen y elegibilidad | Política por modalidad/origen y área, sin sobrescritura silenciosa | CEDIS y PDV pueden ejecutar las combinaciones definidas; Recoge hoy conserva sus restricciones. |
| P1/P2 — Administración y selección | Completar reglas municipales del catálogo; selector único de paquetería | Se habilita con reglas completas, se explica exclusión y no hay dos ids divergentes. |
| P2 — Validaciones y lenguaje | Requisitos compartidos, folio tipado, prioridad persistida, terminología de costo | La acción disponible coincide con validación final; no se recaptura información heredable. |
| P2 — Separación técnica | Extraer estado/borrador y secciones después de fijar contratos | Una sola política de dominio; componentes consumen el mismo contrato, sin reescritura total del formulario. |

No aplicar todavía ninguna entrega. No activar flags municipales masivamente ni reescribir pedidos históricos. Conservar columnas, referencias, documentos, ids y tipos existentes; migraciones futuras aditivas con campos opcionales para borradores viejos. Un snapshot emitido no se actualiza retroactivamente al cambiar el catálogo. La reversión de una nueva política debe conservar datos ya capturados y poder deshabilitar combinaciones nuevas sin eliminar registros.

La regla operativa que falta concretar es quién prepara/autoriza/despacha CEDIS→municipio y qué requisitos corresponden a cada paquetería. El código demuestra la restricción actual; no demuestra que sea la regla de negocio deseada. El retiro definitivo de la capacidad de costo diferido requiere identificar casos pendientes y consumidores del tipo en datos del entorno objetivo.

## 7. Matriz de regresión necesaria para la corrección futura

| Escenario | Resultado a verificar |
| --- | --- |
| CEDIS→municipio y PDV→municipio | Origen conservado; inventario, matriz, bandeja y área correctos. |
| Cambiar modalidad ida/vuelta | No modifica origen ni borra destino/captura compatible; incompatibilidad explícita. |
| Recoge hoy, transferencia, Tienda→Bodega | Apartado, pagos y traspaso mantienen sus restricciones actuales. |
| Municipal con/sin destinatario cliente | Contactos visibles y editables según necesidad; validación consistente. |
| Guardar/cerrar/reabrir y recargar | Municipio, paquetería, cobro, contacto, modalidad, prioridad y referencia intactos. |
| Archivos en borrador | Soporte persistido visible; binarios no serializados localmente y errores de carga visibles. |
| Dos pestañas y edición durante guardado | Conflicto real no se presenta como guardado ni se elude adoptando el token. |
| Solicitar antes del debounce | La tarea consume la última revisión de cliente, origen y datos de pedido. |
| Cero transportes, inactivo, reglas pendientes | Estado accionable y rechazo coherente en backend; historial aún legible. |
| Paquetería general distinta de municipal | No hay dos selecciones independientes ni cambio posterior silencioso. |
| Incidencia/corrección y cambio a municipal | Sustituta mantiene datos compatibles y exige los nuevos; carátula deriva de ella. |
| Carátula vigente/obsoleta/id omitido | Confirma exactamente la vigente; no variable fuera de alcance; conserva versiones/hash. |
| Transporte sin peso/caja | Preparación y envío final usan los mismos requisitos efectivos. |
| Pagado/por cobrar/guía cliente/diferido | Totales y cobertura correctos; no se interpreta costo desconocido como flete gratuito. |
| Resguardo principal/complementario | Herencia y Completar envío siguen funcionando; sin nuevos folios huérfanos. |
| Folio interno/documental/remisión | Precedencia visible explicable y estable; no sobrescribe el folio interno. |
| Reintentos simultáneos de solicitud | Una sola tarea activa, respuesta idempotente y trazabilidad completa. |
| Históricos con almacén hoy deshabilitado | Se visualizan y conservan; no se permite una nueva salida inválida. |

## 8. Verificación ejecutada

Frontend: `npm test -- resources/js/Pages/ControlPedidos`: **19 archivos, 85 pruebas aprobadas**. Son las pruebas existentes del módulo, no una prueba de extremo a extremo de captura municipal.

Backend: ejecución con PHP 8.4.25 del contenedor de desarrollo, PHPUnit 12.5.25 y configuración del repositorio (SQLite `:memory:`). El PHP 8.2 del host no satisface PHPUnit; se usó el runtime disponible sin cambiar dependencias.

Comando: `docker exec -w /var/www/html gelianv-laravel.test-1 php vendor/bin/phpunit tests/Unit/ControlPedidos tests/Feature/ControlPedidos --log-junit /tmp/gelia-p0-pedidos-phpunit.xml`.

Resultado: **339 pruebas (303 Unit, 36 Feature), 1,171 aserciones; 19 errores, 9 fallos y 1 prueba riesgosa**. Ninguno es introducido por una modificación de aplicación en este checkpoint. No se declara la suite sana ni todos esos fallos como defectos de producto.

| Grupo de resultado no satisfactorio | Casos | Lectura de la evidencia |
| --- | --- | --- |
| Cierre de consulta requerido antes de enviar | 7 errores y 2 fallos de mensaje esperado | Fixtures/expectativas llegan a una precondición nueva; no prueban por sí solos que deba quitarse la confirmación. |
| SQLite sin tablas requeridas | 8 errores | Mejores28 (4), ResguardoApartado (3), EnvioBodegaTraspaso (1); fixtures/esquema insuficientes. |
| Bootstrap de contenedor/vistas | 3 errores | Fase7 (2) y render municipal (1); el render hereda TestCase de PHPUnit y no prepara Laravel. |
| Bultos obligatorios | 1 error | Fase22 intenta empacar mostrador sin evidencia requerida por el contrato actual. |
| Candidatos principal | 3 fallos | Contrato de fecha y filtros fase/fecha no satisface expectativas. |
| Retrasos | 2 fallos | Duplicación esperada/conteo y texto de voz. |
| Permisos de cierre | 1 fallo | Titular obtiene 403 al marcar enviado; requiere análisis de contrato/fixture. |
| Enmascaramiento municipal | 1 fallo | Expectativa de teléfono difiere de implementación. |

La prueba riesgosa `ControlPedidosHandoffAlertasTest:102` dejó manejadores de errores/excepciones sin retirar, según el reporte de PHPUnit. No se cuentan esos avisos como casos adicionales.

Se ejecutó también `tests/Feature/ControlPedidos` de forma independiente para comprobar aislamiento: **36 pruebas aprobadas, 230 aserciones**, 14.172 segundos; resultado en `/tmp/gelia-p0-pedidos-feature.log`.

Las pruebas Feature cubren rutas de preparación, bultos, remisión, evidencia y eliminación; la cobertura existente no garantiza persistencia municipal ni salida CEDIS→municipio. La prueba `EnvioMunicipioCaratulaTest` comprueba flags, constantes, transiciones y plantilla, pero no completa el flujo transaccional de colocación de carátula.

Los archivos temporales de resultados se encuentran en `/tmp/gelia-p0-pedidos-phpunit.log`, `/tmp/gelia-p0-pedidos-phpunit.xml` y `/tmp/gelia-p0-pedidos-vitest.log`. No contienen un respaldo de datos de clientes. La matriz de la sección 7 es cobertura propuesta para implementar correcciones, no una afirmación de pruebas ya ejecutadas.

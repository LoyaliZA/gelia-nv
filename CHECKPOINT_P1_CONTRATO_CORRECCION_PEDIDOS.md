# CHECKPOINT P1 — Contrato funcional y plan de corrección de Control de Pedidos

Fecha: 14 de octubre de 2026. Estado: **propuesta para revisión; implementación no autorizada por este checkpoint**.

Base: `CHECKPOINT_P0_AUDITORIA_CONTROL_PEDIDOS.md`; código revisado en HEAD `861cb19` (prefijo). El árbol estaba limpio al iniciar P1. Solo se crea este documento. No se ejecutaron migraciones, consultas de escritura, pruebas con migraciones, instalación de dependencias, commits ni push.

## 1. Decisiones técnicas propuestas

1. Ampliar el pedido y sus rutas actuales para persistir la intención de preparación antes de crear una tarea. Mantener los datos operativos en sus relaciones actuales; no crear otro registro de pedidos.
2. Un único valor editable de paquetería en captura. La tarea y la carátula conservan snapshots coordinados y con trazabilidad, no selectores independientes.
3. Un borrador puede estar incompleto. «Guardado» significa que el servidor confirmó exactamente la revisión visible; no significa que el pedido esté listo para solicitar preparación o enviar a revisión.
4. Revisión monotónica del registro, comparación dentro de una transacción y cola única de escritura en la UI. No usar `updated_at` ni presencia en caché como exclusión mutua.
5. Creación y solicitud idempotentes por intención estable; exclusión de tareas vigentes mediante bloqueo del pedido, reutilizando la definición actual de vigencia.
6. Guardar y confirmar la última revisión antes de solicitar. La solicitud debe consumir esa revisión y rechazar discrepancias; un id existente no basta.
7. Conservar las máquinas de estados. Reutilizar sustitución, invalidación de carátulas, requisitos efectivos, referencias y políticas de salida. No agregar transiciones para sortear una validación.
8. Conservar el almacén al cambiar modalidad. La elegibilidad de preparación será explícita; CEDIS→municipio queda pendiente de definición operativa y habilitación específica.
9. Ampliar la administración del catálogo existente de paqueterías. No activar transportes ni cambiar reglas durante P1.
10. Refactorizar por contratos y responsabilidades, conservando el modal actual hasta verificar equivalencia. Mantener capacidad e históricos de costo diferido.

Las decisiones de esta sección son recomendaciones técnicas para aprobar. Los nombres de campos, códigos y auxiliares nuevos definidos abajo son **extensiones propuestas**, no APIs que ya existan. Las reglas operativas no resueltas están enumeradas en la sección 10 y no se consideran aprobadas por presentar este contrato.

## 2. Evidencia crítica revalidada

| Ref. | Archivo / punto de entrada | Evidencia que condiciona el diseño |
| --- | --- | --- |
| E1 | `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx:132`, `:346`, `:1097`, `:1210` | La serialización guarda `data`; modalidad, entrega municipal, prioridad y referencia adicional están fuera. |
| E2 | Mismo archivo `:482`, `:577`, `:2487` | Filtro de almacenes de preparación, reemplazo/vaciado automático y selector restringido. |
| E3 | Mismo archivo `:1168`, `:1189`, `:1976` | Adopta timestamp tras conflicto; retorna id sin guardar; solicitud usa otro payload y clave con hora. |
| E4 | `app/Http/Controllers/ControlPedidos/PedidoBmaController.php:114`, `:220`, `:558` | Autoguardado compara timestamp opcional antes del update; update manual no comparte esa comparación; solicitud devuelve redirect/flash. |
| E5 | `app/Http/Requests/ControlPedidos/PedidoBmaRequestBase.php:10`; `SolicitarPreparacionTiendaRequest.php:24` | Borrador parcial y solicitud tienen contratos distintos; no existe persistencia de entrega municipal previa a tarea. |
| E6 | `app/Services/ControlPedidos/ActualizarPedidoBmaService.php:34`; `ResuelveDatosPedidoBma.php::atributosPedidoBase` | La transacción de update existe, pero el resolvedor llena defaults para atributos omitidos; solo algunos campos vacíos se conservan expresamente. Intersectar keys en controller no garantiza semántica PATCH. |
| E7 | `app/Services/ControlPedidos/CrearTareaPreparacionService.php:119`, `:135`, `:193`; `app/Models/ControlPedidos/PedidoBma.php:627` | Clave única ya existe; comprobación de tarea vigente fuera de transacción; paquetería/cobro municipal se copian al pedido. |
| E8 | `app/Services/ControlPedidos/CorregirTareaPreparacionService.php:73`; `CambiarModalidadPreparacionService.php:128` | Sustituta omite entrega municipal; corrección solo admite CON_INCIDENCIA; cambio de modalidad tiene otra selección de estados y registra historial cuando no puede cancelar. |
| E9 | `app/Services/ControlPedidos/ConfirmarCaratulaColocadaService.php:33`, `:47`; `PedidoBmaTiendaController.php:507` | `$caratulaId` no está capturado por closure; ruta acepta id/version opcionales mediante Request genérico. |
| E10 | `app/Observers/PedidoBmaTareaPreparacionCaratulaObserver.php:20`; `app/Providers/AppServiceProvider.php:207`; `InvalidarCaratulasVigentesService.php` | Observador registrado: invalida por cambios municipales en la tarea; puede usar RESPONDIDA→LISTA_PARA_CARATULA, transición ya existente. No observa cambios del pedido. |
| E11 | `CalcularRequisitosPreparacionService.php:32`, `:306`; `ResponderPreparacionTiendaService.php:326`; `ValidacionCamposPedidoBma.php:105` | Requisitos efectivos ya existen; respuesta Tienda marca fecha de pesaje; validación final puede exigir cajas CEDIS aun cuando la preparación no las requiere. |
| E12 | `PreparacionTiendaConfig.php:156`; `app/Support/ControlPedidos/VisibilidadTareaPreparacion.php:17`; `CrearTareaPreparacionService.php:152` | Default PDV; bandeja Tienda filtra área TIENDA; creación fuerza área TIENDA. Agregar CEDIS al arreglo no define otro preparador. |
| E13 | `ObtenerCatalogosPedidoBmaService.php:84`; `app/Models/ControlPedidos/CatalogoPaqueteriaPedido.php:71`; `app/Http/Controllers/CatalogoController.php:770` | Elegibilidad municipal de tres flags; administración no acepta el conjunto completo de reglas municipales. |
| E14 | `app/Models/ControlPedidos/PedidoBmaReferencia.php::visible`; `GenerarCaratulaPedidoService.php:104`; `RegenerarCaratulaPedidoService.php` | Precedencia documental existente, snapshot de destino, regeneración ordinaria bloqueada si ya hay carátula colocada. |
| E15 | `database/migrations/2026_08_24_180000_fase4_preparacion_tienda.php:95`; `2026_07_09_150000_create_modulo_control_pedidos_tables.php:62`; `GenerarFolioPedidoBmaService.php` | Idempotencia de tarea y folio interno tienen índices únicos; folio se calcula leyendo el último. La unicidad evita folios duplicados, pero no asegura éxito de dos creaciones distintas simultáneas. |

En E6–E15, los nombres de servicios sin prefijo pertenecen a `app/Services/ControlPedidos/`. Las ubicaciones se verificaron contra el árbol actual. No se volvieron a consultar los datos operativos: el conteo local de 23 paqueterías sin elegibles es evidencia histórica de P0, no una certificación de configuración actual o de producción.

Precisiones respecto a P0: sí existe invalidación automática **al editar la tarea** (E10); sigue pendiente coordinar la edición **del pedido**. `RevisionEnCursoPedidoBma` es presencia de revisión en caché con TTL, no un bloqueo transaccional ni una revisión numérica reutilizable. RESPONDIDA no pertenece a `tareaPreparacionVigente()`; «una vigente» no debe reinterpretarse como «una tarea para toda la vida del pedido».

## 3. Matriz completa de problemas y soluciones

Severidad alta: pérdida de captura, inconsistencia o bloqueo de operación. Media: claridad y mantenibilidad. Certeza C: comportamiento o defecto demostrado en código; R: riesgo de ejecución no reproducido; O: decisión operativa pendiente. C no significa reproducción de extremo a extremo. Las pruebas R01–R15 se especifican en la sección 9; los paquetes CP en la sección 8 contienen archivos, dependencias y reversión.

| P0 | Clase / severidad e impacto | Causa y certeza | Corrección acotada / archivos afectados | Dependencia y riesgo | Verificación / paquete |
| --- | --- | --- | --- | --- | --- |
| H1 | Configuración incompleta + UX / alta para uso municipal | E13; filtros coherentes, administrador incompleto (C). No probar ausencia actual de datos con un conteo viejo | Completar reglas en catálogo, controller, `TablaPaqueteriasPedido.jsx`, mensaje accionable; destino separado | Reglas por paquetería O; habilitar sin reglas altera documentos/cobro | R11; CP7 |
| H2 | Doble captura y divergencia / alta | E1/E7/E14; dos ids editables, folio adicional sin herencia (C); departamento mostrado ya es heredado | Canonizar paquetería, heredar referencias/soporte; modal, servicios de creación/update, serializadores | Snapshots no equivalen a duplicación; cambiar datos emitidos requiere comando | R01/R07/R12; CP1/4/8 |
| H3 | Terminología / media | Flag de catálogo con efectos reales, no importe extra (C); retiro de capacidad O | Etiquetas/contexto, sin borrar flag, costo, tipo ni históricos; catálogo, modal, resolvedor y validación preservados | PAQ. PENDIENTE, tarifas, SAF y regularización | R13; CP8 |
| H4 | Origen sustituido / alta | E2/E12; reemplazo explícito (C); combinaciones deseadas O | Eliminar mutación implícita, explicar incompatibilidad; separar elegibilidad/área; modal, config, servicios/bandejas | Cambiar solo UI no habilita CEDIS; lista global amplía otras modalidades | R10/R14; CP6a/6b |
| T1 | Borrador incompleto / alta | E1/E5; estados fuera de ambos guardados (C) | Persistir intención parcial, serializar/hidratar, clave local por usuario/pedido; requests/modelo/servicios/listado/modal | Adición de esquema futura; no inventar datos perdidos ni sobrescribir históricos | R01/R02; CP1 |
| T2 | Sustitución incompleta / alta | E8; lista de atributos municipal omitida (C) | Constructor común de snapshot compatible; corregir/cambiar-modalidad, requests, serializadores | No copiar ejecución/evidencias como si fueran de tarea nueva; plazos con semántica existente | R07; CP4 |
| T3 | Confirmación de carátula / alta | E9; variable fuera de alcance (C estático) | Capturar parámetro y validar id/version/tarea vigente; servicio/controller | Id omitido debe conservar selección de vigente; no habilitar regeneración colocada | R06; CP3 |
| T4 | Conflicto eludible y falso guardado / alta | E3/E4; comparación no atómica y adopción del token (C); carrera real R | Revisión atómica compartida, estado tipado, reconciliación explícita; persistencia/controllers/UI | Todos los escritores participantes; caché no protege | R03/R04; CP2a/2b |
| T5 | Solicitud consume captura anterior / alta | E3; helper retorna id (C), secuencia afectada R | Barrera de guardado, consumo por revisión; modal/controller/crear tarea | Soporte subido y relaciones deben participar; fallar sin transición | R05; CP2b |
| T6 | Requisitos CEDIS en preparación municipal / alta condicional | E11 (C estático); operación afectada R | Reusar requisitos efectivos por contexto en progreso/envío; no eliminar validaciones CEDIS | Aprobación de requisitos/área, compatibilidad con cajas y costos | R09/R13; CP5 |
| T7 | Requisitos UI/progreso/servidor diferentes / media | E5/E11; folio/soporte, contactos, costo y catálogo (C) | Política por acción compartida y errores por campo; no exigir completar todo para guardar | Progreso es indicativo hasta reevaluación autoritativa; contacto cliente incompleto | R02/R09/R12; CP5/8 |
| T8 | Reintento y carrera de tareas / alta | E7/E15; clave por hora, consulta previa sin lock (C); doble creación R | Idempotencia estable y validación de vigente bajo bloqueo; servicios creadores y sustitutos | Definición actual de vigencia; rutas de reapertura/cambio deben participar | R04/R08; CP2a/4 |

Alcance complementario derivado de T1/T4: corregir la semántica de campos omitidos de E6 en las rutas que adopten el contrato nuevo, con pruebas de montos, SAF y logística. No incluir una refactorización indiscriminada de todos los módulos. Alcance complementario de T8: comprobar colisión de generación de folio y reintento acotado; no renumerar folios existentes.

## 4. P1-A — Contrato de captura, persistencia y concurrencia

### 4.1 Fuente de verdad y almacenamiento propuesto

El pedido es la fuente persistida de captura. La intención de preparación antes de solicitar no es una tarea PENDIENTE: crear tareas al autoguardar enviaría trabajo operativo antes de tiempo.

Propuesta de esquema **aditivo**, para una implementación futura:

| Dato | Ubicación propuesta | Razón / compatibilidad |
| --- | --- | --- |
| Campos generales ya existentes | Columnas/relaciones actuales de `PedidoBma` | Conservar nombres y significado, incluidos almacén, sucursal, paquetería, domicilio, CP, costos y SAF. |
| Intención no representada antes de tarea | `pedidos_bma.solicitud_preparacion_json`, nullable y versionado | Modalidad, contacto municipal, prioridad e intención de referencia adicional sin disparar tarea, evento o referencia oficial al autoguardar. No duplicar columnas generales dentro del JSON. |
| Revisión del registro | `pedidos_bma.revision_registro`, entero creciente, default 0 | Protege captura y precondiciones relevantes de sus comandos. Históricos arrancan en 0 sin inventar una edición. Es distinta de `tarea.version` y `cumplimiento.version`. |
| Recibos de idempotencia | Nueva tabla propuesta `pedido_bma_comandos` | Único `(usuario_id, operacion, clave)`; id de pedido/tarea resultado, hash del payload normalizado y revisión resultante. Evita repetir creación/efectos tras pérdida de respuesta. No guarda copias indiscriminadas de contactos ni binarios. |

No se encontró un recibo durable reutilizable para todos esos comandos en la ruta revisada. La tabla propuesta cubre crear/guardar/solicitar; `idempotencia_clave` única de tarea se conserva como vínculo con la intención de solicitud, no como segunda fuente de decisión. Revisar retención y volumen en CP2a; no eliminar claves mientras un reintento válido pueda producir efectos duplicados. El recibo se confirma en la misma transacción que el efecto.

Forma lógica de la intención (nombres nuevos propuestos):

```json
{
  "version_esquema": 1,
  "codigo_modalidad": "ENVIO_MUNICIPIO",
  "prioridad_md": false,
  "observaciones": null,
  "documento_inicial": "PEDIDO",
  "referencia_adicional": {"tipo": "COTIZACION", "folio": "COT-EJEMPLO"},
  "entrega_municipal": {
    "destinatario_es_cliente": true,
    "destinatario_nombre": null,
    "destinatario_telefono": null,
    "municipio_destino": "Destino de ejemplo",
    "direccion_referencia": null
  }
}
```

El id de paquetería queda exclusivamente en `pedido.catalogo_paqueteria_id`. El cobro municipal se proyecta desde `pedido.envio_por_cobrar`: false→PAGADO y true→POR_COBRAR, conforme al mapeo que ya hace el servicio. PAGADO en entrega **no demuestra pago validado**: `ConfirmarPagoSalidaPreparacionService` y las políticas de cobertura siguen verificando ese hecho. Si la operación necesita «aún sin definir cobro», deberá aprobarse su representación antes de cambiar esta equivalencia.

El objeto de ejemplo admite null/parcial para guardar. `referencia_adicional` es null si no se solicitó una nueva; no se convierte cada autoguardado en una fila de `PedidoBmaReferencia`. `documento_inicial` conserva el significado existente y no sustituye al archivo adjunto. Las líneas/SKU, si la captura las ofrece, se guardan como intención explícita con las reglas de `lineas` existentes; no se afirma que el frontend hoy las capture ni que ya se sincronicen al crear tarea.

### 4.2 Estado de UI y serialización

Un estado canónico serializable incluye todos los campos de captura de `formDefaults` que ya participan en guardado más la intención anterior. Estados puramente visuales —panel abierto, previews, carga, foco— quedan separados. Inventario, cajas respondidas, saldos calculados, estatus, autorías y documentos de ejecución no son valores libremente editables por ese objeto.

| Regla | Contrato propuesto |
| --- | --- |
| Ausente vs null | Campo omitido conserva valor; null expresa limpieza donde se permite; 0 y false no son ausencia. No aplicar defaults de creación a un update parcial. |
| Datos derivados | Total, seguro, tipo de operación y logística del complemento se calculan en servidor mediante servicios actuales; no confiar en el valor derivado del cliente. |
| Números/ids | Serialización estable: ids positivos o null; montos decimales normalizados según contratos actuales; no sustituir costo desconocido por 0. |
| Archivos | Binarios fuera del JSON/localStorage. Guardado de captura y carga de archivo tienen confirmaciones separadas; referencia a documento confirmado del servidor. Solicitud bloqueada si una carga necesaria sigue pendiente/fallida. |
| Cambio de modalidad | Conserva captura compatible sin tocar almacén. Valores de ramas ocultas pueden conservarse como borrador, pero solo la rama activa forma la solicitud operativa. No reenviar campos `prohibited` a otra modalidad. |
| Una paquetería | Adaptador inicial refleja el único id en payload municipal legado; si llegan dos ids distintos, 422 por inconsistencia en vez de elegir silenciosamente uno. |
| Hidratación | Respuesta del servidor tipada y completa para captura; no reconstruir contactos sensibles desde un resumen enmascarado ni desde `tarea_preparacion.entrega_municipal` incompleto. Ampliar serialización autenticada de Ventas con alcance existente. |

### 4.3 Recuperación histórica y copia local

Orden de recuperación: cargar versión persistida → identificar snapshot base de la copia local → ofrecer recuperar cambios locales pendientes sobre esa base. Si las revisiones difieren, entrar en conflicto y comparar; no mezclar automáticamente dos objetos completos. Refrescos de bandeja con UI sucia actualizan contexto operativo y alertan, pero no reemplazan la base ni cambian su token por detrás.

Nueva clave local propuesta: usuario + pedido id; antes de tener id, usuario + UUID estable de intención de creación. Guardar `version_esquema`, revisión base, secuencia local, captura y estado de archivos pendiente/confirmado. Un segundo tab debe detectar otra versión local con evento de almacenamiento, conservar su copia y resolver explícitamente; el servidor sigue siendo árbitro.

Leer v1/v2 mediante adaptadores, sin borrar la copia hasta confirmar persistencia nueva. Como las claves viejas carecen de usuario verificable, no asignarlas automáticamente a la sesión actual: mostrar la opción de recuperación ya existente y validar acceso al pedido antes de usar su id; si no puede atribuirse, ofrecer recuperar como captura nueva con confirmación y sin reutilizar id ajeno. Una versión de esquema futura desconocida no se sobreescribe. Error de cuota/almacenamiento muestra «No se pudo guardar copia local» y conserva el estado en memoria.

Histórico sin JSON: si hay tarea, leer snapshot de esa tarea con sus relaciones; sin tarea, conservar campos existentes y pedir solo la información nueva que falte. No deducir modalidad municipal del tipo MUNICIPIO_DIFERIDO, de la categoría local/regional o de PAQ. PENDIENTE. No inventar municipio/contacto; un dato nunca guardado no es recuperable. La lectura de un histórico no crea automáticamente una intención nueva ni lo vuelve a enviar a Tienda.

### 4.4 Revisiones, idempotencia y barrera de guardado

Invariantes propuestas:

- Todas las escrituras de captura pasan por un coordinador transaccional común: leer pedido con `lockForUpdate`, revalidar acceso/estado, comparar revisión esperada, aplicar merge y reglas, escribir e incrementar revisión. Revalidar en el lock, no solo en el controller antes de adquirirlo.
- Cada transacción exitosa que cambia datos relevantes incrementa una vez la revisión. Una repetición idempotente devuelve el resultado original y no incrementa; un no-op confirmado devuelve la revisión vigente y no simula una edición.
- Deben participar también comandos que cambien precondiciones o datos que Ventas podría sobrescribir: documentos, dirección, costos/cajas, cliente/contexto, preparación/respuesta, cierre/reapertura, envío, cancelación, pagos/SAF relevantes y sustitución. El inventario completo de escritores es una condición de CP2a; no prometer protección total antes de cerrarlo.
- Mutaciones puramente operativas conservan sus campos y sus versiones existentes; la UI no las reenvía como una copia editable. El coordinador no reemplaza bloqueos actuales de cajas, tarea, custodia ni autorización. Para operaciones que toquen ambos agregados se fija orden pedido→tarea→cumplimiento→documentos; revisar lectores/escritores existentes para evitar inversión y deadlocks.
- `RevisionEnCursoPedidoBma` continúa siendo presencia informativa. Ninguna decisión depende de un TTL para impedir escrituras.
- Crear genera UUID de intención una sola vez. Reintentar después de timeout usa la misma clave/payload. Nueva captura o intención real usa otra clave. Clave repetida con otro hash devuelve conflicto; otra identidad no puede recuperar el resultado de un usuario distinto. Revalidar acceso al devolver un recibo.
- Para solicitudes se conserva el campo existente `idempotencia_clave`, con valor UUID estable; su resultado queda vinculado a pedido, actor, revisión de captura y hash. Consultar recibo ya confirmado antes de rechazar por revisión adelantada por ese mismo comando. Las reglas nuevas de catálogo no deben impedir reconocer un éxito anterior sin repetirlo.

La UI mantiene contador de edición local `secuencia_local` y hash del snapshot normalizado enviado; no confundirlos con la revisión del servidor. Una respuesta confirma **el snapshot enviado**, no el contenido que exista al llegar la respuesta. Si hubo edición mientras guardaba, el estado sigue pendiente y se encola la última captura. Una sola promesa/cola por instancia para autoguardado, guardar manual, creación previa a subir archivo y solicitar. Una respuesta retrasada de otra instancia se ignora por identidad/intención.

Secuencia para solicitar: detener debounce → bloquear temporalmente edición/acciones dependientes → esperar cargas necesarias → drenar cola y guardar captura actual → obtener revisión confirmada → solicitar con esa revisión y clave estable → servidor bajo lock compara revisión, relee snapshot y valida elegibilidad → crea tarea y proyecciones atómicamente → responde con ids/revisiones → refresca estado. Error en cualquier paso no produce transición posterior. Si se pierde respuesta, reintentar el mismo comando, no crear otro.

El backend no toma una copia municipal antigua del payload sobre el snapshot recién guardado. En transición, los campos legados redundantes deben coincidir con el persistido; se rechazan discrepancias. La generación de folio único requiere prueba con dos creaciones distintas: ante colisión del índice, reintentar transacción/generación de forma acotada, sin repetir SAF/documentos/notificaciones ni devolver éxito para la clave ajena.

### 4.5 Estados y errores visibles

| Estado tipado propuesto | Mensaje / acción | Condición para salir |
| --- | --- | --- |
| `pendiente` | «Cambios sin guardar en servidor»; puede existir copia local | Confirmación del snapshot actual |
| `guardando` | «Guardando en servidor…» | Respuesta o error; edición nueva mantiene pendiente |
| `guardado` | «Guardado en servidor» con hora opcional | Hash confirmado = captura visible; ninguna carga requerida pendiente |
| `conflicto` | «El pedido cambió. Compara las versiones antes de continuar» | Reconciliar o recargar expresamente; nunca adoptar token y reintentar solo |
| `error_validacion` | Campo y motivo; captura intacta | Corregir y volver a guardar |
| `sin_conexion` / `error_servidor` | «Cambios pendientes; copia local…» según resultado real | Reintento explícito/seguro con misma intención |

Conflicto: mostrar base local, cambios del usuario y versión autorizada del servidor. Permitir conservar servidor o reaplicar diferencias expresamente sobre la nueva base, volviendo a validar. No ofrecer «forzar sobrescritura» por defecto. Si el pedido avanzó a estado no editable, conservar/exportar la captura local y volver a su detalle; no crear un pedido sustituto automáticamente.

## 5. P1-B — Contrato de preparación y carátulas

### 5.1 Una construcción de snapshot, tres comandos existentes

Reutilizar `CrearTareaPreparacionService`, `CorregirTareaPreparacionService` y `CambiarModalidadPreparacionService`. Extraer un ensamblador validado común, no otro proceso de preparación. Guardar intención no recalcula plazos; solicitar/corregir/cambiar los aplican en el momento operativo correspondiente.

| Grupo de datos | Solicitud inicial | Corrección misma modalidad | Cambio de modalidad |
| --- | --- | --- | --- |
| Cliente/departamento/origen/almacén/paquetería | Desde revisión confirmada; una sola selección | Mantener salvo cambio explícito permitido, sincronizado con pedido | Conservar origen; verificar elegibilidad nueva sin default silencioso |
| Destinatario, teléfono, municipio, referencia y cobro | Resolver contacto visible confirmado; validar con reglas actuales | Copiar todos; modificar solo diferencias explícitas y revalidar | Municipal→municipal: conservar; otra→municipal: exigir datos faltantes antes de sustituir; al salir: histórico queda en tarea anterior |
| Modalidad/área/traslado | Política vigente, actualmente TIENDA; no aceptar área arbitraria del cliente | Conservar lo aplicable y validar; no perder `requiere_traslado_cedis` | Calcular con política aprobada, no copiar traslado incompatible |
| Prioridad/plazo/observaciones | Semántica actual de prioridad y cierre | Conservar plazo de corrección actual; no ampliarlo por defecto | Semántica actual recalcula plazo; cambio de esa política requiere decisión O3 |
| Productos solicitados | Líneas explícitas o flujo actual desde soporte | Cantidades solicitadas/SKU compatibles | Copiar intención de productos compatible, manteniendo origen del pedido |
| Ejecución | Vacía | No copiar encontrados, asignación, estado físico confirmado ni evidencias como ejecución nueva | Igual; documentos previos solo referencia histórica identificada |
| Referencias y soporte | Reutilizar `PedidoBmaReferencia` y documento del pedido | Mantener relación documental sin cargar de nuevo el soporte | Misma relación; nueva referencia solo por acción explícita |
| Carátula/cumplimiento | Crear por flujo actual, nunca al guardar borrador | Nueva tarea tiene nuevo cumplimiento y carátula; enlazar anterior | Igual; cerrar/compensar custodia anterior mediante servicios existentes |

Validar **antes de cancelar o modificar la anterior**, y repetir precondiciones bajo lock. Si falla la nueva, rollback completo: anterior, pedido, cumplimiento y documentos continúan válidos. Notificaciones tras commit, sin duplicarlas al reintentar. Los archivos externos requieren compensación/limpieza si se generan antes de fallar transacción; no basta el rollback SQL. No borrar PDFs/evidencias históricas.

### 5.2 Exclusión de tareas vigentes

Usar exactamente el conjunto de `PedidoBma::tareaPreparacionVigente()`: PENDIENTE, EN_ATENCION, CON_INCIDENCIA, LIBERACION_SOLICITADA, LISTA_PARA_TRASLADO, LISTA_PARA_CARATULA, EN_TRASLADO, RECHAZADA_CEDIS. RESPONDIDA y RECIBIDA_CEDIS tienen otra relación y no se incorporan sin una decisión explícita.

Lock del pedido en **todo** creador, corrector, cambio de modalidad y reapertura que pueda producir una vigente; volver a consultar sin usar relaciones precargadas. Una intención repetida devuelve su tarea; una intención distinta encuentra la vigente y falla sin duplicar. Sustitución cancela la vigente por transición permitida y crea PENDIENTE dentro de la misma transacción. El índice único actual de idempotencia no excluye claves distintas. No añadir `UNIQUE(pedido_bma_id)` que prohibiría historial legítimo. Si el motor objetivo requiere defensa adicional de esquema, diseñarla por vigencia, no por existencia histórica, después de probar la exclusión común.

### 5.3 Carátulas: contrato concreto

Conservar estados GENERADA, COLOCADA e INVALIDADA, versión por tarea y hash PDF existentes. Corregir la captura lexical de `$caratulaId` sin cambiar la transición. Validar entrada id/version como enteros positivos; comprobación autoritativa bajo lock de tarea. Si id viene: pertenece a la misma tarea y es la última GENERADA vigente. Si se omite: escoger la última GENERADA, como intenta hacer el código actual. Id de otra tarea, INVALIDADA, obsoleto o sin PDF rechaza sin efectos. No deducir vigencia por nombre de archivo.

La versión de tarea enviada es obligatoria en cliente nuevo; mantener compatibilidad temporal explícita para cliente legado según sección 7. No reutilizar `version` de carátula como si fuera la de tarea: se exponen ambos campos con significado claro. La confirmación exitosa conserva el cierre operativo existente y no se confunde con despacho físico.

El observador existente (E10) es una red de integridad para cambios de datos de tarea por Eloquent; no cubre cambios del pedido ni updates SQL masivos. El comando explícito coordina el cambio, actor/motivo e invalidación en una transacción. Reutilizar `InvalidarCaratulasVigentesService`, evitando doble invalidación, historial repetido y reentrada del observador. Probar también versiones en memoria: la invalidación no puede dejar que el modelo exterior escriba un estado/version anterior tras la transición interna.

Antes de carátula colocada, corregir datos invalida GENERADA y exige generar/colocar la nueva. Para carátula colocada, la regeneración ordinaria continúa rechazada; el mecanismo existente de invalidación admite RESPONDIDA→LISTA_PARA_CARATULA. Su uso por cambio explícito antes de salida necesita delimitar autorización, motivo y compensación del cumplimiento. Después de autorización/empaque/despacho no se habilita aquí: O4. La indicación de P0 «corregir por incidencia» no basta: `ReportarIncidenciaPreparacionService` solo admite PENDIENTE/EN_ATENCION y `CorregirTareaPreparacionService` solo CON_INCIDENCIA.

### 5.4 Transiciones preservadas y límites

| Acción | Transición existente que se conserva | Límite de P1 |
| --- | --- | --- |
| Guardar intención | Ninguna transición de pedido/tarea por sí sola | No crear tarea ni notificar preparación |
| Solicitar preparación | Pedido a consulta pendiente; nueva tarea PENDIENTE | Revisión + elegibilidad + soporte válidos antes del cambio |
| Atender/responder municipio | PENDIENTE→EN_ATENCION→LISTA_PARA_CARATULA | Requisitos efectivos, sin suponer cajas CEDIS |
| Colocar | LISTA_PARA_CARATULA→RESPONDIDA | Id vigente + permisos + versión |
| Invalidación municipal | RESPONDIDA→LISTA_PARA_CARATULA | Ya existe; acotar O4, sin habilitar regresos desde despacho |
| Corregir incidencia | CON_INCIDENCIA→CANCELADA; sustituta PENDIENTE | Datos heredados y atomicidad, no nuevas aristas |
| Cambiar modalidad | Estados cancelables usan cancelación actual | En estados no cancelables, el código actual registra historial y crea otra; caracterizar antes de cambiarlo. No introducir una cancelación artificial de RESPONDIDA |
| Traslado | LISTA_PARA_TRASLADO→EN_TRASLADO→RECIBIDA_CEDIS | Mantener Traspasos e inventario; no usarlo para CEDIS→CEDIS |
| Salida municipal | Cumplimiento separado→salida autorizada→empacado→despachado | Pago/permiso por cobrar, documentos, receptor y separación de funciones actuales |

### 5.5 Requisitos efectivos y consistencia final

Reutilizar la precedencia actual de `CalcularRequisitosPreparacionService`: modalidad + fila aplicable de matriz + reglas del transporte, con sus OR específicos para peso/caja/evidencia. No cambiarla a un OR universal ni deducirla en React. Publicar una evaluación por **acción** para guardar, solicitar, responder, generar carátula, enviar a revisión y salir. Un campo pendiente para envío final no invalida el guardado parcial.

Para preparación Tienda municipal, usar requisitos efectivos en validación final en lugar de inferir «hay cajas» de `pesaje_respondido_at`. Para pesaje CEDIS conservar validación de cajas, pesos y costos actuales. Conservar por compatibilidad las fechas/hitos existentes; agregar un contexto calculado de respuesta/preparador en el serializador, sin renombrar columnas históricas en esta corrección. No fabricar una caja ni peso 0 para eludir requisitos.

El código permite `requiere_caratula=false` en catálogo, pero la ruta municipal aún pasa por colocación obligatoria: no habilitar una modalidad municipal «sin carátula» solo porque el formulario administrativo permita false. Hasta O5 se mantiene la exigencia operacional actual, se informa de la restricción y no se ofrece esa combinación como habilitada. Igual criterio para destinatario/municipio/teléfono: siguen siendo mínimos del servicio de solicitud aunque un arreglo configurable omita sus nombres. Cambiar esos mínimos es regla operativa, no simplificación de validaciones.

## 6. P1-C y P1-D — Logística municipal y simplificación

### 6.1 Modelo municipal propuesto

| Eje | Fuente / contrato | Independencia necesaria |
| --- | --- | --- |
| Almacén de salida | `almacen_id`, catálogo actual y `ValidarAlmacenSalidaPedido` | Inventario y origen de mercancía; no se cambia por destino o paquetería |
| Destino | Municipio libre actual, dirección y sucursal cuando corresponde | Propuesta desde dirección verificada; municipio no se deduce del transportista |
| Medio | `CatalogoPaqueteriaPedido`, flags/reglas actuales | Una selección; elegibilidad municipal no equivale a costo diferido |
| Preparador | `area_responsable_codigo`, actualmente TIENDA | Lo decide política de combinación autorizada, no un default a partir del nombre del almacén |
| Traslado | Modalidad/requisitos efectivos, `SolicitudTraspaso` | Solo cuando hay movimiento real entre almacenes definido; conservar origen y destino de traspaso |
| Despacho | Políticas de pago/salida + bultos/receptor | Registrar salida en el lugar y rol aprobados, no asumir que responder preparación es despachar |

Propuesta técnica: una resolución común de elegibilidad por modalidad, almacén, área y contexto actual de permisos/configuración. Devuelve permitido, motivo, área/flujo aprobados y requisitos; frontend informa y backend aplica la misma resolución. La matriz actual se reutiliza para **requisitos**, sin convertirla silenciosamente en una tabla de asignaciones. El lugar de configuración de combinaciones/área será una extensión controlada de preparación, sujeta a O1; no una activación global de `almacenes_habilitados`.

| Combinación | Situación comprobada | Alternativa a revisar | Condición de habilitación |
| --- | --- | --- | --- |
| PDV→municipio | Modalidad municipal fuerza TIENDA; default de almacén PDV | Conservar preparador Tienda y salida municipal actuales | Transporte elegible con reglas revisadas y defectos P1-B resueltos |
| CEDIS→municipio | No habilitada por default; área actual seguiría TIENDA | Preparación CEDIS usando tareas/contratos comunes, bandeja y permisos CEDIS; o preparación Tienda con origen CEDIS si Operación realmente lo define | O1/O2 resueltas; diseño de bandeja/roles y recorrido probado. No elegir automáticamente una alternativa |
| PDV→CEDIS→municipio | Traspasos actuales son una rama distinta | Solo si existe requerimiento operativo de traslado | Definir lugar de empaque/despacho y recepción; no añadir este paso por defecto |
| Recoge hoy / transferencia | Restricciones propias existentes | Conservar | No ampliar por habilitar municipio CEDIS |

Corrección que puede separarse de esa habilitación: preservar almacén y mostrar «El origen seleccionado no está habilitado para esta preparación; revisa la modalidad o solicita habilitación». Guardar borrador permite la intención incompatible; solicitar queda bloqueado. No borrar el origen para obligar a elegir PDV.

### 6.2 Administración de paqueterías

Ampliar las rutas `admin.catalogos.paqueterias_pedido.store/update` y el componente existente. Campos ya presentes en modelo: habilitado municipal, reglas pendientes, carátula, identificación, remisión, por cobrar, peso, caja, evidencia de conjunto, campos de destino y plantilla. Conservar categoría, tarifas y capacidad de diferir costo; una edición parcial no resetea otros campos.

Validar booleanos, lista permitida de campos de destino y plantilla elegible que exista; no aceptar una ruta de vista arbitraria como entrada libre. Mostrar claramente activo, habilitado y reglas revisadas como condiciones distintas. Activar requiere revisión explícita de reglas; no inferirla por categoría o porque el catálogo no esté vacío. Mantener borrador de configuración sin habilitar. La excepción municipal sin carátula queda bloqueada a nivel de elegibilidad hasta resolver O5.

Deshabilitar no borra referencias de pedidos/tareas históricos. Para tareas ya solicitadas, definir efecto O6 antes de cambiar reglas: mantener documentos emitidos y advertir si las nuevas reglas obligan a reevaluar; nunca reescribir PDFs históricos. El primer alcance administrativo no modifica automáticamente tareas en curso. Mantener auditoría de actor y campos cambiados; invalidar cachés pertinentes sin tocar flags de preparación no relacionados.

### 6.3 Campos: capturar, heredar, consultar y confirmar

| Dato | Captura única | Herencia / solo lectura | Edición o confirmación justificada |
| --- | --- | --- | --- |
| Cliente y departamento operativo | Al iniciar registro | Solicitud, tarea y seguimiento | Cambio previo a solicitar, sujeto a permisos y revisión; no otro selector en preparación |
| Almacén | Origen logístico | Tarea e inventario | Cambio explícito permitido; nunca por seleccionar municipio |
| Paquetería | Único selector contextual | Tarea y carátula | Corrección versionada si ya hay tarea; no segundo selector documental |
| Municipio/dirección/contacto | Propuesta desde dirección/contacto, editable antes de solicitar | Snapshot de tarea y PDF | Confirmar destinatario alternativo o datos incompletos; «es cliente» no impide completar teléfono |
| Folio interno | Generación servidor | Siempre solo lectura | Ninguna edición desde captura |
| Folio documental | Captura inicial y tipo conocido | Referencia visible con precedencia actual | Agregar otra referencia con tipo, sin recaptura obligatoria ni sustituir remisión |
| PDF/foto de pedido | Una carga al pedido | Visor de solicitud en Tienda | Reemplazo explícito con control de revisión; evidencias de ejecución son otros documentos |
| Prioridad | Intención persistida | Visible con efecto en plazo | Confirmación explícita al cambiar si afecta vencimiento; no valor perdido al cerrar |
| Productos encontrados/estado/evidencias | Ejecución por preparador | Ventas revisa respuesta | Confirmación del cliente y cierre de consulta siguen siendo acciones propias |
| Costo de envío | Cotización cuando corresponda | Totales calculados | Si puede quedar pendiente, explicar «Puedes completar el costo después»; no otro importe «diferido» |
| Pago y carátula colocada | Hechos registrados por roles correspondientes | Seguimiento | No derivarlos de un checkbox de captura ni del mero guardado |

Folios: conservar `folio`, `folio_remision`, referencias tipadas y remisión operativa. `PedidoBmaReferencia::visible` sigue excluyendo REMISION para priorizar la referencia visible, con fallback actual. Los nuevos registros tipados se proponen sin clasificar retrospectivamente todos los folios de nombre ambiguo. Mostrar «Folio interno», «Pedido / Cotización / Otro» y «Remisión» según la entidad real; una misma cadena textual puede corresponder a documentos distintos.

Extracción incremental del modal: (a) serializador/hidratador puro probado, (b) hook de borrador con cola y reconciliación, (c) selector de elegibilidad sin setters de origen, (d) bloque municipal controlado por estado canónico, (e) bloque de referencias/soporte, (f) comandos de preparación que consumen la barrera de guardado. Mantener `ModalFormPedido.jsx`, foco, diálogos y fases; no crear otra pantalla de registro ni reemplazar toda la UI a la vez.

Mensajes accionables: «No hay paqueterías con reglas municipales aprobadas» con enlace administrativo solo si tiene permiso; «Completa el teléfono para este destinatario»; «Se guardó la versión anterior; hay cambios pendientes»; «La carátula cambió. Actualiza y coloca la versión vigente». Los límites/formatos se comparten con backend; la validación municipal conserva 2–255 para nombre/municipio, teléfono normalizado no vacío y límites actuales, dirección de referencia hasta 500 y cobro compatible. No inventar un patrón nacional de teléfono.

## 7. Cambios de API, errores, compatibilidad y reversión

### 7.1 Ampliaciones propuestas sobre rutas actuales

| Contrato | Antes | Entrada / salida esperadas | Validación, persistencia y error | Riesgo / reversión |
| --- | --- | --- | --- | --- |
| A1 Guardar (`store`, `autoguardar`, `update`) | Payload parcial general; autoguardar JSON con id/folio/timestamps/progreso; manual redirect | Añadir `solicitud_preparacion`, `revision_esperada`, `clave_comando`, versión de contrato. JSON añade revisión y snapshot normalizado; conservar campos actuales | Formato parcial + merge explícito; acceso/estado y revisión bajo lock. 422 por campo, 409 por conflicto; creación sin id requiere clave estable | E6 puede cambiar defaults; adaptar clientes conjuntamente. Detener escrituras nuevas antes de rollback; conservar columnas/recibos |
| A2 Lectura de captura (`listado`/props) | Pedido y resumen parcial de tarea | Añadir intención, revisión y capacidades de contrato; tarea completa solo para actor autorizado | Histórico sin intención no falla; no exponer contacto por usar otro serializador | Adición compatible de lectura; no retirar al revertir frontend si existen capturas nuevas |
| A3 Solicitar (`solicitar_preparacion_tienda`) | Modalidad/almacén/meta/entrega separados; redirect/flash | Usar id + `revision_esperada` + `idempotencia_clave`; durante transición aceptar campos legados coincidentes. JSON negociado añade pedido/revisión/tarea/version/replay; Inertia conserva redirect | Snapshot persistido autoritativo; soporte, habilitación y mínimos; recibo antes de efecto. Desacuerdo 422; versión o vigente distinta 409 | No habilitar otra área con esta ampliación; rollback nunca recrea una intención ya confirmada |
| A4 Corregir/cambiar (`preparacion.corregir`, `.cambiar_modalidad`) | Corrección sin versión; cambio con versión opcional y sin entrega | Añadir revisión pedido, versión tarea, clave estable y diferencias explícitas/intención compatible. Respuesta ids anterior/nueva y versiones | Validar snapshot nuevo antes de cerrar anterior; motivos/permisos/transiciones actuales | Riesgo de custodia/documentos; transacción y compensación. No borrar sustitutas al revertir |
| A5 Confirmar (`tienda.caratula.confirmar`) | Request genérico id/version opcionales; parámetro perdido por closure | Mismos id/version; nuevo cliente envía versión tarea siempre; respuesta Inertia actual o JSON negociado con ids/versión | Id positivo y perteneciente; vigente, PDF, estado y permisos. Capturar `$caratulaId`. Error sin marcar colocada | Cambio mínimo puede desplegarse separado; no revertir al defecto si ya hay bloqueo confirmado |
| A6 Requisitos/progreso | Condiciones repartidas, pesaje como supuesto de cajas | Añadir requisitos/faltantes por acción y contexto de preparación en respuesta existente | Backend reevalúa al ejecutar; edición no satisface un hecho de pago/salida | Mantener lógica CEDIS y excepciones existentes; desactivar contexto nuevo ante divergencias |
| A7 Catálogo municipal (`admin...store/update`) | No acepta reglas completas | Ampliar campos existentes de modelo; mismas rutas/nombres, redirect actual | Revisión explícita, listas/tipos y reglas compatibles; 422 conserva formulario | Sin activación masiva ni eliminación de históricos; revertir habilitación selectiva conservando datos |
| A8 Elegibilidad logística | Config global/default PDV y mutación visual | Resolución por combinación aprobada añadida a catálogos; origen se conserva | Guardado parcial posible; solicitud rechazada con motivo si no habilitada | Desactivar combinaciones nuevas y mantener lectura/seguimiento en curso; no mover pedidos a PDV |

No se inventan rutas paralelas. Los campos nuevos son propuesta de ampliación en las existentes. `Accept: application/json` debe negociarse explícitamente para A1/A3/A4/A5 sin romper redirects Inertia; no enviar un 409 JSON sin probar el manejo específico del cliente Inertia. En el camino legado, errores por campo van al error bag y conflictos a un estado/flash estructurado equivalente; no convertir el 409 de dominio en el protocolo de ubicación de Inertia.

Propuesta de JSON para un conflicto (contenido mínimo, sujeto al alcance del actor):

```json
{
  "code": "REVISION_CONFLICTO",
  "message": "El pedido cambió. Compara las versiones antes de continuar.",
  "revision_actual": 8,
  "requiere_reconciliar": true,
  "errors": {}
}
```

Respuesta exitosa de guardado conserva id/folio/saved_at/updated_at/progreso y añade `revision_registro`, snapshot normalizado y clave confirmada. Solicitud añade `tarea_id`, `version_tarea` y revisión resultante. Campos nuevos de replay señalan si fue repetición; el cliente no infiere éxito de un flash genérico ni de HTTP 200 si el resultado de dominio fue error.

### 7.2 Catálogo de errores propuesto

| Caso | JSON / tratamiento equivalente Inertia | Efecto persistido |
| --- | --- | --- |
| Formato/minimos/paqueterías discordantes | 422 `errors` por campo, incluyendo ruta municipal | Ninguno del comando rechazado; captura local intacta |
| Falta revisión/clave en cliente nuevo | 422 contrato incompleto y recarga/actualización indicada | Ninguno; no fallback silencioso a timestamps |
| Revisión vieja | 409 `REVISION_CONFLICTO`, comparación explícita | Ninguno; token base no avanza automáticamente |
| Otra vigente | 409 `TAREA_VIGENTE`, referencia autorizada a la tarea | Ninguna tarea adicional |
| Misma clave y otro contenido | 409 `CLAVE_REUTILIZADA` | No ejecutar ni devolver como si coincidiera |
| Misma clave/resultado previo | Éxito/replay con resultado original, lectura actual por separado | No repetir efectos ni aumentar revisión |
| Estado cambió | 409 `ESTADO_CAMBIO`, detalle autorizado para volver al pedido | Ninguna transición forzada |
| No autorizado/no visible | 403/404 según política existente | No revelar snapshot ni recibos ajenos |
| Fallo inesperado/red | 5xx/error de conexión; reintento con misma clave | Puede existir commit sin respuesta; resolver por recibo, no asumir fallo definitivo |

### 7.3 Despliegue y reversión seguros

Compatibilidad de **datos históricos** no equivale a mantener indefinidamente escrituras sin revisión de clientes antiguos. Preparar esquema aditivo y backend lector compatible; desplegar UI con contrato anunciado; habilitar conjuntamente revisión/cola/acciones después de cubrir todos los escritores. Una vez exigida revisión, una pestaña antigua recibe recarga requerida y conserva su copia, no acceso de escritura sin control. Hasta ese corte, declarar que la garantía de concurrencia aún no está activa.

Campos ausentes en clientes antiguos conservan intención persistida, no la limpian. Requests antiguos duplicando paquetería solo se aceptan si coinciden. Migraciones futuras no borran ni reclasifican registros. No ejecutar migración down destructiva para revertir; primero pausar mutaciones nuevas o desactivar la combinación concreta, mantener lectores compatibles, conservar revisiones/recibos y reconciliar operaciones en curso. Volver al frontend viejo sin lector de la nueva captura puede volver a perderla: no es una reversión aceptable de CP1/CP2.

## 8. Checkpoints de implementación propuestos

El orden prioriza integridad. Cada CP requiere autorización posterior a P1. CP3 puede adelantarse como corrección puntual con su propia prueba. CP6b no bloquea arreglar persistencia o PDV. No se presenta ningún CP como implementado.

### CP0 — Reproducciones y línea base aislada

- **Alcance/archivos:** pruebas de caracterización R01–R09 y manifiesto de fallos de P0; `tests/Feature/ControlPedidos`, `tests/Unit/ControlPedidos` y pruebas del modal/hook. No cambiar reglas de producción.
- **Dependencias:** P1 revisado; entorno de prueba aislado PHP compatible; identificar exactamente conexión/esquema antes de usar RefreshDatabase. Pruebas de carreras requieren MySQL de prueba separado.
- **Añadir/preservar:** demostrar pérdida, conflicto, solicitud anterior, clonado y cierre de carátula; mantener grupos de base G1–G7 de sección 9.
- **Aceptación/paso:** pruebas fallan por la causa esperada, no por bootstrap, permiso ausente o catálogo sin fixture; fallos anteriores trazados por caso.
- **Riesgo/reversión:** fixtures inválidas falsean el diagnóstico; revertir solo pruebas defectuosas sin eliminar la evidencia fallida. No declarar corregido producto por arreglar bootstrap.

### CP1 — Persistencia parcial y recuperación completa

- **Alcance/archivos:** schema aditivo JSON/revisión aún no exigida; `PedidoBma`, requests base/store/update, crear/actualizar/resolvedor, listado, `ModalFormPedidoLegado`, serializador/hidratador extraído. Sin rediseño visual ni habilitación logística.
- **Dependencias:** CP0 R01/R02; estrategia de presencia/null y precedencia histórica aprobada. Evitar escribir paquete nuevo antes de que backend lector lo soporte.
- **Añadir/preservar:** R01/R02 y montos/SAF omitidos; G1/G2/G3/G6. Pruebas de ida/vuelta de todos los campos serializables, no solo municipio.
- **Aceptación/paso:** recarga/cierre conserva intención completa, archivos recuperables, ausencia no borra; antiguos sin JSON siguen legibles; sin generar tarea/referencia/evento al guardar.
- **Riesgo/reversión:** cambio de defaults borra datos; parar escritores y conservar nuevas columnas/copia local. Pasar con comparación semántica antes/después y cero regresiones nuevas en captura/pagos.

### CP2a — Revisión atómica e idempotencia de backend

- **Alcance/archivos:** coordinador y recibos propuestos, schema aditivo, controllers y servicios de escritura inventariados; folio único y reintento acotado. No cambiar plazos ni áreas.
- **Dependencias:** CP1; inventario de todos los escritores y orden de locks, incluyendo reaperturas, pagos, documentos y sincronizaciones. Implementar por grupos probados; no activar garantía parcial como total.
- **Añadir/preservar:** R03/R04/R08, colisión de folio, fallo/rollback de SAF y respuesta perdida; G1–G7. Carreras en motor objetivo con barreras explícitas, no sleeps como única coordinación.
- **Aceptación/paso:** un éxito por revisión, una creación por intención; misma clave distinta huella falla; todo escritor cubierto incrementa/revalida; accesos y notificaciones no duplicados.
- **Riesgo/reversión:** deadlocks, cliente viejo, escritura no coordinada. Esquema se conserva; pausar comandos afectados y habilitación hasta cubrir escritores; no quitar protección permitiendo sobrescritura vieja.

### CP2b — Cola de UI, guardado verdadero y barrera de solicitud

- **Alcance/archivos:** hook de borrador, estados tipados y reconciliación; modal, `FormularioProgresivo`, negociación JSON y solicitud por revisión. Sin cambios cosméticos independientes.
- **Dependencias:** CP2a disponible para activación conjunta; CP1 lectura/serialización; cargas de soporte incluidas en barrera.
- **Añadir/preservar:** R03/R05 y respuestas desordenadas, edición durante request, desconexión, cierre inmediato antes de debounce; G1/G6 y pruebas de foco/diálogo.
- **Aceptación/paso:** guardado solo para snapshot confirmado; 409 detiene autosave y solicitud; id existente no salta guardado; una promesa de creación; refresh no sobrescribe captura sucia.
- **Riesgo/reversión:** bucles de reintento o bloqueo de edición permanente. Conservar captura y ofrecer recarga; revertir conjuntamente protocolo/UI hacia lector compatible, nunca volver a timestamp tolerante con escrituras libres.

### CP3 — Confirmación correcta de carátula

- **Alcance/archivos:** `ConfirmarCaratulaColocadaService`, método `confirmarCaratula` y request validado equivalente; ninguna nueva transición.
- **Dependencias:** CP0 R06; no depende de activar CEDIS ni de tener catálogo real habilitado. Si se adelanta, versión/id siguen compatibilidad existente; endurecimiento obligatorio se coordina con CP2.
- **Añadir/preservar:** R06 id vigente/obsoleto/ajeno/omitido y error PHP estricto; G2/G4; rollback en fallo de sincronización.
- **Aceptación/paso:** reproduce el defecto antes, pasa después, sin colocar documento incorrecto; mantiene permisos y tarea/versión esperados.
- **Riesgo/reversión:** rechazo indebido de clientes actuales. Volver al contrato opcional probado manteniendo arreglo lexical; no reintroducir el defecto. Pasar solo con prueba transaccional y no únicamente constants/strings.

### CP4 — Sustitución consistente y exclusión de vigente

- **Alcance/archivos:** ensamblador común propuesto; crear/corregir/cambiar/reabrir, requests, tarea, observador/invalidador y serializadores. Herencia de entrega, producto origen, plazos y cierre de custodia con reglas actuales.
- **Dependencias:** CP2a, CP3; resolver O3/O4 solo para ampliar alcances posteriores a respuesta. Corrección de incidencia previa puede avanzar sin esas ampliaciones.
- **Añadir/preservar:** R07/R08, rechazo de nueva inválida deja anterior intacta; observador sin doble transición; G2/G4/G5/G7.
- **Aceptación/paso:** municipal corregida conserva todos sus datos; otra→municipal exige faltantes; una vigente entre comandos; evidencia histórica no se reutiliza como ejecución nueva.
- **Riesgo/reversión:** custodia anterior abierta o carátulas huérfanas; pausar sustituciones, conservar historial y revertir código hacia lector compatible. No eliminar tareas creadas ni recrear anterior sin compensación autorizada.

### CP5 — Requisitos comunes por acción

- **Alcance/archivos:** `CalcularRequisitosPreparacionService`, `ValidacionCamposPedidoBma`, `CalcularProgresoPedidoBmaService`, `EnviarPedidoBmaService`, respuesta/listado/modal; reutilizar matrices y políticas actuales.
- **Dependencias:** CP4; fixture de transporte con/sin peso/caja; O5 para ofrecer opciones hoy incompatibles. No cambiar criterio comercial de pago/cobertura.
- **Añadir/preservar:** R09/R13 y mínimos/contactos; G1–G5. Matriz con override por departamento/almacén y excepción complementaria.
- **Aceptación/paso:** progreso/acción y envío usan mismos faltantes; municipal sin pesaje no exige cajas CEDIS; CEDIS conserva controles; guardar incompleto sigue permitido.
- **Riesgo/reversión:** aceptar pedido sin requisitos reales; desactivar la rama nueva de evaluación, retener diagnóstico y bloquear operaciones inciertas. No rellenar pesos/costos ficticios ni relajar todas las variantes.

### CP6a — Preservar origen y explicar incompatibilidad

- **Alcance/archivos:** efectos/selector del modal y resolución de elegibilidad compartida con config/crear/corregir; sin habilitar nuevas combinaciones.
- **Dependencias:** CP2b/CP5 para preservar e informar estado correcto.
- **Añadir/preservar:** R10, modalidad ida/vuelta y borrador recuperado; G2/G5 y prueba actual de restricción PDV.
- **Aceptación/paso:** destino nunca sustituye/vacía origen; solicitud incompatible explica el motivo; Recoge hoy sigue PDV. El test de default de preparación permanece, diferenciándolo de la mutación visual.
- **Riesgo/reversión:** UX conserva un origen no elegible; bloquear solo solicitud, no borrador. Revertir resolver sin restaurar sobrescritura silenciosa.

### CP6b — Habilitación dirigida de CEDIS→municipio

- **Alcance/archivos candidatos:** política/config por combinación; área en tarea, visibilidad y listados CEDIS/Tienda, permisos/acciones y carátulas/salida reutilizables. Definir lista final después de O1/O2; no extender automáticamente rutas Tienda.
- **Dependencias:** CP3–CP6a y O1/O2/O5 resueltas; catálogo por transporte revisado. Requiere aprobación operativa concreta de combinación, roles y reglas.
- **Añadir/preservar:** R14, inventario por origen, asignación, cola, empaque, receptor y ausencia de traslado ficticio; G2/G4/G5/G7.
- **Aceptación/paso:** CEDIS y PDV completan sus recorridos aprobados sin intercambiar área, inventario o autorización. Se prueba permiso negativo y separación vendedor/autorizador.
- **Riesgo/reversión:** tareas nuevas sin bandeja o salida incorrecta. Deshabilitar nuevas solicitudes de esa combinación; mantener atención y cierre de las existentes. Nunca cambiarles el almacén para esconderlas.

### CP7 — Administración de reglas municipales

- **Alcance/archivos:** `CatalogoController`, `TablaPaqueteriasPedido.jsx`, catálogo/modelo, servicio de catálogos, reglas de validación y cachés. Ninguna siembra/activación masiva.
- **Dependencias:** CP5 para restricciones compatibles; O5/O6; puede prepararse antes de CP6b sin habilitar CEDIS.
- **Añadir/preservar:** R11, permisos administrativos, parcial sin resetear tarifas/flags, histórico deshabilitado; G3/G4 y cálculos existentes de tarifas.
- **Aceptación/paso:** administrador puede guardar/revisar reglas y habilitar explícitamente un transporte probado; usuario ve causa de exclusión; no cambia tareas/documentos viejos por editar catálogo.
- **Riesgo/reversión:** reglas insuficientes o caché desfasada. Revertir habilitación específica con auditoría y refrescar caché; mantener lectura histórica. Pasar con prueba de edición ida/vuelta sin cambios colaterales.

### CP8 — Simplificación y extracción incremental

- **Alcance/archivos:** bloques controlados de entrega, referencias, soporte y selector; hook/serializador ya probados; modal/progreso y etiquetas administrativas. Sin cambiar estructura general ni reglas.
- **Dependencias:** CP1–CP7 en las ramas aplicables; CP6b puede seguir pendiente si no se ofrece CEDIS municipal. Política documental actual conservada, O7 si se quiere cambiar.
- **Añadir/preservar:** R12/R13/R15, una sola selección y sin recaptura; G1/G3/G6 y 85 pruebas frontend como línea inicial a actualizar por resultados reales.
- **Aceptación/paso:** misma captura/solicitud con menos duplicación, tipos de folio claros, mensajes accionables, costo pendiente comprensible; sin pérdida al desmontar ni regresión de foco/teclado.
- **Riesgo/reversión:** componente nuevo diverge del contrato. Revertir la extracción visual manteniendo persistencia y protocolo nuevos. Cierre solo tras matriz de regresión, defectos pendientes identificados y aprobación del recorrido operativo.

## 9. Línea base, reproducciones y pruebas de regresión

### 9.1 Evidencia heredada y clasificación de resultados

P0 ejecutó 19 archivos/85 pruebas frontend aprobadas; suite backend conjunta 339 pruebas (303 Unit + 36 Feature), 1,171 aserciones, 19 errores, 9 fallos y 1 riesgosa; ejecución Feature independiente 36 aprobadas/230 aserciones. En P1 se leyó también `/tmp/gelia-p0-pedidos-phpunit.xml` y los resultados guardados; **no se reejecutaron pruebas**. Los temporales no son permanentes: al implementar guardar resultados nuevos en los artefactos del checkpoint y referenciar commit/entorno, sin datos reales.

| Grupo preexistente | Casos/clases de prueba | Clasificación provisional y acción necesaria |
| --- | --- | --- |
| Cierre de consulta | 7 errores: ErroresPorDueno (2), Fase2 (1), GuiaCliente (1), HandoffAlertas (1), Mejoras21 (1), Direcciones/Snapshot (1). 2 fallos de mensaje: Fase2/Mejoras21 | Precondición no satisfecha por fixture. Revisar qué contrato pretendía aislar el test; completar cierre para llegar al requisito probado, conservando pruebas negativas de no cierre. No eliminar confirmación para pasar tests. |
| Tablas SQLite ausentes | 8 errores: Mejoras28 (4), ResguardoApartado (3), EnvioBodegaTraspaso (1) | Preparación de esquema/relaciones insuficiente; aislar fixtures/bootstrap. No prueban que falten tablas en producción. |
| Bootstrap Laravel/vista | 3 errores: Fase7 (2), EnvioMunicipioCaratula render (1) | Clase base/contendor sin preparar; render usa TestCase de PHPUnit. Preparar aplicación y vista antes de atribuirlo al PDF. |
| Bultos requeridos | 1 error: Fase22 mostrador | Test anterior no satisface evidencia de bultos exigida; decidir si fixture es incompleta y mantener prueba negativa. |
| Candidatos principal | 3 fallos: fecha en payload y filtros fase/fecha | Desacuerdo observable endpoint-test; decisión de contrato pendiente de su análisis específico. No clasificar automáticamente como fixture. |
| Retrasos | 2 fallos: conteo/duplicación y mensaje de voz | Verificar reloj, configuración, evento y contrato de voz; no son evidencia de pérdida municipal. |
| Permisos cierre | 1 fallo: marcar enviado devuelve 403 al titular | Revisar permiso/actor esperado y política; no conceder permisos nuevos para silenciarlo. |
| Enmascaramiento | 1 fallo: teléfono en EnvioMunicipioCaratula | Comparar expectativa y política de visibilidad actual antes de cambiar máscara. |
| Manejadores | 1 riesgosa: HandoffAlertas:102 | Limpieza de error/exception handlers; puede acompañar un error anterior, no contar como otro fallo de producto. |

### 9.2 Reproducciones deterministas propuestas

Todas son **por añadir/ejecutar**, no resultados obtenidos en P1. Fixtures municipales con flags explícitos, usuario/permiso/estado/soporte válidos; ninguna depende de habilitar transportes reales. Antes de corregir, observar el fallo esperado; después, ejecutar la misma prueba y verificar efectos persistidos/historial, no únicamente texto o presencia de código.

| Id | Reproducción / oráculo | Tipo / checkpoints |
| --- | --- | --- |
| R01 | Capturar modalidad, municipio, paquetería, cobro, contacto alternativo, prioridad y referencia; guardar; desmontar/remontar y recargar desde servidor. Comparar todos los valores, sin tarea/evento creado | UI + Feature, CP1/8 |
| R02 | Histórico sin JSON, v1/v2 local, cuenta distinta, copia local vieja, versión desconocida, null/false/0 y claves omitidas; solo un campo editado no borra montos/SAF/dirección | Serializador puro + Feature/UI, CP1 |
| R03 | A/B leen revisión r. A guarda; B guarda con r, recibe conflicto sin writes. Interceptar 409 en UI, editar otra vez y comprobar que no reintenta con token nuevo hasta resolver; respuesta tardía no dice guardado | Feature + timers/promesas controlados, CP2 |
| R04 | Dos procesos MySQL con barrera tras leer misma revisión: exactamente uno escribe. Misma clave de creación con respuesta perdida da mismo id; clave con otro payload falla; dos claves distintas no comparten folio ni efectos | Integración aislada MySQL, CP2a |
| R05 | Pedido ya tiene id; cambiar cliente/origen antes del debounce y solicitar. Interceptar secuencia: save confirmado antes de request y tarea usa cliente/entrega de revisión confirmada. Fallo de save/carga o nueva revisión externa impide solicitud | UI + Feature, CP2b |
| R06 | Tarea LISTA_PARA_CARATULA válida con GENERADA; invocar servicio bajo manejador PHP estricto. Probar id vigente, omitido, invalidado/obsoleto, ajeno, versión vieja, sin PDF y fallo de sincronización; solo válido coloca y responde | Feature transaccional, CP3 |
| R07 | Municipal CON_INCIDENCIA con todos sus datos: corregir y comprobar anterior cancelada, nueva PENDIENTE con entrega completa y ejecución vacía. Cambio otra→municipal incompleto falla antes de cancelar; completo funciona en estado permitido | Feature, CP4 |
| R08 | Dos claves distintas solicitan simultáneamente una vigente; barrera antes de creación; una sola nueva. Repetir con corregir/cambiar/reabrir concurrentes y misma clave reintentada; revisar roles y recibos | MySQL de prueba + Feature, CP2a/4 |
| R09 | Transporte municipal sin peso/caja y otro que sí los exige; respuesta/cierre/envío final recorren misma política. Probar matriz específica, costo diferido, guía cliente y por cobrar; CEDIS sigue exigiendo pesaje detallado | Feature y política, CP5 |
| R10 | Seleccionar CEDIS, cambiar modalidad municipio ida/vuelta y recuperar borrador; origen nunca cambia. Config vacía, una, varias y cero opciones; solicitud incompatible falla con motivo | UI + Feature, CP6a |
| R11 | Crear/editar catálogo con permisos: flags, regla pendiente, habilitación explícita, destino/plantilla/cobro, actualización parcial y deshabilitación. Lista vacía tiene causa; histórico legible; sin activación colateral | Admin Feature + UI, CP7 |
| R12 | Folio inicial, referencia vigente y remisión distinta: no cambia interno ni se duplica referencia por autosave/replay. Soporte original visible en preparación, sin cargarlo otra vez; contacto cliente incompleto editable | Feature/UI, CP8 |
| R13 | Local fija/por peso, PAQ. PENDIENTE, resguardos/complementos, seguro/reexpedición, SAF y cobro: totales/regularización previos se conservan y costo desconocido no pasa a 0 | Unit/Feature, CP5/8 |
| R14 | CEDIS→municipio y PDV→municipio según O1/O2 aprobadas: inventario, responsable, bandeja, pago, carátula, empaque y despacho; actor incorrecto falla y no crea traslado ficticio | Integración de recorrido en datos sintéticos, CP6b |
| R15 | Edición posterior a generación invalida una vez con historial; colocada sigue restricción actual; después de salida no muta por autosave. Foco, Escape, previews y navegación entre fases no pierden captura | Feature/UI, CP4/8 |

### 9.3 Pruebas existentes que deben preservarse

Los grupos identifican archivos reales; se ejecutan completos antes/después de cada CP que los cite. Fallos heredados no se ocultan con skips, exclusión del grupo o expectativas debilitadas. Primero aislar la causa de fixture si impide llegar a la regla objeto de la corrección.

| Grupo | Archivos / cobertura existente |
| --- | --- |
| G1 Captura/progreso | `tests/Unit/ControlPedidos/Fase3FormularioProgresivoTest.php`, `ControlPedidosFase2Test.php`, `ControlPedidosMejoras21Test.php`; `Direcciones/SnapshotDireccionPedidoTest.php` |
| G2 Preparación/origen | `PreparacionTiendaTest.php`, `PreparacionTiendaP0Test.php`, `ValidarAlmacenSalidaPedidoTest.php`, `DestinoSucursalPedidoBmaTest.php`, `RecogeTiendaFlujoTest.php`; `tests/Feature/ControlPedidos/PreparacionTiendaWorkspaceTest.php` |
| G3 Costos/pagos | `ControlPedidosEnvioDiferidoFase1Test.php`, `ControlPedidosPaqPendienteTest.php`, `ControlPedidosTarifaLocalTest.php`, `CalcularSeguroPedidoServiceTest.php`, `Fase2EnviosCajasTest.php`, `ControlPedidosGuiaClienteTest.php`, `BancosContextoDepartamentoTest.php` |
| G4 Carátula/salida | `EnvioMunicipioCaratulaTest.php`, `Fase5MunicipioEmpaqueTest.php`, `CumplimientoFisicoApartadoTest.php`; Feature `EmpaqueBultosCedisTest.php`, `RemisionAuditoriaTest.php` |
| G5 Traspaso/históricos | `EnvioBodegaTraspasoTest.php`, `ControlPedidosComplementoFase5Test.php`, `ControlPedidosResguardoAbiertoFase2Test.php`, `ControlPedidosMaquinaEstadosTest.php`, `ControlPedidosFase7Test.php` |
| G6 Frontend | `npm test -- resources/js/Pages/ControlPedidos`; incluye destinoSucursalPedido, resolverReexpedicionForm, useActualizacionesPedidos, usePedidoDialog, tienda/modal y almacenes CEDIS |
| G7 Permisos/efectos | `ControlPedidosVisibilidadTest.php`, `ControlPedidosPermisosCierreTest.php`, `ControlPedidosCedisVisibilidadTest.php`, `ControlPedidosHandoffAlertasTest.php`; Feature `SesionEvidenciaCedisTest.php`, `EliminarRegistroPedidoBmaTest.php` |

En G2–G5/G7, Unit sin prefijo pertenece a `tests/Unit/ControlPedidos/`; Feature sin prefijo a `tests/Feature/ControlPedidos/`. Los tests nuevos de carreras no se reemplazan por SQLite: `lockForUpdate` necesita comprobación en el motor de destino. Ninguna prueba futura debe apuntar a los MySQL de desarrollo/producción por conveniencia.

Antes de cada CP: ejecutar sus reproducciones y grupos base en estado previo, guardar manifiesto por test. Después: repetir exactamente ese conjunto, inspeccionar cambios en DB/archivos/eventos; luego al cerrar la rama ejecutar 339 backend y frontend del módulo con los resultados reales del momento. La aprobación exige cero regresiones nuevas y cero fallos sin resolver en los contratos modificados. Los heredados ajenos pueden quedar identificados y separados con responsable/alcance, nunca presentarse como suite verde. No se considera arreglado T3 por corregir una línea sin R06 ni T8 por un test secuencial.

## 10. Decisiones operativas pendientes

| Id | Decisión imprescindible / propuesta de responsable | Afecta / comportamiento hasta resolver |
| --- | --- | --- |
| O1 | Operación + responsables CEDIS/Tienda: quién prepara CEDIS→municipio, quién puede tomarla y en qué bandeja | CP6b cerrado para nuevas solicitudes; conservar captura y explicar incompatibilidad. No asignar TIENDA por el almacén ni por conveniencia técnica |
| O2 | Logística: lugar de empaque/despacho y necesidad real de traslado, quién entrega/recibe y autoriza por cobrar | No crear traslados ni permisos nuevos. PDV actual conserva sus reglas; CEDIS necesita recorrido aprobado |
| O3 | Operación: plazo/prioridad al sustituir y cambiar modalidad, límite mismo día y efecto de pausas | Mantener semántica actual diferenciada; ninguna renovación automática por guardar |
| O4 | Logística/control: cambios de destinatario/transportista tras colocación, autorización, empaque y despacho; motivo, roles y compensación física/documental | Mantener bloqueos de regeneración y no abrir edición libre; reutilizar invalidación solo dentro del alcance aprobado |
| O5 | Logística: requisitos por paquetería (carátula, peso/caja, identificación/remisión, contacto, por cobrar) y si existe municipal sin carátula | Ninguna habilitación masiva; contradicciones con flujo actual se consideran combinación no habilitable |
| O6 | Logística/control: cambios/deshabilitación de reglas aplican a tareas nuevas o también a en curso; necesidad de snapshot de requisitos vs reevaluación | No modificar catálogo real en P1. Para futura administración, no cambiar aplicación temporal de reglas sin definirla; PDF histórico permanece |
| O7 | Ventas/control documental: tipo del folio inicial cuando se desconoce y cuándo se agrega otra referencia | Mantener precedencia actual y legado sin clasificar; sin convertir remisión en pedido ni recaptura obligatoria |
| O8 | Ventas/finanzas: significado del reporte «costo diferido» y casos de regularización que deben continuar | Conservar flag/tipo/costos e históricos. Simplificar lenguaje sin cambiar cobertura o liberar pendientes |

Los responsables son áreas sugeridas para revisión, no personas contactadas ni aprobaciones obtenidas. Las decisiones no bloquean reproducciones ni correcciones de integridad cuyo comportamiento está demostrado; sí bloquean habilitar reglas nuevas que dependan de ellas.

## 11. Criterios de revisión y de detención

El contrato es revisable cuando se acuerden fuentes de verdad, semántica de ausencia/null, alcance de revisión, idempotencia, compatibilidad y orden de checkpoints. Para autorizar implementación no hace falta resolver todas las combinaciones futuras: puede aprobarse un CP acotado, manteniendo bloqueadas las decisiones operativas de su dependencia.

Detener una entrega si pierde un dato en recuperación, crea más de una vigente, reconoce como guardada una captura pendiente, acepta carátula obsoleta, mueve origen sin acción explícita, deja una tarea sin bandeja, cambia cobertura financiera o permite actuar fuera de permisos. También si aparece una escritura fuera del coordinador o una migración propuesta exige borrar/reclasificar históricos para funcionar. Conservar evidencia, detener solo los comandos afectados y usar lector compatible; no reparar datos reales automáticamente.

**P1 termina con este documento para revisión. No se ha implementado ningún checkpoint ni se considera corregido un hallazgo por haber definido su contrato.**

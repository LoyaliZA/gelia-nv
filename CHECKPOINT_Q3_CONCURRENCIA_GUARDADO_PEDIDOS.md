# CHECKPOINT Q3 — Integridad concurrente, guardado seguro y sincronización

Fecha: 15 de octubre de 2026, America/Mexico_City. Base real inspeccionada: `4d008c2` (RC1), árbol inicialmente limpio. Q1, Q7 y Q2 ya estaban incorporados. No se trabajó sobre la base anterior `861cb19`.

Estado: **Q3-A backend y Q3-B frontend implementados y verificados juntos; entrega para revisión, sin despliegue**. El plan P1.2 se conserva. La publicación exige migración previa y un corte coordinado de servidores/clients; no se declara segura una convivencia con backend antiguo que aún acepte escrituras sin control.

## 1. Defectos reproducidos

Se leyeron P0, P1, P1.1, P1.2, Q2 y RC1; se contrastaron los servicios y el modal actuales. Q2 ya resolvía persistencia/merge de intención, pero no la concurrencia.

Antes de cambiar backend se agregaron cuatro pruebas HTTP sintéticas en `ConcurrenciaCapturaPedidoTest`. Resultado original: **4 pruebas, 14 aserciones, 4 fallos**. El servidor aceptaba una segunda captura obsoleta dentro del mismo segundo y sobrescribía la primera; aceptaba captura sin base; aceptaba la base anterior después de un cambio operativo intermedio; y no devolvía revisión monotónica. No fueron errores de bootstrap ni de tablas.

Frontend se comparó con el archivo exacto de HEAD (`4d008c2`), conservando/restaurando el archivo editado durante la comparación. Las reproducciones montadas mostraron confirmación «Guardado» mientras quedaban cambios, manual independiente del automático y nuevo autoguardado tras 409. La ejecución inicial tenía cuatro fallos: tres demostraban esos mecanismos; otro inicialmente comparaba el texto «error» con distinta capitalización. Esa aserción nueva se corrigió a coincidencia sin distinción de mayúsculas y se verifica además la base usada para reintentar. No se presenta ese detalle textual como defecto funcional ni se cambiaron expectativas de pruebas existentes para hacerlas pasar.

Hallazgos de código que se corrigieron:

- Comparación de updated_at fuera de transacción, con tolerancia de un segundo.
- Adopción del timestamp recibido en un 409.
- Guardado manual por Inertia y automático por Axios sin una cola común.
- `asegurarPedidoEnBd` devolvía un id existente sin confirmar los cambios pendientes.
- Clave de preparación generada con Date.now por envío y consulta de vigente antes de adquirir bloqueo.
- Clave de tarea existente devuelta sin comprobar pedido/actor/contenido.

## 2. Mapa de escritores y límites de autoridad

La revisión se delimitó antes de crear la migración. No se hizo una versión global de todos los procesos.

| Escritor/entrada | Qué puede escribir | Protección Q3 / límite |
| --- | --- | --- |
| `PedidoBmaController::autoguardar/update` → `ActualizarPedidoBmaService` | Captura comercial editable, dirección, costos permitidos, sucursal, prioridad e intención Q2; archivos solo en manual | HTTP exige base+revisión; servicio vuelve a leer bajo lock, comprueba base, acceso y editabilidad, hace merge y aumenta revisión |
| `store/autoguardar` → `CrearPedidoBmaService` | Alta, folio, datos iniciales, intención, documentos/reservas ya existentes en el contrato | Clave de creación opcional estable por intención nueva; identidad y hash persistidos en el mismo pedido; unicidad DB y replay comprobado |
| `PersistirIntencionPreparacionPedido` / `FusionarSolicitudPreparacionPedido` | prioridad_md y solicitud_preparacion_json; omitido/null/false/0 | Siguen siendo pasos subordinados de alta/update, dentro de su transacción; no crean tarea ni referencia oficial; sin cambio de reglas Q2 |
| `CrearTareaPreparacionService` | Tarea, cumplimiento, snapshot municipal, referencia oficial/historial y sincronización comercial existente | Lock del pedido antes de replay, base, vigente y creación; clave única existente + hash de contenido/actor/pedido |
| `SolicitarPesajePedidoBmaService` | Consulta CEDIS, fase/fechas de consulta e historial/notificación | Lock del pedido y relectura; base comprobada salvo replay exacto del hito ya pendiente; reglas de consulta intactas |
| `CorregirTareaPreparacionService`, `CambiarModalidadPreparacionService` | Sustitución de tarea y sus efectos existentes | Solo adición mínima de lock común y revalidación de estado/otra vigente dentro del lock. No se corrigieron snapshots ni reglas pendientes de Q6 |
| `ReabrirApartadoRecogeHoyService` | Reabre la tarea existente y actualiza su cumplimiento | Lock del pedido antes de la tarea; rechaza otra vigente. No se amplían estados ni plazos |
| `ResponderPesajePedidoBmaService`, `ResponderPreparacionTiendaService`, `ActualizarConsultaPedidoBmaService`, cierre/reapertura/retorno a borrador | Peso, cajas, revisiones, almacén autorizado y estado de consulta | Autoridad operativa existente. No aumentan revision_registro; cambios reales de la fila invalidan la huella de una captura previa |
| Costos/totales/SAF/pagos: `ActualizarCostosCajasPedidoBmaService`, `CalcularTotalesEnvioPedidoService`, servicios `SaldosAFavor`, auditoría/pago | Costos derivados, aplicaciones, pagos, cobertura, estados/historial | Versiones/validaciones financieras existentes conservadas; Q3 no sustituye ni aumenta una revisión financiera |
| Direcciones y documentos: servicios bajo `ControlPedidos/Direcciones`, PDF/anexos/guías/remisión | Snapshots, enlaces de dirección, archivos, numero_remision y referencias | Conservan su autoridad. Si cambia la fila de pedido, la huella detecta divergencia; Q3 no transforma tablas relacionadas en captura libre |
| Guías, CEDIS salida, Traspasos, cancelación y Pedidos↔PDV (`InformarEntrega/DevolucionResguardoPdvService`, proyección sucursal) | Guías, empaque/salida, fases, cancelación y hechos físicos/proyecciones | No se modificaron sus reglas ni estados. Un cambio de fila no es una edición comercial y no aumenta la revisión de captura |

La búsqueda de creadores de `PedidoBmaTareaPreparacion` identificó creación, corrección y cambio de modalidad; la reapertura también puede volver vigente una tarea. Los cuatro caminos participantes comparten ahora bloqueo del pedido. No se agregó otra máquina ni una tabla paralela de tareas.

La huella usa valores persistidos de la fila leída, también para escritores que usan UPDATE SQL sin un observer Eloquent. No se afirma que Q3 sea control global de concurrencia de pagos, archivos, inventarios o PDV: sus entidades mantienen sus contratos y autoridades. El control nuevo protege el merge comercial y sus comandos frente a una fila base obsoleta.

## 3. Contrato de revisiones

Se implementa `pedidos_bma.revision_registro`: entero creciente, default 0 para históricos. Aumenta una vez por actualización comercial aceptada, después del merge Q2 y dentro de la misma transacción. Un guardado rechazado/revertido no aumenta nada. Una creación o replay de creación conserva revisión 0; no inventa una edición. No se reutilizan las versiones de tarea ni cumplimiento.

Cada lectura serializa además `base_captura`, SHA-256 de los atributos originales de la fila. Excluye created_at/updated_at y los campos técnicos de idempotencia de creación. No depende de precisión de reloj. Incluye revisión y valores persistidos de captura/precondiciones: un cambio operativo real de fila puede invalidarla aunque no aumente revision_registro.

El cliente envía:

```text
revision_esperada: entero >= 0
base_captura: huella de 64 caracteres recibida del servidor
```

Ambos se comprueban; el entero del cliente no es autoridad. Conflicto: HTTP 409 con `codigo=conflicto_captura` y mensaje, sin ofrecer un token nuevo para adoptarlo a ciegas.

Clientes HTTP antiguos que intenten editar sin esos datos reciben conflicto y deben recargar la versión actual. Los métodos internos, cuando no reciben base explícita, toman como base el modelo que el llamador leyó y la comparan después de adquirir el lock; no sustituyen esa base por la fila nueva antes de comprobarla. En HTTP no se usa ese fallback para permitir una escritura sin precondiciones.

No hay incremento global para cada notificación, pago o respuesta de tarea. La huella evita depender de que todos esos escritores aumenten un contador comercial. Dos guardados comerciales que vuelven al mismo valor siguen teniendo revisiones distintas. Un ciclo operativo que deja exactamente los mismos valores no se convierte en una edición comercial adicional.

## 4. Mecanismo atómico aplicado

`ActualizarPedidoBmaService` adquiere lockForUpdate del pedido, relee estado/acceso, compara revisión+huella, ejecuta el merge/cálculos existentes y aumenta la revisión. Todo se confirma o revierte junto. La comprobación temprana del controller no sustituye esta comprobación dentro del lock.

Se conserva el merge de Q2: omisión conserva, null limpia la clave permitida, false/0 se escriben. Se calculan atributos desde la fila recién leída, no desde un objeto antiguo que hubiera quedado en memoria mientras otro escritor guardaba.

El servidor devuelve id, folio, revisión y base correspondientes a su operación confirmada. No hace una nueva lectura libre de fila después del commit para adoptar accidentalmente una edición ajena como confirmación propia; cargar relaciones no reemplaza los atributos originales del acuse.

Las validaciones de editabilidad, permisos, salida de almacén, sucursal, dirección, SAF y costos siguen aplicándose. No hay UPDATE de tareas, carátulas, traspasos ni resguardos por guardar un borrador. Un archivo manual no se procesa antes de validar la base.

## 5. Contrato de idempotencia y exclusividad

### Alta de borrador

El modal mantiene captura_clave estable para reintentos de la misma apertura/intención de creación; al iniciar un pedido nuevo genera otra. La clave se liga al vendedor. Se reutiliza la tabla pedidos_bma con clave única, hash de entrada y base de la creación confirmada. No hay nueva tabla de recibos general.

La misma clave/contenido devuelve el mismo id, sin repetir alta/folio/historial/reservas. La misma clave con contenido distinto se rechaza. Si el pedido ya cambió después de aquella creación, el replay se rechaza para no presentar una fila modificada por otro escritor como si confirmara la captura inicial. Se comprueban también las colisiones de unicidad; no se acepta cualquier error de clave como éxito.

La captura de base del alta se calcula desde una lectura completa de la fila persistida, incluidos defaults SQL. Durante verificación se detectó y corrigió una diferencia entre el modelo recién creado (atributos parciales) y esa lectura completa; la reproducción final de creación/replay pasa.

### Preparación

Se reutiliza `pedido_bma_tareas_preparacion.idempotencia_clave`, ya única. Se agrega solicitud_hash y se comprueban pedido, actor y contenido. La lectura de replay precede al rechazo por revisión obsoleta para que la respuesta perdida de un comando ya aplicado pueda recuperarse sin volver a ejecutarlo. La autorización de mutación se mantiene.

Dos claves distintas también compiten por el mismo lock del pedido; la segunda relee la vigente/base y no crea otra tarea. Corrección, sustitución y reapertura participan en esa exclusión, sin cambiar sus payloads ni estados.

Frontend reutiliza clave mientras el contenido del comando sea el mismo. Una intención con contenido distinto recibe otra; la exclusividad de vigente sigue aplicando. Una clave histórica sin hash verificable se rechaza de forma segura; no se considera un replay demostrado. Claves de filas eliminadas que continúan sujetas a unicidad tampoco autorizan recrear efectos.

CEDIS mantiene su idempotencia de hito: consulta ya pendiente y no respondida devuelve el resultado actual sin segundo historial, ahora bajo lock. No se cambió el circuito de respuesta/pesaje.

## 6. Coordinación de frontend

`ColaGuardadoPedido` serializa el trabajo por apertura. Automático y manual consumen el mismo coordinador. El manual conserva validaciones, envío de archivos y endpoints actuales, y solicita acuse JSON; los clientes tradicionales conservan redirecciones cuando corresponde. Ya no hay un post de formulario independiente que pueda adelantarse al automático.

Cada trabajo obtiene el snapshot actual al comenzar. Una respuesta solo confirma el fingerprint enviado y su base/revisión. No aplica sobre el formulario la captura antigua de esa respuesta. Si hubo cambios durante la petición, se conserva el dato local y el estado sigue «cambios pendientes» hasta el siguiente guardado.

Estados visibles: cambios pendientes, guardando, confirmado por servidor, error y conflicto. El header no clasifica conflicto como guardado. Un 409 detiene la cola antes de cualquier reintento, conserva la captura y no toma updated_at/base/revisión del error. El manual y la solicitud también quedan bloqueados ante conflicto.

Errores de red conservan la base y permiten reintento, sin «Guardado» falso. La copia local Q2 conserva intención, usuario, id y ahora la base de recuperación y clave de alta. Una copia antigua sin base no habilita la escritura de un pedido existente. Cambios locales no son reemplazados por una actualización operativa de la bandeja mientras la captura está sucia.

Cerrar/reabrir distingue sesiones: una respuesta pendiente de una apertura anterior no puede confirmar la nueva. Al cerrar con cambios se conserva copia local. La acción explícita «Conservar copia local y recargar» permite consultar de nuevo el servidor; si el navegador no puede guardar esa copia, no se recarga automáticamente. No se implementó merge automático ni un botón para sobrescribir una versión ajena.

Se mantiene el modal y sus controles. Solo se agregó indicación explícita de estado y recuperación dentro de la presentación actual, sin rediseño de fases/campos.

## 7. Barrera antes de solicitar

`asegurarPedidoEnBd` ya no considera el id una confirmación. Para captura editable espera la cola y drena los cambios hasta que el fingerprint actual coincida con el confirmado. La solicitud usa entonces base/revisión confirmadas y el contenido del render actual, no el cierre del primer clic.

Un flag síncrono excluye doble clic antes del siguiente render. Mientras hay comando pendiente no se inicia un manual ni un autoguardado detrás de la barrera. Si ya había un manual/automático, la barrera espera su turno. Un error o conflicto impide enviar el comando.

Los endpoints de solicitud mantienen sus redirecciones tradicionales y admiten acuse JSON. El modal usa ese acuse para tratar también un 409 de comando como conflicto comprensible, sin mostrar una respuesta JSON cruda a través de Inertia. El backend comprueba la base en el lock antes de un efecto nuevo; un replay verificable no duplica ese efecto.

No se sustituyen validaciones de soporte, modalidad, destinatario, almacén o sucursal por la revisión. No se omite un pesaje ni se permite un almacén nuevo por este mecanismo.

## 8. Archivos y migración

Nuevos:

- `app/Exceptions/ControlPedidos/ConflictoCapturaPedido.php`
- `app/Support/ControlPedidos/RevisionCapturaPedido.php`
- `app/Support/ControlPedidos/HuellaSolicitudPedido.php`
- `database/migrations/2026_10_15_000001_add_revision_registro_pedidos.php`
- `resources/js/Pages/ControlPedidos/Partials/colaGuardadoPedido.js`
- `resources/js/Pages/ControlPedidos/Partials/modalConcurrenciaContrato.test.jsx`
- `tests/Feature/ControlPedidos/ConcurrenciaCapturaPedidoTest.php`
- `tests/Support/control_pedidos_mysql_q3.php`
- Este checkpoint.

Modificados: `PedidoBmaController`, `PedidoBmaRequestBase`, `SolicitarPreparacionTiendaRequest`, modelos `PedidoBma`/`PedidoBmaTareaPreparacion`, servicios `ActualizarPedidoBma`, `CrearPedidoBma`, `CrearTareaPreparacion`, `SolicitarPesajePedidoBma`, `CorregirTareaPreparacion`, `CambiarModalidadPreparacion`, `ReabrirApartadoRecogeHoy`, y `ModalFormPedidoLegado.jsx`.

`BorradorPreparacionContratoTest` solo suma revisión/base a los requests de actualización para probar Q2 bajo el contrato nuevo. Sus expectativas de campos/estatus/ausencia de efectos no se debilitaron. Q1/Q7 y sus pruebas/documentos no se reescribieron.

Migración aditiva: revision_registro default 0; captura_creacion_clave única nullable, captura_creacion_hash/base nullable; solicitud_hash nullable en tareas. Históricos conservan sus datos y estados. Los campos técnicos del alta se ocultan de la serialización ordinaria. No se renombra ni elimina ninguna columna de Q2.

## 9. Pruebas antes/después y regresión

| Cobertura | Antes / reproducción | Resultado verificado |
| --- | --- | --- |
| Backend inicial Q3 | 4 pruebas HTTP, 4 fallos funcionales/de contrato, sin bootstrap roto | 6 pruebas Q3, 45 aserciones, aprobadas; incluye alta/replay y preparación/clave incompatible |
| Modal montado | Comparación con HEAD; confirma falso guardado, escritores independientes y reintento tras 409 | 7 casos nuevos aprobados: snapshot pendiente, manual en cola, 409, red, barrera/doble clic, reapertura y caracterización CEDIS→PDV |
| Frontend ControlPedidos | Se conserva cobertura previa; no se reescriben sus expectativas | 22 archivos, **98 pruebas aprobadas** |
| Backend relacionado ampliado | Q1/Q7/Q2 y familias indicadas abajo; no se atribuye al baseline Q3 una ejecución previa inexistente de todo el lote | **91 pruebas, 665 aserciones, aprobadas** |
| MySQL real | No se considera SQLite evidencia de carreras | Tres carreras con procesos independientes y contención InnoDB comprobada (§10) |
| Build | Se ejecutó el script de compilación existente | `npm run build` correcto; advertencia de chunks >500 kB; sin cambiar límites ni dependencias |

Backend ampliado ejecutado con PHP 8.4.25 / PHPUnit 12.5.25, SQLite :memory:, APP_ENV=testing:

```text
tests/Feature/ControlPedidos/ConcurrenciaCapturaPedidoTest.php
tests/Feature/ControlPedidos/BorradorPreparacionContratoTest.php
tests/Feature/ControlPedidos/ConfirmarCaratulaIdentidadTest.php
tests/Feature/ControlPedidos/BusquedaRemisionDelegadoTest.php
tests/Feature/ControlPedidos/PreparacionTiendaWorkspaceTest.php
tests/Unit/ControlPedidos/DestinoSucursalPedidoBmaTest.php
tests/Unit/ControlPedidos/ValidarAlmacenSalidaPedidoTest.php
tests/Unit/ControlPedidos/ControlPedidosPesajeTest.php
tests/Unit/ControlPedidos/RecogeTiendaFlujoTest.php
tests/Feature/PuntoVenta/HandoffRecepcionEsperadaPdvTest.php
```

Invocación: `docker exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e APP_ENV=testing -w /var/www/html gelianv-laravel.test-1 php vendor/bin/phpunit` seguida de esas rutas. Frontend: `npm test -- resources/js/Pages/ControlPedidos`; también se ejecutó el archivo nuevo aislado. Sintaxis PHP y `git diff --check` sin errores.

La prueba del modal usa React montado y promesas controladas; no se limita a helpers. El caso de incidencia inicialmente esperaba la etiqueta de otro helper; se corrigió la caracterización nueva al texto que ese DOM muestra («Esperando respuesta de Tienda»), sin cambiar la lógica productiva ni presentar el texto inicialmente supuesto como reproducción literal.

No se ejecutó toda la suite del repositorio ni se afirma que queden resueltos los fallos municipales antiguos documentados en Q1.

## 10. Pruebas de concurrencia MySQL

Se creó un MySQL 8.4.9 desechable, contenedor `gelia-q3-mysql`, base `gelia_q3_pruebas`, red exclusiva `gelia-q3-pruebas`. El runner usó la imagen PHP existente `sail-8.4/app`, sin conectar esa red a los servicios operativos y sin publicar puertos. No se leyeron ni copiaron filas operativas. Credenciales sintéticas del harness no son credenciales de GELIA.

El harness verifica los nombres exactos de host/base y la autorización de entorno antes de preparar esquema. El primer arranque ejecuta migrate:fresh **solo en esa base desechable**. Se preparó el esquema completo de 425 migraciones, incluida Q3. La opción de reutilización solo sirve para repetir sobre ese mismo esquema de pruebas; una primera repetición reveló un nombre único de fixture ya creado y se corrigió la reutilización de ese catálogo sintético. No se contó ese error como carrera ni se modificó un catálogo operativo.

Usa pcntl y sockets como barreras. El primer proceso aplica el servicio real dentro de una transacción aún abierta; el segundo intenta el mismo pedido desde otra conexión. El proceso coordinador comprueba `performance_schema.data_lock_waits` antes de autorizar el commit del primero. Así se demuestra bloqueo real, no solamente dos llamadas secuenciales ni un sleep probabilístico.

Resultado final repetido sobre código actual:

```text
captura ["OK","CONFLICTO"]; espera InnoDB verificada.
preparacion-misma-clave ["OK","OK"]; espera InnoDB verificada.
preparacion-claves-distintas ["OK","CONFLICTO"]; espera InnoDB verificada.
Q3 MySQL: tres carreras verificadas sobre servicios reales.
```

Se comprueba revisión 1 tras una sola captura aceptada, una tarea por pedido y un único historial de solicitud. No se sustituyen el servicio de actualización/creación de tarea ni sus transacciones. Se sustituyen notificaciones externas y se habilita la configuración de la fixture para la modalidad existente de bodega, sin alterar elegibilidad productiva.

Reproducción: crear esa red y contenedor con la imagen mysql:8.4 ya disponible y credenciales sintéticas del script; ejecutar en un runner aislado:

```text
Q3_MYSQL_DESECHABLE=SI
APP_CONFIG_CACHE=/tmp/q3-sin-cache.php
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=gelia-q3-mysql
DB_DATABASE=gelia_q3_pruebas
DB_USERNAME=root
DB_PASSWORD=q3-solo-pruebas
CACHE_STORE=array
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=null
php tests/Support/control_pedidos_mysql_q3.php
```

El runner requiere pcntl y acceso a performance_schema en ese MySQL desechable. No se recomienda invocarlo apuntando a una base existente ni adaptar su guard a nombres operativos. Finalizada la verificación se eliminan contenedor/volumen y red creados exclusivamente para Q3; el harness queda como artefacto reproducible.

## 11. Regresiones y compatibilidad

Los lotes seleccionados pasan. Se mantienen salida de almacén, destino, preparación Tienda, consulta CEDIS, handoff a resguardos y versiones/estados de entidades físicas. No se añadieron modalidades, permisos, rutas de despacho ni condiciones para omitir pesaje.

Clientes tradicionales siguen recibiendo redirect cuando hacen solicitudes válidas con precondiciones; el modal nuevo usa JSON en guardado y solicitud. Editores antiguos sin base son bloqueados con 409 y deben recargar; esto es deliberado y necesario para no dejar una vía de sobrescritura sin control. Nuevas altas antiguas sin captura_clave conservan su API, pero no adquieren mágicamente identidad de replay; los clientes nuevos sí la envían.

Las correcciones puntuales de exclusión en sucesoras/reapertura se limitan a revalidar lo que ya exigían antes de adquirir el lock y a impedir otra vigente. No recuperan campos ni trasladan ejecución anterior; eso sigue en Q6.

## 12. Riesgos pendientes

- Backend antiguo aceptando edición sin precondiciones puede eludir Q3. No es seguro un rolling deploy con versiones distintas sirviendo esas rutas. Es necesaria actualización coordinada de código, procesos y frontend, con bloqueo temporal de esas escrituras durante el corte.
- La huella es precondición de la fila, no revisión de cada tabla de documentos, pagos o inventario. Se conservan sus controles de dominio; Q3 no los sustituye por una revisión global ni acredita todas sus carreras posibles.
- La exclusividad de tarea se basa en la clave única existente y el lock de pedido compartido por los escritores inspeccionados. Código futuro que cree tareas ignorando ese protocolo necesitaría la misma revisión.
- Los efectos externos de notificación/archivos no se vuelven reversibles por un rollback SQL. Se evitó repetir efectos por replay; no se rehízo el sistema de entrega de eventos ni el generador de PDF.
- Reintentar una actualización de captura cuya respuesta se perdió puede producir 409 si el servidor ya la aplicó. Se conserva la copia y se revisa; no se adopta automáticamente el token para sobrescribir.
- Históricos con claves de tarea sin hash pueden requerir revisión en vez de replay automático. No se completaron hashes por inferencia.
- No se ofrecen reconciliación automática ni comparación visual campo por campo; existe conservación local y recarga explícita. No se promete recuperar binarios desde localStorage.
- La advertencia de tamaño de bundle no impidió compilar; no se refactorizó la aplicación completa por ella.

## 13. Incidencia CEDIS→PDV: «Pendiente pesaje en Tienda»

No se consultó ni modificó el pedido productivo mencionado. La frase literal aportada no aparece en el código fuente inspeccionado; no se atribuye a una versión/caché concretas sin evidencia del entorno servido.

Rutas de cálculo relacionadas:

- `PedidoBma::usaPreparacionTienda()` devuelve true si existe **cualquier** tarea histórica, no solamente una vigente.
- `ListarPedidosBmaService` expone ese flag y resuelve aparte la tarea vigente/respondida.
- `ModalFormPedidoLegado` calcula enFlujoTienda por flag/historia, tarea o modalidad seleccionada; no usa sucursal destino como responsable de consulta. pendientePesaje se deriva de estatus_envio=pendiente_pesaje o la bandera local.
- `etiquetaEstatusEnvio`/`badgeEstatusEnvio` en pedidosBmaStyles distinguen «Pendiente de respuesta de Tienda» frente a CEDIS por respondeTienda. El panel del modal muestra «Esperando respuesta de Tienda». `CatalogoEstatusPedido` usa la misma fase PESAJE_PENDIENTE para semánticas distintas.
- La elegibilidad del modal filtra modalidades por requiere_logistica y almacenes por preparación. `SolicitarPesajePedidoBmaService` aún rechaza consulta CEDIS de mercancía cuando la configuración Tienda aplica. Ese rechazo es una condición operativa real, no una etiqueta; no se cambió en Q3.

Fixture ejecutada: origen comercial Tienda/no logística, almacén CEDIS configurado explícitamente, sucursal destino 3, fase PESAJE_PENDIENTE/estatus_envio pendiente_pesaje, historia Tienda=true sin tarea actual y sin intención de preparación. El modal conserva almacén 9 y sucursal, pero atribuye la espera a Tienda. La prueba no envía comandos ni fabrica una transición. Demuestra ambigüedad de presentación/selección de responsable, no que el pedido productivo tenga esos mismos campos.

Actor documentado para la rama histórica sin tarea Tienda real: CEDIS atiende consulta/separación y la salida de su mercancía; PDV recibe y confirma custodia después del handoff de envío (P1.1 D14/D15, P1.2 B/G, P02/P04). Una tarea Tienda vigente y elegible puede tener otro actor; no se decide por el nombre comercial ni por el destino sucursal.

Clasificación: Q4 si hay selección/almacén/elegibilidad incorrectos; Q5 para requisitos y responsable de consulta/documentación; Q8a para continuidad salida→recepción/custodia una vez enviado. Con los datos disponibles no se declara un fallo de resguardos ni una entrega bloqueada. Si el caso real no tiene tarea Tienda válida y el gate de consulta impide continuar, es candidato a **hotfix separado prioritario Q4/Q5**, con reproducción de sus campos autorizados. No se resuelve falsificando ENVIADO ni desactivando pesaje.

## 14. Preparación para siguiente liberación

**Veredicto técnico: pruebas de Q3-A/Q3-B y carreras MySQL aprobadas; paquete para revisión. Publicación condicionada, no despliegue autorizado por este checkpoint.**

Secuencia requerida para el futuro corte:

1. Verificar checkout/stack/esquema reales. El NO-GO de RC1 describía dos destinos distintos; este checkpoint no ejecutó ni verificó una liberación de esos entornos y no lo revoca por inferencia.
2. Respaldo y pausa de escrituras de captura/solicitud durante el corte; detener/actualizar los procesos de código anterior que puedan servirlas.
3. Aplicar la migración aditiva Q3 en el esquema alineado, antes de habilitar el código nuevo. No ejecutar migraciones de toda otra rama por conveniencia. No se aplicó aquí sobre bases operativas.
4. Publicar backend y frontend juntos, renovar cachés/opcache y workers según el procedimiento de ese destino. Las páginas antiguas deben recargar; sus requests sin base se rechazan de forma segura.
5. Humo aislado con borrador sintético: lectura de base/revisión, guardado, conflicto de otro editor, replay de solicitud y acceso por permisos. No usar un pedido operativo para el ensayo.

`npm run build` terminó correctamente y el script habitual verificó/publicó los artefactos **locales de build**; no se ejecutaron scripts de despliegue, reinicios de servicios ni migraciones operativas. No hay configuración productiva nueva requerida por Q3.

Rollback no destructivo: dejar columnas, claves y hashes; deshabilitar temporalmente las escrituras del paquete mientras se vuelve al código anterior, y reabrirlas solo con una política explícita. Revertir a un backend que acepta escrituras antiguas recupera también su riesgo de concurrencia. No usar migrate:rollback como paso automático: down elimina evidencia de revisión/idempotencia. No borrar tareas ni resguardos para deshacer.

## 15. Siguiente checkpoint

Detenerse para revisar Q3. El siguiente paquete del plan sigue siendo **Q4**, selección/elegibilidad y conservación del almacén, incorporando la caracterización CEDIS→PDV. Q5 y Q8 mantienen sus propios límites. No se iniciaron ni se reordenó P1.2.

Sin commits, push, despliegue automático, migraciones operativas ni reescritura de checkpoints previos.

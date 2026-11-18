# M-01 — Auditoría independiente y estabilización de Codex

Fecha: 14 de noviembre de 2026, hora de Ciudad de México. Repositorio: `/home/ti-admin/gelianv`.

## Dictamen y alcance

Se encontraron y corrigieron defectos de autorización, referencias históricas, versionado, reenvío de consultas, resolución de excepciones y presentación financiera. La auditoría amplió las pruebas del incremento existente; no implementó carátulas definitivas, logística física municipal, P-01 completo ni M-02 a M-12.

El resultado **permite continuar con aceptación controlada de C y A**, con las reservas de navegador descritas abajo. **B tiene comprobación de servidor, pero no se declara aceptado de extremo a extremo en interfaz**: falta acreditar con cuentas operativas cómo se capturan/presentan importes y pagos después de cerrar la consulta municipal y cómo se llega al auxiliar. El formulario municipal actual no incorpora un panel de pagos ni un botón propio de envío al auxiliar. No se desarrolló otra fase para cubrir esa ausencia.

El reporte de implementación original permanece intacto. Sus afirmaciones no se utilizaron como sustituto de pruebas. No se hizo pull, merge, reset, rebase, commit ni push. No hubo delegación a otros agentes.

## 1. Estado inicial y referencias

- Rama inicial y final: `develop`.
- HEAD inicial: `52208b5028e42119c9d8fbb6acb6be2ce7697dfd`.
- 17 archivos con modificaciones rastreadas; además, 20 archivos nuevos de código/pruebas y dos documentos sin seguimiento. El inventario final identifica también los archivos nuevos, que no aparecen en `git diff --stat`.
- El reporte anterior y el plan mencionan `9e821679` como base histórica. La comparación de esta auditoría se hizo contra **el HEAD efectivamente disponible**, `52208b5`; no se reconstruyó una base distinta ni se actualizó la rama.
- Sail, Laravel, MySQL 8.4 y Selenium estaban activos. El comando de aislamiento del host falló inicialmente con `bwrap: loopback: Failed RTM_NEWADDR`; las operaciones posteriores se ejecutaron con la aprobación del entorno, conservando Sail para PHP y npm.

Referencias contrastadas: `AGENTS.md`, `GUIA_DISENO_GELIA.md`, `GUIA_EVIDENCIA_VISUAL.md`, `M01_IMPLEMENTACION_REPORTE_DETALLADO.md`, `PROCESO PARA PEDIDOS.md` y el análisis V02.1, especialmente M-01, M-05/M-06 y P-01. Se localizó y contrastó `/home/ti-admin/.cursor/plans/m-01_municipal_ordinario_952c8ed6.plan.md`: sus marcas «completed» no acreditaban la operación.

Para las superficies compartidas se consultaron las decisiones vigentes y continuidad de Tienda, configuración operativa y acciones rápidas de disponibilidad. Las referencias de implementación fueron `FormularioRegistrarTienda`, `PanelRespuestaDisponibilidad`, `AccionesRapidasDisponibilidad`, el resumen financiero y las primitivas temáticas existentes. No se inició un rediseño ni se instalaron skills/dependencias.

## 2. Hallazgos confirmados y corregidos

Los niveles expresan impacto dentro de esta implementación. «Corregido» significa que existe un cambio y evidencia dirigida; no equivale a certificar todos los recorridos de producción.

### H-01 — Alta: consultas fuera de sucursal/departamento

**Evidencia:** `ConsultaMunicipalController::tienda` consultaba hasta 50 pedidos de cualquier sucursal. `responder` solo comprobaba el permiso del área y llamaba al servicio, sin alcance por sucursal ni departamento. Un usuario CEDIS ajeno respondió por identificador y recibió **200**, cuando debía recibir 403; consta en `01-reproduccion.txt`. La exposición de la cola Tienda se confirma también en la consulta original, sin ningún filtro de actor.

**Consecuencia:** lectura de clientes/pedidos de otra sucursal y registro de una respuesta operativa ajena.

**Corrección:** `AlcanceConsultaMunicipal` utiliza las relaciones reales `User::idsSucursalesOperables()` → `almacenes.sucursal_id`; no crea una relación usuario-almacén. CEDIS reutiliza `VisibilidadPedidoBma::puedeVerEnCedis`, incluyendo departamentos principal/múltiples y las excepciones administrativas ya existentes. Tienda exige permiso y sucursal operable; administradores conservan el alcance de `VisibilidadPedidoBma::esAdmin`. El filtro se aplica antes del límite. La respuesta se vuelve a autorizar **dentro del bloqueo del pedido**, por lo que también queda protegida la entrada CEDIS compartida. La lectura del detalle usa el mismo alcance.

**Prueba:** usuario con otra sucursal recibe lista vacía y 403 directo; al asignarle la sucursal correcta obtiene un pedido, abre el detalle y responde. No se crea una tarea de preparación.

### H-02 — Alta: consultar no autoriza reescribir la solicitud

**Evidencia:** `RegistrarSolicitudMunicipalService::ejecutar` y `EnviarConsultaMunicipalService::ejecutar` aceptaban `puedeMutarComoVendedora OR puedeConsultar`. No exigían el permiso de creación ni una fase editable. El guardado solo excluía la marca `nacional`, por lo que podía convertir otras solicitudes.

**Consecuencia:** un lector autorizado del pedido podía cambiar destino, consignatario, costo o consulta; un llamado directo podía operar un pedido fuera de su etapa de captura.

**Corrección:** ambos servicios exigen el permiso `control_pedidos.crear` y titularidad de Ventas. Dentro del bloqueo comprueban fase editable y ausencia de cancelación/pago validado/remisión. El guardado requiere la marca municipal explícita. Después del envío no permite reescribir silenciosamente la solicitud. La prueba de lector afirma primero que el actor sí puede consultar, y comprueba después el rechazo de ambas mutaciones.

### H-03 — Alta: evidencia activa sin validación en la ruta municipal

**Evidencia:** el controlador nuevo validaba solo el arreglo de excepciones y la confirmación. Un archivo `script.html` se guardó como evidencia pública y la respuesta fue **200** (`01-reproduccion.txt`). No se afirma haber ejecutado un ataque en navegador ni un archivo en el servidor.

**Consecuencia:** incorporación de contenido activo a un almacenamiento público desde una ruta que debía aceptar imágenes/PDF de consulta.

**Corrección:** la ruta municipal reutiliza `ResponderDisponibilidadInicialRequest`, con validación anidada, cantidades, tamaños y límites de arreglos. El servicio añade validación de MIME/tamaño para municipal, incluso al entrar por la ruta compartida. HTML recibe 422 y no crea documentos. Las identificaciones continúan en `local`, cuyo root es `storage/app/private`; no se movieron a `public`.

### H-04 — Alta: barreras comerciales incompletas

**Evidencia:** el gancho original de `GestionarRemisionPedidoBmaService` estaba solo en `subir`; `actualizarNumeroRemision` no aplicaba la política. Pago y validación comprobaban el estado antes de adquirir el bloqueo. `assertPagoListoParaAvanzar`, utilizado al enviar/aprobar, y el cierre financiero directo no aplicaban la política municipal.

**Consecuencia:** entradas alternativas podían asociar una remisión o avanzar con una consulta no vigente; entre lectura y bloqueo podían cambiar las condiciones examinadas.

**Corrección:** la remisión municipal exige consulta vigente, cobertura exigible y pago validado; tanto archivo como número se comprueban bajo bloqueo. Registrar pago y validar vuelven a comprobar la política al adquirir el pedido. El método común de avance comercial y el registro de cierre financiero también la aplican. La política sigue siendo un no-op para otros tipos de pedido.

**Pruebas:** validación y número de remisión rechazan consulta abierta; los endpoints de auxiliar deniegan sin permiso y bloquean con permiso si falta cierre. Costo conocido 100 + 30: pagar 100 no valida, completar 30 sí verifica las exhibiciones y permite asociar archivo/número. Jaguar permite validar mercancía y asociar la remisión con transporte todavía desconocido.

### H-05 — Alta: el transporte desconocido se presentaba como cero y cubierto

**Evidencia:** `RegistrarCierrePagoPedidoService` conservaba `monto_envio=0` y agregaba metadata, pero `ObtenerDetalleReportePagoPedidoService`, `ExportarReportePagosPedidosCsvService`, `CalcularMetricasReportePagosPedidosService` y los componentes del reporte no la leían. El agregado podía devolver saldo «cubierto» sin señalar transporte pendiente. No bastaba la marca `monto_envio_no_es_gratuito`.

**Corrección sin migración contable:**

- El decimal no nullable del snapshot conserva su representación técnica; no se modificaron registros históricos ni el significado canónico de `cubierto` para la etapa exigible.
- El detalle entrega `monto_envio=null` cuando es desconocido y la dimensión municipal separada. La interfaz muestra «Pendiente de determinar».
- CSV escribe esa descripción, y aclara que la cobertura es de la etapa exigible.
- Las métricas incluyen cantidad de transportes sin determinar, marca de subtotal conocido y saldo `transporte_pendiente` cuando no hay otra diferencia monetaria.
- El listado entrega la dimensión municipal; sus etiquetas y el detalle aclaran transporte pendiente. Las sumas de importes conocidos no inventan un valor para el flete pendiente.
- `CoberturaPagoPedidoBmaService` expone la evaluación municipal de transporte junto con su cálculo habitual. El cálculo de Tienda/Nacional y saldos no se redefine.

**Pruebas:** Jaguar validado conserva costo nulo y `pendiente_regularizacion`; snapshot con `desconocido=true`, `cubierto=false`; detalle nulo y métrica de un transporte pendiente. La presentación/CSV se revisó en código; el CSV municipal no tiene una prueba específica de descarga/exportación en esta entrega.

### H-06 — Media: reenvío destructivo y cierre repetido

**Evidencia:** `EnviarConsultaMunicipalService::ejecutar` hacía `unset(revision_disponibilidad)` en cada envío. La prueba perdió la revisión al repetir la solicitud (`01-reproduccion.txt`). `cerrarMunicipal` reescribía fecha/historial en cada cierre.

**Corrección:** repetir envío al mismo almacén conserva revisión y estado; cambiar el almacén una vez enviado se rechaza. Se exigen datos de solicitud guardados y almacén activo/visible. El cierre ya cerrado devuelve el pedido sin duplicar historial ni renovar fecha. Envío, respuesta y cierre utilizan bloqueo de fila.

### H-07 — Media: referencias históricas inaccesibles después de baja lógica

**Evidencia:** se conservaba el archivo, pero el binding de descarga omitía documentos eliminados y `ClienteDocumento::consignatario` omitía consignatarios dados de baja. Una descarga autorizada por pedido pasó de 200 a **404** después de la baja (`01-reproduccion.txt`).

**Corrección:** únicamente las rutas de descarga permiten binding con eliminados; la relación documento-consignatario admite baja lógica. La autorización sigue exigiendo `ver_identificacion`, alcance y permiso histórico o referencia exacta desde un pedido consultable. La baja del consignatario impide tratar su documento como vigente de forma indiscriminada. Los endpoints de mutación no recuperan automáticamente filas dadas de baja.

**Pruebas:** sustitución, baja de documento y baja de identidad conservan la descarga desde el pedido que lo cita; sin ese contexto se deniega. Documento de otro cliente responde 404; sin permiso de identificación responde 403; no aparece `ruta` en JSON. Un `pedido_id` explícito inexistente ahora responde 404, en vez de convertirse silenciosamente en contexto nulo.

### H-08 — Media: versiones repetidas y carrera en la primera identificación

**Evidencia:** `guardarDocumento` calculaba la versión desde el documento vigente. Después de eliminarlo generó otra vez **versión 1**, en vez de 2 (`01-reproduccion.txt`). Bloquear solo el registro vigente no proporcionaba una fila estable para serializar la primera carga. Esto último era un riesgo estático de concurrencia; no se reprodujo la carrera original antes de corregir.

**Corrección:** editar datos, guardar/sustituir documentos y bajas bloquean la identidad estable. La siguiente versión de documento se toma de toda la cadena, incluyendo bajas lógicas. Se conserva el predecesor. Guardar una referencia en el pedido bloquea también la identidad mientras toma el snapshot.

**Prueba concurrente MySQL real:** cuatro procesos independientes, tres ediciones y tres cargas por proceso: 13 versiones de datos y 12 de documento, secuencias completas y una sola vigente de cada tipo. Los documentos de esta prueba se escriben en `/tmp/m01-auditoria-documentos` del contenedor.

### H-09 — Media: excepciones municipales sin salida funcional

**Evidencia:** `AccionesDisponibilidadTiendaService::ejecutar` exigía `modalidad_entrega=tienda`. Un municipal con faltante no podía retirarlo y recibió 422 (`02-excepciones-reproduccion.txt`). La cola municipal de Tienda solo enviaba confirmación sin excepciones y respondía automáticamente al elegir archivo. El formulario de Ventas tampoco presentaba acciones sobre las piezas reportadas.

**Corrección:** se admite municipal en las decisiones documentales existentes, manteniendo propietario, permiso, claves idempotentes y bloqueo de sin existencias. La rama municipal no registra retiros físicos ni cambia el estado financiero del envío. Confirmar todos los detalles puede dejar decisión registrada; retiro/sustitución solicita una nueva respuesta del conjunto. La cola reutiliza `PanelRespuestaDisponibilidad`; Ventas reutiliza `AccionesRapidasDisponibilidad` y puede actualizar la respuesta. El request conserva `actualizacion_id`. Se evita que la rama compartida escriba `pesaje_listo` en municipal.

**Prueba:** retirar faltante sin SKU, bloquear cierre pendiente de nueva revisión, responder la actualización y cerrar conservando costo nulo y estado del flete. Sustitución municipal completa con catálogo/escaneo queda sin recorrido específico; no se extrapola desde el retiro.

### H-10 — Media: reapertura incoherente

**Evidencia:** `ReabrirConsultaPedidoBmaService` limpiaba las columnas de cierre, pero dejaba `consulta_municipal.cerrada_at/estado` cerrados. El evaluador municipal podía seguir considerándolo cerrado. El motivo de bloqueo no incluía la actualización pendiente.

**Corrección:** reapertura municipal limpia ambos cierres, invalida aceptación y solicita nueva respuesta; no reabre un pedido con pago validado/remisión. Motivo de bloqueo y estado derivado consideran actualización/aceptación inválida. Se conservan datos, documentos y flete. La última corrección del estado derivado, sin cambios SQL, se verificó además con una prueba dirigida SQLite de 11 aserciones después del lote MySQL.

### H-11 — Media: envío al auxiliar dependía de pesaje nacional

**Evidencia:** un pedido municipal cerrado y cubierto, con origen logístico, folio, almacén y documento, recibió **422** al llamar a `control_pedidos.enviar` (`03-envio-auxiliar-reproduccion.txt`). Las pruebas anteriores simulaban el cambio a fase auxiliar y no descubrían esta validación heredada.

**Corrección:** el contrato municipal nuevo usa la consulta municipal, sin exigir pesaje, cajas o domicilio nacional para enviarlo al auxiliar. Se mantienen datos comerciales básicos, documento, folio, almacén y política financiera. La excepción se limita a la marca municipal; los municipales históricos no marcados conservan sus reglas. La prueba llama al endpoint real, comprueba `PENDIENTE_AUXILIAR` y conserva el flete diferido.

### H-12 — Media: snapshot reemplazado y total de encabezado desactualizado

**Evidencia:** guardar nuevamente la misma identidad tomaba su nombre/teléfono/documento vigentes, reemplazando la referencia ya elegida por el pedido. El servicio actualizaba costo de envío sin recalcular `total_a_cobrar` del encabezado.

**Corrección:** la misma identidad conserva su snapshot; elegir otra identidad toma su versión vigente. Se recalcula el total de encabezado con la fórmula existente. Se rechaza también un conocido que redondee a cero. Prueba: editar el teléfono del expediente y guardar de nuevo conserva snapshot; mercancía 100 y flete 30 dejan total 130.

### H-13 — Media: interfaz y avisos incompletos del alcance existente

**Evidencia:** el expediente tenía creación y carga, pero no controles de edición, baja ni historial; `crear` podía dejar una promesa rechazada sin mostrar error. El listado histórico del servidor no recuperaba identidades dadas de baja ni enumeraba documentos históricos. Enviar consulta no llamaba a notificaciones.

**Corrección:** controles de edición y, en Clientes, bajas lógicas e historial; permisos separados en el payload, captura de errores y bloqueo del botón de guardado. Históricos solo con su permiso, sin rutas internas. Enviar notifica al área una vez; los destinatarios municipales se filtran por visibilidad real para no volver a exponer otras sucursales en avisos. El recorrido de recepción de notificaciones entre cuentas no fue ejercido; se acredita la conexión y filtro por inspección, no una entrega efectiva.

## 3. Funcionalidad comprobada y límites por área

| Área | Evidencia obtenida | Lo que no acredita |
| --- | --- | --- |
| Identidad estable y teléfono | Integración SQLite/MySQL, edición real en navegador | Corrección masiva de expedientes existentes |
| Identificación privada | Upload permitido, MIME rechazado, descarga autorizada/denegada, sustitución y bajas; UI real de carga/sustitución/baja | Retención definitiva o incrustación en carátula |
| Histórico | Pedido conserva ids y datos; autorización tras sustitución/bajas; historial de Clientes en navegador | Selección explícita de otra versión de la misma identidad para un borrador ya guardado |
| Concurrencia de expediente | 4 procesos MySQL, secuencias sin duplicados | Prueba de carga prolongada, fallo de disco o deadlock con otros módulos |
| Consulta CEDIS | Endpoint, respuesta, evidencia, excepción, cierre y alcance de departamento | Operación completa de la bandeja CEDIS en navegador con cuenta operativa |
| Consulta Tienda | Alcance por sucursal y respuesta directa; navegador real con evidencia; no crea tareas | Entrega de avisos a otros usuarios ni escáner/cámara física |
| Excepciones | Faltante sin SKU → retiro → nueva revisión → cierre; detalles/decisiones compartidos revisados | Sustitución municipal completa por catálogo o captura en navegador |
| Costo conocido | Insuficiencia, complemento, validación, remisión y número | Prueba con banca/Wizerp reales |
| Jaguar | Cierre no paga flete, validación de mercancía, reporte con transporte pendiente y remisión | Regularización posterior P-01, expresamente fuera de alcance |
| Avance a auxiliar | Llamado real a endpoint sin requisitos nacionales | Recorrido visual desde alta nueva hasta captura de pago y auditoría |
| Reporte financiero | Snapshot, detalle/métrica municipal y regresiones de agregados | Exportación CSV municipal ejecutada en navegador |

## 4. Permisos y administración

La migración registra seis permisos de expediente y el de respuesta municipal; solo asigna automáticamente a Super Admin si ese rol existe. El catálogo de seed conserva los mismos nombres. Se inspeccionó el flujo de Administración (`AdminController` y asignación existente): puede administrar permisos de usuarios/grupos usando el catálogo, con las restricciones de quien asigna. No se añadió otro mecanismo ni se distribuyeron permisos a roles operativos por su nombre.

Antes de aceptación, Administración debe conceder expresamente las facultades necesarias a Ventas, al personal de respuesta y a quienes ven identificaciones, además de sus alcances de departamento/sucursal. Las bajas e históricos no son necesarios para todos. No se asignaron permisos a usuarios reales durante esta auditoría. Las concesiones de las pruebas solo existen en memoria o en `codex_m01_audit_20261114`.

## 5. Pruebas ejecutadas

Los logs conservados están en [evidencias-m01-auditoria](evidencias-m01-auditoria/). Los lotes se solapan: **no deben sumarse como pruebas únicas**.

| Ejecución | Resultado |
| --- | --- |
| Base: municipal original + diferido | 7 aprobadas, 47 aserciones |
| Nuevas reproducciones iniciales | 5 fallos reproducidos, 3 aprobadas; después corregidos |
| Primer lote de regresiones | 36 aprobadas, 252 aserciones |
| Lote ampliado Tienda/transferencia/saldos/envíos | 96 aprobadas, 1 fallo, 789 aserciones; fallo MD reproducido en HEAD |
| Lote final financiero/municipal/remisión/cobertura/pesaje | **58 aprobadas, 380 aserciones** |
| Municipal MySQL final | **19 aprobadas, 223 aserciones**, 85,932 segundos |
| Ajuste final de estado derivado de reapertura | 1 aprobada SQLite, 11 aserciones |
| Concurrencia MySQL | 4 procesos, 13 versiones de datos y 12 documentos, una vigente por tipo |
| Vitest final | **11 aprobadas**, 2 archivos |
| Build final | Publicado; manifiesto completo y archivos verificados |
| Chromium final | Aprobado, seis mediciones, sin desbordamiento ni errores de consola |

### Comandos de backend

Comando base de todas las ejecuciones SQLite, con el filtro de cada lote:

```bash
./vendor/bin/sail exec \
  -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php \
  -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php \
  laravel.test php artisan test --filter='FILTRO'
```

Filtros ejecutados:

```text
MunicipalOrdinarioM01Test|ControlPedidosEnvioDiferidoFase1Test
MunicipalOrdinarioM01Test
MunicipalOrdinarioM01Test|ControlPedidosEnvioDiferidoFase1Test|RemisionAuditoriaTest|Fase1IntegridadPagosPedidoBmaTest|IntegridadCoberturaPagoTest|CalcularMetricasReportePagosPedidosTest|AccionesDisponibilidad|ReabrirConsulta
MunicipalOrdinarioM01Test|RecorridoTiendaIntegralTest|TransferenciaTiendaTest|ImportesPagoTiendaTest|ElegibilidadPesajePedidoTest|BandejasOperativasTest|ReglasSaldosAFavorTest
test_envio_al_auxiliar_usa_consulta_municipal
MunicipalOrdinarioM01Test|ControlPedidosEnvioDiferidoFase1Test|RemisionAuditoriaTest|Fase1IntegridadPagosPedidoBmaTest|IntegridadCoberturaPagoTest|CalcularMetricasReportePagosPedidosTest|ElegibilidadPesajePedidoTest
test_reapertura_invalida_ambos
```

Los nombres sin pruebas coincidentes en un filtro no constituyen cobertura adicional. No se ejecutó toda la suite del repositorio.

### SQLite y MySQL

PHPUnit normal fuerza SQLite en memoria, cache array y colas síncronas. La clase municipal comprueba el destino **antes de que RefreshDatabase ejecute migraciones**: admite únicamente SQLite `:memory:` o la base MySQL exacta de esta auditoría.

Se creó una base vacía **exclusiva**, `codex_m01_audit_20261114`, usando el MySQL ya activo. `RefreshDatabase` reconstruyó únicamente esa base. Las 463 migraciones aplicaron; las cuatro tablas del expediente utilizan InnoDB y las claves foráneas se registraron correctamente. [Inspección de esquema](evidencias-m01-auditoria/14-esquema-mysql.txt). No se ejecutó una migración destructiva contra bases compartidas/operativas. No se cambió ninguna migración para resolver esta auditoría.

```bash
./vendor/bin/sail exec \
  -e APP_CONFIG_CACHE=/tmp/gelia-m01-mysql-config.php \
  -e APP_ROUTES_CACHE=/tmp/gelia-m01-mysql-routes.php \
  laravel.test php vendor/bin/phpunit \
  --configuration=scripts/qa/m01-auditoria/phpunit-mysql.xml \
  --filter=MunicipalOrdinarioM01Test
```

El primer intento por Artisan produjo la advertencia de doble `--configuration`; un intento inicial de preparación informó tabla `migrations` ausente. Se separó la ejecución y se usó PHPUnit directamente mediante Sail. Se obtuvo después una pasada aislada y dos lotes completos aprobados (13 y 19 escenarios). No se presenta aquel error transitorio como una incompatibilidad demostrada de la migración M-01.

El guion `scripts/qa/m01-auditoria/concurrencia.php` se ejecutó con `DB_CONNECTION=mysql`, `DB_DATABASE=codex_m01_audit_20261114`, `DB_USERNAME=root`, configuración/rutas en `/tmp`, `APP_ENV=testing`, cache array y colas síncronas. Rechaza otra base. No imprime contraseñas. La comparación del fallo MD usó una configuración PHPUnit temporal y un autoloader prioritario de copias de los archivos PHP modificados obtenidas con `git show HEAD:ruta`; no revirtió el árbol de trabajo.

### Frontend y build

```bash
./vendor/bin/sail exec --user sail laravel.test npm exec vitest run \
  resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.seleccion.test.jsx \
  resources/js/Pages/ControlPedidos/Partials/AccionesRapidasDisponibilidad.test.jsx
./vendor/bin/sail exec --user sail laravel.test npm run build
```

Sail por defecto ejecuta como root en este servidor; por eso npm se ejecutó explícitamente como `sail`. Se conservó `scripts/retain-vite-assets.mjs`. No se abrieron permisos generales. El manifiesto final quedó publicado sobre los assets verificados y se conservaron assets previos. No hay `public/hot` ni se inició Vite. Vitest conserva advertencias de `act(...)` de pruebas compartidas; el build advierte sobre tamaño de chunks. Ninguna equivale a un error de ejecución en las pantallas comprobadas.

## 6. Evidencia real de navegador

Se usó el Chromium disponible en Selenium, después de verificar que no hubiera una sesión ajena. Laravel de auditoría corrió en **9193**, con la base exclusiva, `APP_ENV=testing`, cookie `gelia_m01_auditoria`, correo array y colas síncronas. La aplicación de uso en 80/9080 no se reinició. Se cerraron exclusivamente los procesos del puerto 9193 al terminar; Selenium quedó sin sesiones propias abiertas.

Guiones conservados: `scripts/qa/m01-auditoria/fixtures.php` y `navegador.mjs`. Fixtures del recorrido final: usuario 24, cliente 21 y pedido 20 **de la base exclusiva**, todos ficticios. El password escrito en el fixture es únicamente de ese usuario sintético, no una credencial operativa.

Interacciones reales:

1. Inicio de sesión mediante el formulario de login.
2. Alta de consignatario desde Clientes y edición del teléfono mediante el formulario.
3. Carga de identificación ficticia PNG y sustitución desde el control de archivos; se observó versión 2.
4. Lectura de ese mismo expediente desde el endpoint de Pedidos; guardado/envío de solicitud mediante Axios autenticado. Estas dos acciones **no se realizaron con los botones del alta municipal**.
5. Apertura de la cola Tienda y del panel, selección de estado, archivo ficticio y envío de la respuesta desde los controles.
6. Cierre de Ventas mediante endpoint autenticado; **no se acredita el botón de cierre del modal municipal**.
7. Regreso a Clientes, baja de identificación, baja de consignatario y apertura del historial.

Se capturaron expediente y respuesta a **1440, 1024 y 390 px**: oscuro en escritorio/tableta y claro en móvil. Sin desbordamiento horizontal y con consola final vacía. Inspección visual de ambas vistas a 390 px. El fixture no contenía PDF del pedido, por lo que el botón «Ver pedido» estaba deshabilitado: no acredita visor de un pedido real.

[Capturas y resultado JSON](../resources/design-references/m01-auditoria/). Los intentos previos del guion necesitaron esperar el montaje de React, leer `textContent` en vez de mayúsculas visuales y corregir comillas de un selector. El recorrido final se ejecutó con fixtures nuevos; no incluyó los 422 de reintentos anteriores y su consola está vacía.

## 7. Regresiones y defecto previo pendiente

No se observaron nuevas regresiones en los escenarios dirigidos de elegibilidad/pesaje nacional, remisión, cobertura, bancos, saldos, Tienda/transferencia, custodia y segundo traslado ejecutados. Esto no certifica todos los pedidos históricos ni todo el repositorio.

**Defecto confirmado, pendiente y ajeno al cambio de M-01:** `RecorridoTiendaIntegralTest::test_la_cobertura_de_mismo_dia_esta_abierta_a_departamentos_y_exige_dia_habil_sin_confirmarla` esperaba rechazar la selección de Mismo día en sábado y no recibió la excepción. Se reprodujo el mismo fallo con las clases rastreadas de HEAD (`06-md-base-head.txt`), tres aserciones. El flujo y la prueba deben reconciliarse con la decisión operativa vigente; no se cambió una regla de Tienda para dejar verde esta auditoría municipal. El nombre de una prueba aprobada del lote tampoco sustituye esas decisiones.

## 8. Riesgos, limitaciones y decisiones pendientes

### Confirmado / pendiente de integración o aceptación

- **B visual:** la pantalla municipal no ofrece un panel propio de importes/pagos ni envío al auxiliar. Los servicios y el endpoint fueron ejercidos, pero hace falta comprobar la entrada operativa prevista desde la interfaz. No afirmar M-01 completo ni una aceptación integral de B.
- **Asignación de permisos reales:** existe la administración, pero las facultades nuevas no se conceden automáticamente a Ventas/PDV/CEDIS. La asignación por responsables y sucursal/departamento debe completarse antes del recorrido de aceptación.
- **Fallo previo de MD:** reproducido y pendiente, como se detalla arriba.
- **Avisos:** enviar ya conecta el servicio y su filtro; no hubo prueba de entrega real entre dos cuentas ni del dispositivo receptor.
- **Cambios de solicitud ya enviada:** ahora se rechaza el cambio silencioso de destino/costo/consignatario. La reapertura permite revisar mercancía; no se inventó una operación nueva para reescribir datos comerciales enviados. Si el negocio requiere esa operación, debe definir su alcance y trazabilidad.

### Riesgos potenciales sin reproducción de fallo

- Falla de almacenamiento o rollback posterior a `store`: el archivo podría quedar huérfano; no se simuló una falla de disco. El contenido permanece privado para identificaciones.
- Los reportes conservan snapshots de la validación. Si la remisión se carga después, el indicador basado en `metadata_snapshot.remision_documento_id` puede reflejar la ausencia **en ese momento**, aunque el detalle consulte la remisión actual. No se reescribió el cierre financiero ni se decidió cambiar su semántica histórica. Verificar el indicador y decidir si debe etiquetarse como snapshot o acompañarse de referencia actual; esta auditoría no acredita esa conciliación temporal.
- La fórmula municipal usa cobertura exacta a centavos, mientras el cálculo general permite su tolerancia configurada. Se probaron montos completos e insuficiencia de 30, no el borde de tolerancia en una validación municipal; no se cambió la regla contable.
- Un borrador con la misma identidad conserva la referencia inicial aunque después cambie el documento de su expediente. Falta validar con negocio cómo debe elegirse explícitamente otra versión **de esa misma identidad** sin reescribir una referencia histórica.
- No hubo estrés de consultas simultáneas en navegador, deadlocks prolongados ni prueba de recuperación tras corte de red. Hay locks e idempotencia comprobada secuencialmente para consulta/respuesta/cierre; la prueba multiproceso cubre el expediente.

### Fuera de alcance

Carátula definitiva, retención/borrado permanente de identificaciones, empaque, bolsas, traslados, salida municipal, terminal, cobro posterior P-01, M-02 a M-12 y reglas nuevas de crédito/cancelación. No se generan remisiones de Wizerp ni se declara entregada mercancía por responder o cerrar consulta.

## 9. Guion exacto de aceptación manual restante

1. **Permisos:** con usuarios de Ventas, CEDIS, PDV de dos sucursales y auxiliar, asignar solo sus facultades necesarias. Comprobar cola/detalle/respuesta, identificaciones e históricos concedidos y denegados. No usar Super Admin para acreditar restricciones.
2. **Alta visual nueva:** registrar desde la entrada municipal real con cliente, folio/documento Wizerp y almacén; guardar, cerrar y reabrir desde Registro y detalle. Verificar que no abra el formulario legado ni cree una tarea de apartado. Repetir con un municipal histórico no marcado y un nacional para comprobar la compatibilidad visual.
3. **CEDIS:** enviar desde los botones del formulario, localizar en bandeja, abrir el PDF/visor, responder una vez y verificar aviso a Ventas. Reintentar envío/respuesta y verificar que no desaparezca la revisión.
4. **Excepciones:** faltante sin SKU, detalle con foto, retiro y sustitución por catálogo/captura. No permitir confirmar sin existencias. Resolver, obtener respuesta actualizada y cerrar **desde el botón de Ventas**. Comprobar actualización del estado al reabrir y ausencia de cobro/remisión mientras sigue abierta.
5. **Costo conocido:** capturar mercancía y flete en la entrada visual prevista. Registrar importe insuficiente, comprobar bloqueo, completar, enviar a auxiliar, verificar pago, asociar número/PDF Wizerp y consultar el reporte. Esta conexión visual de B sigue pendiente.
6. **Jaguar:** repetir con transporte diferido, leyenda PAGADO y `costo_envio=null`. Debe remisionarse mercancía verificada y seguir pendiente el flete. Revisar detalle, subtotal del periodo y exportación CSV; no debe mostrarse gratuito ni transporte liquidado.
7. **Histórico:** desde Clientes editar teléfono y sustituir identificación, abrir un pedido previo y comprobar que conserva su referencia. Dar de baja y comprobar la descarga histórica solo con alcance/referencia o permiso histórico. Elegir una versión nueva para otro pedido sin modificar el anterior.
8. **Avisos y visibilidad:** comprobar recepción efectiva entre cuentas y que PDV de otra sucursal no reciba ni pueda responder la consulta. Revisar escritorio/móvil y teclado con datos largos; escáner/cámara física si se usarán.

## 10. Recomendación C / A / B

| Incremento | Recomendación |
| --- | --- |
| C — Expediente | **Listo para aceptación controlada**, tras asignar permisos. CRUD, versiones y descarga tienen pruebas; el navegador ejerció datos/documentos/bajas/historial. Validar elección de otra versión y política de retención antes de ampliar su uso. |
| A — Alta y consultas | **Listo para aceptación controlada de servidor y PDV**, con pendientes visuales de alta/reapertura, CEDIS, sustitución y cierre desde su botón. No darlo por aceptado por compilar ni por las capturas de PDV. |
| B — Política financiera y remisión | **Listo para pruebas dirigidas de servidor; aceptación integral pendiente.** Conocido/Jaguar, validación, remisión y envío tienen evidencia. Falta el recorrido comercial completo en interfaz, CSV y conciliación temporal de referencias del reporte. |

No se recomienda afirmar que M-01 está terminado ni iniciar logística posterior como si estas comprobaciones manuales ya hubieran ocurrido.

## 11. Inventario inspeccionado y correcciones por archivo

La tabla siguiente incluye archivos nuevos, que `git diff` por sí solo omite. «M-01 inicial» identifica que ya contenían trabajo al abrir la auditoría. «Ampliación» identifica el archivo que esta auditoría incorporó al conjunto de diferencias. Las correcciones se detallan por sus métodos en H-01 a H-13.

| Archivo | Procedencia | Inspección / corrección |
| --- | --- | --- |
| `app/Http/Controllers/Clientes/ExpedienteClienteController.php` | M-01 inicial, sin seguimiento | H-07/H-13: contexto válido, capacidades por operación; binding de descarga en rutas. |
| `app/Http/Controllers/ControlPedidos/Municipal/ConsultaMunicipalController.php` | M-01 inicial, sin seguimiento | H-01/H-03: filtro de sucursal, request anidado compartido. |
| `app/Http/Controllers/ControlPedidos/Municipal/SolicitudMunicipalController.php` | M-01 inicial, sin seguimiento | Inspeccionado; conserva validación/presentación original. |
| `app/Models/Cliente.php` | M-01 inicial, rastreado | Inspeccionado; conserva relación inicial de consignatarios. |
| `app/Models/ClienteConsignatario.php` | M-01 inicial, sin seguimiento | Inspeccionado; conserva identidad y relaciones. |
| `app/Models/ClienteConsignatarioVersion.php` | M-01 inicial, sin seguimiento | Inspeccionado; conserva modelo de versiones de datos. |
| `app/Models/ClienteDocumento.php` | M-01 inicial, sin seguimiento | H-07: relación histórica con identidad dada de baja; ruta sigue oculta. |
| `app/Models/ClienteExpedienteEvento.php` | M-01 inicial, sin seguimiento | Inspeccionado; conserva eventos y casts. |
| `app/Services/Clientes/Expediente/GestionarExpedienteClienteService.php` | M-01 inicial, sin seguimiento | H-07/H-08/H-13: descarga, locks de identidad, versiones con bajas e historial. |
| `app/Services/ControlPedidos/AccionesDisponibilidadTiendaService.php` | Ampliación de auditoría, rastreado | H-09: decisiones documentales municipales, sin retiro físico ni cambio del flete. |
| `app/Services/ControlPedidos/CerrarConsultaPedidoBmaService.php` | M-01 inicial, rastreado | H-06: cierre municipal idempotente bajo bloqueo. |
| `app/Services/ControlPedidos/EnviarPedidoBmaService.php` | Ampliación de auditoría, rastreado | H-11: no exige dirección nacional en municipal marcado. |
| `app/Services/ControlPedidos/GestionarRemisionPedidoBmaService.php` | M-01 inicial, rastreado | H-04: política/validación en archivo y número, bajo bloqueo. |
| `app/Services/ControlPedidos/ListarPedidosCedisService.php` | M-01 inicial, rastreado | Inspeccionado; conserva inclusión municipal inicial y alcance CEDIS existente. |
| `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php` | M-01 inicial, sin seguimiento | H-02/H-06/H-13: actor/fase, datos, almacén, reenvío y notificación. |
| `app/Services/ControlPedidos/Municipal/EtiquetaConsultaMunicipal.php` | M-01 inicial, sin seguimiento | Inspeccionado; conserva presentador municipal. |
| `app/Services/ControlPedidos/Municipal/PoliticaFinancieraMunicipal.php` | M-01 inicial, sin seguimiento | Inspeccionado y ejercido; conserva fórmula y tres dimensiones. |
| `app/Services/ControlPedidos/Municipal/RegistrarSolicitudMunicipalService.php` | M-01 inicial, sin seguimiento | H-02/H-12: actor/fase, marca, snapshot y total de encabezado. |
| `app/Services/ControlPedidos/NotificarPedidoBmaService.php` | Ampliación de auditoría, rastreado | H-01/H-13: filtra destinatarios municipales por alcance. |
| `app/Services/ControlPedidos/ObtenerCatalogosPedidoBmaService.php` | M-01 inicial, rastreado | Inspeccionado; conserva campo permite_costo_diferido. |
| `app/Services/ControlPedidos/ReabrirConsultaPedidoBmaService.php` | Ampliación de auditoría, rastreado | H-10: invalida cierre municipal y bloquea reabrir pagado/remisionado. |
| `app/Services/ControlPedidos/ResponderDisponibilidadInicialService.php` | M-01 inicial, rastreado | H-01/H-03/H-09: alcance dentro de lock, MIME/tamaño y protección de estado financiero. |
| `app/Services/ControlPedidos/ResuelveDatosPedidoBma.php` | M-01 inicial, rastreado | Inspeccionado; conserva elección explícita frente a legado. |
| `app/Services/ControlPedidos/ValidacionCamposPedidoBma.php` | Ampliación de auditoría, rastreado | H-11: consulta municipal sustituye requisito nacional de pesaje/logística. |
| `app/Services/ControlPedidos/ValidarPagoPedidoBmaService.php` | M-01 inicial, rastreado | H-04: revalida política tras bloquear el pedido. |
| `app/Services/Reportes/PagosPedidos/CalcularMetricasReportePagosPedidosService.php` | Ampliación de auditoría, rastreado | H-05: contador de transporte pendiente y subtotal conocido. |
| `app/Services/Reportes/PagosPedidos/ExportarReportePagosPedidosCsvService.php` | Ampliación de auditoría, rastreado | H-05: texto para flete desconocido y alcance de cobertura. |
| `app/Services/Reportes/PagosPedidos/ListarReportePagosPedidosService.php` | Ampliación de auditoría, rastreado | H-05: dimensión municipal para la etiqueta. |
| `app/Services/Reportes/PagosPedidos/ObtenerDetalleReportePagoPedidoService.php` | Ampliación de auditoría, rastreado | H-05: monto visible nulo cuando desconocido y metadata separada. |
| `app/Services/Reportes/PagosPedidos/RegistrarCierrePagoPedidoService.php` | M-01 inicial, rastreado | H-04/H-05: exige consulta vigente y conserva metadata de transporte. |
| `app/Services/SaldosAFavor/CoberturaPagoPedidoBmaService.php` | Ampliación de auditoría, rastreado | H-05: expone evaluación municipal sin cambiar la fórmula general. |
| `app/Services/SaldosAFavor/RegistrarPagoPedidoBmaService.php` | M-01 inicial, rastreado | H-04: política dentro del lock y en avance comercial común. |
| `app/Support/ControlPedidos/AlcanceConsultaMunicipal.php` | Ampliación de auditoría, nuevo | H-01: nueva política común de autorización por área/alcance real. |
| `app/Support/ControlPedidos/ConsultaMunicipal.php` | M-01 inicial, sin seguimiento | H-10: bloqueo/estado coherente con actualización pendiente. |
| `app/Support/ControlPedidos/VisibilidadPedidoBma.php` | Ampliación de auditoría, rastreado | H-01: lectura del operador municipal autorizado. |
| `database/migrations/2026_11_14_120000_create_cliente_expediente_tables.php` | M-01 inicial, sin seguimiento | Inspeccionada y aplicada en SQLite/MySQL exclusivo; no editada. |
| `database/seeders/data/permisos_actuales.php` | M-01 inicial, rastreado | Inspeccionado; conserva catálogo M-01 original. |
| `resources/js/Pages/Admin/Clientes.jsx` | M-01 inicial, rastreado | Inspeccionado; conserva enlace inicial al expediente. |
| `resources/js/Pages/Admin/ExpedienteCliente.jsx` | M-01 inicial, sin seguimiento | Inspeccionado; conserva página y servicio compartido. |
| `resources/js/Pages/ControlPedidos/Cedis/Index.jsx` | M-01 inicial, rastreado | Inspeccionado; conserva entrada municipal al panel CEDIS. |
| `resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx` | M-01 inicial, rastreado | Inspeccionado; conserva botón municipal inicial. |
| `resources/js/Pages/ControlPedidos/Municipal/ConsultasTienda.jsx` | M-01 inicial, sin seguimiento | H-09: reemplaza respuesta automática por panel común con excepciones. |
| `resources/js/Pages/ControlPedidos/Municipal/ExpedienteConsignatario.jsx` | M-01 inicial, sin seguimiento | H-13: edición/bajas/históricos por permiso y errores de captura. |
| `resources/js/Pages/ControlPedidos/Municipal/FormularioRegistrarMunicipal.jsx` | M-01 inicial, sin seguimiento | H-09/H-12: actualización de respuesta y acciones de excepciones; preserva paquetería local. |
| `resources/js/Pages/ControlPedidos/Municipal/SeguimientoSolicitudMunicipal.jsx` | M-01 inicial, sin seguimiento | Inspeccionado; conserva presentación inicial y botón de cierre. |
| `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx` | M-01 inicial, rastreado | Inspeccionado y regresión dirigida; conserva separación por marca municipal. |
| `resources/js/Pages/Reportes/PagosPedidos/Partials/MetricasPagosPedidos.jsx` | Ampliación de auditoría, rastreado | H-05: aviso de subtotal conocido y transportes pendientes. |
| `resources/js/Pages/Reportes/PagosPedidos/Partials/ResumenFinancieroPedido.jsx` | Ampliación de auditoría, rastreado | H-05: flete pendiente de determinar y alcance del cierre. |
| `resources/js/Pages/Reportes/PagosPedidos/Partials/pagosPedidosStyles.js` | Ampliación de auditoría, rastreado | H-05: etiqueta de cobertura con transporte pendiente. |
| `routes/web/admin/clientes.php` | M-01 inicial, rastreado | H-07: descargas históricas con binding limitado a la ruta. |
| `routes/web/modules/control-pedidos/core.php` | M-01 inicial, rastreado | H-07: descarga histórica desde pedidos; conserva rutas nuevas originales. |
| `scripts/qa/m01-auditoria/README.md` | Ampliación de auditoría, nuevo | Guion/configuración/evidencia reproducible de esta auditoría. |
| `scripts/qa/m01-auditoria/concurrencia.php` | Ampliación de auditoría, nuevo | Guion/configuración/evidencia reproducible de esta auditoría. |
| `scripts/qa/m01-auditoria/fixtures.php` | Ampliación de auditoría, nuevo | Guion/configuración/evidencia reproducible de esta auditoría. |
| `scripts/qa/m01-auditoria/limpiar-archivos.php` | Ampliación de auditoría, nuevo | Guion/configuración/evidencia reproducible de esta auditoría. |
| `scripts/qa/m01-auditoria/navegador.mjs` | Ampliación de auditoría, nuevo | Guion/configuración/evidencia reproducible de esta auditoría. |
| `scripts/qa/m01-auditoria/phpunit-mysql.xml` | Ampliación de auditoría, nuevo | Guion/configuración/evidencia reproducible de esta auditoría. |
| `tests/Feature/ControlPedidos/MunicipalOrdinarioM01Test.php` | M-01 inicial, sin seguimiento | 2 escenarios originales ampliados a 19; restricción previa de base de pruebas. |

Además se inspeccionaron dependencias de alcance: `User`, `Sucursal`, `Almacen`, `AlmacenPdv`, `PedidoBma`, `PedidoBmaPago`, `Cliente`, `CatalogoPaqueteriaPedido`, `CatalogoOrigenPedido`, `ActualizacionConsulta`, `ListarPedidosBmaService`, `AprobarPedidoBmaService`, `DisponibilidadTiendaController`, `PedidoBmaCedisController`, `PedidoBmaAuditoriaController`, `PedidoBmaSaldosPagosController`, `ResponderDisponibilidadInicialRequest`, `ResolverAlmacenesConsultaCedisService`, `AdminController`, las rutas de auditoría, `config/filesystems.php`, `phpunit.xml`, `tests/TestCase.php`, las pruebas dirigidas enumeradas y los componentes/tokens compartidos referidos. No se examinó todo el código ajeno a estos recorridos.

Documentos iniciales preservados: `M01_IMPLEMENTACION_REPORTE_DETALLADO.md` y `PROCESO PARA PEDIDOS.md`. Nuevos artefactos: este reporte, los logs de `evidencias-m01-auditoria/`, los guiones/configuración de `scripts/qa/m01-auditoria/` y seis capturas más resultado JSON en `resources/design-references/m01-auditoria/`.

## 12. Estado final del entorno y árbol de trabajo

- HEAD y rama conservados; índice sin cambios preparados. Se conservan todos los archivos iniciales, incluidos los no rastreados.
- Los archivos fuente nuevos siguen sin seguimiento; ningún resultado depende de un commit o push. No se incluyen `.env`, credenciales operativas, base de datos ni documentos reales.
- 31 archivos rastreados modificados respecto de HEAD; `git diff --stat` omite los nuevos. El estado final se conserva en `evidencias-m01-auditoria/16-git-final.txt`; el inventario anterior expande los directorios nuevos.
- Build publicado con usuario `sail`; `public/hot` ausente. No se inició Vite.
- No se detuvo ni reinició ningún contenedor o servicio de uso. Se cerraron exclusivamente el Laravel temporal 9193 y su sesión Selenium.
- Se conserva la base exclusiva `codex_m01_audit_20261114` con fixtures ficticios. Las pruebas la reconstruyen; no usarla como base de aplicación compartida.
- Los uploads del navegador ejercieron los discos configurados con nombres aleatorios. Se retiraron después **cuatro binarios ficticios**, por sus registros exclusivos y hash de la imagen sintética del primer recorrido; no se borraron carpetas ni documentos reales. Las capturas y JSON permanecen en el repositorio. Para repetir descargas en esa base hay que reconstruir fixtures. Los binarios de concurrencia permanecen en `/tmp` del contenedor.
- `git diff --check` aprobado. No se declara la suite completa aprobada: permanece el fallo MD previo documentado.

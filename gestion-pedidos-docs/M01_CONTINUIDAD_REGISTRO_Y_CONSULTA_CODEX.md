# Continuidad del registro municipal y consulta CEDIS

17 de noviembre de 2026, Ciudad de México. Trabajo sobre `/home/ti-admin/gelianv`, rama `develop`, HEAD `52208b5028e42119c9d8fbb6acb6be2ce7697dfd`. Sin pull, merge, reset, rebase, commit ni push.

## 1. Estado inicial y alcance

El árbol inicial tenía **63 entradas de estado Git**, entre archivos rastreados modificados y directorios/archivos sin seguimiento. Ya estaban implementados servicios municipales, expediente, políticas financieras y correcciones de auditoría. Se preservaron los cambios previos y los reportes originales. El inventario inicial está en [01-git-inicial.txt](evidencias-m01-continuidad/01-git-inicial.txt).

Se leyeron AGENTS, las guías de diseño/evidencia, el procedimiento y análisis V02.1, los tres reportes municipales anteriores y las decisiones de Tienda/consulta compartida. Se contrastaron con código y pruebas. La solicitud actual **sustituye la selección por Sin logística** documentada en el refinamiento anterior: el registro municipal nuevo selecciona terminales activas de categoría Local / Regional.

Se corrigió el registro y los pasos de consulta; no se desarrollaron fases físicas posteriores, carátula definitiva ni un nuevo panel financiero. PHP, pruebas, npm y build se ejecutaron mediante Sail. La base de navegador fue exclusivamente `codex_m01_audit_20261114`; las pruebas generales usaron SQLite en memoria y ambos archivos de caché aislados. No se modificaron bases operativas ni se reiniciaron servicios compartidos.

## 2. Causa raíz de la discontinuidad

`ModalFormPedido` cambiaba de componente después de elegir Municipal. La captura común guardaba parcialmente el pedido y se desmontaba. `FormularioRegistrarMunicipal` recuperaba algunos datos, pero solo mostraba cliente/folio como contexto y documentos ya existentes: no tenía la captura común ni la carga de Wizerp. Por tanto, elegir Municipal antes de adjuntar el archivo dejaba sin esa acción al usuario.

Además, la fusión del autoguardado común no conservaba `expediente`, `finanzas_municipales` ni `consulta_municipal`. Conectar visualmente los campos sin corregir esa escritura habría vuelto a perder la solicitud al corregir el folio o guardar un documento.

El servicio de envío municipal tampoco exigía previamente folio, departamento y soporte comercial vigente. Las pruebas anteriores podían enviar una consulta sin documento y no demostraban el registro completo desde los controles reales.

## 3. Integración de datos comunes y municipales

Se conserva `FormularioRegistrarTienda` como contenedor de la captura actual por compatibilidad con sus consumidores. Ahora compone también Municipal: mantiene los mismos estados, búsqueda/selección de cliente, departamento automático, folio Wizerp, modalidad, carga/visor del documento y cola de guardado. Elegir Municipal no lo desmonta. Nacional continúa con su transición anterior al formulario legado.

`FormularioRegistrarMunicipal` aporta mediante una función de composición sus secciones específicas y las acciones de pie. Sus servicios siguen separados de Tienda/Nacional. No se copió Registrar Pedido ni se convirtió `ModalFormPedidoLegado` en el módulo municipal.

Orden visible: datos del pedido; documento; destino/procedencia; consignatario y condiciones de transporte; respuesta/evidencia y seguimiento. El pie mantiene Guardar solicitud y Enviar consulta. Agregar consignatario conserva el tratamiento secundario. Se reutilizan las clases de Registrar Pedido, paneles, tokens, iconos, controles y visor; no se agregaron CSS globales, fuentes ni colores particulares.

Guardar solicitud confirma primero los datos comunes y luego los municipales. Una validación municipal fallida conserva los datos comunes que ya se guardaron y muestra el error; no finge una solicitud completa. Los borradores municipales específicos conservan el almacenamiento por usuario/pedido; no se escribe una clave para pedidos todavía sin identificador. Se bloquea el cierre durante las operaciones en curso. Los campos del pedido quedan de consulta después del envío. El expediente puede mantener sus versiones mediante sus facultades propias; ello no reemplaza silenciosamente el snapshot seleccionado por el pedido.

La fusión conserva las claves municipales **desde el estado persistido**, nunca desde valores enviados por el cliente del autoguardado. La prueba intenta falsificar un estado cerrado y una identidad distinta y comprueba que no se incorporan. Se conserva el control de revisión/huella de la captura común.

## 4. Servicios, persistencia y reglas comerciales

- Registro municipal: se conserva la comprobación de creación, propietario, fase editable, terminal válida, consignatario vigente del cliente, costo conocido positivo, costo diferido y POR COBRAR según catálogo. No se reescribió su versionado.
- Primer envío: exige cliente, departamento, folio Wizerp y PDF/captura comercial **activo**, además de la solicitud municipal guardada. Revalida terminal activa Local / Regional y pertenencia/vigencia del consignatario. No exige pago, remisión, pesaje ni captura individual del conjunto correcto.
- Reenvío: se conserva el retorno idempotente al mismo destino, sin borrar respuesta ni cambiar almacén. Se mantienen bloqueo de fila, permisos y alcance de departamento/sucursal.
- Registro común después del envío: rechaza cambios de cliente, número, folio y departamento; impide limpiar/cambiar la modalidad enviada.
- Respuesta y decisiones: se mantienen servicios existentes, revisión vigente, claves de ajuste y retiro documental. No se registra retiro físico ni inventario inexistente.
- Nueva revisión: el formulario ofrece Solicitar nueva revisión después del cierre. Reutiliza el servicio existente y bloquea nuevamente el cierre hasta la respuesta vigente. Los avisos de ajustes y reapertura ahora utilizan el área municipal real: CEDIS o la bandeja municipal de Tienda.
- Finanzas: se conservan las políticas de pago/remisión. Se encontró además Anexar pago ofrecido desde el listado en transporte diferido antes del cierre. Se suprimió esa acción en consulta municipal abierta y se protegió la capacidad del modelo. La ruta alternativa de anexo comprueba la política municipal dentro del bloqueo antes de guardar el comprobante. No se desarrolló P-01 ni se redefinió la cobertura.

El seguimiento y el badge del registro municipal distinguen Consulta pendiente: CEDIS/Tienda, Consulta respondida, Excepciones pendientes y Consulta cerrada. No se renombraron globalmente las fases heredadas PESAJE_* ni los estados de otros módulos.

## 5. PDF/capturas Wizerp y mercancía

Se reutiliza la carga autenticada `control_pedidos.pdf_pedido.store`, su validación de PDF/JPG/PNG/WEBP, límite de 10 MB, representación documental y visor existente. Antes de cargar se confirma también cualquier cambio común pendiente. El soporte se conserva al cerrar, recargar Registro y reabrir el pedido; CEDIS abre el mismo archivo mediante Ver pedido. El envío inicial rechaza un soporte ausente o inactivo.

No se agregó OCR, catálogo paralelo ni captura obligatoria de todas las piezas. El documento comercial representa el conjunto solicitado. CEDIS responde el conjunto y captura únicamente excepciones mediante catálogo/escaneo o descripción manual. Se reutiliza `PanelRespuestaDisponibilidad`, con evidencia general y fotos de las piezas con detalle según sus reglas. Ventas ve evidencia y excepciones desde el registro; utiliza `AccionesRapidasDisponibilidad` para confirmar/retirar/sustituir y el cierre explícito existente. Una respuesta sin excepciones no cierra automáticamente la consulta ni acepta al cliente.

## 6. Análisis de Sin logística

La columna es `catalogo_paqueterias_pedido.sin_logistica`, introducida por la migración **2026_10_19_161800**, presente en HEAD junto con su modelo y administración. Por tanto, **precede al refinamiento municipal** y tiene consumidores legítimos ajenos al selector nuevo.

| Consumidor | Dependencia conservada |
| --- | --- |
| `CatalogoPaqueteriaPedido::sinLogistica` | Regla histórica independiente de la categoría. |
| `ResuelveDatosPedidoBma`, `ValidacionCamposPedidoBma`, `EnviarPedidoBmaService`, elegibilidad de pesaje | Requisitos legados de guía, dirección y peso; no se reclasificaron. |
| `ModalFormPedidoLegado`, `pedidosBmaStyles` | Presentación/validaciones de pedidos anteriores y modalidades de consulta. |
| `PrioridadAtencionPedidosConfig` | Identificación de municipal legado sin marca de modalidad. |
| `ComisionesEmpaque/Index`, `ComisionesEmpaqueController`, `GestionarUnidadComisionEmpaqueService` | Configuración de logística de las unidades de comisión. |
| Catálogos y `ObtenerCatalogosPedidoBmaService` | Campo administrativo y payload compatibles con los consumidores anteriores. |

Se retiró únicamente su utilización como clasificación del registro municipal nuevo. No se borró la columna, no se migraron sus valores y no se eliminó su control necesario. Para reducir confusión, el control administrativo se llama **Omitir guía y domicilio en pedidos anteriores**, con ayuda que aclara su independencia respecto de Local / Regional. La tabla muestra Omite guía y domicilio. Las fórmulas de tarifas permanecen iguales.

## 7. Regla definitiva de terminales y municipio histórico

Frontend `terminalesMunicipales` y backend `habilitadaParaRegistroMunicipal` coinciden: **activa + categoría `local_regional`**. No dependen de `sin_logistica`, de permitir costo diferido ni de las banderas de habilitación municipal del recorrido anterior. El registro viejo conserva `habilitadaParaEnvioMunicipio` y sus reglas.

Se probaron Local / Regional activa sin Sin logística, Comercial marcada Sin logística y con diferido, inactiva e inexistente. Las opciones financieras siguen dependiendo de sus respectivas configuraciones. No se asignaron categorías por nombres como Cardesa, FedEx o Estafeta: Cardesa aparecerá si su fila real es activa y Local / Regional; una comercial queda excluida por su categoría. No se alteró el catálogo operativo.

La referencia de destino es `catalogo_paqueteria_id`; se lee el nombre de su relación. `municipio_destino` no se pide, no se rellena con una terminal ni con un municipio inventado. El valor histórico se conserva durante el guardado común/municipal, sin migraciones ni actualización de pedidos anteriores.

## 8. Expediente y seguridad

Se reutiliza `ExpedienteConsignatario`, sus rutas y servicios. El recorrido real creó una identidad contextual, abrió/canceló su edición, cargó identificación y la sustituyó: versión 2. El guardado del pedido conserva `consignatario_id`, `version_datos_id` y `documento_id`; la lectura final verificó la referencia elegida.

Identificaciones permanecen privadas, con permisos separados y referencias históricas. No se movieron a almacenamiento público ni se reescribió su backend. Las pruebas municipales conservan los rechazos de áreas/sucursales ajenas, descarga sin facultad, documentos de otro cliente, acceso histórico, versionado, respuestas inválidas y cierre/pago/remisión prematuros. El navegador usó un usuario **sintético con facultades de Ventas y CEDIS**; no se presenta como aceptación con cuentas operativas de dos personas.

## 9. Pruebas ejecutadas y resultados

| Comprobación | Resultado y alcance |
| --- | --- |
| Tarifas locales | **8 aprobadas, 14 aserciones**: tarifa fija, peso kg/g, comercial, POR COBRAR, guía de cliente y override manual. [Log](evidencias-m01-continuidad/13-tarifas.txt). |
| Backend final SQLite | **45 aprobadas, 378 aserciones**: MunicipalOrdinarioM01Test, ElegibilidadPesajePedidoTest y ControlPedidosEnvioDiferidoFase1Test. [Log](evidencias-m01-continuidad/02-backend-final.txt). |
| MySQL exclusivo | **24 municipales, 293 aserciones**, aprobadas. Esta pasada precede a los últimos ajustes de documento activo/anexo de envío/avisos; estos se comprobaron con el lote final SQLite y la interfaz MySQL aplicable. [Log](evidencias-m01-continuidad/03-mysql.txt). |
| Frontend de persistencia/concurrencia | **17 aprobadas**, incluido el componente municipal. [Log](evidencias-m01-continuidad/04-frontend-regresiones.txt). |
| Frontend final | **24 aprobadas**: captura integrada Municipal/Nacional, selección de formulario actual/histórico, registro municipal, confirmación de Tienda y reglas de selección de captura. Incluye 4 pruebas que coinciden con el lote anterior; no sumar ambos como casos distintos. [Log](evidencias-m01-continuidad/05-frontend-final.txt). |
| Regresión ampliada de Tienda | **116 aprobadas y 2 fallidas**, 1426 aserciones, en el lote que incluyó SolicitudRegistrarTiendaTest. Los mismos dos fallos se reprodujeron con las clases previas cargadas desde `/tmp`, sin sustituir archivos del repositorio. [Lote](evidencias-m01-continuidad/07-regresion-tienda-ampliada.txt), [contraste previo](evidencias-m01-continuidad/06-tienda-fallos-previos.txt). |
| Build final | Publicado; manifiesto completo y archivos verificados. Solo advertencia de chunks grandes. [Log](evidencias-m01-continuidad/08-build.txt). |
| Revisión del árbol | `git diff --check` aprobado. Sin `public/hot`, sin Vite. |
| Style guard JSX | No completado: falta `docs/style-system/baseline/jsx-hex-allowlist.tsv`. No se regeneró baseline ni se ocultó el fallo. [Salida](evidencias-m01-continuidad/11-style-guard.txt). |

Los dos fallos anteriores de Tienda son `test_el_faltante_queda_pendiente_del_cliente_y_no_se_acepta` (espera cerrar inmediatamente tras retiro y recibe 422) y `test_una_foto_ya_guardada_se_reconoce_sin_volver_a_cargarla` (id de documento esperado distinto). No se modificaron sus reglas ni sus aserciones para ponerlos en verde.

## 10. Navegador real y evidencia

Laravel temporal en 9193, React/CSS del build real, Chromium/Selenium y datos ficticios. Se utilizaron los controles del producto para guardar, adjuntar, abrir visores, responder, retirar y cerrar; **no se ejecutaron operaciones de negocio mediante Axios directo desde el guion**. El interceptor de Axios registró únicamente respuestas de red producidas por esos controles.

El [resultado principal](../resources/design-references/m01-continuidad/resultado.json) acredita:

1. Nuevo pedido → búsqueda y selección exacta de cliente → folio → Municipal sin perder campos ni departamento automático.
2. Terminal Local / Regional, almacén CEDIS, transporte diferido y alta contextual de consignatario; abrir/cancelar edición sin perder la captura.
3. Carga y sustitución de identificación, luego carga de imagen Wizerp y apertura de Ampliar.
4. Guardar solicitud → cerrar → recargar Registro → reabrir: cliente, folio, modalidad, documento, terminal, almacén, consignatario y condiciones conservados.
5. Enviar consulta con su botón; localizar **el mismo folio único** en CEDIS y abrir el documento en Ver pedido.
6. Responder faltante manual sin SKU y evidencia; Ventas ve ambos y no dispone de Cerrar consulta.
7. Retirar y confirmar retiro; CEDIS recibe la actualización, responde sin excepciones y Ventas cierra mediante Cerrar consulta.
8. Recargar/reabrir conserva el cierre; no se convirtió el flete diferido en cero ni en liquidado.

El [resultado de nueva revisión](../resources/design-references/m01-continuidad/reapertura.json) acredita la acción Solicitar nueva revisión, bloqueo del cierre con la respuesta anterior, nueva respuesta CEDIS y segundo cierre. Su primera ejecución verificó la reapertura y se interrumpió por comillas de un selector del guion; tras corregirlo se continuó la revisión ya solicitada. El JSON declara `reanudo_revision_previamente_solicitada: true`.

La [prueba de validación](../resources/design-references/m01-continuidad/validacion.json) muestra un error servidor en la solicitud incompleta, el botón Enviar consulta deshabilitado y el cierre habilitado después de finalizar la operación. Su único error de red de consola es el **422 esperado**; no hubo excepción JavaScript. Los recorridos principal y de nueva revisión terminaron con consola vacía.

Anchos comprobados **1440, 1024 y 390**, claro/oscuro: sin desbordamiento de página, diálogo ni cuerpo; pie de acciones visible. Se inspeccionaron imágenes de escritorio y móvil, documento CEDIS y seguimiento. Las capturas están en [m01-continuidad](../resources/design-references/m01-continuidad/):

- `registro-{1440,1024,390}-{light,dark}.png`.
- `documento-cedis-1440-dark.png`, `respuesta-cedis-1440-dark.png`, `faltante-ventas-1440-dark.png`.
- `consulta-cerrada-1440-light.png`, `nueva-revision-1440-dark.png`, `cierre-final-390-dark.png`, `validacion-390-light.png`.

La [lectura final de persistencia](evidencias-m01-continuidad/09-persistencia-final.json) verifica el pedido **42 / WIZ-CONTINUIDAD-38**: cliente/departamento, terminal/almacén, expediente versión 2, soporte, cierre, respuesta final sin excepciones y flete diferido. También confirma ausencia de pago verificado, remisión, empaque, entrega, tareas de Tienda y municipio inventado.

## 11. Incidencias de comprobación y correcciones

El aislamiento del host falló antes de ejecutar comandos (`bwrap: loopback: Failed RTM_NEWADDR`); las operaciones se ejecutaron con la autorización del entorno, conservando Sail. No se sustituyeron PHP/npm por los del host.

Los primeros fixtures excedían la longitud del código de departamento; luego se ajustó su unicidad. La búsqueda existente de clientes busca números cuando empiezan por dígito: se usaron números sintéticos numéricos y selección de coincidencia exacta. También se corrigieron esperas de hidratación/carga de imagen, limpieza del input de archivo para sustituir la misma imagen ficticia, elección de la acción Continuar y del Retirar habilitado, y un selector con comillas mal escapadas.

Los folios iguales entre intentos hacían que CEDIS abriera otro pedido de prueba, ya que el usuario sintético tenía acceso a toda su bandeja. Se cambió a folios únicos por usuario y se registró el endpoint del pedido correcto. Los intentos fallidos no se presentan como aprobados; la evidencia principal conservada corresponde al recorrido correcto.

Durante estas comprobaciones se detectaron y corrigieron la acción financiera prematura y el destino incorrecto de avisos de ajustes municipales. No se usaron datos reales para resolver los fallos del guion.

## 12. Inventario de código modificado en esta intervención

Los archivos indicados como previos sin seguimiento ya estaban presentes al iniciar; no se atribuye su creación a esta intervención.

| Archivo | Cambio propio |
| --- | --- |
| `app/Models/ControlPedidos/CatalogoPaqueteriaPedido.php` | Elegibilidad municipal por activa + Local / Regional. |
| `app/Models/ControlPedidos/PedidoBma.php` | Bloqueo del anexo financiero municipal antes de cierre vigente. |
| `app/Services/ControlPedidos/ActualizarPedidoBmaService.php` | Protección de datos comunes enviados. |
| `app/Services/ControlPedidos/FusionarSolicitudPreparacionPedido.php` | Conservación de claves municipales persistidas. |
| `app/Services/ControlPedidos/PersistirIntencionPreparacionPedido.php` | Rechazo de limpieza/cambio de modalidad enviada. |
| `app/Services/ControlPedidos/AnexarPagoEnvioPedidoBmaService.php` | Política municipal bajo bloqueo antes del anexo. |
| `app/Services/ControlPedidos/AccionesDisponibilidadTiendaService.php` | Avisos según área municipal real. |
| `app/Services/ControlPedidos/ReabrirConsultaPedidoBmaService.php` | Aviso al reabrir municipal. |
| `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php` (previo sin seguimiento) | Datos/documento vigentes y consignatario antes del primer envío. |
| `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx` | Reapertura de municipal marcado en captura común; histórico y Nacional legados. |
| `resources/js/Pages/ControlPedidos/Partials/FormularioRegistrarTienda.jsx` | Composición común con secciones/pie municipal, carga coherente y bloqueo durante operaciones. |
| `resources/js/Pages/ControlPedidos/Municipal/FormularioRegistrarMunicipal.jsx` (previo sin seguimiento) | Secciones/pie integrados, guardado común previo, evidencia, nueva revisión y seguimiento. |
| `resources/js/Pages/ControlPedidos/Municipal/SeguimientoSolicitudMunicipal.jsx` (previo sin seguimiento) | Estado vigente, excepciones y solicitud de nueva revisión. |
| `resources/js/Pages/ControlPedidos/Municipal/terminalMunicipal.js` (previo sin seguimiento) | Selector Local / Regional. |
| `resources/js/Pages/ControlPedidos/Partials/pedidosListadoUi.js` | Etiquetas municipales y ocultación de pago anticipado. |
| `resources/js/Pages/Admin/Partials/Catalogos/TablaPaqueteriasPedido.jsx` | Nombre y ayuda del control histórico de omisión de guía/domicilio. |
| `tests/Feature/ControlPedidos/MunicipalOrdinarioM01Test.php` (previo sin seguimiento) | Precondiciones documentales, categorías, continuidad del autoguardado y bloqueos. |
| `resources/js/Pages/ControlPedidos/Municipal/registroMunicipal.test.jsx` (previo sin seguimiento) | Contrato compuesto y criterio de terminales. |
| `resources/js/Pages/ControlPedidos/Municipal/continuidadRegistro.test.jsx` (nuevo) | Captura montada Municipal/Nacional. |
| `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.seleccion.test.jsx` | Reapertura de marcado frente a histórico. |

Archivos de QA nuevos, bajo `scripts/qa/m01-continuidad/`: `README.md`, `fixtures.php`, `navegadorComun.mjs`, `navegador.mjs`, `reapertura.mjs`, `validacion.mjs`, `verificar-persistencia.php` y `limpiar-archivos.php`. Se agregan este reporte, los logs de `gestion-pedidos-docs/evidencias-m01-continuidad/` y las capturas/JSON enumerados en `resources/design-references/m01-continuidad/`. El build publicado conserva sus assets anteriores; los archivos generados no se añadieron al índice de Git.

## 13. Regresiones, límites y cierre

Se comprobó en backend el alcance de áreas/sucursales, idempotencia, evidencia inválida, excepciones, reapertura, políticas de pago/remisión y conservación del diferido. Se comprobaron con componentes montados la confirmación de Tienda, la continuación Nacional y la elección de formulario legado para municipal sin marca. No se alteraron tarifas ni reglas nacionales para obtener pruebas verdes.

**Límites explícitos:** navegador con imagen PNG ficticia; no se recorrió un PDF binario en este lote, aunque conserva la carga/visor y validación existentes. No se ejerció escáner/cámara física ni sustitución por catálogo en navegador. La descarga privada de identificación y sus rechazos se acreditan por las pruebas de servidor; en navegador se comprobaron carga/sustitución y versiones. Las entregas de notificaciones entre cuentas operativas y la asignación real de permisos quedan para aceptación operativa. El usuario de navegador combinó facultades de Ventas/CEDIS; los rechazos de actores ajenos se probaron por endpoints. Nacional y municipal histórico no tuvieron un recorrido autenticado completo de navegador en esta entrega. La administración del catálogo se revisó en código y build; no se editó un catálogo real desde navegador. Persisten los dos fallos anteriores de Tienda y el baseline de estilos ausente.

Se retiraron **25 binarios sintéticos** mediante relación y hash exacto después de guardar evidencia; [limpieza](evidencias-m01-continuidad/10-limpieza.json). Los registros permanecen exclusivamente en la base de auditoría; sus descargas requieren recrear fixtures. Se cerró solo el Laravel temporal 9193 y sus workers. Los contenedores compartidos continúan activos. No hubo migraciones operativas, datos reales, nuevas fases, commit ni push.

El estado final tiene **47 archivos rastreados modificados y 28 entradas sin seguimiento** (los directorios agrupan varios archivos); el índice permanece sin cambios preparados. El estado final de Git se conserva en [12-git-final.txt](evidencias-m01-continuidad/12-git-final.txt). Las modificaciones previas y los archivos sin seguimiento se mantienen. La reproducción está en [README de QA](../scripts/qa/m01-continuidad/README.md).

## Corrección posterior: identificación del consignatario — 17 de noviembre

El usuario reportó `Unable to create a directory at .../storage/app/private/clientes/expediente/...` y un control de archivos sin aspecto de botón. Se confirmó que `storage/app/private/clientes` y sus 17 directorios descendientes pertenecían a root con modo 700, mientras el servidor activo ejecutaba PHP como sail. Las comprobaciones anteriores con un Laravel QA como root no acreditaban esos permisos del servidor de uso.

Se corrigió únicamente el propietario de los directorios de clientes pertenecientes a root, a sail:sail, sin ampliar permisos ni mover identificaciones a público. Se conservaron los modos 700. La comprobación nueva levantó Laravel como **sail**, igual que el servidor activo, sobre la base exclusiva de auditoría. El README de QA ahora exige ese usuario para los procesos que escriben archivos privados.

Cambios puntuales:

- `ExpedienteConsignatario.jsx`: botones temáticos con iconos para Adjuntar/Sustituir identificación, Editar datos y Descargar; input nativo oculto, estado Subiendo identificación, bloqueo mientras carga, reintento y confirmación en español. Alta contextual con título Nuevo consignatario, Guardar consignatario y Cancelar. No cambia los permisos ni las versiones seleccionadas por un pedido.
- `GestionarExpedienteClienteService.php`: convierte fallos de almacenamiento en un mensaje de validación en español; conserva la identificación vigente y los datos del consignatario. El error técnico se registra en servidor y no se muestra su ruta en el formulario.
- Se añadió la prueba de fallo de escritura a `MunicipalOrdinarioM01Test.php` y dos pruebas del componente en `expedienteConsignatario.test.jsx`. Se añadió el guion real `scripts/qa/m01-continuidad/consignatario.mjs`; el ayudante admite `GELIA_QA_FIXTURE`/`GELIA_QA_OUTPUT` para separar sus fixtures/evidencias. Se actualizó el guion de registro para el nuevo nombre del botón Guardar consignatario.

Resultados: **25 pruebas municipales, 304 aserciones**, y **2 pruebas del componente**, aprobadas. Build publicado, sin Vite/public/hot. Navegador real con alta contextual, botón con clic nativo de WebDriver, carga privada y sustitución de la identificación (versiones 1 y 2) como sail. Anchos 1440/1024/390, claro y oscuro, sin desbordamiento ni errores/avisos de consola en la ejecución final. [Capturas y resultado](../resources/design-references/m01-consignatario/consignatario.json), [logs](evidencias-m01-consignatario/). Se inspeccionó visualmente la captura móvil oscura. La prueba no utilizó clientes ni documentos reales.

Después de preservar evidencia se eliminaron únicamente los binarios ficticios de esa prueba por cliente de fixture, nombre y hash. Se cerraron solo el Laravel 9193 y sus workers; los servicios compartidos permanecen activos. Sin commit ni push.

## Corrección posterior: envío sin guardado manual — 17 de noviembre

Decisión del usuario: completar la captura debe permitir enviar sin pulsar Guardar solicitud por separado. El formulario municipal deja de bloquear por cambios no guardados o por ausencia de expediente persistido cuando la selección actual está completa. «Enviar consulta» confirma automáticamente la captura común y municipal y después realiza el envío sobre el id devuelto por el guardado. Se conserva Guardar solicitud como acción opcional independiente.

Se mantienen bloqueos por campos obligatorios incompletos, documento en carga, conflicto de otra sesión, cancelación y consulta enviada. Si falla cualquiera de los guardados no se envía la consulta y se conserva la captura para corregir/reintentar. Un bloqueo inmediato evita doble clic; durante la operación se deshabilitan también los controles del consignatario. No se cambiaron servicios, permisos ni validaciones de backend.

Archivos: `Municipal/FormularioRegistrarMunicipal.jsx`, `Partials/FormularioRegistrarTienda.jsx`, pruebas de `Municipal/registroMunicipal.test.jsx` y nuevo guion `scripts/qa/m01-continuidad/envio-automatico.mjs`.

Verificación: **14 pruebas frontend** aprobadas (8 municipales, 2 continuidad Municipal/Nacional y 4 confirmación de Tienda), incluyendo guardado en orden, fallo municipal, fallo de captura común y doble clic. Build publicado y `git diff --check` aprobado. Navegador real, Laravel aislado como sail, base exclusiva, datos ficticios: captura completa → Enviar consulta sin guardado manual → cierre del formulario → recarga y reapertura con folio, terminal, costo diferido y estado enviado conservados. El registro de red contiene en orden `/autoguardar`, `/municipal/45/solicitud`, `/municipal/45/consulta`. Sin errores de consola ni desbordamiento en 1440/1024/390, tema oscuro. Se inspeccionó la captura móvil. [Resultado/capturas](../resources/design-references/m01-envio-automatico/envio-automatico.json), [logs](evidencias-m01-envio-automatico/).

Se retiró únicamente el soporte sintético de este fixture después de guardar evidencia y se cerró solo el Laravel temporal con sus workers. Sin commit ni push.

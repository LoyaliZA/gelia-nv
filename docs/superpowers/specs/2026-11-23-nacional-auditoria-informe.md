# Auditoría de Envíos Nacionales 2.0 — Informe

Fecha: 23-nov-2026 · Rama `develop` (HEAD `1c0f841f`, árbol limpio al iniciar) · Spec: `2026-11-23-nacional-auditoria-design.md` · Contrato: guía V3.1.
Estados: `PASS` / `FAIL` / `BLOCKED` / `NOT_RUN`. Severidad: Crítica / Alta / Media / Baja.
Alcance de esta corrida: **solo lectura**. No se creó ningún pedido, no se guardó ningún formulario, no se modificó código.

## Resumen

- **Recorrido E2E con datos de prueba (L5):** el pedido `AUD-NAC-001` (PBMA-2026-00058, DHL, mercancía $1,000, caja 3.8 kg → 4 kg cobrados) recorrió **Ventas → CEDIS → Ventas → Auxiliar → Delegado → CEDIS** con todos los endpoints en 200, **pero no pudo marcarse como enviado**: se quedó en «Pendiente de recolección o envío» tras 4 intentos (NAC-013, **Crítica**). Antes, el cotizador se bloquea si el cliente no tiene dirección verificada y la pantalla no permite crearla (NAC-014).
- **Pruebas automáticas:** PHPUnit nacional 103 verdes / 1 roja (prueba desactualizada, anterior a Cursor). Vitest `Nacional/` y `Cedis/` 64/64. Regresión Tienda/Municipal: 155 verdes / **3 rojas** sin resolver (NAC-004). La falla municipal que Cursor dejaba abierta ya pasa.
- **Lo que sí funciona (observado en navegador):** alta nacional y envío de consulta (`solicitar-pesaje` 200), bandeja CEDIS, pesaje con redondeo 3.8→4 kg (V3.1), respuesta y notificación a Ventas, cierre de consulta, cotización con seguro y reexpedición (total $1,425 = 1,000 + 200 + 150 + 75), exhibición con comprobante, revisión del Auxiliar (validar pago, remisión, aprobar), captura de guía del Delegado y empaque en CEDIS. Consola sin errores. Las barreras de guía/salida existen en servidor.
- **Lo que está roto o incompleto:** salida nacional inalcanzable (NAC-013), sin alta de dirección en la continuación (NAC-014), 2 endpoints sin pantalla (NAC-001/002), 3 pruebas rojas de Tienda, catálogo de paqueterías con banderas sin efecto y flete capturado a mano, total sin desglose, botones secundarios sin aspecto de botón y varios textos/estados obsoletos.
- **Lo que NO pude comprobar:** dispositivo móvil real, respuesta con varias cajas/piezas identificadas, idempotencia ante doble envío, comparación detallada con la v1.0 y la separación por roles (superadmin). La emulación a 390 px no reprodujo el problema móvil del pesaje.

## Línea base

### Pruebas

| Corrida | Resultado |
| --- | --- |
| PHPUnit nacional (`Nacional\|MarcaNacionalCaptura\|OrigenEnvioNacional\|CalcularSeguro…\|PaqPendiente\|CandadoCostoEnvio\|SnapshotDireccion\|ActualizarGuias\|ElegibilidadPesaje`) | 103 pasan, 1 falla, 814 aserciones, 16 s |
| Vitest `ControlPedidos/Nacional` + `Cedis` | 11 archivos, 64 pruebas, todas pasan |
| PHPUnit regresión Tienda/Municipal (`MunicipalOrdinarioM01\|RegistrarPedidosTienda\|CorteMismoDiaTienda\|SolicitudRegistrarTienda\|ConcurrenciaCapturaPedido\|UnidadesRevisionConsulta`) | 155 pasan, 3 fallan, 1,901 aserciones |

### Rutas nacionales y su consumidor en la interfaz

Entorno Sail activo (MySQL, queue, reverb, mailpit). 228 rutas bajo `control-pedidos`. Consumidor en `resources/js` (excluyendo `ziggy.js` y pruebas):

| Ruta | Acción | Consumidor UI |
| --- | --- | --- |
| `POST {pedido}/solicitar-pesaje` | `solicitarPesaje` | `FormularioRegistrarNacional.jsx` |
| `POST cedis/{pedido}/responder-pesaje` | `PedidoBmaCedisController@responderPesaje` | `ModalResponderPesaje.jsx:1150` |
| `POST {pedido}/cerrar-consulta` | `cerrarConsulta` | `SeguimientoSolicitudNacional.jsx`, `SeguimientoSolicitudTienda.jsx` |
| `GET {pedido}/consulta-nacional` | `consultaNacional` | `SeguimientoSolicitudNacional.jsx`, `FormularioRegistrarTienda.jsx` |
| `POST {pedido}/ajuste-nacional` | `ajusteNacional` | `adaptadoresConsulta.js`, `FormularioRegistrarNacional.jsx` |
| `POST {pedido}/paqueteria-nacional` | `paqueteriaNacional` | `ContinuacionEnvioNacional.jsx`, `FormularioRegistrarNacional.jsx` |
| `POST {pedido}/cambio-tienda-nacional` | `cambioTiendaNacional` | `FormularioRegistrarNacional.jsx` |
| `POST …/custodia-nacional` (núcleo y cedis) | `custodiaNacional` | `ModalCustodiaResguardo.jsx`, `ModalDetalleCedis.jsx`, `TarjetasCedis.jsx` |
| `POST cedis/{pedido}/responder-complemento-nacional` | `responderComplementoNacional` | `ModalDetalleCedis.jsx` |
| `POST cedis/{pedido}/marcar-enviado` | `marcarEnviado` | `ModalDetalleCedis.jsx:255`, `TarjetasCedis.jsx:376` |
| `POST delegado/{pedido}/asignar-guia`, `actualizar-guia`, `guia-pdf` | Delegado | `ModalDetalleDelegado.jsx` |
| `POST {pedido}/solicitar-repesaje` | `solicitarRepesaje` | solo `ModalFormPedidoLegado.jsx:2604` (formulario histórico) |
| **`POST {pedido}/remision-caja`** | `remisionCajaNacional` | **ninguno** |
| **`POST {pedido}/cancelacion-nacional`** | `cancelacionNacional` | **ninguno** |

La mayoría de las rutas nacionales no declaran middleware de permiso en el archivo de rutas; la autorización va dentro de controlador/FormRequest (no auditada a fondo, ver *No verificado*).

### Catálogo de paqueterías (lectura en la base `gelianv`)

28 registros. FEDEX (1), ESTAFETA (2) y DHL (4): `categoria=comercial`, `sin_logistica=0`, `requiere_peso=0`, `requiere_caja=0`, `modalidad_tarifa=NULL`, `costo_seguro_default=0.00`. `PAQ. PENDIENTE` (23): `categoria=local_regional`. Todas tienen `requiere_peso=0`.

## Matriz de relevos

| Relevo (V3.1) | Cobertura | Estado | Evidencia |
| --- | --- | --- | --- |
| N-F01 1-3 Alta, importe y paquetería, enviar consulta | Alta con PDF, DHL, $1,000 y envío a CEDIS: `autoguardar` y `solicitar-pesaje` 200; pedido en «Pesaje pendiente» | `PASS` | Navegador, `AUD-NAC-001` |
| N-F01 4-7 Bandeja CEDIS, responder pesaje, Ventas cierra | Aparece en CEDIS; respuesta con 1 caja (3.8 → 4 kg) 200 «Se notificó a Ventas»; Ventas ve el resumen y cierra (`cerrar-consulta` 200). Sin piezas identificadas ni varias cajas | `PASS` (caso simple) | Navegador; `NOT_RUN` con piezas SKU/varias cajas |
| N-F01 8 Domicilio y destinatario | Sin direcciones el cotizador queda vacío y no permite crear una; hubo que usar el módulo Direcciones | `FAIL` | NAC-014 |
| N-F01 9-10 Flete, seguro, reexpedición, pago | Cotización 200 con flete manual; total correcto ($1,425) pero sin desglose; exhibición 200, `enviar` al Auxiliar 200 | `PASS` con reservas | NAC-008, NAC-009, NAC-015 |
| N-F01 10 Auxiliar verifica y remisiona | Aparece en «Revisión de pedidos»; validar pago, número y PDF de remisión y aprobar: 200 | `PASS` | Navegador |
| N-F01 11 Delegado genera guía | Aparece en «Actualizar guías» con destinatario y domicilio; asignar guía y PDF: 200. La guía queda a nivel pedido, no por caja | `PASS` con reservas | NAC-013 |
| N-F01 12-13 CEDIS empaca y marca salida | Empacar 200; Marcar enviado fallaba en 4 intentos («Cada caja que sale requiere guía vigente»). **Tras la corrección:** «Paquetería confirmada», pedido en «Enviado», caja recolectada con la guía | `PASS` (corregido) | NAC-013 |
| N-F02 `PAQ. PENDIENTE` | Excepción explícita en servidor | `PASS` por código | `PedidoBma.php:1141` |
| N-F03 Cajas, seguro por caja (N-11) | Backend y pruebas sí; sin pantalla | `FAIL` | NAC-001 |
| N-F04 Complemento | Backend, pruebas y bandeja CEDIS sí | `PASS` por código; E2E `NOT_RUN` | rutas, `ModalDetalleCedis.jsx` |
| N-14 Cancelación | Backend y pruebas sí; sin pantalla | `FAIL` | NAC-002 |
| N-10 Nacional→Tienda | Ruta con consumidor | `NOT_RUN` | `FormularioRegistrarNacional.jsx` |
| Separación de roles (§2) | No acreditable con superadmin | `NOT_RUN` | spec |

## Hallazgos

| ID | Sev | Área | Descripción | Evidencia | Regla V3.1 | Lote |
| --- | --- | --- | --- | --- | --- | --- |
| NAC-001 | Alta | Endpoints / N-11 | `POST {pedido}/remision-caja` (asigna remisión a caja, base del seguro con varias cajas) existe en backend pero ninguna pantalla lo llama. El seguro de varias cajas queda inoperable. | `routes/web/modules/control-pedidos/core.php:88`; `grep` sin resultados en `resources/js` | N-F03 paso 5, §11.2, §2 | L2 |
| NAC-002 | Alta | Endpoints / N-14 | `POST {pedido}/cancelacion-nacional` sin consumidor en interfaz; la cancelación nacional con causa, guía y pago no es operable por un usuario. | `core.php:90`; `grep` sin resultados | §6 N-14, §7.6, §11.2 | L2 |
| NAC-003 | Baja — CORREGIDO 23-nov | Pruebas | `SnapshotDireccionPedidoTest::test_enviar_crea_snapshot_y_proyecta_domicilio` falla porque el servicio exige consulta CEDIS cerrada antes de enviar. La regla existe desde ago-2026 (`9a030908`, `21bc807b`) y la prueba es de jul/ago: prueba desactualizada, **no** introducida por Cursor. | salida de PHPUnit; `ValidacionCamposPedidoBma.php:58-63` | N-F01 paso 7 | L1 |
| NAC-004 | Alta — 1 de 3 corregida; 2 requieren decisión | Regresión Tienda | Tres pruebas de Tienda fallan en HEAD: `ConcurrenciaCapturaPedidoTest::test_preparacion_replay_exclusividad_y_hash_de_contenido` (`:61`, ValidationException), `SolicitudRegistrarTiendaTest::test_el_retiro_documental_conserva_todas_las_capturas_sin_sku` (`:925`) y `…test_el_auxiliar_aplica_una_vez_el_retiro_de_catalogo_y_rechaza_lo_ajeno` (`:985`). Cursor las reportó como «preexistentes»; sus archivos de prueba se tocaron el 20-nov. Sin clasificar: puede ser regla cambiada o regresión real en retiro de piezas de Tienda. | `phpunit_reg` (scratchpad); `git log` de los tests | §11.1.6 (no dañar Tienda) | L1 |
| NAC-005 | Media | Pesaje CEDIS / móvil | **No se reprodujo** la falta de adaptación a 390 px con emulación: sin desbordamiento horizontal, controles ≥ 40 px y consola limpia en ambos pasos. Reservas: (a) la vista previa del PDF ocupa la primera pantalla antes de la revisión física; (b) el componente es un monolito de 1,982 líneas con ramas `nacional`/Tienda, costoso de mantener; (c) no se probó en teléfono real ni el flujo «Tomar evidencias con celular» (cámara, sesión QR). | `ModalResponderPesaje.jsx`; CSS `control-pedidos-gestion-bma.css:660-701,1746-1825` | §11.3 (vista compacta, coherente) | L4 |
| NAC-006 | Media | Diseño / afordancia | Botones secundarios que no parecen botones: «Ver PDF del pedido», «+ Producto», «Buscar y agregar» y «Tomar evidencias con celular» se ven como texto plano sobre el fondo. «Buscar y agregar» además está deshabilitado mientras no hay almacén seleccionado, lo que lo hace parecer texto atenuado y sin explicar por qué. Requiere borde/fondo visibles, estado deshabilitado explicado y verificación en otros modales nacionales. (Solicitado por el usuario durante la auditoría.) | `ModalResponderPesaje.jsx:1354, 1628, 1666, 1670` (`BTN_SECONDARY`); capturas del modal | `GUIA_DISENO_GELIA.md`, §11.3 | L4 |
| NAC-007 | Media | Paqueterías / catálogo | Las 28 paqueterías tienen `requiere_peso=0`; FEDEX, ESTAFETA y DHL hacen pesaje solo porque son `categoria=comercial`. La bandera `requiere_peso` del catálogo no tiene efecto para ellas, así que quien la edite en Admin no cambia nada. `PAQ. PENDIENTE` está como `local_regional` y depende de una excepción en código para no caer en «sin kilogramos». Configuración frágil. | `PedidoBma.php:1130-1152`; catálogo vía `tinker` | N-F01 paso 4, N-F02 | L3 |
| NAC-008 | Alta | Paqueterías / flete | Para DHL/Estafeta/FedEx no hay tarifa en catálogo (`modalidad_tarifa=NULL`, seguro por defecto 0) y la continuación pide que Ventas **teclee el flete de cada caja**. V3.1 pide reutilizar «la fórmula ya vigente del servidor» (N-F01 paso 9, AC-09). **Por confirmar con negocio** si el flete nacional se captura a mano por diseño; si no, falta el cálculo. | `ContinuacionEnvioNacional.jsx:60-69,120` | N-F01 paso 9, AC-09, §7.7 | L3 |
| NAC-009 | Media | Paqueterías / reexpedición | El resumen de pagos de la continuación recibe `costoReexpedicion={0}` fijo con `envioEsBase`. Si el servidor suma la reexpedición dentro de `costo_envio`, el total es correcto pero la reexpedición no se muestra como concepto separado (N-02). Por comprobar en navegador con un pedido cotizado. | `ContinuacionEnvioNacional.jsx:144` | N-02, §7.7 | L3 |
| NAC-010 | Media | Endpoints / repesaje | `solicitar-repesaje` solo lo usa el formulario legado; Nacional 2.0 no tiene acción de repesaje propia (el peso obsoleto se maneja por ajuste y cierre). Confirmar si es intencional. | `ModalFormPedidoLegado.jsx:2604` | N-F01 paso 6, §11.2 | L2 |
| NAC-011 | Media | Regresión (riesgo) | Los commits de Cursor modifican archivos compartidos por las tres modalidades: `PedidoBmaController` (+266 líneas), `FormularioRegistrarTienda.jsx` (222 líneas), `adaptadoresConsulta.js`, `PedidoBma.php`, `FusionarSolicitudPreparacionPedido.php`. NAC-004 sugiere que ya hubo efecto en Tienda. No hay prueba de regresión visual de Tienda/Municipal. | `git diff --stat b5be2bb5^ 1ac45c5f` | §11.1.6 | L1 |
| NAC-012 | Baja | Accesibilidad | En el panel lateral, ~30 botones de icono sin nombre accesible. | `read_page` de `/control-pedidos` | `.ux-profile.md` | L4 |
| NAC-013 | **Crítica — CORREGIDO 23-nov** | Salida nacional / endpoint que no concluye | El envío nacional no puede completarse desde la interfaz. `MarcarEnviadoPedidoBmaService::assertGuiaVigente` exige `numero_rastreo` ya guardado en cada caja (nacional compartida), pero el Delegado guarda la guía solo en el pedido (`AsignarGuiaPedidoBmaService`: `numero_rastreo` del pedido) y la guía por caja que CEDIS escribe en «Detalle» llega en la misma petición y solo se guarda después de la comprobación (líneas 80 vs 132-134). Resultado: `POST cedis/58/marcar-enviado` responde 200 y el pedido sigue en «Pendiente de recolección o envío». El botón de la tarjeta ni siquiera envía cajas (`TarjetasCedis.jsx:376`), y el campo dice «Guía de este envío (opcional si ya hay guía del pedido)», contradiciendo al servidor. Mensaje visto: «Cada caja que sale requiere guía vigente». Aquí está el «endpoint que no concluye» reportado. **Corrección:** `assertGuiaVigente` ahora cuenta la guía de la caja, la que llega en la misma petición o la del Delegado a nivel pedido, y solo revisa las cajas que salen en esa recolección; al marcar la caja recolectada hereda la guía del pedido. Pruebas rojas→verdes: `test_la_guia_del_delegado_a_nivel_pedido_cubre_la_caja_y_la_salida_concluye` y `test_la_guia_de_caja_que_llega_en_la_misma_peticion_cuenta_y_solo_se_revisan_las_cajas_que_salen`. Verificado en navegador: `AUD-NAC-001` pasó a «Enviado» con el botón de la tarjeta. | `MarcarEnviadoPedidoBmaService.php:80, 132-134, 228-235`; `AsignarGuiaPedidoBmaService.php:73-110`; `TarjetasCedis.jsx:376`; `ModalDetalleCedis.jsx:217-255`; BD: caja `numero_rastreo=NULL`, `estatus_recoleccion=pendiente`, estatus 10 tras 4 intentos | N-F01 11-13, §1.1.8, §11.2 | L2 |
| NAC-014 | Alta — CORREGIDO 23-nov | Continuación / direcciones (v1.0) | La cotización no puede iniciarse si el cliente no tiene una dirección verificada, y no hay forma de crearla ahí: «Agregue una dirección verificada en el catálogo del cliente y vuelva a abrir el pedido». El alta de dirección dentro del pedido (`registrar-direccion-catalogo`) solo existe en el formulario legado. En este entorno ninguno de los ~6,875 clientes tiene direcciones activas. Capacidad de la 1.0 (N-07/N-09) que Nacional 2.0 perdió. Hubo que salir al módulo Direcciones. **Corrección:** botón «Agregar dirección» en la cotización (nuevo `ModalAgregarDireccionNacional.jsx`, reutiliza `CamposDireccionPedido` y el endpoint `registrar-direccion-catalogo`); la dirección guardada queda en la lista y elegida, la primera del cliente va como principal, y sin el permiso `clientes.direcciones.crear` el mensaje pide ayuda a un auxiliar. Pruebas Vitest rojas→verdes (3) y verificado en navegador con `AUD-NAC-002`. | `ContinuacionEnvioNacional.jsx:113`; `ModalFormPedidoLegado.jsx:1915`; consulta a `cliente_direcciones`: 0 filas | N-F01 8, N-07, N-09 | L2 |
| NAC-015 | Media | Cotización / claridad | Tras «Guardar cotización» el formulario se reinicia mostrando «Elegir» y solo queda un «Total exigible» sin desglose de flete, reexpedición ni seguro: Ventas no ve lo que cotizó y V3.1 exige la reexpedición como concepto separado. Confirma NAC-009 en navegador. | pantalla «Cotización y pago» ($1,425 sin partidas) | N-02, §7.7 | L3 |
| NAC-016 | Media | Estados obsoletos / copy | Estados que no se actualizan: la lista y la insignia del modal siguen en «Pesaje respondido» después de cerrar la consulta y de cotizar; el pie dice «Borrador guardado» tras enviar la consulta; la tarjeta de CEDIS muestra «Destino: Por confirmar» cuando el domicilio ya existe; la aprobación del Auxiliar pregunta «¿enviar a Registro General?» y la exhibición muestra «Pendiente de revisión» junto a «Pagado y validado». | capturas de la corrida | §11.3 | L4 |
| NAC-017 | Baja | Formularios | Mensaje de validación sin traducir («The monto field is required.») que no se limpia al corregir el campo; el campo «Guía de este envío» solo tiene placeholder, sin etiqueta visible; en Registrar pago el primer intento devolvió 200 con error de validación en pantalla, sin aviso general. | captura del formulario de pago; `ModalDetalleCedis.jsx` | `.ux-profile.md` (labels, errors) | L4 |
| NAC-018 | Baja — CORREGIDO 23-nov | Pruebas / empaque | `ControlPedidosFase22Test` (empacar mostrador sin paquetería pasa a pendiente de envío) falla con «Debe registrar bultos con evidencia para el pedido…»; falla igual sin el cambio de NAC-013, así que es anterior. Puede ser regla de empaque cambiada o prueba desactualizada. | `MarcarEmpacadoPedidoBmaService.php:72` | — | L1 |
| NAC-019 | Media — 14 de 16 corregidas; 2 requieren decisión | Pruebas / formularios | 16 pruebas Vitest de `ControlPedidos/Partials` fallan igual con y sin los cambios de esta sesión: 20+ en `modalConcurrenciaContrato.test.jsx`, 6 en `modalPersistenciaContrato.test.jsx`, 2 en `modalElegibilidadCedisPdv.test.jsx` y 1 en `ConsultaSolicitudTienda.test.jsx` (contrato de concurrencia y borrador del modal compartido). Sin clasificar; cubren justo el formulario que Cursor modificó. | corrida Vitest `Nacional` + `Partials`: 16 rojas / 347 verdes | §11.1.6 | L1 |
| NAC-020 | Media | Resolución L1 (23-nov) | **Clasificación de las pruebas rojas.** Se corrieron las 3 de Vitest restantes sobre `b5be2bb5^` (justo antes del primer cambio de Cursor) y fallan igual: **no son regresiones de Cursor**. Causas: (a) 13 de Vitest: Node 26 del contenedor activa un `localStorage` experimental que tapa el de happy-dom → se corrigió con `execArgv: ['--no-experimental-webstorage']` en `vite.config.js`; (b) `ConsultaSolicitudTienda.test.jsx`: el texto «Sin incidencias» pasó a «Todo OK» → se actualizó la prueba; (c) `ControlPedidosFase22Test`: desde el 15-oct empacar un pedido de mostrador exige bultos con evidencia (`MarcarEmpacadoPedidoBmaService.php:66-72`, cubierto por `EmpaqueBultosCedisTest`) → se actualizó la prueba con bultos; (d) `SnapshotDireccionPedidoTest`: enviar exige consulta cerrada (ago-26) y documento del pedido → se completó el pedido de la prueba; (e) `ConcurrenciaCapturaPedidoTest`: desde el 15-oct solicitar preparación exige `folio_remision` → se agregó al pedido de la prueba. | `vite.config.js`; `ConsultaSolicitudTienda.test.jsx`; `ControlPedidosFase22Test.php`; `SnapshotDireccionPedidoTest.php`; `ConcurrenciaCapturaPedidoTest.php:37` | §11.1.2 (un test verde no cambia la regla) | L1 |
| NAC-021 | Media | **Decisión pendiente** · Retiro de piezas en Tienda | `SolicitudRegistrarTiendaTest::test_el_retiro_documental_conserva_todas_las_capturas_sin_sku` (`:925`) y `…test_el_auxiliar_aplica_una_vez_el_retiro_de_catalogo_y_rechaza_lo_ajeno` (`:985`) esperan `revision_disponibilidad.aceptacion_vigente === false` tras un retiro. Desde el 18-nov el código deja `aceptacion_vigente = true` salvo que haya un anexo sin responder (`RegistrarRetiroPiezasTiendaService.php:129`, `ActualizacionConsulta::requiereRespuesta`). Los docs se contradicen: `TIENDA_DECISIONES_VIGENTES.md:117` dice «un ajuste invalida el cierre» y la entrega se bloquea con un ajuste pendiente; `docs/ai/modulos/CONSULTAS_DISPONIBILIDAD.md:12,41` dice que el retiro no pide reconfirmación ni marca ronda; V3.1 §3C dice «si anexa o retira… se repite la parte afectada». **No se tocó código ni prueba**: ¿un retiro en Tienda invalida el cierre vigente o solo se informa? | `RegistrarRetiroPiezasTiendaService.php:129`; docs citados | V3.1 §0.1, §3C, §7.1 | L1 |
| NAC-022 | Baja | **Decisión pendiente** · Formulario legado | `modalElegibilidadCedisPdv.test.jsx` (2 pruebas de «Q6.1 prioridad de captura de Ventas») monta el formulario **legado** y espera que «Paquetería *» no aparezca cuando hay traslado interno a PDV; hoy aparece junto a «Almacén de salida» y «Sucursal destino». Falla igual antes de Cursor. Puede ser la prueba (diseño previo a la modalidad nacional/municipal) o el formulario. **No se tocó**: el formulario legado solo atiende pedidos históricos. | `modalElegibilidadCedisPdv.test.jsx:200-251` | — | L1 |

## Regresiones

Ver NAC-004 y NAC-011. La prueba municipal `MunicipalOrdinarioM01Test::test_resolver_faltante_y_responder_actualizacion_permite_cierre_sin_pisar_flete` ya pasa en HEAD; los reportes de Cursor la daban por rota. No se verificó con navegador un alta de Tienda ni una Municipal.

## No verificado

- Respuesta de pesaje con piezas identificadas (SKU/captura), varias cajas, anexo/retiro de piezas y re-pesaje.
- Salida con varias cajas (recolección parcial) y guía por caja con seguro (N-11).
- Complementos, cambio de paquetería después de cotizar, cambio de dirección con guía emitida, Nacional→Tienda y cancelación.
- Teléfono real y cámara/QR del pesaje; modal «Registrar pedido» en 390 px (la emulación del pesaje no reprodujo falla).
- Idempotencia por `solicitud_id` y 409 `vista_atrasada` (solo leídos en pruebas de Cursor).
- Autorización por rol dentro de controlador/FormRequest y separación de responsabilidades: la cuenta superadmin no la acredita.
- Comparación detallada de capacidades v1.0 vs. 2.0 (`ModalFormPedidoLegado.jsx` no se inventarió completo).

### Datos de prueba que quedaron en el entorno

- Pedido `AUD-NAC-001` (`PBMA-2026-00058`), cliente 7716 PRUEBAS SISTEMA, ya en «Enviado» tras corregir NAC-013.
- Pedido `AUD-NAC-002` (`PBMA-2026-00059`), cliente 3170 PRUEBAS TARJETA CREDITO, con pesaje respondido, consulta cerrada y una dirección de prueba «AUD DESTINATARIO DOS».
- Dirección verificada «AUD prueba» del cliente 5665 (7716), una exhibición por $1,425 validada, remisión `RM-AUD-001` y guía `AUDGUIA0001`, con archivos sintéticos.
- Un cliente con la dirección «AUD DESTINATARIO PRUEBA» (CP 06000). Sin pagos, guías ni mensajería reales.

## Lotes propuestos

1. **L1 — Pruebas rojas y regresiones de Tienda** (NAC-003, 004, 011, 018, 019, 020-022). **Ejecutado 23-nov:** de 20 rojas conocidas quedan 4, todas por decisión de negocio (NAC-021 y NAC-022); ninguna resultó regresión de Cursor. Clasificar cada falla (regla cambiada vs. regresión), corregir o actualizar con decisión explícita, y añadir una prueba de regresión visual/funcional de Tienda y Municipal. Riesgo: bajo. Aceptación: suite dirigida de Tienda/Municipal en verde.
2. **L2 — Flujo que no concluye y endpoints sin pantalla** (NAC-013, 014, 001, 002, 010). **Primero NAC-013**: guardar la guía por caja antes de validar (o propagar la guía del Delegado a sus cajas), y hacer que el botón de la tarjeta explique o envíe lo que falta; alta de dirección dentro de la continuación. Superficie para remisión por caja y cancelación nacional en la bandeja del rol correcto; decidir repesaje. Aceptación: relevo observable Ventas/Auxiliar/CEDIS → siguiente bandeja.
3. **L3 — Paqueterías con logística** (NAC-007, 008, 009, 015). Decidir con negocio si el flete es manual, normalizar banderas del catálogo y mostrar reexpedición separada. Aceptación: cotización DHL/Estafeta/FedEx coherente con la prueba `NacionalLogisticaCobroTest`.
4. **L4 — Diseño, estados y móvil** (NAC-005, 006, 012, 016, 017). Botones secundarios con afordancia y estado deshabilitado explicado, colapsar la vista previa del PDF en móvil, probar en teléfono real y evaluar dividir `ModalResponderPesaje`. Aceptación: revisión en 375 y 1280 px según `GUIA_EVIDENCIA_VISUAL.md`.
5. **L5 — Recorrido E2E con datos de prueba** — ejecutado el 23-nov para el caso simple (ver Resumen); queda repetirlo con varias cajas, piezas identificadas, complementos y bifurcaciones. Alta → pesaje → cierre → pago → guía → salida y las bifurcaciones, para detectar los endpoints que no concluyen. Primero crear cliente y PDF de prueba; ideal con cuentas por rol.

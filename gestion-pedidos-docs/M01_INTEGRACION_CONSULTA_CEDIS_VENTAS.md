# M-01 — Integración operativa Ventas / consulta municipal / CEDIS

Fecha: 17 de noviembre de 2026. Sin commit y sin push. Rama local `develop`, HEAD `08d240a7`.

Este cierre cubre las cuatro correcciones del recorrido municipal Ventas → CEDIS/Tienda → Ventas: enviar consulta sin guardado manual (incluso con archivo aún sin ID), seguimiento con el patrón de Gestión de Pedidos, actualización automática con un solo dueño y respaldo de 15 s independiente de Echo aparentemente sano, y tarjetas/detalle/panel CEDIS para Envío municipal. No cierra M-01 completo: no hay carátula PDF, empaque, traslado CEDIS→PDV, finanzas nuevas ni M-02+.

El árbol ya traía el municipal de Codex/Cursor (expediente, alta, consulta, pruebas `MunicipalOrdinarioM01Test`). No se hizo pull, merge, reset, rebase, commit ni push. No se usó `migrate:fresh` ni `ALLOW_DESTRUCTIVE_DB`. Sail aisló caché de configuración y rutas en `/tmp`. Un build al cerrar el lote (`public/build/manifest.json` 17 nov 18:56). No quedó `public/hot`.

## 1. Qué se corrigió

| Corrección | Causa raíz | Cambio |
| --- | --- | --- |
| C1 Enviar consulta | `motivoComun` exigía `documento.id`; el PDF seleccionado inhabilitaba el botón y el autoguardado nunca arrancaba. `antesDeGuardar` usaba `guardar()` no silencioso. | El botón se habilita con archivo seleccionado. Al pulsar: persistir pedido → POST `pdf-pedido` sobre ese ID → solicitud → consulta. Estados visibles. |
| C2 Seguimiento | Bloque de párrafos; «aceptación del cliente»; `EXCEPCIONES` se leía como consulta pendiente. | `EncabezadoFolioPedido`, `AvisoOperativoPedido`, `RespuestaDisponibilidadPedido`. Etiqueta «Excepciones pendientes». |
| C3 Vivo | El hook del padre tenía `intervaloRespaldo` 0 y, si Echo figuraba suscrito, omitía el GET. | Un solo `useActualizacionesPedidos`. Con consulta municipal enviada: 15 s y `respaldoAunqueSuscrito`. Merge por `respondida_at`. |
| C4 CEDIS | Tipo por `origen.nombre`; logística por defecto; PDF solo con `pendientePesaje`; destino «Por confirmar». | Rama `esRegistroMunicipal`: Envío municipal, terminal, área, Revisar disponibilidad, visor Wizerp sin pesaje. |

## 2. Arquitectura conservada

Sigue `consulta_municipal` + `revision_disponibilidad` en `solicitud_preparacion_json`. No hay `PedidoBmaTareaPreparacion` para responder. El GET `/control-pedidos/{id}/disponibilidad-tienda` es el mismo de Tienda; `DisponibilidadTiendaController::show` autoriza con `VisibilidadPedidoBma::puedeConsultar`. El merge municipal ignora pagos, domicilio y captura común. `pdf_pedido.store` sigue exigiendo `pedidoBma` persistido: sin ID no hay POST de archivo.

## 3. Inventario de esta intervención

### Creados

- `resources/js/Pages/ControlPedidos/Municipal/vigenciaConsultaMunicipal.js`. `lecturaMunicipalMasNueva` y `fusionarLecturaMunicipal`. Orden por `respondida_at` (si falta, `updated_at` del pedido). UUID solo identifica. No pisa terminal, consignatario, leyenda, costo, cliente ni folio.
- `resources/js/Pages/ControlPedidos/Municipal/vigenciaConsultaMunicipal.test.js`. Lectura anterior se descarta; la posterior sustituye; captura local de terminal no se pisa.
- `resources/js/Pages/ControlPedidos/Municipal/envioDirectoMunicipal.test.jsx`. Pedido nuevo sin ID + PDF seleccionado: autoguardar → `pdf-pedido` → solicitud → consulta. Fallo al guardar no dispara PDF ni consulta. Doble clic no duplica.
- `resources/js/Pages/ControlPedidos/Municipal/SeguimientoSolicitudMunicipal.test.jsx`. Encabezado, nomenclatura, nota de mercancía/cierre, sin apartado físico.

### Modificados (esta corrección)

- `resources/js/Pages/ControlPedidos/Municipal/FormularioRegistrarMunicipal.jsx`. `asegurarDocumento` antes de solicitud+consulta. Etapas: Preparando solicitud / Guardando documento / Enviando consulta / Consulta enviada. `antesDeGuardar` es `confirmarCapturaComun` (silencioso). Guardar solicitud sigue aparte.
- `resources/js/Pages/ControlPedidos/Municipal/SeguimientoSolicitudMunicipal.jsx`. Recompuesto con primitivas de Tienda. Modalidad Envío municipal, terminal, área. `gelia-estado-vivo`. `AccionesRapidasDisponibilidad` con `onOcupado`. Nota: CEDIS o Tienda confirman mercancía; Ventas registra el cierre con el cliente. Una respuesta positiva no cierra ni autoriza pago.
- `resources/js/Pages/ControlPedidos/Municipal/terminalMunicipal.js`. `areaResponsableMunicipal`: almacén de la consulta o CEDIS/Tienda.
- `app/Services/ControlPedidos/Municipal/EtiquetaConsultaMunicipal.php`. `EXCEPCIONES` → «Excepciones pendientes».
- `resources/js/Pages/ControlPedidos/Partials/FormularioRegistrarTienda.jsx`. `archivoPendiente` / `ejecutarSubirArchivo` / `asegurarDocumentoMunicipal`. `motivoComun` municipal ya no exige `documento.id`. Hook: `intervaloRespaldo` 15 s y `respaldoAunqueSuscrito` solo con consulta municipal enviada y no cerrada. `pausado` municipal: guardado/envío/ajuste, no `estadoGuardado === 'pendiente'`. GET de disponibilidad; no `pagos.resumen`. Merge con `fusionarLecturaMunicipal`.
- `resources/js/Pages/ControlPedidos/Partials/useActualizacionesPedidos.js`. Parámetro `respaldoAunqueSuscrito`. El intervalo corre aunque Echo figure `connected`+`subscribed`. Omite ciclo si en esos 15 s ya hubo evento o lectura de ese `pedidoId`, pestaña oculta, offline, petición en vuelo o `pausado`. Un solo intervalo. Tienda/Nacional conservan `intervaloRespaldo` 0 salvo quien ya lo usaba.
- `resources/js/Pages/ControlPedidos/Partials/useActualizacionesPedidos.test.jsx`. Respaldo con Echo «suscrito»; omisión si hubo evento reciente; segundo ciclo a los ~30 s de montaje.
- `resources/js/Pages/ControlPedidos/Partials/AccionesRapidasDisponibilidad.jsx`. `onOcupado` para pausar el vivo mientras Ventas resuelve una excepción.
- `resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx`. Municipal: tipo Envío municipal, terminal, área, sin ruta logística ni bultos/guía, acción Revisar disponibilidad, PDF/`cotizacion` sin `pendientePesaje`.
- `resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis.jsx`. Misma rama municipal. Sin INE en el detalle general.
- `resources/js/Pages/ControlPedidos/Partials/PanelRespuestaDisponibilidad.jsx`. Destino municipal: terminal y área, no «Por confirmar».
- `resources/js/Pages/ControlPedidos/Cedis/Partials/q81Interfaz.test.jsx`. Tarjeta municipal vs nacional/tienda. Folio: `EncabezadoFolioPedido` muestra «Folio: PED-81».
- `resources/js/Pages/ControlPedidos/Partials/PanelRespuestaDisponibilidad.test.jsx`. Municipal no pinta «Por confirmar».
- `resources/js/Pages/ControlPedidos/Municipal/registroMunicipal.test.jsx`. Botón habilitado sin `documento.id`; archivo rechazado; estados de envío.
- `app/Services/ControlPedidos/ListarPedidosCedisService.php`. `hidratarConsultaMunicipal`: carga `almacen` de la consulta. No escribe `origen_id` ni `sucursal_destino_id`.
- `app/Services/ControlPedidos/ListarPedidosBmaService.php`. `etiqueta_consulta` y `motivo_bloqueo_cierre` municipales.
- `tests/Feature/ControlPedidos/MunicipalOrdinarioM01Test.php`. GET 200 de Ventas y 403 ajeno; cuerpo con `consulta_municipal` y sin `storage/app/private`; etiqueta de excepciones; cierre bloqueado si la revisión no sigue vigente; `respondida_at` posterior sustituye a la anterior (UUID no ordena).

### Municipal previo (sin seguimiento, no rehecho)

Alta, expediente, consulta, finanzas municipales, rutas, seeders y reportes Codex anteriores (`M01_IMPLEMENTACION_REPORTE_DETALLADO.md`, `M01_CONTINUIDAD_REGISTRO_Y_CONSULTA_CODEX.md`, `M01_REFINAMIENTO_CAPTURA_MUNICIPAL_CODEX.md`, `M01_AUDITORIA_CODEX_DETALLADA.md`). Esta intervención no los reescribe.

### No tocados a propósito

Finanzas municipales, `costo_envio = null` diferido, `pendiente_regularizacion`, leyenda PAGADO/POR COBRAR, bloqueo de pago, versionado de expediente, carátula, empaque, traslado, M-02+. Consulta a Tienda/PDV: mismas respuestas y `AlcanceConsultaMunicipal`, sin tarea de preparación.

## 4. Recorrido resultante

1. Ventas captura municipal (cliente, departamento, folio Wizerp, PDF/imagen, terminal, consignatario, costo). Puede pulsar Enviar consulta sin Guardar solicitud y sin `documento.id`.
2. GELIA persiste el pedido, sube el archivo a `pdf_pedido.store`, guarda la solicitud y envía la consulta. Idempotencia del backend al mismo almacén (`enviada_at` existente). Un fallo después del guardado deja la solicitud y un aviso de reintento.
3. CEDIS ve Envío municipal, terminal, área, Revisar disponibilidad y el visor del Wizerp de ese folio. Responde Todo OK o detalle/faltante. No aparta ni empaca.
4. El padre lee Echo y, si hace falta, GET cada 15 s. El seguimiento pinta la revisión ganadora por `respondida_at`. Ventas cierra cuando la revisión vigente lo permite. Una respuesta positiva no abre el pago.

## 5. Verificación

Leyenda: **backend** (Sail/SQLite en memoria) · **vitest** · **navegador** (aplicación real, datos ficticios) · **inspeccionado** (código) · **no comprobado**.

### Backend — comprobado

`./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test tests/Feature/ControlPedidos/MunicipalOrdinarioM01Test.php`

27 pruebas, sin fallos. Incluye, de esta corrección:

- `test_disponibilidad_tienda_responde_a_ventas_y_niega_a_quien_no_tiene_alcance`: 200 de la persona titular, `consulta_municipal.estado = pendiente`, `etiqueta_consulta`, JSON sin `storage/app/private`, 403 de usuario con `control_pedidos.crear` sin alcance.
- `test_etiqueta_de_excepciones_y_cierre_bloqueado_si_la_revision_no_sigue_vigente`: etiqueta «Excepciones pendientes», cierre 422, `respondida_at` posterior sustituye.
- Recorrido Jaguar, idempotencia, reapertura, permisos CEDIS/Tienda, envío al auxiliar sin pesaje nacional: ya cubiertos y siguen pasando.

### Vitest — comprobado

Dirigido a: `envioDirectoMunicipal.test.jsx`, `registroMunicipal.test.jsx`, `SeguimientoSolicitudMunicipal.test.jsx`, `vigenciaConsultaMunicipal.test.js`, `useActualizacionesPedidos.test.jsx`, `q81Interfaz.test.jsx`, `PanelRespuestaDisponibilidad.test.jsx`. Pasaron tras alinear q81 a «Folio: PED-81» (el encabezado compartido no dice «Folio Pedido»).

Cubierto en componente: botón sin `documento.id`; pedido nuevo + PDF → autoguardar y `pdf-pedido`; fallo al guardar; doble clic; archivo rechazado; seguimiento; tarjeta municipal vs nacional/tienda; hook con Echo suscrito, omisión si hubo evento, un intervalo.

### Navegador — comprobado (sin mutar el pedido 252)

Dos sesiones en `http://100.110.75.115:9080`, tema oscuro, escritorio. Pedido real 252 / PBMA-2026-00008, cliente DE LA CRUZ ZARATE JESUS GABRIEL, terminal Taxi Tacotalpa.

| Paso | Resultado |
| --- | --- |
| Formulario municipal nuevo | Guardar solicitud y Enviar consulta inhabilitados hasta cliente. Motivo en pie. |
| CEDIS tarjeta 252 | Tipo Envío municipal. Folios 252 y PBMA-2026-00008. Terminal Taxi Tacotalpa. Área CEDIS. Revisar disponibilidad. PDF pedido. Pedidos 1 y 2 (TIENDA) conservan ruta CEDIS→PDV, nota de compra y empaque. |
| Visor Wizerp | Control PDF pedido. Archivo `image.png`, tipo `pdf_pedido`, folio 252. No se abrió INE. |
| Panel Revisar disponibilidad | Folio 252, Disponibilidad · CEDIS, cliente, terminal, área. Todo OK / Con detalle o faltante. Cerrado con Cerrar consulta **sin** Enviar respuesta. |
| Ventas seguimiento 252 | Seguimiento · Envío municipal, EncabezadoFolioPedido, Consulta pendiente: CEDIS, Esperando respuesta de CEDIS, nota de mercancía/cierre, aviso de que una respuesta positiva no cierra ni autoriza pago. Cierre bloqueado: «La consulta sigue abierta: falta la respuesta de disponibilidad.» |

Notas de sesión: `gestion-pedidos-docs/evidencias-m01-integracion/navegador.txt`.

### Navegador — no comprobado

No se afirma actualización automática en pantalla: el intervalo está instalado y cubierto en Vitest; **no** se esperó una respuesta de CEDIS con el seguimiento abierto.

- Pedido municipal **nuevo** → PDF válido → Enviar consulta sin Guardar solicitud hasta ver el folio en CEDIS. (Vitest sí. En navegador, elegir modalidad disparó autoguardado y dejó el borrador **PBMA-2026-00009**; no se envió esa consulta.)
- Dos sesiones: CEDIS responde y Ventas ve evidencias sin recargar ni pulsar Actualizar respuesta.
- Echo cortado o reconectado.
- Excepción pendiente y segunda respuesta tras ajuste de Ventas (la primera no pisa a la segunda) en UI.
- Cierre explícito cuando la revisión vigente lo permite.
- Un paso de consulta municipal a Tienda/PDV.
- 403 de sucursal ajena en navegador (sí en backend).
- Anchos 1440 / 1024 / 390, tema claro, consola sistemática (`GUIA_EVIDENCIA_VISUAL.md`).

### Inspeccionado, no ejecutado en vivo

GET municipal no llama `pagos.resumen`. Merge no desmonta el formulario. Hidratación CEDIS no asigna `origen_id`. Compatibilidad Tienda del GET: campos municipales aditivos.

## 6. Seguridad y privacidad

El GET no incorpora rutas `storage/app/private` (asserción PHP). Las evidencias de consulta usan URL autorizada de visor (`pdf_pedido` / `cotizacion` / `evidencia_condicion`). INE no se ofrece en bandeja ni detalle CEDIS. El alcance del GET no se amplió.

## 7. Texto visible

Nuevo texto de seguimiento y CEDIS municipal es neutro (área, terminal, registrado por nombre). Sigue en la bandeja CEDIS el filtro «Vendedora» / «Fechas, vendedora y orden» y, en tarjetas no municipales, «Esperando guía del cliente (vendedora)». No se cambió en este lote.

## 8. Residuos de la sesión

- Borrador **PBMA-2026-00009** creado por autoguardado al abrir un alta municipal. No tiene consulta enviada.
- Pedido **252** intacto: consulta pendiente, sin respuesta de esta verificación.
- Árbol Git sucio: ~54 rastreados modificados y el municipal sin seguimiento. Nada de esto se commitó.

## 9. Cómo repetir lo no comprobado

1. Ventas: Nuevo pedido → Envío municipal → cliente, folio, PDF, terminal, consignatario, costo → **Enviar consulta** (no Guardar solicitud). Anotar el folio.
2. CEDIS: localizar ese folio, abrir PDF pedido, Revisar disponibilidad, responder Todo OK con evidencia (o detalle).
3. Ventas: dejar el seguimiento abierto; no pulsar Actualizar respuesta. Comprobar que aparece la respuesta. Repetir con Echo desconectado.
4. Ventas: solicitar nueva revisión; CEDIS responde otra vez; la primera no debe sustituir a la segunda si su `respondida_at` es anterior.
5. Cerrar cuando `puede_cerrar_consulta`. Comprobar que el pago sigue bloqueado hasta el cierre.
6. Tienda: consulta con almacén PDV. Usuario de otra sucursal: 403 al GET.
7. Anchos 1440 / 1024 / 390 y tema claro.

## 10. Límites

No se creó canal Echo paralelo ni segundo hook. No se consolidó el nombre de la ruta `disponibilidad-tienda`. No se hidrataron municipio ni domicilio en CEDIS. Municipales históricos sin `modalidad_entrega = municipal` no entran en la rama nueva.

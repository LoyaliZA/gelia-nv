# Ampliación del API externa para la app móvil

Este documento describe el API que consume la app móvil de GELIA, qué cubría antes de esta ampliación, qué información opera el escritorio y el contrato de las rutas nuevas. Sirve para implementar o consumir el API sin redescubrir permisos, alcances ni campos.

La documentación operativa que descarga el panel de API externa (PDF generado por `ApiDocumentacionService`) incluye las mismas rutas. Este archivo es el mapa de cobertura y las decisiones de alcance.

## Cómo está organizado el API

Hay dos autenticaciones bajo `/api/v1`. No son intercambiables.

| Modelo | Rutas | Credencial | Para qué |
| --- | --- | --- | --- |
| Aplicación externa | `POST /auth/token`, `GET/POST/PUT /clientes` | `client_id` y `client_secret` | Integración de máquinas. El recurso `clientes` filtra campos por aplicación. |
| App móvil | `/mobile/*` y passkeys | Token Bearer de una persona usuaria, habilidad `mobile`, dispositivo `device_uuid` | Operación de quien inicia sesión. Hereda permisos Spatie del escritorio. |

Archivos de referencia:

- Rutas: `routes/api.php`
- Auth móvil: `app/Http/Middleware/AuthenticateMobileUser.php`, `app/Services/Mobile/MobileAuthService.php`
- Auth de aplicación: `app/Http/Middleware/AuthenticateApiApplication.php`, `app/Http/Middleware/CheckApiResourcePermission.php`
- Registro de recursos de aplicación: `database/seeders/ApiExternaSeeder.php` (hoy solo `clientes`)
- Documentación oficial: `app/Services/ApiExterna/ApiDocumentacionService.php`
- Paginación y autorización de la ampliación: `app/Support/Api/V1/RespuestaPaginada.php`, `app/Support/Api/V1/AutorizacionMovil.php`

Convenciones que se respetan:

- Prefijo `/api/v1` y encabezado `Accept: application/json`.
- Listados en `{ data, meta }` con `current_page`, `last_page`, `per_page`, `total`. `per_page` máximo 50, salvo donde el servicio de dominio ya fija otro tamaño.
- Un 401 si el token no es móvil o el dispositivo está revocado. Un 403 si falta el permiso. Un 404 si el registro existe pero queda fuera del alcance de la persona (no se confirma que exista).
- Las rutas nuevas no usan `X-Mobile-Scope-Version`. Ese encabezado sigue siendo obligatorio solo en `/mobile/sync/*`.
- No se amplió el token de aplicación. Pedidos, RH, inventarios y el resto dependen de la persona autenticada y de su permiso. Un `client_id` no tiene ese alcance.

## Estado anterior

| Área | Rutas previas |
| --- | --- |
| Salud y token de aplicación | `GET /health`, `POST /auth/token` |
| Clientes de aplicación | `GET /clientes`, `GET /clientes/{numero}`, y escritura solo si la aplicación tiene el recurso habilitado |
| Sesión móvil | `POST /mobile/login`, `POST /mobile/logout`, `GET /mobile/me`, `PATCH/POST /mobile/profile`, passkeys |
| Clientes móviles | `GET /mobile/clientes`, `GET /mobile/clientes/{numero}` |
| Sincronización | bootstrap y changes de clientes |
| Punto de venta | contexto, sucursal activa, resguardos (alta, recepción, custodia, entrega con firma), turnos, visitas del día |
| Comercial | visitas programadas de quien las registra |

## Mapa del escritorio

Las rutas web viven en `routes/web/modules`. El permiso de la primera columna es el que abre el módulo. El aislamiento adicional está en el servicio de listado, no solo en la ruta.

| Módulo web | Permiso de entrada | Dato principal | Aislamiento |
| --- | --- | --- | --- |
| Control de pedidos | `control_pedidos.ver_listado` | `PedidoBma` | `VisibilidadPedidoBma`: propios, equipo de gerencia o departamento; borradores ajenos ocultos. Consulta de detalle también por bandeja de auditoría, CEDIS o delegación. |
| RH | `rh.ver` y un permiso por submódulo | `RhColaborador` y movimientos | Quien entra al módulo ve el padrón. Compensación incluida, igual que la ficha web. |
| Almacenes e inventarios | `almacenes.inventarios.ver` y permisos hermanos | `Almacen`, `Inventario` | `AlcanceAlmacenesService`: sucursales operables de la persona. |
| Activos | `activos.ver` | `Activo`, `ActivoAsignacion` | Departamento de la persona, salvo `activos.ver_todos` o rol de administración. |
| Solicitudes de tag | `solicitudes.ver_listado` | `SolicitudTag` | Vendedor propio; gerencia o verificación por departamento; administración sin filtro. |
| Traspasos | `traspasos.ver_listado` | `SolicitudTraspaso` | `ListarSolicitudesTraspasoService::usuarioPuedeVer`. El abastecimiento de preparación no entra al listado. |
| Mensajería | sesión autenticada | `Conversacion`, `Mensaje` | `ConversacionPolicy`: solo participantes. |
| Punto de venta | permisos `pdv.*` | resguardos, turnos, visitas | Ya estaba en el API móvil. |
| Facturas | `facturas.ver_listado` | `SolicitudFactura` | Propias; gerencia o quien verifica o responde, por departamento; borradores ajenos ocultos. |
| Cancelaciones y cotizaciones | `cancelaciones_cotizaciones.ver_listado` | `SolicitudTag` con proceso operativo | El mismo aislamiento del módulo web. |
| Contabilidad, cobranza automática, saldos, comisiones | permisos de cada módulo | cortes, importaciones, crédito | No se expusieron. Ver brechas. |
| Integraciones, medios, reportes exportables, Gelia AI | permisos de cada módulo | credenciales, archivos, agregados | No se expusieron. |

## Rutas implementadas

Todas exigen token móvil. La forma de lista es `data` + `meta`. Donde el servicio web ya calcula métricas, la respuesta añade `metricas`.

### Pedidos

| Método | Ruta | Permiso | Efecto |
| --- | --- | --- | --- |
| GET | `/mobile/pedidos` | `control_pedidos.ver_listado` | Listado. Query `q`, `tab`, `page`, `per_page`. |
| GET | `/mobile/pedidos/{id}` | el mismo, más `VisibilidadPedidoBma::puedeConsultar` | Detalle. |

`tab` acepta `TODAS`, `BORRADORES`, `PESAJE_PENDIENTE`, `PESAJE_RESPONDIDO`, `PENDIENTE_AUXILIAR`, `EN_CEDIS`, `PENDIENTE_GUIA_CLIENTE`, `ENVIADOS`, `RECHAZADAS`, `OBS_CEDIS`, `SIN_EXISTENCIA` y `ELIMINADAS`. `ELIMINADAS` exige además `control_pedidos.eliminados`; si falta, el listado vuelve vacío.

Campos de lista: `id`, `folio`, `folio_remision`, `numero_remision`, `fecha`, `prioridad_md`, `es_resguardo`, `estatus_envio`, `estatus_sucursal`, `numero_rastreo`, `total_mercancia`, `costo_envio`, `total_a_cobrar`, `cantidad_piezas`, `cliente` (`id`, `numero_cliente`, `nombre`), `vendedor` (`id`, `name`), `estatus` (`id`, `nombre`, `fase_ciclo`, `etiqueta`, `color_hex`), `origen`, `almacen`, `sucursal_destino`, `paqueteria`, `puede_editar`, `puede_cancelar`, `esta_esperando_pago`, `progreso`, resumen de `tarea_preparacion`, `referencia_captura`, `updated_at`.

El detalle suma contacto, domicilio, código postal, comentarios, cajas, peso, motivo de cancelación, zona, tipo de guía, la tarea de preparación que ya arma el servicio web, la cancelación operativa y los últimos 20 cambios de estatus. No incluye rutas de documentos, comprobantes ni hashes de captura.

Errores: 403 sin permiso o sin autorización de consulta; 404 si el id no existe.

### RH

| Método | Ruta | Permisos |
| --- | --- | --- |
| GET | `/mobile/rh/colaboradores` | `rh.ver` |
| GET | `/mobile/rh/colaboradores/{id}` | `rh.ver` |
| GET | `/mobile/rh/horas-extra` | `rh.ver` y `rh.horas_extra.ver` |
| GET | `/mobile/rh/salidas-personales` | `rh.ver` y `rh.salidas_personales.ver` |
| GET | `/mobile/rh/incidencias` | `rh.ver` y `rh.incidencias.ver` |
| GET | `/mobile/rh/prestamos` | `rh.ver` y `rh.prestamos.ver` |
| GET | `/mobile/rh/banco-tiempo` | `rh.ver` y `rh.banco_tiempo.ver` |

Filtro común: `q` o `busqueda`, más `departamento_id`, `area_id`, `rh_colaborador_id`, fechas y `estado` cuando el servicio web ya los aplica. `vinculo` en colaboradores acepta `con_cuenta` o `sin_cuenta`.

Colaborador: `folio`, nombre, apellidos, `nombre_completo`, `activo`, departamento, área, puesto, turno, usuario vinculado y bloque `compensacion` (`salario_base`, `salario_diario`, bonos, `saldo_comisiones`, `horas_laboradas_oficiales`). La ficha añade hasta 8 incidencias si hay permiso de incidencias o deducciones, y los préstamos activos o pausados si hay `rh.prestamos.ver`.

Horas extra: folio, fecha de turno, horas a pagar, `total_economico`, `estado_pago`, motivo, colaborador y supervisor.

Salida personal: folio, fecha, motivo, horas, minutos ausentes y monto. No se envían las rutas de las fotos.

Incidencia: folio, fecha, estado, origen, total, SKU, regla y descripción. No se envían rutas de firma de recibo.

Préstamo: concepto, cuota, pagos, modalidad y estado.

Banco de tiempo: horas pendientes, origen de la deuda, estado y fechas.

### Almacenes e inventarios

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/mobile/almacenes` | cualquiera de `almacenes.inventarios.ver`, `almacenes.costos.ver`, `gestion_interna.productos.ver`, `almacenes.productos.ver`, `catalogos.gestionar` |
| GET | `/mobile/almacenes/{id}` | el mismo conjunto |
| GET | `/mobile/inventarios` | `almacenes.inventarios.ver` o `catalogos.gestionar` |
| GET | `/mobile/inventarios/{id}` | el mismo |

Almacén: `codigo`, `nombre`, `activo`, banderas de visibilidad y salida, sucursal y tipo. Query `q`, `sucursal_id`, `activo`. La respuesta de lista incluye `sucursales` operables. La sucursal activa del escritorio vive en sesión web; el móvil debe mandar `sucursal_id` cuando quiera acotar.

Inventario: `ubicacion`, `existencia`, `apartado`, `disponible`, tránsitos, mínimo, máximo, producto (`folio`, `sku`, `descripcion`, `codigo_barras`, marca, categoría) y almacén. `costo` y `precio_venta` viajan porque el listado web de inventarios ya los muestra a quien tiene el permiso de ver existencias. Query `q`, `sucursal_id`, `almacen_id`, `sort`, `dir`. `sort` acepta los mismos valores que el escritorio: `producto`, `almacen`, `ubicacion`, `existencia`, `apartado`, `disponible`, `transito_oc`, `transito_ot`.

Un almacén fuera de las sucursales operables responde 404.

### Activos y firmas

| Método | Ruta | Permiso | Efecto |
| --- | --- | --- | --- |
| GET | `/mobile/activos` | `activos.ver` | Listado con el aislamiento de departamento. |
| GET | `/mobile/activos/{id}` | `activos.ver` | Detalle, asignación activa, mantenimiento abierto y `consulta_token`. |
| GET | `/mobile/activos/resolver-codigo` | `activos.ver` | Query `codigo`. Folio, serie, MAC, IMEI o token dentro de una URL de QR. |
| GET | `/mobile/activos/asignaciones/pendientes-firma` | `activos.ver` | Asignaciones activas sin firma de la persona autenticada. |
| POST | `/mobile/activos/asignaciones/{id}/firmar` | `activos.ver` | Escribe la firma. |

Filtros de lista, los mismos del escritorio: `q`, `estado`, `mis_activos`, `sin_asignar`, `en_mantenimiento`, `pendientes_firma`, `departamento_id`, tipo y categoría.

La firma es un data URL `data:image/png|jpg|jpeg;base64,...`. Puede firmar la persona asignada. Otra persona necesita `activos.asignar` y acceso al departamento del activo. La respuesta trae `firmado` y `firma_fecha`, no la ruta del archivo. El escritorio usa el mismo `FirmarAsignacionActivoService`.

Errores de firma: 422 si el formato no es válido; 403 si no hay autorización.

### Solicitudes y traspasos

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/mobile/solicitudes` | `solicitudes.ver_listado` |
| GET | `/mobile/solicitudes/{id}` | el mismo, y el aislamiento del listado |
| GET | `/mobile/traspasos` | `traspasos.ver_listado` |
| GET | `/mobile/traspasos/{id}` | el mismo, vía `usuarioPuedeVer` |

Solicitud de tag: montos, `pago_confirmado`, remisión, pedido, fecha de operación, observaciones, cancelación, cliente, vendedor, proceso, estado y departamento. También `cotizacion` (bandera `solicitar_cotizacion` y los montos que ya persiste `solicitudes_tags`: cotizado, aplicado, tentativo y `total_proyectado_neto`), tipo de cliente, lista de descuento, lista de rebaja y el nombre del banco de catálogo. Query `q`, `tab`, `estado_id`, `proceso_id`, `vendedor_id`, `lista_id`, `tipo_cliente_id`, `fecha_inicio`, `fecha_fin`, `motivo_incorrecta`. La lista añade `metricas` y `resumen` que ya calcula `ListarSolicitudesService`.

El bloque `factura` de la solicitud trae razón social, si hay datos fiscales, si hay archivo, y un identificador con `rfc`, `uso_factura` y `nombre_razon_social`. El detalle agrega remisiones por `id`, `nombre_original` y `orden`. No se envía el JSON `factura_datos_fiscales`, ni correo, teléfono, código postal, régimen, ni la ruta `archivo_facturas_path` o `evidencia_path`.

| Método | Ruta | Permiso | Efecto |
| --- | --- | --- | --- |
| POST | `/mobile/solicitudes/{id}/solicitar-cancelacion` | `solicitudes.solicitar_cancelacion` | Pide cancelar el folio. No lo cancela. |

Body: `motivo_cancelacion` (mínimo 10 caracteres) y `catalogo_lista_rebaja_id` cuando el proceso es de cambio de lista. Solo la persona dueña del folio, igual que `SolicitarCancelacionSolicitudService`. Un folio operativo responde 403: se cancela en el módulo de abajo.

### Solicitudes de facturación

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/mobile/facturas` | `facturas.ver_listado` |
| GET | `/mobile/facturas/{id}` | el mismo, vía `usuarioPuedeVer` |

Campos útiles para métricas: `folio`, `legacy_solicitud_id`, `destinatario_tipo`, `razon_social`, `estado`, `motivo_respuesta`, `motivo_incorrecta`, `campos_incorrectos`, fechas (`created_at`, `respondida_at`, formulario enviado y respondido), cliente (`numero_cliente`, `nombre`), departamento, cuenta que registró la solicitud, quien respondió, banco de catálogo (`id`, `nombre`) y receptor (`id`, `codigo_interno`). Indicadores de documento: `tiene_voucher`, `tiene_pdf_emitido`, `tiene_xml`, `tiene_archivo_fiscal`. Identificador fiscal reducido: `rfc`, `uso_factura`, `nombre_razon_social`.

Query `q`, `tab` (`BORRADORES`, `PENDIENTES`, `RESPONDIDAS`, `VERIFICADAS`, `INCORRECTAS`, `CANCELADAS`), `departamento_id`, `area_id`, `vendedor_id`, `fecha_inicio`, `fecha_fin`. `metricas`: `borradores`, `pendientes`, `respondidas_hoy`, `incorrectas`, las que ya cuenta `ListarSolicitudesFacturaService`.

`solicitudes_facturas` no tiene columnas de importe, impuesto, moneda ni sucursal. La unidad de organización es el departamento. No se publica el alta: `StoreSolicitudFacturaRequest` exige comprobantes de pago en modo pendiente, acepta `archivo_fiscal` y puede generar un enlace público de datos fiscales.

### Cancelaciones y cotizaciones

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/mobile/cancelaciones-cotizaciones` | `cancelaciones_cotizaciones.ver_listado` |
| GET | `/mobile/cancelaciones-cotizaciones/{id}` | el mismo, y `usuarioPuedeVer` |
| POST | `/mobile/cancelaciones-cotizaciones/{id}/solicitar-cancelacion` | `cancelaciones_cotizaciones.solicitar_cancelacion` |

Son filas de `solicitudes_tags` cuyo proceso tiene `categoria_flujo = operativo`. El escritorio las separa en cotización, remisión y cancelación de pedido con el filtro `tipo_operativo` (`COTIZACION`, `REMISION`, `PEDIDO`). La misma forma de solicitud incluye montos, `numero_pedido`, `numero_remision`, `fecha_operacion`, `motivo_operacion`, `solicitar_cotizacion` y estado. Query adicional: `tab`, `proceso_id`, `departamento_id`, `area_id`, `vendedor_id`, fechas y `q`. `metricas`: `pendientes`, `respondidas_hoy`, `incorrectas`.

La cancelación de una factura no es otro modelo: en facturación el tab `CANCELADAS` filtra el estado `Cancelada` de `SolicitudFactura`. La cancelación del folio de tag o del folio operativo es `cancelacion_solicitada_at` más `motivo_cancelacion`. El POST solo registra esa solicitud. Quien confirma usa `solicitudes.cancelar` o `cancelaciones_cotizaciones.cancelar` en el escritorio; esa confirmación no se expuso.

No hay tabla de partidas ni de vigencia de cotización. Se buscó `solicitud_cotizacion`, `cotizaciones_solicitud`, `SolicitudCotizacion`, `partida_cotizacion`, `lineas_cotizacion` y `detalle_cotizacion`. `SnapshotCotizacionSolicitudService` arma un arreglo en memoria y no lo persiste. Tampoco hay columnas de impuesto o moneda en `solicitudes_tags` ni en `solicitudes_facturas`.

Traspaso: folio, piezas, fecha estimada, estados físicos, motivo, cliente, vendedor, departamento, sucursal, almacén origen y estado. El detalle incluye productos (`sku`, `descripcion`, `piezas`). Query `q`, `tab` (`PENDIENTES`, `RESPONDIDAS`, `VERIFICADAS`, `INCORRECTAS`, `ENTREGA_MANANA`), `almacen_origen_id`, fechas. No se envía el archivo de evidencia. Se indica `tiene_evidencia_respuesta`.

### Mensajería

| Método | Ruta | Autorización | Efecto |
| --- | --- | --- | --- |
| GET | `/mobile/mensajeria/conversaciones` | participante | Lista con no leídos y presencia, igual que el JSON web. |
| POST | `/mobile/mensajeria/conversaciones` | persona autenticada | Crea directo o grupo. Body `tipo`, `participante_ids`, `nombre` si es grupo. |
| GET | `/mobile/mensajeria/usuarios` | persona autenticada | Query `q`, `limit`. |
| GET | `/mobile/mensajeria/conversaciones/{id}/mensajes` | `ConversacionPolicy::view` | `mensajes`, `next_cursor`, `has_more`. |
| POST | `/mobile/mensajeria/conversaciones/{id}/mensajes` | `sendMessage` | Texto. Body `contenido`, `reply_to_id` opcional. |
| PUT | `/mobile/mensajeria/conversaciones/{id}/leer` | `view` | Marca leída. |

## Fuera de alcance

Estas operaciones existen en el escritorio y no se publicaron. El motivo es de negocio o de riesgo, no de olvido. El contrato queda propuesto para una iteración que reutilice el servicio ya existente.

| Dominio | Por qué no se implementó | Contrato propuesto, sin implementar |
| --- | --- | --- |
| Alta, edición, envío, cancelación y pagos de pedidos | La máquina de estados, el autoguardado y los pagos tienen efectos de dinero y de inventario. No hay una acción móvil acotada que se pueda exponer sin inventar un flujo. | Seguir usando `CrearPedidoBmaService`, `ActualizarPedidoBmaService` y `EnviarPedidoBmaService` detrás de `control_pedidos.crear`, solo para la persona vendedora dueña del pedido. |
| Preparación, CEDIS, pesaje, guías y autorización de salida | Cambian existencia, traslado y entrega. | Rutas de lectura de la tarea ya vienen dentro del detalle. Una escritura futura debe llamar al servicio de la bandeja correspondiente y exigir su permiso (`control_pedidos.cedis`, `control_pedidos.auditar`, preparación de tienda). |
| Altas de RH, aplicar deducciones, sellar periodo, firmar recibos de nómina | Alteran nómina y guardan firmas de recibos con cálculo. | `POST /mobile/rh/incidencias/{id}/firmar` solo cuando se reutilice `FirmarReciboDeduccionService` y el permiso `rh.recibos.generar`. |
| Crear o modificar existencias, importar inventarios o costos | Escritura masiva o de stock. | No exponer importaciones. Un ajuste unitario futuro debe usar los form requests de almacén y `almacenes.inventarios.gestionar`, limitado a un almacén operable. |
| Asignar, transferir, dar de baja o mantener un activo | Cambia custodia. La firma de recibido sí quedó, porque ya era una acción de la persona asignada. | Reutilizar `AsignarActivoService`, `TransferirActivoService` y `CambiarEstadoActivoService` con sus permisos. |
| Fotos, responsivas PDF y evidencias de resguardo de pedido, salida de RH o traspaso | Hace falta una descarga autenticada. Publicar la ruta de almacenamiento no sustituye ese control. | `GET` autenticado que resuelva el archivo con el mismo alcance de la ficha, sin URL permanente. |
| Alta de solicitud de facturación | `StoreSolicitudFacturaRequest` y `CrearSolicitudFacturaService` exigen comprobantes y pueden importar un archivo fiscal o abrir un enlace público. | No publicar `archivo_fiscal`, `vouchers` ni `enlace_url`. |
| Confirmar cancelación de un folio, responder o verificar una factura | Cambia estado, pago o documento emitido. La solicitud de cancelación sí quedó. | Seguir en el escritorio con `solicitudes.cancelar`, `cancelaciones_cotizaciones.cancelar` y `facturas.verificar`. |
| Contabilidad | Se buscó `App\Models\Contabilidad\ContabilidadConfiguracion` y `ContabilidadController`. Es configuración y corte, no una bandeja de documentos. | No exponer. |
| Cobranza automática | Existe `CobranzaFactura` (`folio`, `monto`, fechas, `pagada`) y `AutoCobranzaController`, `ImportarReporteCobranzaService`, `ConfirmarPagoCobranzaService`. No hay un listado con el aislamiento por persona que usan facturas o solicitudes. | No inventar ese aislamiento. |
| Saldos a favor y comisiones | `SaldosAFavorController` y los permisos de comisiones. Mueven crédito o cálculo de pago. | No exponer. |
| CFDI, XML, PDF, constancia y datos bancarios de una cuenta | Rutas de archivo y secretos. El catálogo `catalogo_bancos` solo tiene nombre, no cuenta. | Los indicadores `tiene_xml` y `tiene_pdf_emitido` bastan. La descarga sigue en `ArchivoFacturaController` del escritorio. |
| Confirmar, cancelar o verificar solicitudes y traspasos | Mueven mercancía o confirman pago. | `PUT` que delegue en el controlador web ya existente, con `traspasos.cedis` o `solicitudes.confirmar_pago`. |
| Adjuntos de mensajería | El alta de archivos tiene otro request y almacenamiento. El texto sí quedó. | `POST /mobile/mensajeria/conversaciones/{id}/adjuntos` reutilizando `AdjuntoMensajeController`. |
| Token de aplicación para estos dominios | Mezclaría el alcance de una integración con el de una persona. | No agregar recursos `api_recursos` hasta definir un subconjunto de campos no sensibles y un permiso de aplicación distinto del permiso humano. |
| Entregas, escalonamiento, medios, integraciones y Gelia AI | Configuración, credenciales o agregados que no son una bandeja móvil clara. | Consulta de solo lectura cuando exista un servicio de listado con aislamiento ya probado. |

## Errores comunes

| Código | Cuándo |
| --- | --- |
| 401 | Token ausente, token de aplicación usado en `/mobile`, o dispositivo revocado. |
| 403 | Falta el permiso, o la consulta del pedido no está autorizada. |
| 404 | El registro no existe o está fuera del alcance (almacén, inventario, activo, solicitud, factura, cancelación operativa, traspaso). |
| 422 | Query de inventario incoherente con la sucursal, o firma con formato inválido. |
| 429 | Límite `api-mobile`. |

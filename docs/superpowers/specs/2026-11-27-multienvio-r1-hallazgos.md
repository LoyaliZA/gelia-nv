# Multienvío nacional — Spike R1: qué hereda un miembro sin simular otra respuesta de pesaje

Fecha: 2026-11-27 · Base: `develop` `0e5be6cb` · Solo lectura de código; sin ejecutar pruebas ni tocar PHP/JS. Rutas relativas a la raíz del repo. `[verificado]` = leído en el archivo y línea citados; `[propuesta]` = inferencia o decisión de diseño que no está escrita en el código.

## 0. Respuesta corta

1. **Un miembro queda «consulta cerrada» copiando `consulta_cerrada_at` (+ `consulta_cerrada_por_id`).** `consultaCerrada()` es solo `consulta_cerrada_at !== null` (`app/Models/ControlPedidos/PedidoBma.php:1159-1162`) [verificado]. No hace falta simular una respuesta de CEDIS: la respuesta es una marca en `pedidos_bma` más filas hijas, y no hay otra tabla que `consultaCerrada()` consulte.
2. **`puedeCerrarConsulta()` debe ser FALSE en el miembro, y lo es** porque devuelve `false` de inmediato si `consultaCerrada()` (`PedidoBma.php:1166-1168`) [verificado]. Es lo correcto: el miembro nace ya cerrado. Si alguien reabre la consulta del miembro (`ReabrirConsultaPedidoBmaService.php:45-51`), entonces `puedeCerrarConsulta()` pasa a depender de `tienePesajeRespondido()` (`PedidoBma.php:1200`, `1038-1041`), es decir de `pesaje_respondido_at` (copiarlo).
3. **Las piezas (`pedido_bma_revisiones_producto`) NO están ligadas a la caja.** La tabla solo tiene `pedido_bma_id` (`PedidoBmaRevisionProducto.php:99-116`; `ResponderPesajePedidoBmaService.php:577`), y la relación pieza-envío no existe en ningún modelo. No se pueden «mover por UUID de caja»: se quedan en el origen (envío 1) o se duplican como lectura. Ver §3.3.
4. **Lo que sí viaja con la caja** son los documentos con `pedido_bma_caja_id` (fotos de contenido del envío y guías por caja) y `pedido_bma_caja_remisiones` (cuelga de `pedido_bma_caja_id`, no hay que reescribirlo).
5. **Hallazgo que bloquea el envío si se olvida:** cada miembro necesita al menos un documento de soporte `pdf_pedido`/`cotizacion` (o, en nacional 2.0, un documento activo con `relacion_tipo = pieza_solicitada_nacional`); `ValidacionCamposPedidoBma.php:100` y `PedidoBma::tieneSoporteSolicitud()` (`PedidoBma.php:1062-1075`) lo exigen [verificado]. Hay que duplicar la fila del documento de soporte para cada miembro (misma `ruta_archivo`).

---

## 1. Condiciones de `consultaCerrada()` / `puedeCerrarConsulta()` en el miembro

| Condición | Fuente | Qué hacer en el miembro |
| --- | --- | --- |
| `consulta_cerrada_at != null` | `PedidoBma.php:1159-1162` [verificado] | Copiar del origen (ya cerrada por Ventas). Esto es todo lo que necesita `consultaCerrada()`. |
| `consulta_cerrada_por_id` | `CerrarConsultaPedidoBmaService.php:141-142` [verificado] | Copiar. |
| `consulta_actualizacion_pendiente = false` | `PedidoBma.php:1182`; escrito `false` en `CerrarConsulta...:143` y `Responder...:316` [verificado] | Copiar (debe ser `false`; si el origen la tuviera en `true` la consulta no estaría cerrada). |
| Estatus en fase `PESAJE_RESPONDIDO` (u otra de `esEditablePorVendedora`) | `PedidoBma.php:1612-1622`; cierre mantiene el estatus (`CerrarConsulta...:127-132, 140`) [verificado] | Copiar `catalogo_estatus_pedido_id` del origen. Sin esto `esEditablePorVendedora()` es falso: el miembro no puede enviarse (`EnviarPedidoBmaService.php:46-48`) ni reabrirse (`ReabrirConsulta...:29`). |
| `estatus_envio = 'pesaje_listo'` | `Responder...:310`; constante `PedidoBma.php:56` [verificado] | Copiar. |
| `pesaje_respondido_at` / `pesaje_respondido_por_id` | `tienePesajeRespondido()` `PedidoBma.php:1038-1041`; lo exige el envío `ValidacionCamposPedidoBma.php:25, 48-54` [verificado] | Copiar. No es una «nueva respuesta»: es la misma marca temporal del origen. |
| `esConsultaDisponibilidadCedis()` = false | `PedidoBma.php:1204-1210` (exige `modalidad_entrega=tienda` y `area_revision=control_pedidos`) [verificado] | En nacional es falso; mantener `solicitud_preparacion_json.modalidad_entrega = 'nacional'`. |
| `CapturaNacional::es($pedido)` | `Support/ControlPedidos/Nacional/CapturaNacional.php:12-19` (`modalidad_entrega='nacional'` y `captura_nacional='compartida'`) [verificado] | Conservar ambas claves en el JSON del miembro; sin ellas el miembro cae a rutas legadas (`Responder...:104, 189`; `Sincronizar...:54`). |

Consecuencia: **no hay que crear filas de «respuesta de pesaje»**. Basta copiar las columnas anteriores y recalcular los agregados de la caja (§2, bloque «recalcular»).

`LecturaConsultaNacional::de()` (`Support/ControlPedidos/Nacional/LecturaConsultaNacional.php:13-123`) construye la vista de consulta por pedido: `consulta_cerrada` (55), `puede_cerrar_consulta` (58), `cajas` (74-89), `revisiones_producto` (90-99), `documentos` (51), `soporte_pedido` (39-40), `version_consulta = max(historial.id)` (73) [verificado]. Para un miembro, `revisiones_producto` saldrá vacío si no se duplican (§3.3) y `version_consulta` será 0 si no tiene historial (crear al menos una fila de historial al materializarlo).

---

## 2. Tabla A — columnas de `pedidos_bma` (fuente: `$fillable` `PedidoBma.php:112-204`, `$casts` 206-246, y columnas no-fillable en migraciones)

La lista de columnas es el `$fillable` más cuatro columnas fuera de él que añaden migraciones: `revision_registro`, `captura_creacion_clave`, `captura_creacion_hash`, `captura_creacion_base` (`database/migrations/2026_10_15_000001_add_revision_registro_pedidos.php:10-13`) [verificado]. `folio` es `unique` (`2026_07_09_150000_create_modulo_control_pedidos_tables.php:62`), `captura_creacion_clave` es `unique` (`…000001…:11`) [verificado]. Los únicos índices únicos de `pedidos_bma` hallados son esos dos.

### A.1 Copiar tal cual del origen (identidad comercial y estado post-consulta)

| Columna(s) | Motivo |
| --- | --- |
| `origen_solicitud`, `departamento_operacion_id`, `documento_inicial`, `contacto_nombre_snapshot`, `contacto_telefono_snapshot`, `fecha`, `vendedor_id`, `cliente_id`, `origen_id`, `tipo_operacion_envio_id`, `almacen_id`, `catalogo_banco_id`, `prioridad_md` | Identidad comercial; `vendedor_id` lo exige `puedeMutarComoVendedora` (`CerrarConsulta...:113`, `EnviarPedido...:51`); `almacen_id`, `cliente_id`, `origen_id` los valida el envío (`ValidacionCamposPedidoBma.php:32-40`) [verificado]. `departamento_operacion_id` lo valida `ContextoDepartamentoPedido` al crear (`CrearPedidoBmaService.php:76-80`). |
| `cliente_direccion_id`, `codigo_postal`, `domicilio_entrega`, `envia_otra_persona`, `envia_a_otra_persona`, `sucursal_destino_id`, `catalogo_zona_id`, `catalogo_tipo_guia_id`, `catalogo_paqueteria_id`, `catalogo_envio_tienda_id`, `envio_tienda_otro` | Destino y paquetería (el miembro viaja a la misma dirección con la misma paquetería). Sin `cliente_direccion_id` el envío exige snapshot manual (`EnviarPedido...:77-93`) [verificado]. |
| `solicitud_preparacion_json` | Copiar con cuidado: ver A.3. |
| `catalogo_estatus_pedido_id`, `estatus_envio`, `pesaje_solicitado_at`, `pesaje_respondido_at`, `pesaje_respondido_por_id`, `consulta_cerrada_at`, `consulta_cerrada_por_id`, `consulta_actualizacion_pendiente`, `estado_fisico_general`, `comentario_fisico_general`, `tiene_observaciones_fisicas`, `motivo_repesaje` | Estado post-consulta; ver §1. `Responder...:310-319` escribe exactamente estos campos al responder [verificado]. |
| `aplica_seguro`, `cliente_proporciona_guia`, `envio_por_cobrar`, `anexar_remision`, `es_resguardo`, `envio_solo_resguardos`, `notas_resguardos_externos` | Modalidad. `aplica_seguro` es fuente de verdad del seguro (`CalcularTotalesEnvio...:163-164`) [verificado]. Con `aplica_seguro=true` + varias cajas `BarreraSeguroCajaNacional` bloquea; con 1 caja por miembro pasa (`BarreraSeguroCajaNacional.php:20-24`) [verificado]. |
| `origen_solicitud`, `comentarios_drive` | Texto de contexto; copiar. |

### A.2 NO copiar (poner NULL/0/valor inicial o generar nuevo)

| Columna(s) | Valor en el miembro | Motivo |
| --- | --- | --- |
| `id`, `folio` | nuevo folio con `GenerarFolioPedidoBmaService::ejecutar()` | `folio` es `unique` (`…create_modulo…:62`); el servicio se llama en `CrearPedidoBmaService.php:81-83` [verificado]. |
| `folio_remision`, `numero_remision` | Folio Wizerp de la caja (del borrador) / NULL | El folio Wizerp no se repite en pedidos activos (`PedidoBmaRequestBase.php:84-101`); el envío exige `folio_remision` (`ValidacionCamposPedidoBma.php:29-31`) [verificado]. |
| `captura_creacion_clave/hash/base` | NULL | `captura_creacion_clave` es único (`…000001…:11`); copiarlo violaría la unicidad. |
| `revision_registro` | 0 | Contador de edición por pedido (`LecturaConsultaNacional.php:33`). |
| `pago_validado_at/por_id`, `esperando_pago_at`, `saldo_a_favor` | NULL / 0 | Pagos y SAF son del origen (`CrearPedido...:99-101` reserva SAF por pedido). Nivel 4 del diseño: no se clonan montos. |
| `empacado_at/por_id`, `retraso_*_alertado_at`, `detalle_incidencia_empaque`, `incidencia_empaque_*` | NULL | Hechos operativos posteriores. |
| `numero_rastreo`, `guia_subida_at`, `guia_retraso`, `guia_corregida_*` | NULL | La guía/rastreo vive por caja (`pedido_bma_cajas.numero_rastreo`); el nivel 4 regulariza si ya existen. |
| `campos_incorrectos`, `detalle_error_datos`, `error_datos_at/por_id`, `motivo_rechazo` | NULL | Cola de errores por pedido. |
| `motivo_cancelacion`, `comentario_cancelacion`, `resolucion_financiera_cancelacion`, `cancelado_*`, `eliminacion_registro_*` | NULL | Hechos propios. |
| `pedido_principal_id` | NULL | Los complementos (`pedido_principal_id`, `LecturaConsultaNacional.php:20-27`) cuelgan del origen. |
| `resguardo_apartado_at/por_id`, `detalle_resguardo_apartado` | NULL | Hechos propios del resguardo. |

### A.3 Recalcular (no copiar) — son agregados de las cajas del propio miembro

| Columna(s) | Cómo | Fuente |
| --- | --- | --- |
| `numero_cajas` | 1 | `Responder...:277, 333` [verificado] |
| `peso_real_kg`, `peso_volumetrico_kg`, `peso_cobrado_guia_kg` | los de su caja | `Responder...:330-332`; el envío exige `peso_real_kg` y `numero_cajas` no nulos (`ValidacionCamposPedidoBma.php:114-121`) [verificado] |
| `catalogo_tipo_caja_id` | el tipo de su caja | `Responder...:334`; exigido en `ValidacionCamposPedidoBma.php:117` [verificado] |
| `total_mercancia` | valor por caja del borrador (suma = total vigente) | diseño §4 paso 2; el envío exige `total_mercancia > 0` (`ValidacionCamposPedidoBma.php:97-99`) |
| `costo_envio`, `costo_seguro`, `total_a_cobrar` | NULL en el miembro (cotización por miembro, nivel 2) y recalcular el origen | `Responder...:298-306, 348-356`; el origen debe recalcular también tras perder cajas |

**El origen también hay que recalcular** (menos cajas): `numero_cajas`, pesos, `catalogo_tipo_caja_id`, `total_mercancia`, `folio_remision`, y vaciar `cotizacion_nacional` del JSON. Hacerlo con las mismas fórmulas que `Responder...:277-334` y luego `CalcularTotalesEnvioPedidoService::aplicarAlPedido()` (`CalcularTotalesEnvio...:153-179`) [verificado].

### A.4 `solicitud_preparacion_json` — claves a mantener en el miembro

Claves nacionales que el modelo proyecta (`PedidoBma.php:270-276`): `captura_nacional`, `piezas_nacionales`, `piezas_nacionales_vigentes`, `respuestas_piezas_nacionales`, `pago_solo_mercancia_nacional`, `cambio_paqueteria_cobertura`, `cotizacion_nacional`, `ajustes_nacionales`, `invalidacion_guia`, `complementos_nacionales`, `complemento_nacional`, `respuesta_complemento_nacional`, `vinculo_resguardo`, `cambio_a_tienda`, `cancelacion_nacional` [verificado].

| Clave | En el miembro |
| --- | --- |
| `modalidad_entrega`, `captura_nacional` | **Copiar** (condición de `CapturaNacional::es`). |
| `cotizacion_nacional` | **Quitar** (se cotiza por miembro). `Responder...:347` también la elimina al ajustar piezas. |
| `pago_solo_mercancia_nacional`, `invalidacion_guia`, `vinculo_resguardo`, `complementos_nacionales`, `complemento_nacional`, `respuesta_complemento_nacional`, `cambio_a_tienda`, `cancelacion_nacional`, `ajustes_nacionales` | **Quitar**: son hechos del origen (pago, vínculo, complemento, cancelación). Si el origen los tiene, es nivel 4 → regularización. |
| `piezas_nacionales`, `piezas_nacionales_vigentes`, `respuestas_piezas_nacionales` | **Decisión abierta (§3.3):** si el miembro conserva `piezas_nacionales_vigentes`, un repesaje del miembro exigirá responder todas las piezas (`Responder...:115-123`, «Responda cada pieza identificada por Ventas antes de enviar el pesaje») aunque las piezas no sean de su caja. `[propuesta]` dejar estas tres claves como lista vacía en el miembro y leer las piezas desde el origen en la vista. Ojo: `piezasVigentes()` usa `??` (`CapturaNacional.php:26-28`), así que `[]` en `piezas_nacionales_vigentes` se respeta. |

---

## 3. Tabla B — relaciones a MOVER por UUID de caja

Regla de movimiento: identificar la caja por `pedido_bma_cajas.uuid_operativo` y mover la fila `pedido_bma_cajas` con `UPDATE pedido_bma_id`. La unicidad es `(pedido_bma_id, uuid_operativo)` (`2026_08_24_140000_fase2_envios_cajas_uuid_costos.php:60`) [verificado]: no choca al cambiar de pedido. Los disparadores de comisiones registran el cambio automáticamente (`2026_11_25_210000_registrar_cambios_de_fuentes_de_comisiones.php:12, 41-56`) [verificado]: no hace falta tocar `comisiones_empaque_unidades`.

### 3.1 `pedido_bma_cajas` (la caja)

Mover: `UPDATE pedido_bma_cajas SET pedido_bma_id = <miembro>` y reasignar `orden` a 0 dentro del miembro (`Sincronizar...:68, 80`). Conservar `uuid_operativo`, medidas, pesos, `estado_operativo`, `numero_rastreo`, costos, `catalogo_tipo_guia_id`, `estatus_recoleccion` (todos en `PedidoBmaCaja.php:23-51`) [verificado].
Precaución: **`SincronizarCajasPedidoBmaService::ejecutar` retira (`ESTADO_RETIRADA`) toda caja activa del pedido que no venga en `$lineas`** (`Sincronizar...:110-143`) y exige `motivo_retiro`. Tras el split, el origen tiene solo su caja; cualquier repesaje del origen/miembro debe enviar solo las cajas de ese pedido (la UI del miembro ya lo hará porque carga `pedido->cajas`). Mover cajas con `UPDATE` directo evita ese retiro [verificado].

### 3.2 `pedido_bma_documentos` — qué mover y qué duplicar

El modelo trae `pedido_bma_id`, `pedido_bma_caja_id` (nullable FK, `nullOnDelete`; `2026_08_24_140000…:90-93`), `relacion_tipo`, `relacion_id` (`PedidoBmaDocumento.php:38-54`) [verificado].

| Documento | Cómo identificarlo | Acción |
| --- | --- | --- |
| Foto de contenido del envío | `tipo='evidencia_condicion'` y `relacion_tipo='envio_caja'` y `relacion_id = caja.id`; también `pedido_bma_caja_id = caja.id` (`Responder...:517, 529`; promoción de sesión `SesionEvidenciaCedisService.php:233-290` crea `relacion_tipo=envio_caja`, `relacion_id`=id de caja) [verificado] | **Mover**: `UPDATE pedido_bma_documentos SET pedido_bma_id=<miembro> WHERE pedido_bma_caja_id=<caja> OR (relacion_tipo='envio_caja' AND relacion_id=<caja>)`. Los documentos antiguos pueden tener solo `relacion_id` (backfill en `…fase2_envios_cajas_uuid_costos.php:99-117`). `cajaTieneFoto()` (`Responder...:979-998`) los busca por `pedido->documentos()` + `relacion_id=caja.id`: si no se mueven, un repesaje del miembro exigirá fotos nuevas (`Responder...:189-192`). La `ruta_archivo` no cambia (`…store('pedidos_bma/'.$pedido->id…)`, `Responder...:518`): solo la fila. |
| Guías PDF por caja | `tipo='guia'`, `relacion_tipo in ('guia_caja_avance','guia_caja_confirmada')`, con `pedido_bma_caja_id` (`GuiasPdfPorCaja.php:12-13, 36-42`; `GestionarGuiaPdf...:192`) [verificado] | **Mover** por `pedido_bma_caja_id`. Quedan fuera si `aplica_seguro` (diseño §2); en ese caso no deberían existir. |
| Soporte de la solicitud (`pdf_pedido`, `cotizacion`) y piezas solicitadas (`relacion_tipo=pieza_solicitada_nacional`) | `PedidoBma.php:1062-1075`, `CapturaNacional.php:10` [verificado] | **Duplicar** una fila por miembro (misma `ruta_archivo`) para que `tieneSoporteSolicitud()` y `tienePdfPedido()` pasen (`ValidacionCamposPedidoBma.php:100`; `ActualizarConsulta...:35`; `RegistrarAjusteConsultaNacional.php:67`; `SolicitarPesaje...:91`). El origen se queda con los suyos. |
| Foto general del lote | `relacion_tipo='revision_general'` (`Responder...:366-376`) | **No tocar** (origen): no es de una caja; `tieneLoteVigente` (`Responder...:968-977`) solo se consulta en modo «solo revisiones» (tienda). |
| Fotos de pieza | `relacion_tipo='revision_producto'`, `relacion_id = revision.id` (`Responder...:411-419`; `PedidoBmaRevisionProducto::evidencias()` `PedidoBmaRevisionProducto.php:144-149`) | **No tocar** (siguen a la revisión; ver §3.3). |
| Comprobantes, remisión, evidencia de bulto, apartado | `tipo in (comprobante, remision, evidencia_bulto_empaque, evidencia_ticket_bulto_empaque, evidencia_apartado)` (`PedidoBmaDocumento.php:12-23`) | **No tocar**: hechos de pago/remisión/empaque del origen; si ya existen es nivel 4. |

### 3.3 `pedido_bma_revisiones_producto` (piezas) — NO se mueve por caja

- La tabla solo se liga por `pedido_bma_id` (`PedidoBmaRevisionProducto.php:99-116`; creación `Responder...:577`) [verificado]. No existe `pedido_bma_caja_id` ni uuid de caja en la pieza; la pieza se identifica por `client_uuid` (`Responder...:117-134`) que viaja en `solicitud_preparacion_json.respuestas_piezas_nacionales` (`Responder...:341-358`) [verificado].
- Qué la consume: `LecturaConsultaNacional.php:90-99`; `PedidoBma::tieneSinExistenciaAbierta()` (`PedidoBma.php:1655-1664`) que bloquea el envío (`EnviarPedido...:55`, `assertSinExistenciaAtendida` `PedidoBma.php:1666-1673`) [verificado].
- Estado de cierre: para cerrar la consulta (`CerrarConsulta...:117`) no hay chequeo de revisiones en `puedeCerrarConsulta()` para nacional; las resoluciones se resuelven antes. En un origen con consulta cerrada **no hay `sin_existencia` abierto** (el envío lo comprobaría). `[propuesta]`: por eso el miembro no necesita revisiones para enviarse.
- `[propuesta]` recomendada: **dejar las revisiones en el origen** (envío 1) y no moverlas; en la vista del miembro mostrar las piezas desde el origen (solo lectura, mismo grupo `multienvio_nacional_id`). Alternativa más cara y riesgosa: duplicar filas con `retirada_at=NULL` y las fotos por `relacion_id`, lo que duplica archivos y agrega filas que podrían reabrirse en `sin_existencia` de forma independiente. Decidir en la Tarea 6/7.
- **Riesgo a cubrir con prueba:** repesaje del origen. `Responder...:115-123` exige responder cada pieza vigente; el origen conserva todas las piezas pero solo tiene su caja. No es un bug nuevo, pero la UI de repesaje del origen deberá aclarar que las piezas son del pedido original.

### 3.4 `pedido_bma_caja_remisiones` (`PedidoBmaCajaRemision`)

Columnas: `pedido_bma_caja_id` (FK `cascadeOnDelete`), `pedido_bma_id` (nullable), `solicitud_id` único, `exclusividad_activa` único (`2026_11_20_191500…:11-29`) [verificado].
- **Se mueve sola con la caja** (la FK es `pedido_bma_caja_id`): no hay nada que reescribir.
- **No reescribir `pedido_bma_id` de esta tabla.** En `AsignarRemisionCajaNacional.php:41-75` el `pedido_bma_id` es el pedido remisionado (resguardo/remisión) que se asigna a la caja: `$origen = PedidoBma::find($pedidoId)` y `exclusividad_activa = 'p:'.$pedidoId` [verificado]. No es el dueño de la caja.
- Cambia la validación «una remisión no se divide entre cajas» solo si dos cajas del grupo comparten el mismo `pedido_bma_id`/folio; el valor de `exclusividad_activa` único lo garantiza (`AsignarRemision...:47-55`).
- `BarreraSeguroCajaNacional` / `DistribuirSeguroCajaNacional` leen estas filas por `pedido_bma_caja_id` y solo con más de una caja activa (`BarreraSeguroCajaNacional.php:20-31`; `DistribuirSeguro...:43-47`): con 1 caja por miembro no actúan [verificado].
- `evidencia_documento_id` apunta a un `pedido_bma_documentos`. Si esa evidencia se mueve con la caja (fila de §3.2), el vínculo sigue válido; si no, queda colgando de otro pedido (válido, es solo FK a documento). Revisar que el documento de evidencia, si existe y es de la caja, se mueva con ella.

---

## 4. Tabla C — a NO tocar / reapuntar / duplicar como solo lectura

Lista cerrada: todas las tablas con `pedido_bma_id` o `pedido_bma_caja_id` en `database/migrations` (búsqueda `grep` + inspección de `Schema::create/table`). Ninguna queda «por ver».

| Tabla | Clave | Acción | Motivo y fuente |
| --- | --- | --- | --- |
| `pedido_bma_cajas` | `pedido_bma_id` | **Mover** (ver §3.1) | Contiene las cajas; el único cambio es el dueño. |
| `pedido_bma_documentos` | `pedido_bma_id`, `pedido_bma_caja_id` | **Mover** foto de envío + guías; **duplicar** soporte; resto no tocar (§3.2) | `PedidoBmaDocumento.php:38-54`. |
| `pedido_bma_caja_remisiones` | `pedido_bma_caja_id` (+`pedido_bma_id`) | **Mueve sola; no reescribir** (§3.4) | `…create_pedido_bma_caja_remisiones…:11-29`. |
| `pedido_bma_revisiones_producto` | `pedido_bma_id` | **Origen conserva; solo lectura en el miembro** (§3.3) | Sin liga a caja. |
| `pedido_bma_sesiones_evidencia` y `pedido_bma_sesion_evidencia_fotos` | `pedido_bma_id`; fotos por `sesion_id` | **No tocar** | La sesión se cierra al responder: `promover()` pasa `estado` a `CERRADA` (`SesionEvidenciaCedisService.php:287`) y copia las fotos a `pedido_bma_documentos` (`:233-290`) [verificado]. Los documentos resultantes se mueven (§3.2). Las sesiones `pendiente/activa` solo existen durante un pesaje abierto (`:63-64`); no hay en un pedido con consulta cerrada. Una sesión abierta en el origen es una precondición de `ConfirmarMultienvio`. |
| `pedido_bma_referencias` | `pedido_bma_id` | **Origen conserva su fila; el miembro genera la suya** | `PedidoBmaReferencia::visible()` (`PedidoBmaReferencia.php:45-71`) muestra la referencia vigente distinta de `REMISION`; la fila de `folio_remision` se registra con `registrar()` (`:16-40`, uso en `GestionarRemisionPedidoBmaService.php:111`). `[propuesta]`: al fijar el folio Wizerp de cada miembro, registrar `PedidoBmaReferencia::registrar($miembro, TIPO_PEDIDO, folio, …)`; la del origen debe actualizarse al folio de su caja para no mostrar el folio global. |
| `pedido_bma_direcciones` | `pedido_bma_id` | **Duplicar si hay snapshot manual** | Si el pedido usa `cliente_direccion_id` el snapshot se crea al enviar (`EnviarPedido...:109-113`; `CrearSnapshotDireccionPedido.php:17-48`). Si es dirección manual (`cliente_direccion_id` nulo, `:22-25, 96-141`) el miembro necesita su propia fila vigente (`EnviarPedido...:77-93`) [verificado]. |
| `pedido_bma_historial_estados` | `pedido_bma_id` | **No tocar; crear filas nuevas en miembros** | `RegistrarHistorialPedidoService::ejecutar` (`:16`). `version_consulta` usa `max(id)` del pedido (`LecturaConsultaNacional.php:73`): el miembro debe tener al menos un evento. |
| `auditorias_pedidos_bma` | `pedido_bma_id` | **No tocar; escribir nuevas** | Historia propia por pedido (`AuditoriaPedidoBma.php`). |
| `pedido_bma_pagos` | `pedido_bma_id` | **No tocar** | Pagos son del origen; el voucher compartido es de la Fase 3 (diseño §2, §6 nivel 3). Si existen pagos validados, nivel 4. |
| `pedido_bma_cierres_pago` | `pedido_bma_id` | **No tocar** | Cierre financiero; nivel 4 (diseño §6). |
| `saf_pedido_aplicaciones`, `saf_creditos`, `saf_incidencias`, `saf_movimientos` | `pedido_bma_id` | **No tocar** | Saldo a favor pertenece al origen; reservado al crear (`CrearPedido...:99-101`). |
| `pedido_bma_bultos_empaque`, `operacion_empaque_miembros`, `comisiones_empaque_unidades` | `pedido_bma_id` | **No tocar** | Posteriores a la consulta (empaque). Precondición de `ConfirmarMultienvio`: no existen; si existen, nivel 4. Los disparadores de `…registrar_cambios_de_fuentes_de_comisiones.php:12-22` anotan el cambio de dueño de la caja (`pedido_bma_cajas`) sin tocar esta tabla. |
| `pedido_bma_tareas_preparacion`, `pedido_bma_alertas_preparacion` | `pedido_bma_id` | **No tocar** | Rutas de Tienda/CEDIS-Tienda; el nacional no las usa en el flujo (`Responder...` no las consulta; `PedidoBma.php:1186-1198` solo en `enRutaPreparacionTienda`). |
| `pedido_bma_anexos_envio`, `pedido_bma_caratulas` | `pedido_bma_id` | **No tocar** | Anexos y carátulas de Tienda/municipal (`FUENTES` de la migración de comisiones `:20-21`). |
| `pedido_bma_cancelaciones_operativas` | `pedido_bma_id` | **No tocar** | Debe no haber cancelación activa (`PedidoBma.php:896-901`). |
| `pedido_bma_errores` | `pedido_bma_id` | **No tocar** | Cola de errores por pedido; el miembro nace sin errores. |
| `pdv_resguardos`, `pdv_resguardo_bultos`, `pdv_resguardo_entregas` | `pedido_bma_id` | **No tocar** | Custodia/PDV posterior (`EventosCustodiaSucursalNacional.php:151, 178`); nivel 4 si existe. |
| `contabilidad_pedido_lineas` | `pedido_id` | **No tocar** | Es de otro módulo (contabilidad); la columna `pedido_id` no apunta a `pedidos_bma` (`2026_06_24_155525_add_tipo_devolucion_to_contabilidad_pedido_lineas.php:14`). |

Tablas fuera del grep pero relevantes: `pedido_bma_cumplimiento_fisico` cuelga de `pedido_bma_tarea_preparacion_id` (migración de comisiones `:17`) y por tanto sigue a las tareas de preparación (no tocar).

---

## 5. Qué no se pudo verificar / decisiones abiertas para la Tarea 6

1. **[no verificado]** cómo `ListarPedidosBmaService`, reportes de ventas y `LecturaFinancieraPedido` agregan `total_mercancia` por pedido (riesgo R2 del diseño); no se leyó. El origen baja `total_mercancia`; solo afecta si aún no hay cierre.
2. **Piezas (§3.3):** decidir si el miembro lee piezas del origen (`[propuesta]`, recomendado) o recibe copias.
3. **Referencias (§4):** confirmar con Wizerp/Ventas si el miembro registra `TIPO_PEDIDO` o `TIPO_REMISION` para su folio.
4. **`pedido_bma_caja_remisiones.evidencia_documento_id`:** si apunta a un documento de caja, asegurar que se mueva con la caja.
5. **No se ejecutó** ninguna prueba; los riesgos de §3.1 (retiro por `Sincronizar...`) y §3.3 (repesaje del origen) deben cubrirse con pruebas en la Tarea 6/7.

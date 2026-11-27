# Multienvío nacional con seguro — diseño técnico (Fase 0)

Fecha: 2026-11-27 · Base: `develop` `0e5be6cb` · Especificación funcional: `GELIA-NV_plan_multienvio_nacional_Claude_Code.md` (ME-01 a ME-12, A01 a A22). Este documento solo concreta el *cómo*; no cambia ninguna decisión funcional.

## 1. Decisión tomada con el usuario

**Reutilizar el pedido original como envío 1** y materializar N-1 expedientes (`PedidoBma`) para las demás cajas. El resto de las decisiones técnicas de abajo son propuestas mías, sujetas a esta aprobación.

## 2. Qué existe hoy y qué reutilizamos

| Pieza | Hecho verificado | Consecuencia |
| --- | --- | --- |
| `PedidoBma` | Entidad financiera/logística: `total_mercancia`, `folio_remision` (folio Wizerp), `numero_remision`, `pago_validado_at`, `aplica_seguro`, `consulta_cerrada_at`, `pagosExhibicion()`. | Cada envío es un `PedidoBma`. No se crea otro motor financiero. |
| `PedidoBmaCaja` | `uuid_operativo`, medidas, flete/seguro, rastreo, `estado_operativo`, `pedido_bma_id`. | La caja conserva su UUID; solo cambia `pedido_bma_id` al pasar a su nuevo expediente. |
| `BarreraSeguroCajaNacional` | Solo actúa con más de una caja activa en el pedido. | Con una caja por expediente pasa sola; no se toca. |
| `DistribuirSeguroCajaNacional` / `CalcularSeguroPedidoService` | DHL 2% + $51 por caja; Estafeta/FedEx 2.5%; base = flete + mercancía. | Con una caja por expediente el cálculo ya es por envío. Sin fórmulas nuevas. |
| `RegistrarPagoPedidoBmaService` | Referencia duplicada se busca por `pedido_bma_id` (líneas ~128-137). | El mismo voucher en miembros distintos ya es posible; se fija con prueba (también en actualizar/sustituir). |
| `GenerarFolioPedidoBmaService` | Folios `PBMA-AAAA-NNNNN`; excluye `-C%` (anexos). | Los miembros nuevos usan `ejecutar()` normal. El anexo no se reutiliza como agrupador. |
| `PedidoBmaRequestBase:101` | El folio Wizerp no puede repetirse en pedidos activos. | El folio original solo pasa a un miembro por confirmación explícita. |
| `GuiasPdfPorCaja` (co-desarrolladora) | `aplica()` exige `! aplica_seguro`. | Los miembros con seguro quedan fuera de ese flujo. Al activar desde «sin seguro» las guías `guia_caja_avance/confirmada` viajan con su caja (`pedido_bma_caja_id`). |
| API móvil | Toda ruta nueva de `control-pedidos` se espeja en `routes/api/mobile-control-pedidos.php` o se excluye en `config/api_paridad.php`; `MobileEspejoRutasTest` lo vigila. | Cada ruta de multienvío se espeja desde el primer commit. |

## 3. Modelo de datos (migraciones aditivas, todo nullable)

**`multienvios_nacionales`** (agrupador, sin importes propios)
`id`, `uuid` único, `pedido_origen_id` (único, FK a `pedidos_bma`), `revision_consulta` (huella de la respuesta CEDIS usada), `estado` (`pendiente_decision`, `estandar_sin_seguro`, `configurando`, `activo`, `regularizacion_pendiente`), `decidido_por_id/at`, `activado_por_id/at`, `snapshot_origen_json` (folio GELIA, folio Wizerp, `total_mercancia`, ids de documentos de soporte, importe de referencia), `borrador_json` (asignación folio/valor/contenido por caja mientras se configura), `regularizacion_json`, timestamps.

**`pedidos_bma`** gana `multienvio_nacional_id` (FK nullable, índice) y `multienvio_orden` (tinyint nullable). Único `(multienvio_nacional_id, multienvio_orden)`. Los miembros se consultan por esta columna: evita un join por fila en `ListarPedidos*` y evita N+1. Pedidos históricos quedan en NULL.

**Unicidad de caja:** una caja activa pertenece a un solo pedido (ya lo garantiza `pedido_bma_id`); se añade verificación en el servicio y una prueba de invariante. La identidad original (`uuid_operativo`) se guarda en `borrador_json`/snapshot para reconciliar repesajes.

**Estado global = proyección.** `estado` del grupo solo describe configuración/decisión. Pagos, remisión, empaque, guía y salida se calculan leyendo los miembros (X/N y estado mixto).

## 4. Servicios (nombres orientativos, en `app/Services/ControlPedidos/Nacional/Multienvio/`)

- `EvaluarMultienvioNacional`: elegible si es Nacional 2.0 (`CapturaNacional`), consulta cerrada vigente, ≥2 cajas activas y modalidad ordinaria. Devuelve `{elegible, n_cajas, decision, estado}`. Es lo que lee el modal de cotización (ME-02/03).
- `RegistrarDecisionMultienvio`: idempotente; crea la fila del agrupador con `estandar_sin_seguro` o `configurando`. Bloquea el pedido origen (`lockForUpdate`) y revalida revisión.
- `GuardarBorradorMultienvio`: persiste folio Wizerp, valor y contenido por caja sin crear pedidos.
- `ConfirmarMultienvio` (transacción, `lockForUpdate` sobre origen y agrupador):
  1. Revalida consulta vigente, UUID de cajas activas, permisos del actor (Ventas propietaria) y que no hay hechos irreversibles (si los hay, deriva a regularización, ver §6).
  2. Valida folios Wizerp únicos y que la suma de valores por caja = `total_mercancia` vigente, en centavos, o ajuste comercial explícito.
  3. El **origen se queda con la caja de menor `orden`**: se le baja `total_mercancia` y `folio_remision` a lo de esa caja y se marca `multienvio_orden=1`. Esto es la regla A.
  4. Crea N-1 `PedidoBma` copiando identidad comercial (cliente, vendedora, almacén, dirección/snapshot, paquetería, modalidad, estado post-consulta, `consulta_cerrada_*`) **sin copiar** pagos, remisiones, SAF ni cierres. Mueve a cada uno su caja, sus documentos ligados a esa caja y su respuesta de pesaje por UUID.
  5. Historial en origen y miembros; evento de actualización en vivo.
  Repetir la llamada con el agrupador ya `activo` devuelve el estado actual sin crear nada.
- `SolicitarActivacionTardiaMultienvio` / `RegularizarMultienvio`: ver §6.

## 5. Interfaz

Todo dentro de lo existente (`FormularioRegistrarNacional`, `ContinuacionEnvioNacional`, `ModalResponderPesaje`, `ModalDetalleCedis`, `ModalDetalleDelegado`, Auditar). Patrones de `DESIGN.md`; sin panel externo. Lectura por un solo endpoint de grupo con todos los miembros (sin una petición por caja).

- Ventas: tras cerrar consulta, tarjeta «Multienvío detectado» antes de la cotización; después, selector de envío 1/N con resumen común.
- Auxiliar/CEDIS/Delegado: grupo expandible por fila; la búsqueda por folio, remisión o rastreo abre el miembro correcto; acciones siempre por miembro.
- Contadores: operaciones agrupadas y envíos pendientes se calculan por separado.

## 6. Hechos previos (activación tardía)

Se implementa por niveles, de menor a mayor riesgo, y cada nivel se prueba antes del siguiente:

1. Sin cotización/pago/remisión/guía: conversión directa (§4).
2. Cotización guardada: se invalida y se recalcula por miembro.
3. Pago capturado sin validar: **decisión del usuario (27-nov-2026)** — en la Fase 1 va a `regularizacion_pendiente` como los demás hechos; no se mueve ni se clona nada y el voucher se conserva intacto en el origen. La reasignación a cada envío con el flujo autorizado de revisión y el voucher compartido se implementa en la Fase 3. (Sustituye la redacción anterior «se conserva en el envío 1 y continúa», que dejaba el pago completo contra un solo envío.)
4. Pago validado / cierre / SAF, remisión emitida, guía asignada, caja empacada, salida parcial: el grupo pasa a `regularizacion_pendiente`; se registra solicitud y motivo, se bloquean solo las acciones incompatibles y se muestra el paso necesario. **No se mueven ni reescriben hechos financieros ni se presume cancelación en Wizerp.** Si falta una política, se pregunta al usuario en ese momento.

## 7. Riesgos y puntos abiertos

- **R1 (el principal):** qué estructuras de respuesta de pesaje/consulta hay que copiar o reapuntar para que cada miembro quede «consulta cerrada» sin simular una respuesta nueva (`LecturaConsultaNacional`, `PedidoBmaRevisionProducto`, `PedidoBmaSesionEvidencia`, `puedeCerrarConsulta()`). Se resuelve con un spike de lectura al inicio de la Fase 1, antes de escribir `ConfirmarMultienvio`.
- **R2:** consumidores de `pedido_bma` que cuentan o suman pedidos (reportes de ventas, pagos, SAF, tarjetas de Auditar) no deben ver un expediente «fantasma». Con A no hay fantasma: el origen es un miembro real. Se revisa que la baja de `total_mercancia` del origen no desordene reportes históricos ya cerrados (solo aplica si el origen aún no tenía cierre).
- **R3:** pruebas de MySQL. Hay que crear una base exclusiva en `gelianv-mysql-1` (p. ej. `gelia_multienvio_test`) y confirmar permisos; no se usa `gelianv_db`.
- **R4:** código de prueba. El contenedor de desarrollo monta `/home/ti-admin/gelianv` (otro checkout). Hay que copiar los archivos modificados antes de cada corrida; no se hace commit ni push en ninguno.
- Baseline previo a cambios (contenedor de desarrollo, SQLite): 109 pruebas dirigidas, 108 pasan; falla `NacionalAltaConsultaTest > alta mixta` (422 por folio `WIZ-MIXTO` duplicado), preexistente.

## 8. Plan por fases (resumen)

0. Este documento + spike R1. 1. Migraciones, modelo, `Evaluar/Registrar/Guardar/Confirmar` + pruebas de dominio. 2. Ventas (aviso, configuración, cotización por miembro). 3. Pagos con comprobante compartido, Auxiliar/remisiones, activación tardía niveles 2-4. 4. CEDIS, Delegado, bandejas, búsqueda y contadores. 5. Regresión, MySQL, evidencia visual, documentación (V3.1 §15, fichas) y build.

## 9. Fuera de alcance (igual que la especificación)

Integración con Wizerp, nuevas fórmulas de seguro, OCR/conciliación bancaria, deduplicación global de vouchers, migración retroactiva de pedidos existentes.

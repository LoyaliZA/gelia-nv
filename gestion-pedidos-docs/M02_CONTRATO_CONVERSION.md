# M-02 — Contrato de conversión de modalidad (Oleada 0)

Congelado el 18 de noviembre de 2026. Fuente normativa para agentes A/B/C/D. El plan completo está en [`M02_PLAN_CONVERSION_MODALIDAD.md`](M02_PLAN_CONVERSION_MODALIDAD.md). Nombres, firmas, constantes y claves JSON de `app/Support/ControlPedidos/Conversion/` no se renombran. Agente A rellena cuerpos que lanzan `LogicException`. No hay apply todavía.

P0: Tienda → Municipal sobre el mismo `PedidoBma`. Sin folio nuevo. Sin `migrate:fresh`. Sin `ALLOW_DESTRUCTIVE_DB`. Sin push.

## 1. Límite compartido del motor

Conversión **solo antes del primer cierre** de consulta. Vive en el motor (`ConsultaPedidoYaCerradaAlgunaVez`), no dentro de cada política de destino.

`ConsultaPedidoYaCerradaAlgunaVez::es($pedido)` es verdadero si **cualquiera** de estas condiciones:

1. Columna `consulta_cerrada_at` no es null (`PedidoBma::consultaCerrada()`).
2. Existe fila en la tabla de historial del pedido (`pedido_bma_historial_estados` vía `$pedido->historial()`, **sin** filtrar bitácora por permisos) con `accion = AccionesHistorialPedidoBma::CIERRE_CONSULTA` (`cierre_consulta`).

Una reapertura (`ReabrirConsultaPedidoBmaService` limpia `consulta_cerrada_at` y no borra el historial) **no** restaura el permiso. Test 5 no negociable.

**No son el límite y no vetan por sí mismos:** `folio_remision`, `numero_remision`, PDF `tipo=remision`, `remision_tienda.emitida_at`, `pedidoPagadoORemisionado()`, exhibiciones o SAF.

**Otros bloqueos del motor (P0 Tienda → Municipal), independientes del límite de cierre:**

- `modalidad_entrega !== tienda`
- `cancelado_at` o fase `ENTREGADO`
- cuenta no propietaria (`VisibilidadPedidoBma::puedeMutarComoVendedora`) o sin `control_pedidos.crear`
- destino no activo (visible, `habilitado=false`)

Consulta no iniciada, abierta o respondida-sin-cerrar: potencialmente permitida. Pago o remisión con consulta aún no cerrada: no añaden veto.

T-03 `cambio_modalidad_tienda.estado=pendiente_conexion` hacia municipal: si el límite se cumple → `accion_ui=continuar` (misma conversión). Si no se cumple → conservar la intención, `puede_iniciar=false`, `puede_continuar=false`, sin apply.

## 2. Políticas por destino

Interfaz `PoliticaConversionModalidad`: `origenes()`, `destino()`, `activo()`, `evaluar(PedidoBma): ResultadoPoliticaConversion`. Dispatcher `DispatcherPoliticasConversion::porDestino(string $destino)`. El orquestador aplica el límite de cierre **antes** de `evaluar`.

| Clase | `origenes()` | `destino()` | `activo()` P0 |
| --- | --- | --- | --- |
| `PoliticaConversionMunicipal` | `['tienda']` | `municipal` | `true` |
| `PoliticaConversionTienda` | `['municipal']` | `tienda` | `false` |
| `PoliticaConversionNacional` | `['tienda', 'municipal']` | `nacional` | `false` |

Municipal P0 no veta por pago ni remisión y no duplica el chequeo de cierre. Destinos inactivos se listan con `habilitado=false` y `motivo_bloqueo`. P0 no implementa Municipal→Tienda ni Nacional.

## 3. Tres casos de herencia de consulta

Metadatos en `consulta_municipal` (nunca `unset` de `revision_disponibilidad` en estos caminos):

| Clave | Valores congelados |
| --- | --- |
| `procedencia` | `nueva` \| `heredada_parcial` \| `heredada_completa` |
| `consulta_origen` | `tienda` \| `cedis` \| `ninguna` |
| `almacen_id` | int o null |
| `heredada_en` | ISO-8601 |
| `heredada_por_id` | int |

Casos de UI/flags (`herencia.caso`):

1. **`sin_consulta`:** no hay `revision_disponibilidad` ni envío. Tras confirmar, circuito municipal listo para **iniciar** consulta con `PublicarConsultaMunicipal` contexto `conversion` (no pasa por guards de alta pagada de `EnviarConsultaMunicipalService`). No inventar `enviada_at` ni respuesta. `procedencia=nueva`, `consulta_origen=ninguna`.
2. **`parcial`:** respuesta incompleta, excepciones, anexos/retiros pendientes o almacén destino distinto. Conservar evidencia y unidades. Estado `excepciones_pendientes`. `requiere_revision_complementaria=true`. No declarar cerrada ni aprobada. `procedencia=heredada_parcial`.
3. **`respondida_abierta`:** reutilizar íntegra. Copiar `enviada_at` original si existía (o el de la solicitud Tienda). `ConsultaMunicipal::estado` deriva de la revisión vigente. **No** copiar `consulta_cerrada_at`. `procedencia=heredada_completa`.

La conversión no llama `RegistrarSolicitudMunicipalService` como apply. M-01 ordinario sigue bloqueando pagado/remisionado para **registrar** solicitud municipal nueva. `PoliticaFinancieraMunicipal` sigue exigiendo consulta cerrada para nuevas exhibiciones.

## 4. Reserva exclusiva por instancia

Clase: `ConversionModalidadEnCursoPedidoBma`. **No** clonar `RevisionEnCursoPedidoBma` (`Cache::put` pisa; soltar solo por `usuario_id`).

| Hecho | Valor congelado |
| --- | --- |
| Clave | `pedido_bma.conversion_en_curso.{pedidoId}` (`clave(int $pedidoId)`) |
| Payload | `{ usuario_id, instancia_id, at }` (`PayloadReservaConversion`) |
| TTL | `TTL_SEGUNDOS = 45` |
| Heartbeat UI | 15–20 s, mismo `instancia_id` |
| Adquirir | `Cache::add` **después** de `lockForUpdate` del pedido. No `Cache::put` para adquirir. |
| Misma instancia | renovar |
| Otra instancia | 409, `codigo=conversion_en_curso`, mensaje «Hay un cambio de modalidad en curso» |
| Renovar / soltar | solo si coinciden `usuario_id` **y** `instancia_id` |
| Token ajeno | no `put`, no `forget` |
| Caducidad | TTL borra la clave; se puede adquirir de nuevo si el pedido sigue elegible |

**Invariante de bloqueo (cierre y conversión):** `lockForUpdate` del pedido **antes** de leer o escribir la reserva en caché. Nunca caché-luego-fila. La clase de caché no bloquea la fila.

El cliente genera `instancia_id` (UUID) al abrir el modal. Métodos congelados: `adquirir`, `renovar`, `soltar`, `payload`, `activa`, `esPropietaria`.

Broadcast `PedidoBmaActualizado.accion`:

- `conversion_reserva_adquirida`
- `conversion_reserva_liberada`
- `conversion_modalidad` (confirmar)

Listado con reserva activa: `puede_cerrar_consulta=false` + `motivo_bloqueo_cierre` + `reserva_instancia` opaca. No filtrar solo en React local.

## 5. Cierre de consulta — contexto esperado

Agente A en `CerrarConsultaPedidoBmaService` + `PedidoBmaController::cerrarConsulta`. DTO `ContextoCierreConsultaEsperado` (`modalidad_esperada`, `version_consulta`). Firma congelada del servicio:

```
ejecutar(PedidoBma $pedido, int $usuarioId, ?ContextoCierreConsultaEsperado $contexto = null): PedidoBma
```

Tras `lockForUpdate`:

1. Reserva activa → **422** `codigo=conversion_en_curso`, «Hay un cambio de modalidad en curso».
2. Request trae `modalidad_esperada` y no coincide con `modalidad_entrega` actual → **409** `codigo=contexto_obsoleto`.
3. Request trae `version_consulta` y no coincide con `(int) $pedido->historial()->max('id')` → **409** vista atrasada (`codigo=vista_atrasada`).
4. Recién entonces el cierre existente.

El frontend **siempre** envía `modalidad_esperada` y `version_consulta`. POST M-01 **sin** esos campos sigue cerrando **solo** si no hubo conversión. Tras confirmar, un cierre Tienda obsoleto se rechaza aunque omita campos: si `conversion_modalidad.estado=confirmada` y no vino `modalidad_esperada=municipal` → 409 `contexto_obsoleto` y la UI pasa al seguimiento municipal.

## 6. Evidencias

No copiar el store crudo de T-03 (`SolicitarSegundoTrasladoTiendaService` L75–82 guarda dentro de la transacción sin limpieza). No nuevo servicio de storage. No modificar `PersistenciaAdjuntosConsulta.php`.

Flujo:

1. `lockForUpdate` + validaciones (sin archivos aún).
2. `PersistenciaAdjuntosConsulta::guardar` con `relacion_tipo=conversion_peticion_cliente`.
3. Resto de la transacción.
4. `catch`: `limpiar($rutas)` **aunque** el rollback SQL ya deshizo las filas.
5. `guardar` ya limpia si falla a mitad de archivos.

El rollback de SQL no borra el disco.

## 7. Idempotencia antes de versión

Orden fijo al confirmar (no reordenar):

1. `lockForUpdate`.
2. **Idempotencia primero:** si `clave_idempotencia` ya está confirmada en `conversion_modalidad` → devolver el resultado aunque `conversion_version` / `revision_esperada` estén atrasadas. No 409 de versión. No duplicar.
3. Reserva de esta `instancia_id` (o caducada y el pedido sigue elegible).
4. Límite de primer cierre + política destino + permiso + `conversion_version`.
5. Validar captura municipal extraída (`ValidarCapturaMunicipal`).
6. Evidencias con limpieza compensatoria.
7. Escribir modalidad municipal, expediente, finanzas, `costo_envio` (null si diferido), `total_a_cobrar`.
8. Tres casos de consulta. `revision_disponibilidad` intacta.
9. Sustituir instrucción Tienda abierta si existe. Custodia excepcional: solo `entrega_bloqueada`.
10. Conectar T-03 municipal solo si el apply procedió.
11. Empujar snapshot a `conversion_modalidad.historial[]`; vigente `estado=confirmada`.
12. Incrementar `revision_registro` y `conversion_version`.
13. Historial «Cambio a envío municipal» (`AccionesHistorialPedidoBma::CAMBIO_MODALIDAD_CONVERSION = 'cambio_modalidad_conversion'`). Soltar reserva de esta instancia. Broadcast `conversion_modalidad`.

## 8. Rutas API

Prefijo existente: `control-pedidos` / `control_pedidos.`. Grupo `can:control_pedidos.crear`. Archivo: `routes/web/modules/control-pedidos/core.php` (Agente A). Recurso `{pedidoBma}`. Permiso efectivo: `control_pedidos.crear` + `puedeMutarComoVendedora`.

| Método | Path | Nombre | Acción |
| --- | --- | --- | --- |
| GET | `/control-pedidos/{pedidoBma}/conversion-modalidad` | `control_pedidos.conversion_modalidad.show` | flags |
| POST | `/control-pedidos/{pedidoBma}/conversion-modalidad/vista-previa` | `control_pedidos.conversion_modalidad.vista_previa` | preview |
| POST | `/control-pedidos/{pedidoBma}/conversion-modalidad` | `control_pedidos.conversion_modalidad.confirmar` | confirmar |
| POST | `/control-pedidos/{pedidoBma}/conversion-modalidad/cancelar` | `control_pedidos.conversion_modalidad.cancelar` | cancelar borrador / T-03 no conectada; no deshace confirmada |
| POST | `/control-pedidos/{pedidoBma}/conversion-modalidad/reserva` | `control_pedidos.conversion_modalidad.reserva.abrir` | adquirir (`instancia_id` obligatorio) |
| POST | `/control-pedidos/{pedidoBma}/conversion-modalidad/reserva/latido` | `control_pedidos.conversion_modalidad.reserva.latido` | renovar el mismo `instancia_id` |
| DELETE | `/control-pedidos/{pedidoBma}/conversion-modalidad/reserva` | `control_pedidos.conversion_modalidad.reserva.soltar` | soltar esa instancia |

Cierre (existente, Agente A añade campos; no reescribe el resto del controller):

| Método | Path | Nombre |
| --- | --- | --- |
| POST | `/control-pedidos/{pedidoBma}/cerrar-consulta` | `control_pedidos.cerrar_consulta` |

## 9. JSON de request / response

Errores JSON: `{ "message": string, "codigo": string }`. Códigos: `conversion_en_curso`, `contexto_obsoleto`, `vista_atrasada`, `consulta_ya_cerrada`, `conversion_version_obsoleta`, `destino_inactivo`, `no_elegible`. 403 permiso. 409 reserva ajena / contexto / versión (salvo idempotencia). 422 validación, consulta ya cerrada, cierre con reserva activa.

### 9.1 GET `conversion-modalidad` y flags de listado

`FlagsConversionModalidad::toArray()`:

```json
{
  "conversion_modalidad": {
    "origen": "tienda",
    "destinos": [
      { "codigo": "municipal", "nombre": "Envío municipal", "habilitado": true, "motivo_bloqueo": null },
      { "codigo": "tienda", "nombre": "Tienda", "habilitado": false, "motivo_bloqueo": "string" },
      { "codigo": "nacional", "nombre": "Envío nacional", "habilitado": false, "motivo_bloqueo": "string" }
    ],
    "puede_iniciar": true,
    "puede_continuar": false,
    "puede_cancelar_borrador": false,
    "consulta_ya_cerrada_alguna_vez": false,
    "reserva_activa": false,
    "reserva_usuario_id": null,
    "reserva_instancia": null,
    "accion_ui": null,
    "solicitud_pendiente": { "fuente": "t03", "estado": "pendiente_conexion" },
    "herencia": {
      "caso": "sin_consulta",
      "consulta": {},
      "pagos": {},
      "remision": {},
      "documentos": {}
    },
    "finanzas": {
      "mercancia": "0.00",
      "pagado": "0.00",
      "costo_envio_actual": null,
      "costo_envio_nuevo": null,
      "pendiente_transporte": "0.00",
      "transporte_desconocido": false,
      "validacion_historica_no_cubre_nuevo_cargo": true
    },
    "fisica": { "condicion": "sin_movimiento", "pendiente": false, "mensaje": null, "entrega_bloqueada": false },
    "version": 0
  }
}
```

`herencia.caso`: `sin_consulta` | `parcial` | `respondida_abierta`. `accion_ui`: `continuar` o null. `solicitud_pendiente` puede ser null. `reserva_instancia` es opaca. `version` = `conversion_version` vigente (0 si no hay conversión).

### 9.2 POST reserva / latido / DELETE reserva

Request:

```json
{ "instancia_id": "uuid" }
```

Response 200:

```json
{
  "ok": true,
  "reserva_activa": true,
  "reserva_usuario_id": 1,
  "ttl_segundos": 45
}
```

409 reserva ajena: `{ "message": "Hay un cambio de modalidad en curso", "codigo": "conversion_en_curso" }`.

### 9.3 POST vista-previa

Request: `destino`, `instancia_id`, campos municipales opcionales para recalcular finanzas. Response: mismo envelope `conversion_modalidad` del GET, con `herencia` y `finanzas` resueltos para ese destino.

### 9.4 POST confirmar (P0)

`SolicitudConfirmarConversion`:

```json
{
  "destino": "municipal",
  "instancia_id": "uuid",
  "motivo": "string max 200",
  "error_captura": false,
  "evidencias": [],
  "catalogo_paqueteria_id": 1,
  "consignatario_id": 1,
  "almacen_id": null,
  "leyenda_terminal": "PAGADO",
  "costo_transporte": "conocido",
  "costo_envio": "150.00",
  "revision_esperada": 0,
  "conversion_version": 0,
  "clave_idempotencia": "string"
}
```

`evidencias` (multipart, igual T-03: máx. 8, pdf/jpg/jpeg/png/webp, 10 MB) **u** `error_captura=true`. Campos M-01: mismos nombres que `FormularioRegistrarMunicipal` / `RegistrarSolicitudMunicipalService`. `costo_transporte=diferido` → `costo_envio` null en persistencia, no cero.

Response 200 `ResultadoConversionModalidad`:

```json
{
  "ok": true,
  "pedido_id": 1,
  "folio": "string",
  "modalidad_entrega": "municipal",
  "conversion_version": 1,
  "revision_registro": 1,
  "version_consulta": 1,
  "herencia": { "caso": "sin_consulta", "procedencia": "nueva" },
  "conversion_modalidad": {}
}
```

El objeto `conversion_modalidad` de la respuesta reutiliza el shape del GET.

### 9.5 POST cancelar

Request: `{ "instancia_id": "uuid", "revision_esperada": 0 }`. Solo borrador o T-03 no conectada. No deshace `estado=confirmada`.

### 9.6 POST cerrar-consulta (campos nuevos)

```json
{
  "modalidad_esperada": "tienda",
  "version_consulta": 12
}
```

409 contexto:

```json
{
  "message": "La consulta ya no corresponde a esta modalidad. Actualice para continuar en envío municipal.",
  "codigo": "contexto_obsoleto"
}
```

### 9.7 Persistencia JSON

`solicitud_preparacion_json.conversion_modalidad`:

```json
{
  "estado": "confirmada",
  "origen": "tienda",
  "destino": "municipal",
  "conversion_version": 1,
  "clave_idempotencia": "string",
  "confirmada_at": "ISO-8601",
  "confirmada_por_id": 1,
  "motivo": "string",
  "error_captura": false,
  "herencia": { "caso": "sin_consulta", "procedencia": "nueva", "consulta_origen": "ninguna" },
  "historial": []
}
```

La vigente no pisa `historial[]`: se empuja un snapshot de la anterior. Autoguardado tras confirmar: 409 por revisión; `FusionarSolicitudPreparacionPedido` pinnea `modalidad_entrega` si hay conversión confirmada.

## 10. Nombres congelados (PHP)

Namespace de contrato: `App\Support\ControlPedidos\Conversion`. Oleada 0 deja interfaces/DTO compilables; Agente A implementa cuerpos.

**Support (ya creados, no renombrar):**

- `PoliticaConversionModalidad` — `origenes(): array`, `destino(): string`, `activo(): bool`, `evaluar(PedidoBma): ResultadoPoliticaConversion`
- `PoliticaConversionMunicipal` / `PoliticaConversionTienda` / `PoliticaConversionNacional`
- `DispatcherPoliticasConversion::porDestino(string $destino): PoliticaConversionModalidad`
- `ConsultaPedidoYaCerradaAlgunaVez::es(PedidoBma $pedido): bool`
- `ConversionModalidadEnCursoPedidoBma` — `TTL_SEGUNDOS`, `clave`, `adquirir`, `renovar`, `soltar`, `payload`, `activa`, `esPropietaria`
- `CodigosConversionModalidad` — destinos, procedencias, casos, códigos HTTP, acciones de broadcast, `RELACION_TIPO_EVIDENCIA`, `CONTEXTO_PUBLICACION`
- DTOs: `DestinoConversionModalidad`, `FlagsConversionModalidad`, `ResultadoPoliticaConversion`, `ResultadoElegibilidadConversion`, `HerenciaConsultaConversion`, `FinanzasConversionModalidad`, `FisicaConversionModalidad`, `SolicitudConfirmarConversion`, `SolicitudReservaConversion`, `ResultadoConversionModalidad`, `ContextoCierreConsultaEsperado`, `PayloadReservaConversion`, `SolicitudPendienteConversion`

**Servicios y HTTP (Agente A crea en oleada 1, no antes, con estos nombres):**

- `App\Services\ControlPedidos\Conversion\CambiarModalidadPedidoService`
- `App\Services\ControlPedidos\Conversion\EvaluarElegibilidadConversion`
- `App\Services\ControlPedidos\Conversion\ResolverHerenciaConsultaConversion`
- `App\Services\ControlPedidos\Conversion\ConciliarFinanzasConversion`
- `App\Services\ControlPedidos\Conversion\SustituirInstruccionAbiertaConversion`
- `App\Services\ControlPedidos\Conversion\PersistirConversionModalidad`
- `App\Services\ControlPedidos\Conversion\ResolverIdempotenciaConversion`
- `App\Services\ControlPedidos\Conversion\PublicarConsultaMunicipal` — contexto `conversion`
- `App\Services\ControlPedidos\Conversion\ValidarCapturaMunicipal`
- `App\Http\Controllers\ControlPedidos\ConversionModalidadController`
- Requests: `ConfirmarConversionModalidadRequest`, `VistaPreviaConversionModalidadRequest`, `ReservaConversionModalidadRequest`, `CancelarConversionModalidadRequest`

**Constante de historial (Agente A añade en `AccionesHistorialPedidoBma`, no en Oleada 0):**

- `CAMBIO_MODALIDAD_CONVERSION = 'cambio_modalidad_conversion'` → etiqueta «Cambio a envío municipal»

UI: «Cambiar modalidad», «Envío municipal». Sin códigos de ficha. Género neutro.

## 11. Matriz de archivos por agente

Un propietario por archivo. Si hay que tocar el de otro, el coordinador serializa.

### Agente A — autorizado

Nuevos:

- `app/Support/ControlPedidos/Conversion/*` (cuerpos; no renombrar)
- `app/Services/ControlPedidos/Conversion/*`
- `app/Http/Controllers/ControlPedidos/ConversionModalidadController.php`
- `app/Http/Requests/ControlPedidos/ConfirmarConversionModalidadRequest.php`
- `app/Http/Requests/ControlPedidos/VistaPreviaConversionModalidadRequest.php`
- `app/Http/Requests/ControlPedidos/ReservaConversionModalidadRequest.php`
- `app/Http/Requests/ControlPedidos/CancelarConversionModalidadRequest.php`
- PHPUnit propio de motor **fuera** de `ConversionModalidadM02Test.php`

Edita (único dueño oleada 1):

- `routes/web/modules/control-pedidos/core.php`
- `app/Services/ControlPedidos/FusionarSolicitudPreparacionPedido.php` (conservar + pin)
- `app/Services/ControlPedidos/CerrarConsultaPedidoBmaService.php` (reserva + contexto)
- `app/Http/Controllers/ControlPedidos/PedidoBmaController.php` **solo** `cerrarConsulta` (aceptar `modalidad_esperada`, `version_consulta`; no reescribir el resto)
- `app/Services/ControlPedidos/ListarPedidosBmaService.php` (flags + motivo cierre)
- `app/Support/ControlPedidos/AccionesHistorialPedidoBma.php`
- `app/Models/ControlPedidos/PedidoBma.php` **solo** `motivoBloqueoEntregaTienda` (adecuación excepcional)
- `app/Services/ControlPedidos/MovimientosTiendaService.php` rama municipal
- `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php` solo skip `unset` si heredada + `enviada_at`
- `app/Services/ControlPedidos/Municipal/RegistrarSolicitudMunicipalService.php` solo delegar validación

Usa sin modificar: `PersistenciaAdjuntosConsulta`.

### Agente A — prohibido

JSX/JS de B. Tests de C. `PersistenciaAdjuntosConsulta.php`. `RetirarCustodiaParaEnvioService`. `esEditablePorVendedora`. Fórmula canónica de cobertura. Nacional legado. Migraciones. M-03, Municipal→Tienda, Nacional en P0.

### Agente B — autorizado (no PHP)

- `resources/js/Pages/ControlPedidos/Partials/pedidosListadoUi.js`
- `resources/js/Pages/ControlPedidos/Partials/TablaPedidos.jsx`
- `resources/js/Pages/ControlPedidos/Index.jsx`
- `resources/js/Pages/ControlPedidos/Partials/ModalMovimientosTienda.jsx`
- `resources/js/Pages/ControlPedidos/Municipal/FormularioRegistrarMunicipal.jsx` (callback / `modo=conversion`)
- `resources/js/Pages/ControlPedidos/Partials/SeguimientoSolicitudTienda.jsx`
- `resources/js/Pages/ControlPedidos/Municipal/SeguimientoSolicitudMunicipal.jsx`
- `resources/js/Pages/ControlPedidos/Partials/useActualizacionesPedidos.js`
- `resources/js/Pages/ControlPedidos/Partials/ModalConversionModalidad.jsx` (nuevo)
- tests jsx **nuevos** de conversión colocalizados

Si un test colocalizado de Seguimiento falla solo por campos extra en el POST de cerrar, B (dueño del archivo) lo ajusta **sin** cambiar aserciones de negocio M-01.

### Agente B — prohibido

Servicios PHP, Support PHP, rutas, controllers, requests, tests de C, suites M-01/Tienda/T-03.

### Agente C — autorizado

- `tests/Feature/ControlPedidos/ConversionModalidadM02Test.php` (nuevo)
- `*.conversion.test.jsx` **nuevos**

### Agente C — prohibido

Producción. `MunicipalOrdinarioM01Test`, `SolicitudRegistrarTiendaTest`, `TransferenciaTiendaTest` y el resto de suites de la sección 11 del plan.

### Agente D

Solo lectura. Informe de hallazgos al coordinador. No edita.

### Nadie (todas las oleadas P0)

`PersistenciaAdjuntosConsulta.php`, `RetirarCustodiaParaEnvioService`, `esEditablePorVendedora`, fórmula canónica de cobertura, Nacional legado, migraciones, permiso nuevo, tabla nueva, `public/hot`, `migrate:fresh`, `ALLOW_DESTRUCTIVE_DB`, commit/push no solicitados, M-03 / Municipal→Tienda / Nacional.

## 12. Fuera de Oleada 0

Motor, UI, tests, rutas, controllers, servicios en `app/Services/ControlPedidos/Conversion/`, edición de `CerrarConsultaPedidoBmaService`, build Vite. Eso es oleada 1+.

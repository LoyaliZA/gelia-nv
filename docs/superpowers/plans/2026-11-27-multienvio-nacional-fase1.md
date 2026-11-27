# Multienvío nacional — Fase 1 (persistencia, dominio y servicios) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el servidor pueda detectar un pedido nacional de ≥2 cajas, guardar la decisión de Ventas, configurar en borrador y confirmar la separación en un expediente `PedidoBma` por caja, de forma transaccional e idempotente.

**Architecture:** Un agrupador sin importes (`multienvios_nacionales`) más dos columnas nullable en `pedidos_bma`. El pedido original se reutiliza como envío 1 y se crean N-1 expedientes que reciben su caja por UUID. Servicios pequeños en `app/Services/ControlPedidos/Nacional/Multienvio/`; los hechos financieros previos no se mueven (van a `regularizacion_pendiente`).

**Tech Stack:** Laravel 12 / PHP 8.4, PHPUnit con SQLite en memoria, MySQL 8.4 para concurrencia, React solo en fases siguientes.

**Spec:** `docs/superpowers/specs/2026-11-27-multienvio-nacional-design.md` (funcional: `GELIA-NV_plan_multienvio_nacional_Claude_Code.md`, ME-01..ME-12, A03-A07). Fases 2-5 tendrán su propio plan.

## Global Constraints

- Aditivo: columnas nullable, sin tocar pedidos históricos, sin migración retroactiva.
- No desactivar `BarreraSeguroCajaNacional` ni relajar `AsignarRemisionCajaNacional`. No cambiar fórmulas de seguro.
- No copiar ni mover `PedidoBmaPago`, SAF, cierres de reporte ni remisiones a los miembros nuevos.
- Importes en centavos: comparar con `round(x, 2)` o enteros, nunca flotantes sin redondear.
- Todo endpoint valida permiso y propiedad en servidor (`VisibilidadPedidoBma::puedeMutarComoVendedora`). El grupo no otorga permisos.
- Cada ruta web nueva de `control-pedidos` se espeja en `routes/api/mobile-control-pedidos.php` (misma uri con prefijo `mobile/`, mismo permiso) o se excluye en `config/api_paridad.php` con motivo.
- Textos de usuario con nombres de operación, nunca códigos de ficha (T-01, N-F01…).
- **No hacer commit ni push** salvo que el usuario lo pida. Donde un plan normal dice «commit», aquí: `git status --short` y reportar.
- **Cómo probar** (contenedores existentes; no crear otros): sincronizar y correr.
  ```bash
  cd /home/ti-admin/produccion-test && (git diff --name-only HEAD; git ls-files -o --exclude-standard) | sort -u | rsync -a --files-from=- ./ /home/ti-admin/gelianv/
  docker exec -u sail -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php gelianv-laravel.test-1 php artisan test --filter='<Clase>'
  ```
  Archivos borrados en el repo hay que borrarlos también en `/home/ti-admin/gelianv`. MySQL: solo una base exclusiva en `gelianv-mysql-1`, nunca `gelianv_db`.
- Baseline conocido: `NacionalAltaConsultaTest > alta mixta` falla antes de cualquier cambio (folio `WIZ-MIXTO` duplicado). No atribuirlo a esta tarea.

## Review Focus

1. Doble clic / dos pestañas / reintento HTTP al confirmar: un solo grupo y N miembros (tests en Tarea 6 y MySQL en Tarea 8).
2. Caja retirada o repesaje entre el borrador y la confirmación: no crear miembros de cajas inactivas; rechazar revisión obsoleta (Tarea 6).
3. Suma de mercancía por caja distinta al total por un centavo: rechazar sin materializar nada (Tarea 6).
4. Folio Wizerp repetido entre envíos o con otro pedido activo, y folio original sin confirmar (Tarea 5 y 6).
5. Pedido con pago capturado/validado, remisión o guía: no mutar hechos, pasar a `regularizacion_pendiente` (Tarea 7). Actor no propietario o de otro departamento: 403 (Tarea 8).

---

### Task 1: Spike R1 — qué hereda un miembro sin simular otra respuesta de pesaje

**Files:**
- Create: `docs/superpowers/specs/2026-11-27-multienvio-r1-hallazgos.md`

**Interfaces:**
- Produces: lista cerrada de (a) columnas de `pedidos_bma` a copiar, (b) relaciones a mover por UUID de caja o a reapuntar/duplicar como solo lectura (`documentos` con `pedido_bma_caja_id`, `revisionesProducto`, `PedidoBmaSesionEvidencia*`, `PedidoBmaCajaRemision`, `referencias`), (c) condiciones que hacen `puedeCerrarConsulta()`/`consultaCerrada()` verdaderas en el miembro. La Tarea 7 la consume.

- [ ] **Step 1:** Leer `PedidoBma::{consultaCerrada, puedeCerrarConsulta, esEditablePorVendedora, esConsultaDisponibilidadCedis}`, `ResponderPesajePedidoBmaService`, `SincronizarCajasPedidoBmaService`, `LecturaConsultaNacional`, `CompletarRevisionesPesajeNacional` y `CrearPedidoBmaService`; anotar para cada tabla con `pedido_bma_id` o `pedido_bma_caja_id` (ver `grep` en `database/migrations`) si el miembro la necesita para cotizar, auditar y despachar.
- [ ] **Step 2:** Escribir el documento con tres tablas (copiar / mover / no tocar), cada fila con `archivo:línea` y motivo. Marcar `[verificado]` solo lo leído.
- [ ] **Step 3:** Verificación: cada tabla de la lista de `grep` está clasificada; no queda ninguna «por ver».

### Task 2: Migraciones y modelo `MultienvioNacional`

**Files:**
- Create: `database/migrations/2026_11_27_100000_create_multienvios_nacionales_table.php`, `database/migrations/2026_11_27_100100_agregar_multienvio_a_pedidos_bma.php`, `app/Models/ControlPedidos/MultienvioNacional.php`, `app/Services/ControlPedidos/Nacional/Multienvio/MultienvioNacionalException.php`
- Modify: `app/Models/ControlPedidos/PedidoBma.php` (`$fillable` y relaciones `multienvio()`)
- Test: `tests/Feature/ControlPedidos/MultienvioNacionalModeloTest.php`

**Interfaces:**
- Produces: `MultienvioNacional` (tabla `multienvios_nacionales`) con constantes `ESTADO_PENDIENTE_DECISION='pendiente_decision'`, `ESTADO_ESTANDAR_SIN_SEGURO='estandar_sin_seguro'`, `ESTADO_CONFIGURANDO='configurando'`, `ESTADO_ACTIVO='activo'`, `ESTADO_REGULARIZACION_PENDIENTE='regularizacion_pendiente'`; relaciones `origen(): BelongsTo` (PedidoBma) y `envios(): HasMany` (PedidoBma por `multienvio_nacional_id`, orden por `multienvio_orden`); casts de `snapshot_origen_json`, `borrador_json`, `regularizacion_json` a array. `PedidoBma::multienvio(): BelongsTo`. `MultienvioNacionalException extends \RuntimeException` con constructores estáticos `revisionObsoleta()`, `noElegible(string $motivo)`, `sumaNoCuadra(float $diferencia)`, `folioRepetido(string $folio)`, `cajaNoPertenece(string $uuid)`, `hechosPrevios()`.

- [ ] **Step 1: Test** `test_la_tabla_y_las_columnas_existen_y_son_nulas_en_pedidos_historicos`: `Schema::hasColumns('multienvios_nacionales', ['uuid','pedido_origen_id','revision_consulta','estado','snapshot_origen_json','borrador_json','regularizacion_json'])`; un `PedidoBma` creado sin los campos tiene `multienvio_nacional_id === null`. `test_pedido_origen_id_es_unico` espera `QueryException` al duplicar. `test_orden_es_unico_por_grupo` espera `QueryException` con dos pedidos del mismo grupo y `multienvio_orden=1`.
- [ ] **Step 2:** Ejecutar `--filter=MultienvioNacionalModeloTest`; debe fallar por tabla inexistente.
- [ ] **Step 3:** Migraciones según §3 del diseño (tipos: `uuid` único, FK `pedido_origen_id` única a `pedidos_bma`, `revision_consulta` string(64), `estado` string(40) con índice, `decidido_por_id/activado_por_id` FK nullable a `users`, JSON nullable, timestamps; en `pedidos_bma` `multienvio_nacional_id` FK nullable con índice y `multienvio_orden` unsignedTinyInteger nullable con único compuesto). `down()` quita columnas y tabla solo si no hay filas con pedidos asignados (si las hay, lanzar excepción: el rollback no borra grupos con hechos).
- [ ] **Step 4:** Ejecutar el test; debe pasar. Ejecutar `NacionalOla3CierreTest` para confirmar que el modelo ampliado no rompe nada.
- [ ] **Step 5:** `git status --short`; reportar archivos.

### Task 3: `EvaluarMultienvioNacional`

**Files:**
- Create: `app/Services/ControlPedidos/Nacional/Multienvio/EvaluarMultienvioNacional.php`
- Test: `tests/Feature/ControlPedidos/MultienvioNacionalEvaluarTest.php`

**Interfaces:**
- Consumes: `CapturaNacional::es`, `PedidoBma::consultaCerrada()`, `PedidoBmaCaja::scopeActivas`.
- Produces: `ejecutar(PedidoBma $pedido): array{elegible: bool, motivo: ?string, n_cajas: int, revision: string, estado: ?string, multienvio_id: ?int}`. `revision` = sha1 de los `uuid_operativo` activos ordenados + `peso_cobrado_kg` de cada uno; es la huella que cierra decisión y confirmación.

- [ ] **Step 1: Test** (patrón de datos: copiar `pedidoNacional()` y `setUp()` de `NacionalOla3CierreTest`, agregando `consulta_cerrada_at => now()`): `test_una_caja_no_es_elegible` (`elegible=false`, `n_cajas=1`), `test_tres_cajas_con_consulta_cerrada_es_elegible` (`n_cajas=3`, `estado=null`), `test_consulta_abierta_no_es_elegible`, `test_tienda_o_municipal_no_es_elegible`, `test_caja_retirada_no_cuenta`, `test_la_revision_cambia_si_cambia_una_caja`.
- [ ] **Step 2:** Correr; debe fallar (clase inexistente).
- [ ] **Step 3:** Implementar la firma de arriba; sin escribir en BD.
- [ ] **Step 4:** Correr; debe pasar.
- [ ] **Step 5:** `git status --short`.

### Task 4: `RegistrarDecisionMultienvio`

**Files:**
- Create: `app/Services/ControlPedidos/Nacional/Multienvio/RegistrarDecisionMultienvio.php`
- Test: `tests/Feature/ControlPedidos/MultienvioNacionalDecisionTest.php`

**Interfaces:**
- Consumes: `EvaluarMultienvioNacional::ejecutar`, `VisibilidadPedidoBma::puedeMutarComoVendedora(User, PedidoBma)`.
- Produces: `ejecutar(PedidoBma $pedido, int $usuarioId, string $decision, ?string $revisionEsperada = null): MultienvioNacional` con `$decision` ∈ `'activar'|'sin_seguro'`. `'sin_seguro'` → `estandar_sin_seguro`; `'activar'` → `configurando`. Permite pasar de `estandar_sin_seguro` a `configurando` (ME-06), nunca de `activo` hacia atrás.

- [ ] **Step 1: Test:** `test_sin_seguro_persiste_y_no_crea_pedidos` (cuenta de `PedidoBma` igual antes y después); `test_activar_deja_configurando_y_guarda_actor_y_revision`; `test_repetir_la_misma_decision_devuelve_el_mismo_grupo` (un solo registro); `test_activar_despues_de_sin_seguro_reutiliza_el_grupo` (mismo `id`); `test_revision_obsoleta_lanza_excepcion` (`MultienvioNacionalException::revisionObsoleta`); `test_usuario_ajeno_no_puede` (excepción de autorización); `test_una_caja_no_elegible_lanza_no_elegible`.
- [ ] **Step 2:** Correr; falla.
- [ ] **Step 3:** Implementar en `DB::transaction` con `lockForUpdate` sobre el pedido; `firstOrCreate` por `pedido_origen_id`; guardar `snapshot_origen_json` (folio, `folio_remision`, `total_mercancia`, ids de documentos activos) solo la primera vez.
- [ ] **Step 4:** Correr; pasa.
- [ ] **Step 5:** `git status --short`.

### Task 5: `GuardarBorradorMultienvio`

**Files:**
- Create: `app/Services/ControlPedidos/Nacional/Multienvio/GuardarBorradorMultienvio.php`
- Test: `tests/Feature/ControlPedidos/MultienvioNacionalBorradorTest.php`

**Interfaces:**
- Produces: `ejecutar(MultienvioNacional $grupo, int $usuarioId, array $envios): MultienvioNacional`; `$envios` = lista de `['uuid_operativo' => string, 'folio_remision' => ?string, 'total_mercancia' => float|string, 'contenido' => ?string]`. Guarda en `borrador_json`; no crea pedidos; solo en estado `configurando`. Rechaza `uuid_operativo` que no sea una caja activa del origen (`cajaNoPertenece`) y folios repetidos dentro del borrador o con otro `PedidoBma` activo distinto del origen (`folioRepetido`). El folio igual al `folio_remision` del origen se acepta solo si viene en esta lista de forma explícita (no se asigna solo).

- [ ] **Step 1: Test:** `test_guarda_borrador_sin_crear_pedidos`; `test_borrador_se_puede_reescribir`; `test_uuid_ajeno_lanza_caja_no_pertenece`; `test_folio_repetido_en_el_borrador_lanza_folio_repetido`; `test_folio_de_otro_pedido_activo_lanza_folio_repetido`; `test_estado_distinto_de_configurando_lanza_no_elegible`.
- [ ] **Step 2:** Correr; falla. **Step 3:** Implementar. **Step 4:** Correr; pasa. **Step 5:** `git status --short`.

### Task 6: `ConfirmarMultienvio` (camino directo, sin hechos previos)

**Files:**
- Create: `app/Services/ControlPedidos/Nacional/Multienvio/ConfirmarMultienvio.php`
- Test: `tests/Feature/ControlPedidos/MultienvioNacionalConfirmarTest.php`

**Interfaces:**
- Consumes: Tarea 3, 4, 5; doc de la Tarea 1 (lista de campos/relaciones); `GenerarFolioPedidoBmaService::ejecutar()`; `RegistrarHistorialPedidoService`.
- Produces: `ejecutar(MultienvioNacional $grupo, int $usuarioId, ?string $revisionEsperada = null): MultienvioNacional`. Resultado: grupo `activo`; el origen conserva la caja de menor `orden` con `multienvio_orden=1`, su `total_mercancia`/`folio_remision` bajados a lo del borrador; N-1 pedidos nuevos con `multienvio_orden` 2..N, folio GELIA normal, cada uno con su caja movida (mismo `uuid_operativo`).

- [ ] **Step 1: Test** con 3 cajas, mercancía 600/500/400 sobre `total_mercancia=1500`: `test_confirma_tres_envios_y_reparte_cajas_por_uuid` (3 pedidos con `multienvio_nacional_id`, una caja activa cada uno, UUID conservados, `sum(total_mercancia)==1500.00`); `test_el_origen_queda_como_envio_1_con_sus_valores`; `test_no_copia_pagos_ni_remisiones` (0 `PedidoBmaPago` en miembros nuevos); `test_confirmar_dos_veces_no_duplica` (3 pedidos, mismo grupo); `test_suma_con_un_centavo_de_diferencia_lanza_suma_no_cuadra_y_no_crea_nada`; `test_caja_retirada_tras_el_borrador_lanza_revision_obsoleta`; `test_hace_rollback_si_falla_un_miembro` (forzar fallo en el 3.º y comprobar que el origen y las cajas quedaron como estaban); `test_cada_miembro_queda_listo_para_cotizar` (consulta cerrada, estatus post-consulta, `aplica_seguro` conforme a la elección y sin segunda consulta); `test_folio_wizerp_faltante_lanza_error_y_no_materializa`.
- [ ] **Step 2:** Correr; falla.
- [ ] **Step 3:** Implementar en una sola `DB::transaction`: bloquear grupo y origen (`lockForUpdate`), revalidar con `EvaluarMultienvioNacional` contra `revision_consulta`, validar borrador completo (todas las cajas activas, folios únicos, suma en centavos = `total_mercancia` actual del origen), crear miembros copiando la lista de la Tarea 1, mover cajas y sus documentos ligados por `pedido_bma_caja_id`, registrar historial en origen y miembros, `DB::afterCommit` para el evento en vivo. Si el grupo ya está `activo`, devolverlo sin cambios.
- [ ] **Step 4:** Correr; pasa. Correr también `NacionalLogisticaCobroTest` y `NacionalVistaPreviaCotizacionTest` (regresión de seguro por caja).
- [ ] **Step 5:** `git status --short`.

### Task 7: Hechos previos → `regularizacion_pendiente`

**Files:**
- Modify: `ConfirmarMultienvio.php`
- Create: `app/Services/ControlPedidos/Nacional/Multienvio/DetectarHechosPreviosMultienvio.php`
- Test: `tests/Feature/ControlPedidos/MultienvioNacionalHechosPreviosTest.php`

**Interfaces:**
- Produces: `DetectarHechosPreviosMultienvio::ejecutar(PedidoBma $origen): array<string, bool>` con claves `pago_capturado`, `pago_validado`, `remision`, `guia`, `empacada`, `salida_parcial`. `ConfirmarMultienvio` con cualquier `true` en `pago_validado|remision|guia|empacada|salida_parcial` no mueve nada: pone el grupo en `regularizacion_pendiente`, guarda motivo y claves en `regularizacion_json`, devuelve el grupo. Con solo `pago_capturado` conserva el pago en el origen (envío 1) y continúa.

- [ ] **Step 1: Test:** un caso por clave (`pago_validado_at` fijo, `numero_remision`, `numero_rastreo`, caja `recolectada`…): grupo `regularizacion_pendiente`, ningún pedido nuevo, `pago_validado_at`/`numero_remision` del origen intactos; `test_pago_capturado_sin_validar_continua_y_no_clona_el_monto`.
- [ ] **Step 2-4:** Correr (falla) → implementar → correr (pasa) y repetir `MultienvioNacionalConfirmarTest`.
- [ ] **Step 5:** `git status --short`.

### Task 8: Lectura, rutas, permisos, espejo móvil y concurrencia

**Files:**
- Create: `app/Http/Controllers/ControlPedidos/MultienvioNacionalController.php`, `app/Services/ControlPedidos/Nacional/Multienvio/ProyeccionMultienvio.php`
- Modify: `routes/web/modules/control-pedidos/core.php` (junto a la ruta de `vista-previa`, línea ~90), `routes/api/mobile-control-pedidos.php`
- Test: `tests/Feature/ControlPedidos/MultienvioNacionalHttpTest.php`, `tests/Feature/ControlPedidos/MultienvioNacionalConcurrenciaMysqlTest.php`

**Interfaces:**
- Produces rutas: `GET /control-pedidos/{pedidoBma}/multienvio` (lectura con `ProyeccionMultienvio::de(MultienvioNacional|PedidoBma): array{estado, n_cajas, decision, envios: list<array{id, folio, orden, folio_remision, total_mercancia, caja_uuid, estado_pago, ...}>, totales: array{mercancia, flete, seguro, total, pagado, pendiente}, x_de_n: array<string,string>}`), `POST …/multienvio/decision` (`decision`, `revision`), `PUT …/multienvio/borrador`, `POST …/multienvio/confirmar`. Errores de dominio → 422 con mensaje por campo.

- [ ] **Step 1: Test HTTP:** `test_lectura_devuelve_estado_y_miembros_sin_n_mas_uno` (contar consultas con `DB::enableQueryLog`, constante respecto de N); `test_vendedor_propietario_decide_y_confirma`; `test_otro_vendedor_recibe_403`; `test_auxiliar_cedis_y_delegado_solo_leen_lo_que_ven`; `test_confirmar_dos_veces_devuelve_200_y_un_solo_grupo`; `test_error_de_suma_devuelve_422_con_campo`. Más `MobileEspejoRutasTest` debe seguir en verde.
- [ ] **Step 2:** Correr; falla. **Step 3:** Implementar controlador delgado (autoriza con `VisibilidadPedidoBma`, delega a los servicios), proyección sin N+1 (`with` de miembros, cajas y pagos), rutas web y espejo móvil.
- [ ] **Step 4:** Correr `MultienvioNacionalHttpTest` y `MobileEspejoRutasTest`; pasan.
- [ ] **Step 5: Concurrencia MySQL** (patrón de `InstruccionRegistrarConcurrenciaMysqlTest`: se omite si no está habilitada con variable de entorno y base exclusiva): dos procesos confirman a la vez → un grupo y N miembros. Antes de ejecutar, crear la base `gelia_multienvio_test` en `gelianv-mysql-1` y **pedir confirmación al usuario** del usuario/credenciales a usar. Correr y reportar; si no hay base, reportar «no ejecutada».
- [ ] **Step 6:** Regresión dirigida completa de la fase: `NacionalAltaConsultaTest|NacionalOla3CierreTest|PesajeNacionalRespuesta20Test|NacionalVistaPreviaCotizacionTest|NacionalLogisticaCobroTest|NacionalAnexoExtraordinarioTest|MultienvioNacional`. Esperado: solo falla el baseline conocido.
- [ ] **Step 7:** Actualizar `docs/ai/modulos/REGISTRO_PEDIDOS.md` y `docs/ai/MAPA.md` con lo verificado, y la tabla §15 de la guía V3.1 (fecha, regla, decisión, casos). Reportar archivos y resultados reales; marcar pendiente lo no ejecutado.

---

## Self-review

- **Cobertura:** ME-02/03/05/06/11/12 y A03-A07 tienen tarea (3-7); pagos compartidos, UI, CEDIS/Delegado y reportes son Fases 2-4 (plan aparte). ME-09 se cubre en Fase 3: la Fase 1 solo garantiza que no se clonan pagos.
- **Consistencia de tipos:** `MultienvioNacional`, estados, `MultienvioNacionalException::*` y firmas coinciden entre tareas.
- **Proporción:** ~8 tareas, solo firmas y aserciones; los cuerpos los decide quien implementa con el doc R1.
- **Pendiente real:** la lista exacta de campos a copiar sale de la Tarea 1; la base MySQL de pruebas requiere confirmación del usuario.

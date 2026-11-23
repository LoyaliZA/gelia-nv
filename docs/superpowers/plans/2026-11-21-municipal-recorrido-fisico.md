# Municipal: recorrido físico propio (fases 1–2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un pedido municipal remisionado se prepara en CEDIS sin estatus de guía, viaja a PDV por el traslado compartido, queda en la custodia compartida y sale a terminal con receptor, sin pasar por la tarea de Tienda.

**Architecture:** Se reutiliza el traslado interno CEDIS→PDV que ya existe para `PedidoBma` (`esTrasladoInternoAPdv` → empaque sin guía → `MarcarEnviadoPedidoBmaService` → `CrearRecepcionEsperadaPdvService` → `ResguardoPdv`). Municipal entra a ese camino por una marca propia, no por `ENVIO_MUNICIPIO`. La carátula se genera desde el pedido. La salida a terminal es una acción nueva sobre un `ResguardoPdv` en custodia, siguiendo el patrón de la rama `esOrigenNacional()`.

**Tech Stack:** Laravel (Sail), PHPUnit sobre SQLite en memoria, React/Inertia, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-21-municipal-recorrido-fisico-design.md` (fases 1 y 2). Las fases 3–5 (selector compartido, cambios de modalidad, Nacional) tendrán planes aparte.

## Global Constraints

- Ejecutar PHP/Artisan/npm solo con `./vendor/bin/sail`. Pruebas: `./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test --filter=...`.
- **No hacer commit ni push**: `AGENTS.md` lo prohíbe sin pedido expreso. Los pasos de cierre de cada tarea son «mostrar `git diff --stat` al usuario», no commit.
- No tocar `PedidoBmaController` ni lo de Nacional/CEDIS en curso de Paola Castellanos (cambios sin confirmar en `custodiaNacional` y la migración `2026_11_21_160000_permiso_autorizar_segundo_traslado_tienda.php`).
- Permiso nuevo: en migración y en `permisos_actuales.php`.
- Build (`sail npm run build`) una sola vez al terminar las tareas de interfaz, y `optimize` si cambian rutas.
- Sin códigos de ficha (M-01, T-02…) en textos visibles; usar nombres de operación.
- Municipal nunca entra a la bandeja del Delegado ni exige guía/rastreo. Nacional y Tienda conservan su comportamiento exacto.
- Interfaz: leer `GUIA_DISENO_GELIA.md` y `.ux-profile.md`; verificar a 375 y 1280 px.
- Sin `migrate:fresh`; no tocar datos reales.

## Review Focus

- Municipal ya con tarea `ENVIO_MUNICIPIO` abierta (flujo viejo): debe seguir funcionando y no duplicar el traslado.
- Municipal sin `sucursal_destino_id` y con más de una sucursal PDV habilitada: debe pedirla, no elegir una.
- City Center oculto o fuera de la lista piloto: nunca aparece como destino.
- Doble clic o reintento en «Salida a terminal»: una sola salida, sin segundo evento.
- Salida a terminal sobre custodia con recepción parcial, incidencia abierta o de otra sucursal: rechazada.
- Pedido municipal cancelado, o con resguardo de envío sin liberar, intentando empacar o salir: rechazado.

---

## File Structure

- `app/Support/ControlPedidos/Municipal/RecorridoFisicoMunicipal.php` (nuevo): única fuente de «este pedido sigue el recorrido físico municipal».
- `app/Models/ControlPedidos/PedidoBma.php`: tres predicados existentes delegan en la marca municipal.
- `app/Services/ControlPedidos/Municipal/ResolverSucursalDestinoMunicipal.php` (nuevo): decide la sucursal PDV destino.
- `app/Services/ControlPedidos/Conversion/ValidarCapturaMunicipal.php`: acepta y valida `sucursal_destino_id`.
- `app/Services/ControlPedidos/Municipal/GenerarCaratulaMunicipalService.php` (nuevo) y migración que vuelve nullable `pedido_bma_caratulas.pedido_bma_tarea_preparacion_id`.
- `app/Services/PuntoVenta/Resguardos/RegistrarSalidaTerminalResguardoPdvService.php` (nuevo), controlador, ruta, permiso y `ResguardoPdvEvento::TIPO_SALIDA_TERMINAL`.
- `resources/js/Pages/PuntoVenta/Resguardos/` (detalle del resguardo): acción «Salida a terminal».
- Pruebas nuevas en `tests/Feature/ControlPedidos/` y `tests/Feature/PuntoVenta/`.

---

### Task 1: Marca de recorrido físico municipal y destino PDV

**Files:**
- Create: `app/Support/ControlPedidos/Municipal/RecorridoFisicoMunicipal.php`
- Create: `app/Services/ControlPedidos/Municipal/ResolverSucursalDestinoMunicipal.php`
- Modify: `app/Models/ControlPedidos/PedidoBma.php` (`esTrasladoInternoAPdv` ~716, `requiereSucursalDestino` ~682, `prohibeSucursalDestino` ~701)
- Modify: `app/Services/ControlPedidos/Conversion/ValidarCapturaMunicipal.php`
- Test: `tests/Feature/ControlPedidos/RecorridoFisicoMunicipalTest.php`

**Interfaces:**
- Produces: `RecorridoFisicoMunicipal::aplica(PedidoBma $pedido): bool` (true si `ConsultaMunicipal::es($pedido)` y el pedido no tiene tarea `ENVIO_MUNICIPIO` abierta); `ResolverSucursalDestinoMunicipal::ejecutar(?int $solicitada): int` (devuelve la sucursal o lanza `ValidationException` con clave `sucursal_destino_id`).
- Consumes: `ConsultaMunicipal::es`, `AlmacenPdv::esAlmacen`, lista piloto de `PreparacionTiendaConfig`, catálogo de almacenes (activo, visible en pedidos, tipo PDV).

- [ ] **Step 1: Write the failing tests** en `RecorridoFisicoMunicipalTest`: `test_municipal_con_cedis_y_sucursal_es_traslado_interno` (afirma `esTrasladoInternoAPdv() === true`, `requiereSucursalDestino() === true`, `prohibeSucursalDestino() === false`, `ofreceRastreo() === false`); `test_nacional_y_tienda_no_cambian` (mismos predicados con los valores previos); `test_municipal_con_tarea_envio_municipio_abierta_no_aplica`; `test_resolver_usa_la_unica_sucursal_habilitada`; `test_resolver_exige_sucursal_si_hay_varias`; `test_resolver_rechaza_city_center_oculta_o_fuera_de_lista_piloto`.
- [ ] **Step 2: Run** `--filter=RecorridoFisicoMunicipalTest`. Expected: FAIL (clases inexistentes).
- [ ] **Step 3: Implement** `RecorridoFisicoMunicipal::aplica` y `ResolverSucursalDestinoMunicipal::ejecutar` con los criterios de almacén de la guía de configuración operativa (activo, visible en pedidos, tipo PDV, lista piloto si no está vacía). Misma lista que usa la consulta de Tienda: reutilizar su servicio, no copiarlo.
- [ ] **Step 4: Modify the three `PedidoBma` predicates** para que, cuando `RecorridoFisicoMunicipal::aplica($this)`, traten el pedido como destino físico PDV (`prohibe` false, `requiere` true, traslado interno true si hay almacén CEDIS y sucursal). Resto de ramas intactas.
- [ ] **Step 5: Modify `ValidarCapturaMunicipal`** para aceptar `sucursal_destino_id` y resolverla con `ResolverSucursalDestinoMunicipal`; el resultado se guarda en `pedidos_bma.sucursal_destino_id` desde `RegistrarSolicitudMunicipalService`.
- [ ] **Step 6: Run** `--filter='RecorridoFisicoMunicipalTest|MunicipalOrdinarioM01Test'`. Expected: PASS (las pruebas M01 existentes siguen verdes).
- [ ] **Step 7:** `git diff --stat` al usuario. Sin commit.

### Task 2: Empaque de CEDIS municipal sin guía ni rastreo

**Files:**
- Modify: `app/Support/ControlPedidos/MaquinaEstadosPedidoBma.php` (`faseDestinoEmpaque`, ~204)
- Modify: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php` (`assertPuedeEmpacar`)
- Test: `tests/Feature/ControlPedidos/EmpaqueMunicipalCedisTest.php`

**Interfaces:**
- Consumes: Task 1 (`esTrasladoInternoAPdv` verdadero para municipal).

- [ ] **Step 1: Write failing tests**: `test_empaque_municipal_termina_en_pendiente_de_envio` (fase `PENDIENTE_DE_ENVIO`, `numero_rastreo` nulo); `test_empaque_municipal_no_notifica_al_delegado` (ninguna notificación `pedido_pendiente_guia`); `test_delegado_no_lista_municipal` (assert contra `ListarPedidosDelegadoService`); `test_empaque_municipal_exige_remision_pago_y_resguardo_liberado`; `test_empaque_municipal_exige_bultos_con_evidencia`; `test_nacional_sigue_pasando_por_guia`.
- [ ] **Step 2: Run** `--filter=EmpaqueMunicipalCedisTest`. Expected: FAIL en los casos municipales si algún predicado aún cae a rama de guía; PASS en `test_nacional_sigue_pasando_por_guia`.
- [ ] **Step 3: Implement** solo lo que falte para que los tests pasen. Por el diseño de Task 1 no debería requerir cambios en `faseDestinoEmpaque`; si lo requiere, añadir la rama `RecorridoFisicoMunicipal::aplica` antes del cálculo de guía.
- [ ] **Step 4: Run** `--filter='EmpaqueMunicipalCedisTest|MarcarEmpacado|ListarPedidosDelegado'`. Expected: PASS.
- [ ] **Step 5:** `git diff --stat`.

### Task 3: Salida de CEDIS y recepción esperada en PDV

**Files:**
- Modify: `app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php` (texto de historial y notificación; rama ~61–80, ~168, ~178)
- Modify: `app/Services/PuntoVenta/Resguardos/CrearRecepcionEsperadaPdvService.php` (`assertElegible`, `crear`: snapshot con `origen => 'municipal'`)
- Test: `tests/Feature/PuntoVenta/RecepcionEsperadaMunicipalTest.php`

**Interfaces:**
- Consumes: Tasks 1–2. Produces: `ResguardoPdv` en `ESTADO_PENDIENTE_RECEPCION` con `snapshot_json['origen'] === 'municipal'`, clave de idempotencia existente `CrearRecepcionEsperadaPdvService::claveIdempotencia`.

- [ ] **Step 1: Write failing tests**: `test_salida_de_cedis_crea_recepcion_esperada_municipal`; `test_reintento_no_duplica_resguardo`; `test_salida_no_pide_guia_ni_rastreo`; `test_historial_dice_salida_a_pdv_no_envio_a_terminal` (texto «Salida de CEDIS hacia PDV», nunca «entregado» ni «terminal»); `test_municipal_ya_en_flujo_viejo_con_tarea_no_crea_segundo_resguardo`.
- [ ] **Step 2: Run** `--filter=RecepcionEsperadaMunicipalTest`. Expected: FAIL.
- [ ] **Step 3: Implement** el snapshot municipal y los textos; la salida de CEDIS no cambia el estatus a «entregado»: el pedido queda en su fase actual hasta la salida a terminal (Task 6).
- [ ] **Step 4: Run** `--filter='RecepcionEsperadaMunicipalTest|TransferenciaTiendaTest'`. Expected: PASS (Tienda intacta).
- [ ] **Step 5:** `git diff --stat`.

### Task 4: Recepción y custodia en PDV

**Files:**
- Modify (solo si las pruebas lo exigen): `app/Services/PuntoVenta/Resguardos/RegistrarConfirmacionCustodiaPdvService.php`, `PasarARecepcionResguardoPdvService.php`
- Test: `tests/Feature/PuntoVenta/CustodiaMunicipalPdvTest.php`

**Interfaces:**
- Consumes: Task 3. Produces: `ResguardoPdv` en `ESTADO_EN_CUSTODIA` para un pedido municipal.

- [ ] **Step 1: Write failing tests**: `test_gerencia_confirma_recepcion_y_pasa_a_custodia`; `test_municipal_en_custodia_aparece_en_bandeja_de_la_sucursal`; `test_recepcion_parcial_o_incidencia_abierta_no_pasa_a_custodia`; `test_usuario_de_otra_sucursal_no_confirma`.
- [ ] **Step 2: Run** `--filter=CustodiaMunicipalPdvTest`. Expected: algunos PASS (mecanismo compartido) y FAIL solo donde haya supuestos de Tienda.
- [ ] **Step 3: Implement** únicamente las correcciones que los fallos muestren; no se crea estado nuevo de custodia.
- [ ] **Step 4: Run** `--filter='CustodiaMunicipalPdvTest|Resguardo'`. Expected: PASS.
- [ ] **Step 5:** `git diff --stat`.

### Task 5: Carátula generada desde el pedido

**Files:**
- Create: `database/migrations/2026_11_21_170000_caratulas_tarea_nullable.php` (hace nullable `pedido_bma_tarea_preparacion_id`; `down` solo revierte si no hay filas nulas)
- Create: `app/Services/ControlPedidos/Municipal/GenerarCaratulaMunicipalService.php`
- Modify: `app/Models/ControlPedidos/PedidoBmaCaratula.php` si hace falta una relación o scope
- Test: `tests/Feature/ControlPedidos/CaratulaMunicipalDesdePedidoTest.php`

**Interfaces:**
- Produces: `GenerarCaratulaMunicipalService::ejecutar(PedidoBma $pedido, User $usuario): PedidoBmaCaratula` (estado `GENERADA`, `pedido_bma_tarea_preparacion_id` nulo, `modalidad_cobro` en `PAGADO|POR_COBRAR` tomada de `finanzas_municipales`, destinatario/teléfono/terminal/identificación del expediente del pedido, nueva `version` por pedido, PDF y `hash_sha256`).
- Consumes: `PedidoBmaCaratula` (campos existentes), plantilla de carátula de la paquetería, expediente de consignatario.

- [ ] **Step 1: Write failing tests**: `test_genera_caratula_con_datos_del_pedido_sin_tarea`; `test_regenerar_invalida_la_anterior_y_sube_version`; `test_modalidad_cobro_sale_del_pedido`; `test_sin_consignatario_vigente_rechaza`; `test_municipal_con_tarea_vieja_sigue_usando_su_flujo` (la carátula con tarea no se toca).
- [ ] **Step 2: Run** `--filter=CaratulaMunicipalDesdePedidoTest`. Expected: FAIL.
- [ ] **Step 3: Implement** migración y servicio, reutilizando el render PDF de `GenerarCaratulaPedidoService` (extraer lo común a un método compartido solo si es corto; no duplicar la plantilla).
- [ ] **Step 4: Run** `--filter='CaratulaMunicipalDesdePedidoTest|ConfirmarCaratulaIdentidadTest'`. Expected: PASS.
- [ ] **Step 5:** `git diff --stat`.

### Task 6: Salida a terminal desde la custodia

**Files:**
- Create: `app/Services/PuntoVenta/Resguardos/RegistrarSalidaTerminalResguardoPdvService.php`
- Create: `app/Http/Controllers/PuntoVenta/Resguardos/SalidaTerminalResguardoPdvController.php`
- Modify: `app/Models/PuntoVenta/ResguardoPdvEvento.php` (`TIPO_SALIDA_TERMINAL = 'resguardo.salida_terminal'`)
- Modify: ruta de resguardos de PDV; migración de permiso `punto_venta.resguardos.salida_terminal` + `permisos_actuales.php`
- Test: `tests/Feature/PuntoVenta/SalidaTerminalMunicipalTest.php`

**Interfaces:**
- Produces: `RegistrarSalidaTerminalResguardoPdvService::ejecutar(ResguardoPdv $resguardo, User $actor, int $versionEsperada, string $idempotencyKey, string $receptor): ResguardoPdv`. Efecto: evento `TIPO_SALIDA_TERMINAL` con receptor; resguardo sale de custodia (`ESTADO_ENTREGADO`) con motivo «salida a terminal»; el pedido pasa a fase `ENVIADO` con historial «Salida a terminal registrada»; `PoliticaResguardoEnvio::envioYaSalio` pasa a true.
- Consumes: Tasks 3–4; patrón de la rama `esOrigenNacional()` de `RegistrarEntregaResguardoPdvService`.

- [ ] **Step 1: Write failing tests**: `test_gerencia_registra_salida_con_receptor`; `test_receptor_vacio_rechazado`; `test_doble_envio_con_misma_clave_no_duplica`; `test_otra_clave_sobre_resguardo_ya_salido_es_conflicto`; `test_rechaza_resguardo_no_en_custodia_con_incidencia_o_de_otra_sucursal`; `test_rechaza_pedido_no_municipal`; `test_sin_permiso_403`; `test_tras_la_salida_no_hay_cambio_ordinario_a_tienda` (usa la política de conversión existente); `test_salida_no_marca_entregado_al_cliente_ni_cobra_flete`.
- [ ] **Step 2: Run** `--filter=SalidaTerminalMunicipalTest`. Expected: FAIL.
- [ ] **Step 3: Implement** servicio con transacción y bloqueo del resguardo y del pedido, versión esperada e idempotencia como en la entrega existente; controlador y ruta con el permiso nuevo.
- [ ] **Step 4: Run** `--filter='SalidaTerminalMunicipalTest|EntregaResguardoPdv'`. Expected: PASS.
- [ ] **Step 5:** `git diff --stat`.

### Task 7: Interfaz mínima: carátula y salida a terminal

**Files:**
- Modify: detalle de resguardo PDV (`ContenidoDetalleResguardo` y su servicio `ConsultaDetalleResguardoPdvService`), para mostrar **solo** en resguardos municipales: botón «Generar carátula» / «Descargar carátula» y acción «Registrar salida a terminal» con campo «Quién recibe» (visible, etiquetado).
- Test: Vitest junto al componente; `npm run build` una vez.

**Interfaces:**
- Consumes: Tasks 5–6 (rutas y datos). Sin rediseñar el detalle: se agregan dos acciones contextuales.

- [ ] **Step 1: Write failing Vitest**: `muestra la salida a terminal solo en resguardo municipal en custodia`; `deshabilita el botón sin receptor y tras enviar`; `no muestra acciones en Tienda ni Nacional`.
- [ ] **Step 2: Run** `./vendor/bin/sail npm run test -- <archivo>`. Expected: FAIL.
- [ ] **Step 3: Implement** la sección reutilizando primitivas y tokens existentes (leer la guía de diseño primero); copy con verbos: «Registrar salida a terminal», «Generar carátula».
- [ ] **Step 4: Run** Vitest y luego `./vendor/bin/sail npm run build` una vez; revisar a 375 y 1280 px con datos ficticios.
- [ ] **Step 5:** `git diff --stat` y capturas al usuario.

### Task 8: Documentación de contexto

**Files:**
- Modify: `docs/ai/modulos/ENVIO_MUNICIPAL.md`, `docs/ai/MAPA.md`, `docs/ai/ESTADO.md`

- [ ] **Step 1:** Registrar el nuevo recorrido físico, la marca `RecorridoFisicoMunicipal`, la carátula sin tarea y la salida a terminal, con `ruta:línea` verificadas, y marcar `[pendiente]` lo no probado (recorrido con cuentas reales, MySQL).
- [ ] **Step 2:** Actualizar «Última verificación» a la fecha real de ejecución.
- [ ] **Step 3:** `git diff --stat`.

## Self-review

- **Cobertura del diseño (fases 1–2):** objetivo 1 (Tasks 1–2), 2 y 4 (Tasks 3–4, 6), 3 (Task 4, sin estado nuevo), carátula (Task 5), receptor sin foto (Task 6). Objetivos 5 y 6 corresponden a fases 3–4 y quedan fuera de este plan por diseño.
- **Decisión que el constructor debe confirmar al ejecutar Task 6:** que `ESTADO_ENTREGADO` del resguardo con evento `salida_terminal` no se confunda con entrega al cliente en la bandeja de PDV; si se confunde, ajustar el rótulo de la lista, no el estado.
- **Riesgo:** los pedidos municipales que ya tienen tarea `ENVIO_MUNICIPIO` abierta quedan en el flujo viejo (`RecorridoFisicoMunicipal::aplica` es falso para ellos).

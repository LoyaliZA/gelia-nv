# Municipal — Diseño de M-03 (anexo) y M-07 (cambio a Tienda)

**Estado:** diseño revisión 4, implementado en backend e interfaz el 23 de noviembre de 2026 salvo lo marcado como pendiente (traslado a otra sucursal con traslado hecho, pendientes de negocio de §4). La referencia vigente del código está en `docs/ai/modulos/ENVIO_MUNICIPAL.md`; donde difieran, manda el código: el saldo de envío se genera en la pantalla de Saldos a favor y un crédito pendiente de conciliación no se usa en pedidos nuevos. La revisión 2 fue aceptada en su dirección general, incluida la bifurcación entre municipal 2.0 e históricos (§1.1). La revisión 3 fue aprobada en M-03b y M-07. La revisión 4 solo reescribe la clasificación de créditos SAF (§3.4), con sus pruebas, pendientes y orden de construcción.
**Fuente de reglas:** guía operativa V3.1 §5 y §7.1/§7.5 (M-03, M-07, S-06, AC-02, AC-07) y las decisiones del usuario. Este documento las aplica; no las amplía.
**Alcance:** ramificaciones del recorrido municipal M-F01. No son procesos independientes.

## 0. Decisiones vigentes

| Tema | Decisión |
| --- | --- |
| Salida a terminal | Se reutiliza `RecorridoFisicoMunicipal::salioATerminal()`. El traslado CEDIS→PDV **no** es salida a terminal. Pedidos históricos conservan sus reglas (§1). |
| M-03 sin remisión | Se modifica el pedido original. No se crea otro pedido. |
| M-03 con remisión | Complemento vinculado con pago y remisión propios. Integración física en CEDIS o PDV según dónde esté la mercancía, siempre antes de la salida a terminal. Anexo y despacho no pueden competir (§2.3). |
| M-07 | Si la mercancía está en CEDIS, se coordina su traslado. Si ya está en PDV, se aprovecha esa ubicación sin traslados ficticios. Se conservan pagos, remisiones, custodia y evidencias. El destino predeterminado es la sucursal prevista y puede cambiar conforme a T-03 (§3.2). |
| Saldo de envío (S-06) | Administración autoriza su generación. Se reconoce para gastos de envío de **pedidos posteriores del mismo cliente, municipales o nacionales**. Nunca mercancía. Se clasifica por **origen económico comprobado**, no por motivo, y se impone con restricción estructural y validación en servidor para créditos nuevos e históricos (§3.4). |
| Mínimo de $10 | No se aplica automáticamente a S-06. El criterio sigue pendiente de negocio (§4). |
| `envioYaSalio()` | No se modifica. M-04, M-09 y M-10 siguen igual. |

## 1. Salida a terminal (corrige la revisión 1)

La revisión 1 proponía un predicado nuevo. **Se descarta**: ya existe `RecorridoFisicoMunicipal::salioATerminal()` y lo usan M-02 (`EvaluarElegibilidadConversion`) y el detalle de resguardo PDV. Su criterio es la marca persistente `solicitud_preparacion_json.salida_terminal`, que escribe `RegistrarSalidaTerminalResguardoPdvService`. No se deduce de `FASE_ENVIADO`.

### 1.1 Pedidos históricos (riesgo a resolver antes de construir)
`salioATerminal()` solo mira la marca; **no** comprueba si el pedido pertenece al recorrido nuevo. Un municipal histórico (sin `recorrido_fisico_municipal`, o con tarea `ENVIO_MUNICIPIO`) nunca recibe esa marca, aunque su paquete ya haya salido. Usarla sola permitiría anexar o cambiar a Tienda un pedido que ya despachó.

Regla propuesta para M-03 y M-07:

| Pedido | ¿Ya salió? |
| --- | --- |
| `RecorridoFisicoMunicipal::aplica($pedido)` | `salioATerminal($pedido)` |
| Histórico / `ENVIO_MUNICIPIO` | Regla anterior, es decir `PoliticaResguardoEnvio::envioYaSalio($pedido)` (solo se **lee**, no se cambia) |

Se encapsula en un único punto de consulta nuevo (p. ej. un método estático en `RecorridoFisicoMunicipal` que combine ambos) para que M-03 y M-07 no repitan la bifurcación. M-02 queda como está.

**Pruebas:**
1. Recorrido nuevo en `FASE_ENVIADO` con traslado CEDIS→PDV, sin `salida_terminal`: anexo y cambio permitidos.
2. Igual, con `salida_terminal`: ambos rechazados, mensaje `MENSAJE_SALIO_A_TERMINAL`.
3. Histórico en `FASE_ENVIADO` sin marca: rechazados (rige `envioYaSalio()`).
4. Histórico con tarea `ENVIO_MUNICIPIO` despachada: rechazados.
5. `envioYaSalio()` sin cambios: las pruebas de M-04, M-09 y M-10 siguen verdes sin tocarlas.

## 2. M-03 — Anexo de piezas

### 2.1 M-03a: sin remisión
- **Estado inicial:** pedido municipal editable por Ventas, sin remisión.
- **Efecto:** se modifica el pedido original («Modificar pedido», ya existe). Conserva versión y evidencia; la consulta se vuelve a responder; Ventas cierra de nuevo.
- **Verificar, no construir:** que el cierre exija la versión vigente de la consulta y que CEDIS reciba la actualización.
- **Dato a re-verificar:** la revisión 1 citaba una prueba fallida en `MunicipalOrdinarioM01Test` (`test_resolver_faltante_y_responder_actualizacion_permite_cierre_sin_pisar_flete`). No se volvió a ejecutar al revisar este documento; correrla antes de depender de este camino.
- La condición de «sin remisión» se evalúa también bajo bloqueo (§2.3): si la remisión aparece entre que Ventas abre el formulario y guarda, se rechaza y se ofrece M-03b.

### 2.2 M-03b: con remisión, antes de la salida a terminal

| Campo | Contenido |
| --- | --- |
| Caso fuente | M-03, AC-05, AC-06 |
| Estado inicial | Principal municipal con remisión vigente y sin salida a terminal según §1. |
| Rol que actúa | Ventas |
| UX | «Agregar piezas» en el menú de la fila (sección Operaciones). Solo visible con remisión y sin salida a terminal. Formulario municipal en modo complemento: cliente, consignatario y terminal **heredados** del principal y no editables; piezas, documento e importe nuevos. |
| Efecto | Crea un `PedidoBma` complemento con identidad propia (§2.4): folio, pago y remisión propios, vinculado al principal con `pedido_principal_id`. No toca remisión ni pago del principal. |
| Guardas | 1) Sin salida a terminal, verificado bajo bloqueo. 2) El complemento exige pago verificado y remisión propios. 3) El principal queda marcado como en espera del complemento y no se despacha mientras el complemento esté sin resolver. 4) Límite de complementos abiertos: **pendiente de negocio** (§4). |
| Responsable siguiente | Auxiliar valida pago y remisión. La mercancía la confirma el área que la tiene (CEDIS o PDV). |
| Integración física | Según ubicación (§2.4). |
| Trazabilidad | Historial en principal y complemento con autor, fecha, motivo y versión. |

**M-03c (ya salió a terminal):** no se integra. «Agregar piezas» no aparece y el servidor rechaza. Ventas abre un envío nuevo. Mensaje: «El paquete ya salió a terminal. Las piezas adicionales van en otro envío».

### 2.3 Concurrencia entre anexo y despacho
Hoy `RegistrarSalidaTerminalResguardoPdvService` bloquea primero el pedido y luego el resguardo; `MarcarEnviadoPedidoBmaService` bloquea el pedido. El anexo debe usar **el mismo orden** (pedido principal, luego resguardo) para no generar interbloqueos.

Reglas:
1. Crear el complemento, marcar la espera en el principal y vincular ocurren en **una sola transacción** con el principal bloqueado (`lockForUpdate`). La condición «sin salida a terminal» se vuelve a evaluar dentro del bloqueo.
2. El registro de salida a terminal comprueba, dentro de su propio bloqueo, que el principal no tenga un complemento sin resolver, y rechaza si lo tiene. Es una guarda adicional en ese servicio; no cambia `envioYaSalio()`.
3. La integración física del complemento también revalida «sin salida a terminal» bajo bloqueo antes de escribir.
4. Idempotencia: clave de solicitud por intento de anexo; un doble clic crea un solo complemento.
5. Quien pierde la carrera recibe el mensaje de M-03c, no un error genérico.

**Pruebas (secuenciales, SQLite en memoria no ejecuta concurrencia real):**
1. Salida registrada después de abrir el formulario y antes de guardar: el anexo se rechaza.
2. Complemento creado, luego intento de salida a terminal: la salida se rechaza mientras el complemento esté abierto.
3. Mismo `solicitud_id` repetido: un solo complemento.
4. Las transacciones toman los bloqueos en el mismo orden (revisión de código, no prueba automática).

### 2.4 Integración física (CEDIS o PDV)
La ubicación se decide por hechos físicos del principal, no por `FASE_ENVIADO` ni por ubicación supuesta:

| Dónde está el principal | Quién integra | Mecanismo a reutilizar |
| --- | --- | --- |
| CEDIS, antes del traslado | CEDIS | `IntegrarRemisionEnCedisMunicipalService` |
| PDV, en custodia | PDV | `IntegrarCustodiaLocalMunicipalService`, con `RetirarCustodiaParaEnvioService` si hay que mover el complemento desde otra custodia |
| En tránsito CEDIS→PDV | Nadie todavía | Espera a la recepción en PDV; no se integra en tránsito |

- Siempre antes de la salida a terminal.
- Ventas no confirma hechos físicos.
- **Aviso:** M-04 solo lista remisiones ya pagadas; el complemento nace sin pago, así que entra al mecanismo solo cuando esté pagado y remisionado.
- **Aviso, patrones paralelos:** ya existen anexo de Tienda (`RegistrarAnexoTiendaService`, `IntegrarAnexoFisicoTiendaService`) y complemento nacional (`RegistrarComplementoNacional`). Este diseño no los reutiliza porque el recorrido municipal tiene su propia custodia y terminal. Confirmar esa elección antes de construir.
- **Identidad del complemento y bandeja (resuelve el conflicto de la revisión 2).**

  *Hallazgo en código.* La semántica de resguardo no la dispara `pedido_principal_id` por sí solo. `CrearPedidoBmaService::resolverPrincipalSiComplementario()` solo la activa cuando el alta llega con `modo_resguardo = 'complementario'` o con un tipo de operación `esResguardoComplementario()`; en ese caso fuerza `es_resguardo = true`, copia el cliente del principal y deja `costo_envio`, cajas y peso en nulo (`ResuelveDatosPedidoBma`). Ese es el resguardo de envío (M-04 / envío en resguardo), no el anexo municipal. Los complementos de Tienda (`RegistrarAnexoTiendaService::pedidoRelacionado()`) y Nacional (`RegistrarComplementoNacional`) **no pasan por ahí**: crean el hijo directamente con `pedido_principal_id`, `es_resguardo = false` y una marca propia en `solicitud_preparacion_json`.

  *Decisión de diseño.* El complemento municipal sigue ese precedente y **no** usa `CrearPedidoBmaService` en modo complementario:
  1. Servicio propio de alta (p. ej. `RegistrarComplementoMunicipalService`), con `es_resguardo = false`, sin tipo de operación de resguardo, folio de complemento (`ejecutarComplemento`) y estatus inicial de borrador.
  2. Marca propia `solicitud_preparacion_json.complemento_municipal` con `pedido_origen_id`, `solicitud_id` (idempotencia), `antes_de_salida` y estado de integración. No reutiliza `anexo_*`, `complemento_nacional` ni `espera_complemento`.
  3. Hereda cliente, almacén, sucursal destino, terminal y consignatario del principal. Importe, pago y remisión son propios; el principal conserva los suyos.
  4. `PedidoBma::esComplemento()` sigue siendo `pedido_principal_id !== null`, y `validarPrincipalParaComplemento` evita complementar un complemento y exige el mismo cliente. Se reutiliza esa validación sin tocar su comportamiento.
  5. El principal registra el vínculo en su propio JSON (lista de complementos y mapa por `solicitud_id`), igual que `anexos_relacionados` en Tienda.

  *Bandeja del área responsable, sin quitar filtros globales.* `ListarPedidosCedisService` filtra con `whereNull('pedido_principal_id')` en `queryBase`, `queryTodos` y `queryPendientesPesaje`, y carga los hijos con las relaciones `complementos.*`. Es la convención vigente: el complemento se muestra **dentro de la tarjeta del principal**, no como fila aparte. Se conserva ese filtro. Para que el complemento sea visible y accionable para el área que tiene la mercancía:
  - **CEDIS:** la tarjeta del principal muestra el complemento con su folio, estado de pago, remisión y estado de integración, y la acción de integrar solo cuando esté pagado y remisionado. Sin cambiar `whereNull('pedido_principal_id')`.
  - **PDV:** el detalle de resguardo (`ConsultaDetalleResguardoPdvService`) ya resuelve el principal por `pedido_principal_id`; se muestra el complemento pendiente de integración en el resguardo del principal.
  - **Auxiliar (pago y remisión):** debe poder encontrar el complemento para validar su pago y remisión propios. Se verifica contra `ListarPedidosAuditoriaService`, que hoy cuenta «consolidados» con `whereNull('pedido_principal_id')` y `whereHas('complementos')`; si el complemento no aparece como trabajo propio del auxiliar, se añade un acceso explícito acotado a complementos municipales, no un cambio global del filtro.
  - **Ventas:** conserva el complemento visible desde el principal y desde su propia lista.
  - Un complemento nunca debe aparecer como candidato anexable de `ResguardosEnvioPedido::listar()`; ese servicio exige `es_resguardo = true` y `pedido_principal_id` nulo, y el complemento no cumple ninguna de las dos condiciones.
  - Si el principal sale de la bandeja de CEDIS (p. ej. ya recibido en PDV), el complemento sigue visible en PDV y en el detalle del principal; no depende de la bandeja de CEDIS.

  *Verificación pendiente antes de construir:* `CalcularRequisitosPreparacionService` ya distingue `pedido_principal` de `complemento` según `pedido_principal_id`; revisar que esa lógica sea correcta para el complemento municipal y no lo trate como complemento de resguardo.

  *No se elimina ni se relaja ningún filtro global.* Las consultas de CEDIS, auditoría y resguardos conservan `whereNull('pedido_principal_id')`.
- Paquete ya cerrado: **pendiente de negocio** (§4).

**Pruebas M-03b:**
1. Con remisión y sin salida: aparece «Agregar piezas»; el complemento hereda terminal y consignatario.
2. Principal no avanza a despacho con complemento sin pago.
3. Complemento pagado y remisionado, mercancía en CEDIS: solo CEDIS integra.
4. Igual, mercancía en custodia PDV: solo PDV integra.
5. En tránsito: la integración se rechaza.
6. Ventas no puede confirmar integración.
7. Con salida a terminal: acción ausente y rechazo de servidor.
8. Sin doble cobro del principal; remisión del principal intacta.
9. El complemento se crea con `es_resguardo = false`, sin tipo de operación de resguardo, con folio, pago y remisión propios y marca `complemento_municipal`.
10. El complemento no aparece en `ResguardosEnvioPedido::listar()` ni activa `espera_complemento` de resguardo.
11. Con pago y remisión propios, el complemento aparece en la tarjeta del principal en CEDIS; con la mercancía en custodia PDV aparece en el detalle de resguardo; el auxiliar lo encuentra para validar pago y remisión.
12. Las consultas de CEDIS conservan `whereNull('pedido_principal_id')`: el complemento no duplica filas en la bandeja.
13. El complemento no se puede complementar de nuevo y exige el mismo cliente que el principal.

**Riesgos:** confundir complemento con M-04 (pagado vs. nuevo); heredar semántica de resguardo por usar `CrearPedidoBmaService` en modo complementario; que el complemento quede invisible para el auxiliar o para PDV; relajar filtros globales para resolverlo; desbloquear despacho por promesa; duplicar cobros.

## 3. M-07 — Cambio de Municipal a Tienda

| Campo | Contenido |
| --- | --- |
| Caso fuente | M-07, S-06, T-04 |
| Estado inicial | Pedido municipal sin salida a terminal según §1. |
| Solicita | Ventas, con motivo y antecedentes (evidencia del cliente o «Se notificó un error de captura»). |
| Autoriza el saldo | Administración. |
| Ejecuta lo físico | CEDIS o PDV según dónde esté la mercancía. Ventas no confirma hechos físicos. |
| Destino | Predeterminado: la sucursal prevista del recorrido. Puede cambiar conforme a T-03 (§3.2). |

### 3.1 Flujo
1. **Solicitud (Ventas).** «Cambiar a recolección en tienda». Pide motivo y antecedentes. Detiene el despacho municipal: bloquea la salida a terminal y la carátula nueva. Se registra bajo bloqueo del pedido (§2.3, mismas reglas).
2. **Verificación (CEDIS o PDV).** Revisa contenido, estado físico y destino. Invalida la documentación de transporte municipal que ya no aplique (carátula, instrucción de terminal) conservando el histórico.
3. **Empaque.** Si el paquete está completo, identificado y sirve, se conserva cerrado. Si no, el área que lo tiene lo reacondiciona con evidencia. No se obliga a desempacar.
4. **Ubicación y traslado** (§3.2).
5. **Recepción, custodia y entrega** en PDV, reutilizando el recorrido de Tienda (resguardo PDV, recepción, custodia, entrega con foto).
6. **Saldo de envío** (§3.4), solo si el envío estaba pagado y procede.

### 3.2 Ubicación de la mercancía

| Dónde está | Qué ocurre | Qué no ocurre |
| --- | --- | --- |
| CEDIS (no ha salido hacia PDV) | CEDIS registra salida hacia la sucursal. PDV recibe. Se reutiliza el traslado compartido. | — |
| En tránsito CEDIS→PDV | El traslado en curso sigue; solo cambia su destino lógico a entrega en Tienda. PDV recibe como ya estaba previsto. | No se crea un segundo traslado. |
| PDV, ya recibida y en custodia | Se conserva la custodia y la recepción existentes. Cambia la instrucción de entrega a Tienda. | **No** se crea salida de CEDIS ni recepción nueva: sería un traslado ficticio. |

La ubicación se decide por hechos físicos (estado del resguardo PDV: pendiente de recepción, en recepción, en custodia; traslado abierto), no por `FASE_ENVIADO`.

**Destino distinto de la sucursal prevista (T-03).** El destino predeterminado de M-07 es la sucursal prevista, pero puede cambiar. Se aplica la regla vigente de [TIENDA_T03_IMPLEMENTACION.md](TIENDA_T03_IMPLEMENTACION.md):

| Situación | Qué ocurre |
| --- | --- |
| Destino igual al de la sucursal prevista | Sigue §3.2: salida de CEDIS, traslado en curso o custodia actual. |
| Destino distinto y **aún no hubo primer traslado** (mercancía en CEDIS) | Se actualiza la instrucción de destino; CEDIS traslada directamente a la sucursal elegida. Conserva pedido, pago, remisión y bolsas; queda registro del cambio. Sin autorización de gerencia. |
| Destino distinto y **ya ocurrió el traslado** (mercancía en PDV o en tránsito hacia la sucursal prevista) | Cambio de sucursal con **autorización de gerencia** (`control_pedidos.tienda.autorizar_segundo_traslado`). La solicitud no cambia destino ni ubicación; autorizar exige escribir el recorrido acordado; el resguardo permanece físicamente en origen hasta la salida. |
| Rechazo de gerencia | Se conservan destino y custodia originales. Una nueva solicitud conserva la anterior en el historial. |

Reglas añadidas:
- No se crean movimientos ficticios: si la mercancía ya está en la sucursal elegida no hay salida ni recepción; si el traslado a la sucursal prevista sigue en tránsito, no se solicita otro hasta que llegue.
- Solicitar o autorizar no equivale a salida, recepción, custodia ni entrega; son hechos distintos.
- Se reutilizan `MovimientosTiendaService` y los servicios de segundo traslado existentes; no se duplica la autorización ni se otorgan permisos automáticamente.
- T-03 no admite solicitudes simultáneas ni durante tránsito; M-07 hereda esa restricción y la comprobación de versión dentro del bloqueo del pedido.
- La salida exige que el destino tenga un almacén PDV activo de custodia (regla de T-03).
- Requisito de verificación: confirmar que el mecanismo de T-03 acepta un pedido con recorrido municipal (hoy se describe sobre el recorrido de Tienda) sin reabrir su alcance.

### 3.3 Reglas
- Se conservan pedido, remisión, pagos, custodia y evidencias. No hay pedido nuevo ni segundo cobro de mercancía.
- Autorización, salida, recepción, custodia y entrega son hechos distintos. Autorizar el cambio o el saldo no los registra.
- Después de la salida a terminal: rechazo con `MENSAJE_SALIO_A_TERMINAL`.
- Reutilización: `InstruccionEntrega`, resguardo PDV, `MovimientosTiendaService`, y el patrón idempotente de M-02 (`CambiarModalidadPedidoService`, `EvaluarElegibilidadConversion`, `ResolverIdempotenciaConversion`) en sentido inverso.

### 3.4 Saldo de envío exclusivo para transporte (S-06)

**Decisión confirmada.** El saldo de transporte no utilizado se reconoce para aplicarse **exclusivamente a gastos de envío de pedidos posteriores del mismo cliente, municipales o nacionales**. Administración autoriza su generación. No sirve para mercancía. No aplica al pedido que lo originó, que al pasar a Tienda ya no tiene transporte.

#### 3.4.1 Hallazgos en código
- La categoría «envío» existe solo como etiqueta. `saf_motivos.categoria = 'ajustes_envio'` solo se lee para mostrar motivos. `ReservarSaldoSafService::creditosDisponibles()` filtra por cliente, estado, saldo, vigencia y `AlcanceSaf`, no por motivo. `SincronizarAplicacionesPedidoSafService` aplica sobre `total_a_cobrar` (mercancía, envío y seguro) sin tope por concepto.
- **El motivo no prueba el origen económico.** `ReconciliarTotalPedidoSafService` genera el crédito con el excedente del **total** del pedido y con el motivo que le pasa quien lo llama. `AtenderSinExistenciaPedidoBmaService` también lo invoca, y una falta de existencia es mercancía. Ese servicio solo deja en `observaciones` «Total antes / Total después», sin separar mercancía, envío y seguro. Además el excedente puede venir de pagos de más (`resumenPago`), no de un cambio de total.
- `ajustes_envio` incluye `ajuste_seguro`, `cambio_paqueteria`, `guia_propia`, `convenio_paqueteria`, `sobrante_envio`, `envio_no_utilizado` y `envio_a_resguardo`. Un mismo motivo agrupa orígenes distintos; `sobrante_envio` puede originarse por diferencias que incluyen mercancía.
- `GenerarCreditoSafService` es el punto único de creación (incluida la generación manual) y `AplicarReservaSafService::aplicarDirecto()` aplica sin reserva previa; ambos deben pasar por la misma política (§3.4.4).

**Consecuencia.** Marcar todo `ajustes_envio` como exclusivo de transporte sería una clasificación sin prueba: podría inmovilizar saldo legítimo de mercancía, y dejar sin marca lo demás repetiría el hueco. La clasificación debe apoyarse en el **origen económico comprobado**.

#### 3.4.2 Clasificación estructural
Dos atributos estructurales en el crédito, independientes del motivo:

| Atributo | Valores | Significado |
| --- | --- | --- |
| Uso permitido | `general` · `solo_transporte` · `pendiente_conciliacion` | Qué conceptos puede pagar el crédito. |
| Origen económico | `transporte_confirmado` · `mercancia` · `mixto` · `no_determinado` | Qué probó el origen, con fuente y responsable (`regla_de_generacion`, `s06_autorizado`, `conciliacion_administrativa`) y fecha. |

Reglas de clasificación:
1. **Transporte confirmado → `solo_transporte`.** Solo con prueba: el crédito nace de S-06 autorizado por Administración, o de una conciliación administrativa que acredita que el origen es transporte pagado y no utilizado, o de una generación cuyo desglose por componente demuestra que lo reducido fue únicamente `costo_envio` (sin mercancía).
2. **Origen en mercancía comprobado → `general`.**
3. **Ambiguo, mixto o sin prueba → `pendiente_conciliacion`.** No se asigna `solo_transporte` ni `general` por inferencia.
4. **El motivo no decide.** `ajustes_envio` no clasifica por sí mismo; sirve a lo sumo para listar candidatos a conciliación.

#### 3.4.3 Créditos históricos: conciliación sin tocar aplicaciones
**Dato disponible hoy.** Un crédito histórico no guarda el desglose por componente; no hay forma automática y fiable de comprobar su origen. Por eso la migración **no clasifica ningún histórico como `transporte_confirmado`** y solo hace lo seguro:

1. **Reporte de simulación** (solo lectura, antes de migrar). Lista los créditos candidatos: los de categoría `ajustes_envio` y los que Administración indique. Por crédito: folio, cliente, motivo, pedido y documento de origen, monto original, disponible, reservado y aplicado, «total antes / después» de las observaciones, y los componentes actuales del pedido (mercancía, envío, seguro). Muestra también las **reservas vigentes** sobre pedidos con mercancía. Es insumo para decidir, no una clasificación.
2. **Migración con valores seguros:** créditos de motivo ajeno a `ajustes_envio` → `general`; créditos de `ajustes_envio` → `pendiente_conciliacion`, origen `no_determinado`. Reversible, un movimiento de auditoría por crédito, sin cambiar montos, estados ni vigencias.
3. **Conciliación administrativa.** Un servicio nuevo, solo para Administración, resuelve cada crédito a `solo_transporte`, `general` o, si procede, deja la decisión de división para §4. Exige motivo escrito y evidencia, deja movimiento de auditoría y no se puede ejecutar por quien generó el crédito. No existe resolución masiva automática ni por motivo.
4. **Un crédito `pendiente_conciliacion` no puede aplicarse a mercancía** (aplicación incompatible con cualquiera de sus orígenes posibles). Sí puede aplicarse a transporte de pedidos posteriores del mismo cliente, porque esa aplicación es válida tanto si el origen fue transporte como si fue general. Esta regla conservadora está sujeta a confirmación (§4.5).
5. **No se alteran aplicaciones históricas.** Las aplicaciones consumadas y las reservas vigentes no se deshacen, no se reclasifican ni se recalculan. Las reservas vigentes sobre mercancía quedan en el reporte; qué hacer con ellas es decisión de negocio (§4.5). Ningún crédito de otro motivo se reclasifica sin evidencia.
6. **Liberación** de la restricción solo por conciliación administrativa con evidencia; nunca por edición del motivo.

#### 3.4.4 Protección de créditos nuevos y de todos los caminos de uso
Una sola política (p. ej. una clase de política de uso de crédito) que consultan **todos** los servicios de saldo; no se duplica la regla en cada uno:

- **Generación** (`GenerarCreditoSafService`, incluida la manual y las rutas de reconciliación): exige clasificación. Si el llamador no la declara y el motivo es de `ajustes_envio`, nace `pendiente_conciliacion`. Si el llamador no la declara y el motivo es de otra categoría, nace `general`. Un valor inválido se rechaza.
- **Reconciliación de total** (`ReconciliarTotalPedidoSafService`): para clasificar necesita el desglose del cambio por componente. Si lo recibe y prueba que solo cambió el transporte → `solo_transporte`; si prueba que cambió mercancía → `general`; si es mixto o no lo recibe → `pendiente_conciliacion`. No se divide un crédito mixto por conjetura (§4.6).
- **S-06:** nace `solo_transporte`, origen `transporte_confirmado`, fuente `s06_autorizado`, autorizado por Administración distinta de quien registró la venta.
- **Reserva y FIFO** (`ReservarSaldoSafService`): el crédito `solo_transporte` o `pendiente_conciliacion` queda fuera del plan FIFO general; entra solo cuando la reserva se solicita explícitamente para transporte.
- **Aplicación** (`AplicarReservaSafService::handle()` y `aplicarDirecto()`) y **sincronización**: revalidan uso permitido, cliente, pedido distinto al de origen y tope. No basta la validación al reservar.
- **Tope:** lo aplicado con un crédito restringido no supera el componente de transporte del pedido destino (`costo_para_cobertura` de `CalcularTotalesEnvioPedidoService`; en municipal, el transporte que evalúa `PoliticaFinancieraMunicipal`). Con transporte pendiente o incompleto el tope es cero. El seguro no cuenta mientras negocio no lo confirme (§4.6).
- **Ajustes, reactivación, reversión y liberación** conservan clasificación y origen. Un ajuste al alza no cambia la clasificación.
- **Defensa en base de datos:** restricción de valores válidos en las columnas nuevas, para que ninguna escritura directa guarde un valor inventado.
- **Pedido de origen:** `AlcanceSaf` ya excluye el pedido que originó el crédito; se conserva.

#### 3.4.5 Mínimo general de $10 (AC-02)
`GenerarCreditoSafService` aplica `ReglasSaf::assertMontoMinimo()` (por omisión $10, configurable) salvo `omitir_monto_minimo`. S-06 debe pasar `omitir_monto_minimo = true` de forma explícita, registrarlo en el detalle del movimiento y **no fijar ningún mínimo propio**. El criterio queda pendiente de negocio (§4.4). Las demás generaciones conservan el mínimo.

#### 3.4.6 Pruebas
1. Crédito de `sobrante_envio` generado por reconciliación sin desglose: nace `pendiente_conciliacion`, no `solo_transporte`.
2. Crédito de `ajuste_seguro`: no se clasifica automáticamente; queda para conciliación.
3. Crédito generado por sin existencia (origen mercancía comprobado): nace `general`.
4. Reconciliación con desglose que prueba solo transporte: `solo_transporte`; mixto: `pendiente_conciliacion` y no se divide.
5. Crédito histórico de `ajustes_envio`: tras la migración queda `pendiente_conciliacion`, origen `no_determinado`; ninguno queda `transporte_confirmado` por la migración.
6. Crédito histórico de motivo ajeno (`pago_de_mas`): `general`, uso sin cambios.
7. `pendiente_conciliacion` y `solo_transporte` no entran al FIFO de un pedido con mercancía y no se aplican a mercancía en reserva, aplicación ni `aplicarDirecto()`, incluso con la solicitud manipulada.
8. `pendiente_conciliacion` sí se aplica a transporte posterior del mismo cliente; el tope impide exceder el transporte; el resto de la mercancía sigue pendiente.
9. No se aplica al pedido de origen ni a otro cliente.
10. La conciliación administrativa exige evidencia y motivo, la hace Administración distinta de quien generó el crédito, deja movimiento y no existe versión masiva.
11. La migración no cambia montos, estados, vigencias, reservas ni aplicaciones; un reporte de simulación enumera créditos y reservas vigentes sin modificar nada.
12. Una reserva vigente preexistente sobre mercancía no se deshace ni se modifica por la migración.
13. Ajuste al alza, reactivación y reversión conservan la clasificación.
14. Generación manual con motivo de `ajustes_envio` sin clasificación declarada: `pendiente_conciliacion`.
15. Valor de clasificación inválido: rechazado por servicio y por restricción de base de datos.
16. S-06 por Administración con $9, $10 y $11: ninguno se rechaza por mínimo; nace `solo_transporte` y `transporte_confirmado`; el autorizador no es quien registró la venta. La generación general de otros motivos con $9 sigue rechazándose.
17. Cualquier ruta que consuma un crédito restringido usa la misma política (prueba por servicio, no solo por pantalla).

### 3.5 Pruebas M-07
1. Empacado sin salida: el cambio no obliga a desempacar y no deja carátula vigente.
2. Mercancía en CEDIS: se registra salida y recepción; en PDV en custodia: no se crea salida ni recepción nueva.
3. En tránsito: no se duplica el traslado.
4. Con salida a terminal: rechazo. Histórico: rige `envioYaSalio()`.
5. Autorizar el saldo no marca recepción ni custodia.
6. Doble clic o reintento: una sola solicitud.
7. Pagos, remisión, custodia y evidencias intactos tras el cambio.
8. Destino distinto antes del primer traslado: se actualiza la instrucción sin autorización de gerencia y CEDIS traslada a la sucursal elegida.
9. Destino distinto con traslado ya ocurrido: exige autorización de gerencia; sin ella no hay salida; con rechazo se conservan destino y custodia.
10. Ningún camino crea salida o recepción cuando la mercancía ya está en la sucursal elegida.
11. Solicitudes simultáneas o durante tránsito: rechazadas, y un reenvío con versión anterior no repite la operación.

### 3.6 Riesgos
- Confundir autorización con movimiento físico.
- Crear traslados ficticios cuando la mercancía ya está en PDV.
- Tratar la categoría de saldo como restricción real.
- Duplicar la autorización de T-03 en M-07 en vez de reutilizarla, o dejar que el cambio de destino cree movimientos que T-03 no admite.

## 4. Pendientes de negocio (no se inventan reglas)
1. **Cantidad de complementos abiertos** por principal en M-03b.
2. **Integración en paquete ya cerrado:** si el complemento puede integrarse a un paquete cerrado en CEDIS o PDV, o solo antes del cierre del empaque.
3. **Gastos de transporte ya consumidos** en M-07 (AC-07): responsable y tratamiento. No hay regla universal; no se imputa automáticamente a un actor.
4. **Mínimo de $10 para S-06:** si aplica al saldo de envío. El diseño no lo aplica ni fija otro.
5. **Créditos ambiguos y reservas vigentes:** (a) confirmar que un crédito `pendiente_conciliacion` pueda usarse en transporte mientras no se concilie; (b) qué hacer con las reservas vigentes que hoy cubren mercancía con créditos ambiguos (el diseño no las altera); (c) qué evidencia basta para que Administración acredite «transporte confirmado» en un crédito histórico.
6. **Créditos mixtos y seguro:** si un crédito con origen en transporte y mercancía se divide en dos y con qué regla; si el seguro cuenta como gasto de envío para el tope. El diseño no divide ni incluye el seguro sin confirmación.
7. **Rutas que hoy generan créditos de envío** (`sobrante_envio`, `envio_a_resguardo`): quién aporta el desglose por componente para clasificarlos al nacer.

**Resueltos:** uso del saldo de envío (pedidos posteriores del mismo cliente, municipales o nacionales); destino de M-07 (predeterminado, modificable con T-03); M-03b (servicio propio, `es_resguardo = false`, visibilidad CEDIS/PDV/Auxiliar sin tocar filtros globales); clasificación por origen comprobado y no por motivo.

## 5. Contradicciones y supuestos que no se sostienen
| # | Supuesto | Hallazgo | Acción |
| --- | --- | --- | --- |
| 1 | Hay que crear un predicado de salida a terminal (revisión 1). | Ya existe `RecorridoFisicoMunicipal::salioATerminal()`. | Se reutiliza (§1). El predicado nuevo que había añadido en `PoliticaResguardoEnvio` y su prueba se retiraron. |
| 2 | `salioATerminal()` basta para proteger pedidos históricos. | No: los históricos nunca reciben la marca. | Bifurcar con `aplica()` y `envioYaSalio()` (§1.1). |
| 3 | La categoría «envío» de SAF restringe el uso. | Es solo una etiqueta; el uso no se filtra. | Restricción estructural, protección de históricos y tope en servidor (§3.4). |
| 4 | M-07 siempre implica salida desde CEDIS (revisión 1). | Si ya está en PDV sería un traslado ficticio. | Se decide por ubicación física (§3.2). |
| 5 | El complemento puede crearse con el mecanismo de pedidos complementarios existente. | `CrearPedidoBmaService` en modo complementario fuerza semántica de resguardo; Tienda y Nacional lo evitan creando el hijo directamente. | Servicio propio con identidad propia; el complemento se muestra dentro de la tarjeta del principal sin tocar filtros globales (§2.4). |
| 6 | El saldo de envío se aplica al pedido que cambia. | Ese pedido ya no tiene transporte. | Confirmado: solo transporte de pedidos posteriores del mismo cliente (§3.4). |
| 7 | El servicio se llama `RegistrarSalidaTerminalResguardoService`. | El nombre real es `RegistrarSalidaTerminalResguardoPdvService`. | Corregido. |
| 8 | La prueba fallida de M-03a sigue fallando. | No se volvió a ejecutar. | Correrla antes de construir (§2.1). |
| 9 | Todo crédito de `ajustes_envio` es transporte. | La categoría incluye `ajuste_seguro` y `sobrante_envio` puede provenir de cambios de total con mercancía; el motivo lo elige quien llama. | Clasificar por origen comprobado; ambiguo → conciliación (§3.4.2, §3.4.3). |
| 10 | Una marca nueva más el motivo bastan para proteger. | El motivo no prueba el origen, y proteger solo históricos deja las rutas nuevas sin control. | Restricción estructural, política única y validación en generación, reserva, aplicación y sincronización (§3.4.4). |
| 11 | S-06 se genera con el servicio general de créditos. | `GenerarCreditoSafService` aplica el mínimo de $10 por omisión. | Generar con `omitir_monto_minimo` explícito, sin fijar otro mínimo (§3.4.5). |
| 12 | Los créditos históricos se pueden clasificar automáticamente. | No guardan desglose por componente. | La migración no confirma transporte en ninguno; reporte y conciliación administrativa (§3.4.3). |

## 6. Orden de construcción propuesto
1. Punto único de consulta «ya salió» que combine `salioATerminal()` e `envioYaSalio()` según §1.1, con pruebas.
2. Verificar M-03a (correr la prueba señalada).
3. SAF: clasificación estructural, política única de uso, reporte de simulación, migración con valores seguros y servicio de conciliación administrativa, con sus pruebas (§3.4). Va antes que M-07 porque cierra un hueco existente; la parte de generación por reconciliación espera el pendiente 7 y el uso de créditos ambiguos en transporte espera el pendiente 5.
4. M-03b: servicio propio de complemento, marca, vínculo y bloqueos (§2.2, §2.3, §2.4).
5. M-03b: visibilidad en CEDIS, PDV y auxiliar, e integración física por ubicación.
6. M-07: solicitud, bloqueo de despacho e idempotencia.
7. M-07: recorrido físico por ubicación y destino conforme a T-03 (§3.2).
8. Generación de saldo de envío S-06 por Administración y aplicación a transporte posterior con tope (§3.4). El mínimo de $10 sigue pendiente (§4.4).
9. Revisión visual con cuentas distintas por rol (Ventas, Auxiliar, CEDIS, PDV, Administración, gerencia).

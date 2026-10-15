# CHECKPOINT P1.2 — Contrato operativo y recuperación de flujos GELIA

Fecha: 14 de octubre de 2026. Revisión estática de `861cb19d0e7ffbdb3695f9443723145e286fb8d1`.
Estado: **contrato del núcleo confirmado y plan para revisión; sin implementación**. «Definitivo» se refiere a los requisitos expresamente confirmados; las decisiones pendientes de la sección 10 no quedan aprobadas por el título.

## 1. Resumen ejecutivo, alcance y evidencias

La recuperación debe conservar la separación CEDIS Bellaroma/Aromas, el soporte documental de Aromas enviado a Tienda sin pesaje en ese hito, el número de remisión, el diseño actual y la continuidad Pedidos–PDV–Resguardos. No corresponde restaurar indiscriminadamente una versión anterior. Hay defectos concretos de captura y carátula; restricciones funcionales recientes que requieren corrección delimitada; una búsqueda por remisión incompleta; e incompatibilidades potenciales entre custodia diferida, estado comercial y cumplimiento físico que necesitan reproducción aislada.

Este checkpoint utiliza [P0](CHECKPOINT_P0_AUDITORIA_CONTROL_PEDIDOS.md), [P1](CHECKPOINT_P1_CONTRATO_CORRECCION_PEDIDOS.md) y [P1.1](CHECKPOINT_P1_1_RECONSTRUCCION_HISTORICA_PEDIDOS.md). No repite su investigación histórica. Los identificadores D01–D30, P01–P08 y U1–U6 conservan exactamente el significado de P1.1. U1: separación interna; U2: captura Aromas documental sin peso; U3: remisión/búsqueda; U4: conservar presentación; U5: justificar cada agregado; U6: integración PDV/resguardos. Las fuentes documentales describen intención; el código demuestra implementación estática; ninguno sustituye una reproducción operativa.

Se inspeccionaron fuentes y rutas, sin ejecutar aplicación, tests, migraciones ni consultas a datos. El inventario de pruebas es futuro. Los resultados históricos de P0 no validan este contrato ni el subárbol PDV: 85 frontend y 36 Feature ControlPedidos aprobadas eran resultados anteriores; la ejecución combinada de ControlPedidos tuvo errores/fallos. No se atribuye a producción ningún caso sintético de este documento.

### 1.1 Reglas que gobiernan todo el contrato

1. Departamento/contexto, origen comercial, almacén físico, sucursal/domicilio destino, preparador y transportista se conservan por separado. Un transporte municipal no convierte el origen en PDV; un preparador Tienda no demuestra un traslado físico CEDIS→Tienda (D01/D04/D17–D19/U1).
2. El pedido comercial conserva identidad, pago, remisión e historial. La tarea describe preparación; el cumplimiento describe ejecución física; el resguardo PDV describe recepción/custodia/entrega. Sus estados se coordinan, no se copian entre sí (D23/U6).
3. Ocultar un campo o cambiar modalidad no autoriza borrar información compatible, cambiar almacén ni crear tareas, movimientos o resguardos. Guardar borrador tampoco (D16/D20/U4/U5).
4. La captura documental de Aromas exige soporte al remitir a Tienda y no exige peso en ese paso. No elimina requisitos posteriores de mercancía, cajas, empaque o entrega (U2).
5. Se reutilizan servicios, catálogos, máquinas y módulos existentes. Una ampliación pendiente exige decisión concreta; no habilita un área ni un módulo paralelo.

### 1.2 Índice de fuentes de código y endpoints

Las rutas de esta tabla son actuales; `P`, `T`, `R` significan id de pedido, tarea y resguardo. Los códigos S se usan en las matrices y heredan aquí servicio, endpoint e integración. Las clases de servicio sin ruta completa pertenecen a `app/Services/ControlPedidos/`; helpers `BusquedaDelegadoPedidoBma`, `PoliticaSalidaPreparacion` y máquinas de estados están en `app/Support/ControlPedidos/`, requests en `app/Http/Requests/ControlPedidos/` y controladores en `app/Http/Controllers/ControlPedidos/`. PDV indica `app/Services/PuntoVenta/Resguardos/`. No se propone crear endpoints nuevos en P1.2.

| Ref. | Fuente / servicio y endpoint comprobado | Responsabilidad e integración |
| --- | --- | --- |
| S01 | `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`, `PedidoBmaController`, `ActualizarPedidoBmaService`; POST `/control-pedidos`, POST `/control-pedidos/autoguardar`, PUT `/control-pedidos/P` | Captura/persistencia; `serializarBorrador`, `asegurarPedidoEnBd`, autoguardado; no ejecutar preparación por guardar |
| S02 | `PedidoBmaController::subirPdfPedido/solicitarPreparacionTienda`; `CrearTareaPreparacionService`; POST `/control-pedidos/P/pdf-pedido`, POST `/control-pedidos/P/solicitar-preparacion-tienda` | Soporte de captura y solicitud actual de tarea Tienda; elegibilidad y semántica Aromas por resolver, no confundir soporte con fotografía de ejecución |
| S03 | `SolicitarPesajePedidoBmaService`, `ResponderPesajePedidoBmaService`; POST `/control-pedidos/P/solicitar-pesaje`, POST `/control-pedidos/cedis/P/responder-pesaje`; POST `/control-pedidos/P/cerrar-consulta` por `PedidoBmaController` | Consulta CEDIS con modo revisión o pesaje; `ValidacionCamposPedidoBma`, modelo `PedidoBma::consultaCedisModoRevisionMercancia` |
| S04 | `TomarTareaPreparacionService`, `GuardarAvancePreparacionService`, `ResponderPreparacionTiendaService`, `CalcularRequisitosPreparacionService`; POST `/control-pedidos/tienda/tareas/T/tomar`, `/preparacion/guardar`, `/responder` | Bandeja Tienda, respuesta y requisitos efectivos; fechas de respuesta no prueban peso |
| S05 | `CrearTraspasoDesdeTareaPreparacionService`, `SincronizarTareaDesdeTraspasoService`, `ConciliarTraspasoTiendaCedisService`; POST `/control-pedidos/tienda/tareas/T/confirmar-salida`, `/regenerar-traspaso` | Salida Tienda y enlace al módulo Traspasos; recepción mediante `ConfirmarTraspasoCedisService`, POST `/traspasos/cedis/{traspaso}/confirmar`; cambio de estado/rechazo por PUT `/traspasos/{traspaso}/estado` y controlador existente, no por otro endpoint de Pedidos |
| S06 | `GenerarCaratulaPedidoService`, `RegenerarCaratulaPedidoService`, `ConfirmarCaratulaColocadaService`; POST `/control-pedidos/tienda/tareas/T/caratula/generar`, `/regenerar`, `/confirmar-colocacion` | Carátula municipal; invalidación por `PedidoBmaTareaPreparacionCaratulaObserver` y `InvalidarCaratulasVigentesService` |
| S07 | `AutorizarSalidaPreparacionService`, `ConfirmarEmpaqueMunicipioService`, `ConfirmarDespachoMunicipioService`; POST `/control-pedidos/tienda/tareas/T/salida/autorizar`, `/municipio/empacar`, `/municipio/despachar` | Pago/autorización y cumplimiento físico municipal; evidencia: POST `/municipio/evidencia-empaque` |
| S08 | `MarcarEmpacadoPedidoBmaService`, `MarcarEnviadoPedidoBmaService`; POST `/control-pedidos/cedis/P/marcar-empacado`, `/marcar-enviado` | CEDIS→transportista/sucursal; salida final puede invocar S10 |
| S09 | `GestionarRemisionPedidoBmaService`, `ActualizarNumeroRemisionPedidoBmaRequest`, `BusquedaDelegadoPedidoBma`, `ListarPedidosDelegadoService`, `PedidoBmaDelegadoController`; PUT `/control-pedidos/auditar/P/numero-remision`; GET `/control-pedidos/delegado/listado`; POST `/control-pedidos/delegado/P/asignar-guia`, `/actualizar-guia`; GET `/exportar`, POST `/importar` bajo Delegado | Persistencia, consulta, asignación/importación de guías; conservar autorización y filtros de bandeja |
| S10 | PDV `CrearRecepcionEsperadaPdvService` | Invocación interna desde S08, sin nueva API de captura de recepción esperada. Evento `RecepcionEsperadaPdvCreada` después de commit |
| S11 | PDV `RegistrarRecepcionFisicaPdvService`, `RegistrarConfirmacionCustodiaPdvService`, `SincronizarEstatusSucursalPedidoBmaService`; PUT `/punto-venta/resguardos/R/recepcion`, `/custodia`; PUT `/pasar-recepcion` según etapa | Recepción física, bultos y custodia por sucursal; bandejas GET `/punto-venta/resguardos/listado` no crean recepción |
| S12 | `AbrirCustodiaDiferidaPdvService`, invocada por S07; `PoliticaSalidaPreparacion` | Entrada local a resguardo existente para transferencia diferida; no salida CEDIS ficticia ni recepción esperada adicional |
| S13 | PDV `RegistrarEntregaResguardoPdvService`, `RegistrarEntregaMultipleResguardoPdvService`; PUT `/punto-venta/resguardos/R/entrega`; POST `/punto-venta/resguardos/entregas-multiples`; `InformarEntregaResguardoPdvService`, `SincronizarCumplimientoDesdeResguardoPdvService` | Firma, tercero, entrega parcial/completa y proyección comercial/física por evento después de commit |
| S14 | PDV `ConfirmarDevolucionResguardoPdvService`; PUT `/punto-venta/resguardos/R/devolucion`; `InformarDevolucionResguardoPdvService`, `SolicitarDevolucionCumplimientoService`, `ConfirmarDevolucionAnaquelService`; POST `/control-pedidos/tienda/tareas/T/apartado/confirmar-devolucion` | Devolución PDV y confirmación posterior en anaquel: hechos distintos |
| S15 | `CorregirTareaPreparacionService`, `CambiarModalidadPreparacionService`; POST `/control-pedidos/tareas-preparacion/T/corregir`, `/cambiar-modalidad` | Sustitución permitida, preservación de captura compatible y exclusión de tarea vigente |

Rutas verificadas en `routes/web/modules/control-pedidos/{core,cedis,tienda,auditar,delegado}.php` y `routes/web/modules/punto-venta/{index,resguardos}.php`. También se contrastó `routes/web/modules/traspasos.php` y `TraspasoCedisController::confirmar`; los tests de rechazo deben usar el request/estado admitido por `SolicitudTraspasoController::actualizarEstado`, sin llamar directamente al espejo para simular una recepción HTTP.

## 2. Matriz de escenarios por hito

Para evitar una tabla de quince columnas, cada escenario se compone de **contexto fijo + filas de hitos**. Cada fila hereda departamento, origen, almacén y destino de su contexto, salvo excepción explícita. «Conservar comercial» significa que ese hito no autoriza otra transición del pedido; no significa ausencia de historial. T = tarea de preparación; CF = cumplimiento físico; R = resguardo. N/A = entidad no creada por ese hito; pendiente = dato o mapeo no acreditado. El servicio S incluye endpoint e integración de §1.2. Las bandejas son las existentes, sujetas a permiso y ámbito departamental/sucursal.

### A. Local/municipal procesado por CEDIS

**Contexto:** departamento del pedido; origen comercial seleccionado, sin convertirlo a PDV; almacén físico CEDIS elegido; destino cliente/localidad; preparación CEDIS; transporte local del catálogo. Modalidad financiera municipal diferida cuando corresponda, distinta de una tarea `ENVIO_MUNICIPIO` (D01/D07–D09/U4).

| Hito | Actor / bandeja | Documento y peso | Estados / traslado real | Servicio, integración y resultado / evidencia |
| --- | --- | --- | --- | --- |
| A1 Captura | Ventas / registro | Soporte de pedido según flujo; no peso por seleccionar municipio | Borrador; T N/A; sin movimiento | S01: mismo origen/almacén tras guardar/recargar. D01/D20, H01 |
| A2 Consulta, si aplica | CEDIS / consulta y ventas al responder | Mercancía y observaciones; peso solo con política efectiva aprobada; alcance local sin peso pendiente O02 | Consulta pendiente→respondida; cierre explícito; T Tienda N/A | S03 conserva consulta CEDIS. D14/D15/D28, U1/U2 no justifican una exención local universal |
| A3 Empaque | CEDIS / CEDIS | Evidencia de empaque y cajas aplicables; no sustituir por PDF de captura | Comercial habilitado; se registra `empacado_at` y fase de envío aplicable (PENDIENTE_DE_ENVIO en circuito local); T N/A; aún sin salida | S08. D03/D07/D08; no crear tarea Tienda por municipio |
| A4 Salida y costo | CEDIS; Auxiliar regulariza cobro / envío pendiente y auditoría | Constancia de salida, datos de transportista; costo diferido puede seguir pendiente según reglas vigentes | Envío parcial conserva pendiente; final→ENVIADO; CEDIS→transportista real | S08, pagos existentes. D07/D08. Sin guía ficticia ni entrega final al cliente inferida |

### B. Separación CEDIS interna Bellaroma

**Contexto:** contexto interno Bellaroma identificado por datos autorizados; origen comercial independiente; almacén CEDIS seleccionado; destino interno por confirmar por subcaso O01; preparador CEDIS. Separación no equivale a transporte, custodia PDV ni origen «Tienda» (D14/D29/U1).

| Hito | Actor / bandeja | Documento y peso | Estados / traslado real | Servicio, integración y resultado / evidencia |
| --- | --- | --- | --- | --- |
| B1 Solicitar separación | Ventas responsable / registro→consulta CEDIS | Identificación/soporte del pedido; no imponer peso por marca | Consulta solicitada; tarea física solo si el subcaso actual la usa; sin traslado | S01/S03. Conservar caso identificable Bellaroma y alcance de acceso D29/D30/U1 |
| B2 Revisar/separar | CEDIS / consulta CEDIS | Conteos, estado de mercancía, fotos/daños según §3; peso según operación efectiva | Consulta respondida; separación física no es estado comercial ENTREGADO | S03 y apartado existente cuando aplique. D14/D15/U1; no retirar pesaje de otros casos |
| B3 Cerrar y continuar | Ventas/CEDIS según acción / cierre de consulta | Respuesta consultable; soporte previo conservado | Cierre administrativo; ruta posterior depende de destino real O01 | S03; S08 solo si existe envío. Sin crear PDV/resguardo por la separación. U1/U6 |

### C. Captura Aromas enviada a Tienda

**Contexto:** contexto Aromas; origen comercial conservado; almacén de mercancía elegido, sin cambio automático a PDV; destino inmediato **documental** Tienda y destino físico posterior independiente. Preparador posterior Tienda cuando corresponde. El dato de marca/contexto no se obtiene del transportista (U1/U2).

| Hito | Actor / bandeja | Documento y peso | Estados / traslado real | Servicio, integración y resultado / evidencia |
| --- | --- | --- | --- | --- |
| C1 Capturar y adjuntar | Ventas Aromas / registro | Evidencia de captura obligatoria antes de C2; peso no requerido en C1/C2 | Borrador/captura guardada; sin traslado; T aún no creada | S01/S02. Falta soporte impide remisión a Tienda; falta peso no la impide. U2/D16 |
| C2 Remitir captura | Ventas Aromas / solicitud→Tienda | Documento persistido, accesible por receptor; no duplicar carga; sin peso, ni cajas de empaque como sustituto del documento | Solicitud documental; estado comercial exacto y modalidad técnica pendientes O03; tarea actual nace PENDIENTE si aplica S02 | S02 es punto existente a adaptar/verificar; no usar respuesta de pesaje como requisito del envío. U2/D19 |
| C3 Procesar mercancía posteriormente | Tienda / tarea asignada | Requisitos efectivos de preparación, peso/cajas si corresponden; fotos distintas del soporte de C1 | T PENDIENTE→EN_ATENCION→respuesta/rama; CF según modalidad; movimiento solo si ruta E/F/H lo requiere | S04 y rama aprobada. U2 no exonera C3; D17–D19. No afirmar CEDIS→Tienda físico por enviar captura |

### D. Tienda con origen PDV

**Contexto:** departamento del pedido; origen PDV comercial explícito; almacén PDV elegible; destino recogida local o sucursal según modalidad; preparador Tienda. `RECOGE_TIENDA` (Recoge hoy) y `RECOGE_TIENDA_TRANSFERENCIA` no son equivalentes (D19/D23–D25).

| Hito | Actor / bandeja | Documento y peso | Estados / traslado real | Servicio, integración y resultado / evidencia |
| --- | --- | --- | --- | --- |
| D1 Solicitar | Ventas / registro→Tienda | Soporte/referencia existentes; peso no anticipado por ser PDV | T PENDIENTE; comercial conserva consulta; sin movimiento | S01/S02, elegibilidad `PreparacionTiendaConfig`. U4/D19 |
| D2 Separar/confirmar | Tienda / Tienda | Evidencia, ubicación y cantidades del apartado; requisitos por modalidad | EN_ATENCION; CF POR_SEPARAR→SEPARADA; `RECOGE_TIENDA` culmina apartado conforme política actual | S04 y endpoints `/apartado/*`. No reactivar cobro/entrega directa solo porque D25 la proponía |
| D3 Salida diferida si es transferencia | Autorizador distinto del preparador / salida | Pago confirmado o excepción por cobrar permitida; custodia local, no pesaje nuevo | CF SEPARADA→LISTA_PARA_SALIDA; R EN_CUSTODIA; comercial compatible pendiente H/O05 | S07/S12→H. Una custodia enlazada; no envío CEDIS simulado. U6 |

### E. Tienda con origen distinto de PDV

**Contexto:** departamento y origen comercial reales; almacén físico configurado/elegible, que no debe reescribirse; destino por modalidad aprobada; preparador Tienda. D19 documenta lista administrable; no acredita elegibilidad irrestricta de todos los almacenes. Recoge hoy conserva su regla PDV específica.

| Hito | Actor / bandeja | Documento y peso | Estados / traslado real | Servicio, integración y resultado / evidencia |
| --- | --- | --- | --- | --- |
| E1 Validar solicitud | Ventas / registro | Soporte del caso; sin peso por elegir preparador | T PENDIENTE solo si combinación aprobada; rechazo explicativo conserva borrador; sin movimiento | S01/S02. O01/O03/O04 fijan casos, sin convertir origen a PDV. D01/D19/U2 |
| E2 Preparar | Tienda / Tienda | Requisitos efectivos, identificación/mercancía/cajas según operación | EN_ATENCION→respuesta o incidencia; comercial independiente | S04. No fabricar exención ni peso universal por origen. D19/U2 |
| E3 Continuar modalidad | Tienda/CEDIS/PDV según destino / bandeja de rama | Carátula/empaque si municipio, evidencia Traspasos si bodega, custodia si transferencia | F o H; municipio usa S06/S07; nunca inferir traslado CEDIS→Tienda | S05/S06/S07/S12 según aprobación; D17/D18/U6 |

### F. Tienda→CEDIS con traspaso real

**Contexto:** departamento/origen comercial conservados; almacén físico de salida Tienda configurado; destino físico CEDIS; preparador Tienda, receptor CEDIS; modalidad `ENVIO_BODEGA`. Transportista no define estos almacenes (D17/D27).

| Hito | Actor / bandeja | Documento y peso | Estados / traslado real | Servicio, integración y resultado / evidencia |
| --- | --- | --- | --- | --- |
| F1 Preparar cantidades | Tienda / Tienda | Productos, disponibles/faltantes y evidencia efectiva; peso según modalidad | EN_ATENCION→LISTA_PARA_TRASLADO cuando aplicable | S04/S05. Cero unidades no fabrica traspaso. D17/D27 |
| F2 Salir | Tienda con permiso / salidas Tienda y Traspasos | Identidad de traspaso, cantidades y bultos aplicables | LISTA_PARA_TRASLADO→EN_TRASLADO; Tienda→CEDIS real | S05; un movimiento por operación/idempotencia, mismo pedido |
| F3 Recibir/rechazar | CEDIS / recepción Traspasos | Conteo, incidencias, diferencias y evidencias propias | T RECIBIDA_CEDIS o RECHAZADA_CEDIS; comercial continúa sin duplicado | `SincronizarTareaDesdeTraspasoService`/conciliación S05; POST recepción o PUT estado de Traspasos indicados en S05. D17/D27 |
| F4 Empacar/enviar si procede | CEDIS / CEDIS | Evidencia y requisitos efectivos de envío | Rama A3/A4 o G; no otro traspaso por recargar | S08/S10; D07/U6 |

### G. Destino sucursal y recepción PDV

**Contexto:** origen comercial y departamento del pedido; almacén salida CEDIS; **sucursal_destino_id independiente** y activa; preparación CEDIS; transporte real a sucursal. Destino no se deduce de almacén PDV (P01–P04/U6).

| Hito | Actor / bandeja | Documento y peso | Estados / traslado real | Servicio, integración y resultado / evidencia |
| --- | --- | --- | --- | --- |
| G1 Guardar destino | Ventas / registro | Identificación de sucursal; sin peso en captura por este dato | Comercial en curso; sin R nuevo por guardar | S01/`PedidoBma::requiereSucursalDestino`; id sobrevive a respuesta, edición y recuperación. P02 |
| G2 Confirmar salida final | CEDIS / pendientes de envío→PDV esperados | Bultos esperados >=1 y constancia salida; no repesar en handoff | Pedido ENVIADO; R PENDIENTE_RECEPCION; T si existe conserva su eje | S08/S10. Una recepción pedido+sucursal; parcial no genera final. P04 |
| G3 Llegada física | Receptor de sucursal / recepción PDV | Bultos recibidos, foto u omisión válida según política actual | Recepción total/parcial; comercial no se convierte en entregado | S11; CEDIS→sucursal se confirma físicamente. Historial y proyección estatus_sucursal |
| G4 Custodia | Responsable autorizado / custodia PDV | Bultos y evidencia efectiva; sin peso nuevo universal | Custodia parcial/EN_CUSTODIA; inicio plazo según confirmación efectiva | S11; no inicia plazo solo por ENVIADO. P01/P08 |
| G5 Entregar/devolver | Piso autorizado / entrega o devoluciones | Firma/evidencias según §6 | R y pedido/cumplimiento según H3/H4; movimiento a cliente o retorno real | S13/S14; P05/P06/U6 |

### H. Custodia diferida, entrega y devolución

**Contexto:** departamento y origen del pedido conservados; mercancía preparada localmente en almacén Tienda; sucursal explícita compatible; modalidad `RECOGE_TIENDA_TRANSFERENCIA`; entrada alternativa a G, no dos recepciones del mismo hecho (D23/P01/U6).

| Hito | Actor / bandeja | Documento y peso | Estados / traslado real | Servicio, integración y resultado / evidencia |
| --- | --- | --- | --- | --- |
| H1 Autorizar | Responsable distinto del creador / salida Tienda | Pago o excepción permitida, separación identificada; sin pesaje nuevo por custodia | CF SEPARADA→LISTA_PARA_SALIDA; pedido no pasa a ENVIADO por ficción | S07/S12; regla comercial de cierre pendiente O05 |
| H2 Abrir custodia local | Servicio / resguardos de sucursal | Bulto y vínculo pedido/tarea/sucursal | R EN_CUSTODIA con recepción/custodia local; CF enlaza resguardo_pdv_id; sin viaje CEDIS | S12. Reintento conserva id y fecha; no reutilizar cerrado sin política explícita O06 |
| H3 Entrega parcial/completa | Piso PDV autorizado / entrega | Receptor titular/tercero, firma y evidencias; no nueva captura en Pedidos | Parcial no termina pedido; completa R ENTREGADO; comercial→ENTREGADO solo por transición compatible; CF→ENTREGADA si enlazado y elegible | S13. Si proyección falla, entrega permanece registrada y sincronización pendiente; reproducir incompatibilidad O05 |
| H4 Devolver | Piso autorizado / devoluciones PDV; después Tienda / retorno | Evidencia/motivo del retorno; luego ubicación y foto de anaquel | R DEVUELTO; comercial conserva fase con historial; CF DEVOLUCION_PENDIENTE→DEVUELTA_ANAQUEL al confirmar físicamente | S14; retorno PDV no acredita llegada al anaquel ni actualización de inventario. P06/D24 |

### I. Remisión para búsqueda y guías

**Contexto:** departamento/origen/almacén/destino del pedido originales; modalidad de envío que lo haga elegible para Delegado; Auxiliar captura remisión y Delegado gestiona guía; ninguna transferencia física por buscar (U3/D13/D23).

| Hito | Actor / bandeja | Documento y peso | Estados / traslado real | Servicio, integración y resultado / evidencia |
| --- | --- | --- | --- | --- |
| I1 Registrar número | Auxiliar autorizado / auditoría | Número de remisión string; archivo remisión es acción distinta; sin peso | Comercial conserva fase; T sin cambio | S09 actualiza numero_remision y referencia REMISION, sin reemplazar folios. U3 |
| I2 Consultar | Delegado / guías | Buscar remisión con campo explícito y búsqueda general; sin nuevas evidencias | Consulta sin mutaciones ni traslado | S09; completar filtro de numero_remision, conservar ámbito y fases elegibles |
| I3 Asignar/actualizar | Delegado / guías | Rastreo/documento guía según operación; sin captura de peso por buscar | Transición comercial que admite el servicio actual; tarea física no se inventa | S09; actualización usa id estable, export/import también. Remisión e identidades originales intactas |

## 3. Contrato de evidencia y pesaje por hito

Documento de captura (DC) = soporte PDF/foto de pedido/remisión/identificación. Mercancía (EM) = revisión, faltantes, daños, contenido. Empaque (EE) = bultos/cajas listos. Entrega (ED) = firma/receptor/constancia. Un archivo puede referenciarse desde varios consumidores con permiso; no se declara equivalente entre categorías sin acreditar el hecho.

| Hito | Evidencia exigible y momento | Peso / cajas | Validador actual y ajuste contractual |
| --- | --- | --- | --- |
| Separación Bellaroma CEDIS B | Soporte aplicable; EM de revisión/daños al responder | Conteo no equivale a peso; peso según subcaso O01/O02 | S03 exige revisión en modo sin logística y evidencia general; preservar U1, no condicionar existencia de separación a un pedido logístico |
| Captura Aromas C1/C2 | **DC obligatorio antes de remitir**, persistido y visible Tienda | **Sin peso en este hito**; sin cajas de empaque obligatorias para enviar captura | S01/S02 debe validar documento antes del efecto. U2; modalidad/estado exactos O03; no llamar S03 para satisfacer solo DC |
| Consulta mercancía CEDIS | Revisiones, EM general o sesión; daños con EM por producto | Modo revisión actual aún exige numero_cajas aproximado >=1; no implica kg; modo completo exige líneas/cajas/pesos | `ResponderPesajePedidoBmaService` distingue soloRevisiones. Exención local D28 pendiente O02; no borrar requisito por arreglo documental Aromas |
| Preparación Tienda D/E/F | DC previo accesible; EM, identificación y detalles efectivos al responder | `CalcularRequisitosPreparacionService::efectivos/validarRespuesta`: modalidad, matriz contexto/almacén y transporte. Solo exigir flags efectivos | S04. Documento de captura no reemplaza EM. Remisión puede pertenecer a fase posterior; no anticiparla universalmente |
| Empaque CEDIS A/G/F4 | EE/EM por cajas según circuito al confirmar empaque | Cajas y peso cuando operación los requiere; conservar detalle y consistencia de totales | S08 y `ValidacionCamposPedidoBma`; corregir divergencias con política por hito, sin deshabilitar validación de otros envíos |
| Preparación municipal Tienda | DC/identificación si efectiva, carátula vigente, EE al empacar | Requisitos efectivos de transporte/modalidad; no peso/caja universales por etiqueta municipio | S04/S06/S07; `ConfirmarEmpaqueMunicipioService` requiere LISTA_PARA_SALIDA y validarEmpaqueMunicipio |
| Despacho | Datos transportista/receptor y constancia aplicable; EE ya disponible | No repesaje por despachar; respetar requisitos previos pendientes | S07 exige empaque/autorización; CF EMPACADA→DESPACHADA. Despachada no es entregada al cliente |
| Recepción/custodia PDV | Foto o excepción de omisión válida en recepción; evidencia/bultos de custodia según política vigente | Conteos de bultos recibidos/custodiados; no peso comercial nuevo | S11, permisos por sucursal. Mantener excepciones actuales justificadas; no imponer foto universal por inferencia |
| Entrega/devolución PDV | ED con firma/receptor en entrega; evidencia/motivo de devolución según servicio; anaquel exige ubicación+foto propia | Entrega parcial por bultos; sin peso nuevo universal | S13/S14. Firmar otra vez en Pedidos duplicaría el hecho; devolver PDV no acredita anaquel |

`requiere_logistica` hoy selecciona parte del modo CEDIS, pero no demuestra por sí solo el requisito de cada hito. `ResponderPreparacionTiendaService::aplicarSincronizacionPedido` escribe `pesaje_respondido_at`: esa fecha no demuestra kg medidos. Un test que impone peso a todo pedido logístico debe revisarse contra esta matriz; una prueba verde de esa regla no es aprobación operativa.

## 4. Inventario y destino de los campos del modal actual

Fuente principal: `ModalFormPedidoLegado.jsx::formDefaults`, estados locales y secciones de captura/preparación/envío/cobro; componentes `CamposDireccionPedido.jsx` y `SeccionPagosExhibicion.jsx`. Se distinguen campos visibles, condicionales, heredados y valores técnicos. Conservar diseño U4; ninguna fila autoriza borrar datos históricos ni retirar controles sin prueba del consumidor.

Leyenda: C capturado; H heredado; K calculado; I informativo; X técnico. Procedencia **base** remite D01–D16; **v2** D17–D22; **oct** D23–D28; **s/f** significa fecha de introducción no verificada en esta revisión. Datar una familia documental no prueba el commit exacto de cada campo. Solo se fecha un campo cuando la evidencia de P1.1 lo permite. Consumidores S remiten §1.2; «conservar condicionado» mantiene requisito solo donde corresponde.

| Campo(s), entidad y naturaleza | Procedencia / necesidad / duplicidad | Consumidor y decisión por campo |
| --- | --- | --- |
| `departamento_operacion_id` pedido C/H | D29/D30, octubre: contexto/visibilidad, U1; no es preparador | S01/S03/S04: conservar, heredar donde procede, nunca inferir por marca textual |
| `origen_id` pedido C | Base D04; comercial, no almacén ni modalidad | S01/S03/S04: conservar independiente; corregir cualquier reescritura colateral |
| `almacen_id` pedido C | Base D01; filtro/effect limitados por `9493ec9` 12-oct; U1/U2 | S01/S02/S05/S11: conservar selección válida y explicar incompatibilidad; no cambiar silenciosamente |
| `sucursal_destino_id` pedido C/H | P02; destino PDV, no duplicado del almacén | S10–S14: conservar al hidratar/responder; invalidar cambio incompatible con operación viva, no borrar |
| `cliente_id`, `numero_cliente` pedido C/H | Base; identidad/referencia, no dos clientes | S01/S09/S13: conservar; número heredado cuando proviene de catálogo |
| `fecha` pedido C | Base, s/f exacta; fecha comercial | S01/listados: conservar; no usar como fecha custodia |
| `folio_remision` pedido C | Base D13: UI «Folio» del pedido; **no numero_remision** | S01/S09/export: conservar identidad y aclarar etiqueta si revisión lo aprueba |
| `numero_remision` pedido, captura Auxiliar fuera del modal principal | Oct/referencias D23/U3; mejora confirmada | S09: conservar y completar búsqueda; no incorporarlo como otro obligatorio en registro |
| `codigoModalidadPreparacion` tarea C, local fuera de data | v2 D19/oct; define ruta física, no origen | S02/S04/S15: conservar y corregir persistencia; opciones por caso aprobado |
| `recogeEnTienda` C, derivación de modalidad | Oct D23–D25; solapa elección de modalidad | `PoliticaSalidaPreparacion`/S02: reutilizar una decisión canónica; mantener presentación y regla Recoge hoy PDV |
| `tipo_operacion_envio_id` pedido H/K | D09; circuito financiero/logístico, no modalidad tarea | Cálculos/S08: conservar derivación; no un segundo selector municipal independiente |
| `es_resguardo` pedido C | Base D10; resguardo comercial, distinto de entidad ResguardoPdv | S01/Auxiliar: conservar condicionado, aclarar alcance; no crear custodia PDV por marcarlo |
| `modo_resguardo` pedido C y `pedido_principal_id` C | Base D10, ampliaciones resguardo; abierto/complementario y vínculo | S01/consolidación: conservar; filtros/búsqueda de principal son controles de selección, no campos comerciales nuevos |
| `prioridadMd` tarea C | Oct D23, s/f exacta; no aprobación U específica | S02/S04: pendiente justificar uso O07; preservar dato, no hacerlo obligatorio |
| `tipoReferencia`, `folioReferencia` tarea/referencia C | Oct D23: cotización/referencia de preparación; posible captura repetida de folio | S02/S15: reutilizar referencia existente cuando sea la misma; conservar distinta solo con semántica O07; persistir mientras exista |
| Soporte `pdf-pedido`, anexo de piezas PDF/fotos C | Base D16/v2; DC confirmado U2 | S02/S03/S04: conservar y reutilizar documento persistido, sin segunda carga obligatoria |
| `motivoRepesaje` solicitud C | Base consulta D15/D16, s/f exacta | S03: conservar condicionado a nueva revisión; no exigirlo al primer envío documental |
| `entregaMunicipal.destinatario_es_cliente` C | v2 D18; deriva receptor | S02/S06: conservar condicionado; heredar cliente sin inventar dato faltante |
| `.destinatario_nombre`, `.destinatario_telefono` C/H | D18; contacto municipal, puede coincidir con cliente/dirección | S02/S06/S07/S15: heredar/reutilizar si mismo receptor; permitir excepción explícita; preservar en corrección |
| `.municipio_destino` C | D18; localidad para salida/carátula | S02/S06: conservar cuando municipal; no mezclar con sucursal; contrastar dirección de cliente antes de duplicar |
| `.direccion_referencia` C | D18; referencias operativas municipales | S02/S06: conservar condicionada, reutilizar snapshot de dirección si describe el mismo destino |
| `.catalogo_paqueteria_id` C y `catalogo_paqueteria_id` pedido C | D18 exige catálogo existente; dos controles editables para un mismo transportista | S01/S02/S06/S08: **duplicación sin necesidad demostrada** de fuente editable; consolidar autoridad/reutilizar catálogo y snapshot tarea. No borrar histórico ni crear catálogo municipal |
| `.modalidad_cobro` C (`PAGADO`/por cobrar) y `envio_por_cobrar` C | D18; representan decisión de cobro compartida | S02/S07/finanzas: una decisión coherente, traducción explícita; corregir divergencia, conservar excepción por transportista |
| `cliente_direccion_id` C/H | Documentos direcciones de P1.1 §2.4, s/f exacta | Direcciones/S01: conservar catálogo e instantánea; no reescribir direcciones globales por editar pedido |
| `direccion_manual_excepcion`, `motivo_direccion_manual` C | Documentación direcciones, s/f exacta; excepción auditada | S01/Direcciones: conservar condicionados, permiso/motivo; no camino obligado por defecto |
| `camposDireccion.nombre_destinatario`, `telefono_destinatario` C/H | Dirección estructurada; se superponen solo si mismo receptor municipal | Direcciones/S01/S06: conservar con regla de herencia explícita, no tres fuentes editables del mismo hecho |
| `calle`, `numero_exterior`, `numero_interior`, `colonia`, `codigo_postal`, `municipio`, `ciudad`, `estado`, `pais` de camposDireccion C/H | Dirección estructurada, s/f exacta; municipio no siempre equivale a ciudad | Direcciones: conservar; requisitos según dirección regular/irregular vigente; no pedir sucursal aquí |
| `referencias`, `indicaciones_entrega`, `domicilio_irregular` de camposDireccion C | Dirección; excepción y detalle operativo, s/f exacta | Direcciones: conservar condicionados; no volver todos obligatorios por municipio |
| `codigo_postal`, `domicilio_entrega` en data H/K | Base/direcciones; proyecciones/snapshot del pedido | S01/cotización: reutilizar fuente dirección; evitar divergencia con camposDireccion, no eliminar snapshot histórico |
| `envia_a_otra_persona`, `envia_otra_persona` pedido C | Base, s/f exacta; receptor alternativo, no quien recoge PDV acreditado | S01/envío: conservar condicionado; reutilizar si mismo destino. Firma/receptor PDV se captura en S13 |
| `numero_cajas`, `catalogo_tipo_caja_id`, `peso_real_kg` H/K o C según fase | Base/v2 D22a; revisión, caja y peso no equivalentes | S03/S04/S08: conservar requisitos por hito; resumen bloqueado no debe obligar a peso en C2 |
| `pesoVolumetrico`, `peso_cobrado_guia_kg` K/H (este último editable según bloque) | Logística D22a, s/f exacta | Cotización/envíos: conservar cálculo/detalle; el peso cobrado no sustituye medición real |
| `catalogo_tipo_guia_id`, `catalogo_zona_id` C | Base/cotización, s/f exacta; tipo guía/reexpedición | Finanzas/S09: conservar condicionado al servicio; no requerir para captura documental |
| `total_mercancia` C/H | Base/seguro; visible tras cierre según circuito | Finanzas: conservar y no destruir al ocultar; proteger revisión de pago existente |
| `costo_envio` C/K | D08/D09; costo diferido legítimo | Finanzas/Auxiliar/S08: conservar desconocido como pendiente, no convertir ausencia en cero |
| `cajas_costos[].uuid_operativo` X; `.costo_envio`, `.costo_seguro`, `.costo_adicional`, `.concepto_adicional` C/K | v2 D22a; identidad y costos por envío | Finanzas/cajas: conservar detalle e identidad; total es agregado, no segunda fuente; concepto condicionado al adicional |
| «Costo diferido» I/K | D08/D09: estado/modalidad derivado, no nuevo monto a capturar | Finanzas: conservar información; no añadir otro campo obligatorio ni duplicar costo_envio |
| `cliente_proporciona_guia` C | Base, mejora documentada y probada en suite | S01/S09/finanzas: conservar condicionado; guía cliente no implica transporte por cobrar |
| `aplica_seguro`, `costo_seguro` C/K | Base; cobertura y cálculo | Finanzas: conservar cálculo/edición permitida, no imponerlo por preparación |
| `anexar_remision` C | Base, s/f exacta; requisito documental de envío | S01/S08: conservar condicionado; no equivale a numero_remision ni a soporte Aromas |
| `reabrir_pago_costos`, `motivo_reapertura_pago` C | Integridad financiera D22; revisión excepcional | Finanzas/S01: conservar permiso/motivo; no autorizar reapertura silenciosa por autosave |
| `aplica_saldo_favor`, `saldo_a_favor` C/K; `saf_aplicaciones[].saf_credito_id`, `.monto`, `.folio` C/H | Base y financiera D22; crédito aplicado y trazabilidad | Pagos/SAF: conservar; folio SAF no es otro folio de pedido; no borrar aplicaciones omitidas en PATCH |
| Pagos: `monto`, `forma_pago`, `catalogo_banco_id`, `comprobante` C | `SeccionPagosExhibicion`, D22; banco/comprobante condicionales al medio | Endpoints pagos actuales: conservar por exhibición. `catalogo_banco_id` legado en formDefaults no autoriza segundo selector global; ocultar solo vestigio sin consumidor demostrado |
| Pagos `referencia`, `fecha_pago` H/X, sin control editable actual | Estado interno de `SeccionPagosExhibicion`; referencia histórica visible en resumen, no prueba de necesidad de volver a capturarla | Pagos: conservar lectura histórica y semántica del payload; no reintroducir inputs por existir claves en useForm |
| `comentarios_drive` C | Base, s/f exacta; comentarios no aprobación de proceso | S01: conservar mientras tenga consumidor, no usar como identificador estructurado |
| `comprobantes`, `documentos_eliminar`, `enviar` X | Transporte/acción; exclusiones de serializarBorrador | S01/documentos: mantener semántica explícita y permisos; archivos no se recuperan mágicamente del JSON local |
| `pedido_id`, `_nombre_cliente`, totales, fechas/estatus/consulta de resumen I/X | Identidad, presentación o metadatos | S01: no convertirlos en nuevos requisitos de captura; guardar revisión/estado verdadero. Totales coherentes con detalle |

Observaciones concretas: el bloque `esConsultaMercancia && consultaCerrada && requiereLogistica` resulta incompatible con el helper que define consulta mercancía como `requiereLogistica === false`; es código potencialmente inalcanzable, no prueba de uso real del campo. Prioridad y referencia adicional tienen consumidores técnicos, pero su existencia no demuestra solicitud operativa. Antes de ocultarlas, O07 debe resolver los casos; la persistencia de lo ya capturado se corrige aunque su permanencia visual esté pendiente.

## 5. Contrato de remisión y guías

### 5.1 Continuidad y defecto localizado

| Paso | Estado estático verificado | Contrato a completar |
| --- | --- | --- |
| Captura | `ActualizarNumeroRemisionPedidoBmaRequest`: numero_remision requerido string hasta 64; prohíbe folio_remision y tipo_referencia | Mantener captura autorizada Auxiliar; no trasladar a vendedor como obligatorio |
| Persistencia | `GestionarRemisionPedidoBmaService::actualizarNumeroRemision` guarda pedido.numero_remision, referencia REMISION e historial; resuelve incidencia del campo | Mantener identidad y no duplicar referencia por replay; archivo de remisión sigue acción separada |
| Serialización | Modelo `PedidoBma` permite numero_remision; consulta base Delegado carga el modelo sin exclusión selectiva del campo | Verificar respuesta HTTP real en fixture; si se agrega una proyección futura incluirlo. No afirmar que ya se probó UI |
| Filtrado | `BusquedaDelegadoPedidoBma::aplicar` busca folio, folio_remision, rastreo y cliente en general; campo tipado folio_remision consulta columna antigua; **no busca numero_remision** | Agregar campo explícito numero_remision y OR general correspondiente; actualizar normalización de q_campo, controlador y opciones `Delegado/Partials/FiltrosDelegado.jsx`; conservar significado del filtro antiguo |
| Actualizar guía | Servicios de asignación/actualización operan sobre pedido id; importación lee ID_Pedido y Guia_Rastreo | Resultado encontrado por remisión conserva id; no cambiar importación a una clave textual potencialmente repetida |
| Resultado | `ExportarPlantillaGuiasPedidoService` usa Folio=folio_remision, no numero_remision | Conservar compatibilidad CSV; si se desea mostrar remisión en exportación, añadir columna explícita con revisión de consumidores, sin renombrar identidad silenciosamente |

El alias histórico PUT `/auditar/P/folio-remision` llama a actualizarNumeroRemision: el alias de URL no autoriza enviar/reescribir folio_remision. La búsqueda debe conservar permisos, departamento, paginación y fases elegibles de `ListarPedidosDelegadoService`; encontrar un registro no autoriza saltarse esas restricciones.

### 5.2 Fixture sintética, no evidencia productiva

Pedido objetivo: id `77`, folio interno `PBMA-2026-00077`, folio_remision de pedido `PED-772`, numero_remision `REM-00935`, rastreo inicialmente vacío. Segundo pedido elegible: id `78`, folio_remision `REM-00935`, numero_remision `REM-00888`. Ambos en contexto autorizado; otro igual fuera de ámbito para probar aislamiento.

Buscar **campo numero_remision=REM-00935** devuelve solo id77; buscar **campo folio_remision=REM-00935** devuelve id78; búsqueda general puede legítimamente devolver ambos y debe mostrar identidades diferenciadas. Buscar `PED-772` sigue encontrando id77 por folio del pedido. No convertir remisiones en números ni perder ceros. Una vez seleccionado id77, asignar/actualizar rastreo y recargar: tres identificadores intactos, guía modificada solo en id77, historial y permisos correctos. Repetir importación CSV por ID_Pedido, con error parcial sin actualizar otra fila. O08 resuelve si la búsqueda tipada será exacta/parcial y cómo normalizar espacios, no se inventa unicidad global.

## 6. Contrato completo Pedidos–PDV–Resguardos

### 6.1 Salida CEDIS y custodia local son dos entradas existentes

**Entrada por envío:** S08 confirma salida final y S10, bajo bloqueo del pedido, exige sucursal activa y bultos esperados >=1; reutiliza recepción por pedido+sucursal y maneja colisión única. Clave `pdv:esp:{pedido}:{sucursal}:envio`. Una recolección parcial no crea el handoff final. El GET de bandeja nunca es el creador. S11 registra llegada/custodia real y actualiza proyección `estatus_sucursal`; no cerrar el pedido por recibir mercancía.

**Entrada diferida local:** S07 autoriza la transferencia diferida y S12 abre R EN_CUSTODIA, con recepción/custodia local y bulto, enlazando `cumplimiento.resguardo_pdv_id`; clave `pdv:custodia:{tarea}`. No corresponde crear además una recepción esperada CEDIS ni simular ENVIADO. Revisar reutilización por pedido+sucursal frente a custodia ya cerrada O06. Conservar misma sucursal y no confundir `es_resguardo` comercial con entidad `app/Models/PuntoVenta/ResguardoPdv.php`.

`PedidoBma::requiereSucursalDestino/prohibeSucursalDestino` consulta modalidad vigente y, si no encuentra tarea activa, cae al origen/requiere_logistica. La relación vigente excluye RESPONDIDA y RECIBIDA_CEDIS. Esto puede cambiar la evaluación tras responder: hay que reproducirlo sin borrar destino para superar una validación. La sucursal validada debe permanecer vinculada durante todo el ciclo.

### 6.2 Entrega: captura única y proyecciones comprobables

S13 verifica permiso de piso, sucursal, versión, idempotencia, cancelación/incidencias y bultos. Captura titular/tercero, nombre/relación y firma según servicio vigente; fotos adicionales según contrato actual. Parcial entrega solo bultos elegidos y no marca todo el pedido ENTREGADO. Completa genera `EntregaResguardoPdvCompletada` después del commit; listener `InformarEntregaResguardoControlPedidos` llama `InformarEntregaResguardoPdvService`.

Contrato: una entrega registrada en PDV debe tener resultado visible y trazable en el pedido y, si está enlazado, en su cumplimiento, sin recaptura de firma en Tienda. Si falla la proyección, preservar el hecho físico confirmado, marcar integración pendiente con error/intentos y poder reintentar con misma identidad. Reintento no vuelve a entregar, cobrar, firmar ni notificar dos veces el mismo hecho.

**Incompatibilidad a reproducir:** `MaquinaEstadosPedidoBma` admite ENTREGADO desde ENVIADO; no lo admite desde varias fases en las que puede permanecer un pedido de custodia diferida. `InformarEntregaResguardoPdvService` intenta la transición comercial y captura el error dejando integración pendiente. No está demostrado en ejecución un caso productivo afectado. O05 debe definir el cierre comercial legítimo para entrega local; no se arregla poniendo ENVIADO en la fixture ni habilitando cualquier fase→ENTREGADO.

**Riesgo adicional:** la rama «pedido ya ENTREGADO con historial de esa entrega» marca integración completa y retorna antes del espejo al cumplimiento. `SincronizarCumplimientoDesdeResguardoPdvService::reflejarEntrega` también retorna sin transición si el CF no está LISTA_PARA_SALIDA o falta vínculo. Por ello «integración completa» comercial no prueba espejo físico correcto. La aceptación debe comprobar ambos resultados o una exclusión explícita y trazable, no solo el booleano. No se confirmó un trabajo automático de reconciliación en la búsqueda acotada; no prometer reintentos automáticos sin localizarlo/probarlo.

### 6.3 Devolución, cancelación y plazos

S14 registra devolución PDV con permisos/evidencias/historial y evento posterior al commit. `InformarDevolucionResguardoPdvService` conserva fase comercial y registra el hecho; el espejo solicita devolución del cumplimiento. Eso produce DEVOLUCION_PENDIENTE, **no** DEVUELTA_ANAQUEL: Tienda confirma llegada física mediante `ConfirmarDevolucionAnaquelService`, con ubicación, foto y evidencia inmutable. El servicio registra `inventario_sincronizado=false`; no asegurar reintegro de existencias sin identificar/probar la integración de inventario O09.

Cancelar el pedido bloquea entrega cuando corresponde, preserva custodia/evidencias y deja disponible retorno autorizado; no equivale a eliminar resguardo ni entregar automáticamente. Corrección debe compensar con historial, no borrar hechos. Repetir devolución/entrega con misma clave devuelve el mismo resultado; distinta carga bajo misma clave debe rechazarse según contrato de idempotencia existente y pruebas propuestas.

Se conservan `custodia_confirmada_at`, configuración de días hábiles/zona horaria y reglas actuales de vencimiento/prórroga/reposición (`PlazosCustodiaResguardoPdvConfig`, `CalcularAntiguedadOperativaResguardoPdvService`, P08). Una recepción esperada no inicia custodia; replay, recarga, corrección o proyección no reinician el plazo. No restaurar políticas del primer documento histórico por ser anteriores.

### 6.4 Eventos, permisos y recuperación

- Creación esperada, entrega y devolución usan eventos después del commit: una transacción revertida no publica el hecho. Verificar listeners y notificación real en pruebas aisladas; la declaración de evento no prueba entrega en producción.
- Consultas y descargas respetan visibilidad departamental y alcance sucursal. Mutaciones conservan permiso específico, control de versión y exclusión de actores cuando procede; autorización de salida impide autoautorizarse.
- Historial debe permitir reconstruir pedido→tarea→cumplimiento→resguardo→entrega/devolución, sucursal, actor, evidencia y clave de operación. Un reintento de proyección no genera segundo hecho físico ni segunda recepción.
- Dos terminales y evento repetido se verifican con conexión aislada que soporte bloqueos reales. Un test con SQLite o solo mocks no acredita exclusión concurrente MySQL.

## 7. Clasificación de defectos y riesgos

«Confirmada» en esta tabla significa mecanismo de regresión demostrado en fuentes/diff; no incidencia productiva medida. Se separa el defecto léxico seguro de los recorridos todavía sin reproducción.

| Id / categoría | Archivo y función concreta | Evidencia y consecuencia / tratamiento |
| --- | --- | --- |
| R01 Regresión funcional confirmada | `ModalFormPedidoLegado.jsx`: filtro almacenes y efecto de selección; `PreparacionTiendaConfig::almacenPermitido` | `9493ec9` reduce default a PDV y puede sustituir/vaciar CEDIS; contradice preservación de selección y elegibilidad administrable D01/D19. Recuperar por casos, sin abrir Recoge hoy a cualquier almacén |
| R02 Regresión funcional confirmada de capacidad; política pendiente | `PedidoBma::consultaCedisModoRevisionMercancia`, helper JS de modo revisión, S03 | `9493ec9` retiró criterio local sin peso de `56189aa`/D28. U2 confirma solo el hito documental Aromas; alcance local adicional O02, no restauración masiva |
| R03 Defecto puntual confirmado estáticamente | `ConfirmarCaratulaColocadaService::ejecutar`, closure de transacción | Usa `$caratulaId` sin capturarlo en `use`; no valida correctamente identidad recibida. Reproducir id vigente/obsoleto bajo errores estrictos y corregir puntualmente |
| R04 Implementación incompleta / riesgo de pérdida | `ModalFormPedidoLegado.jsx::serializarBorrador`, autoguardado; `ActualizarPedidoBmaService` | Modalidad/municipal/prioridad/referencia viven fuera de data; snapshot serializado no los cubre. Merge parcial y null/default pueden destruir datos. Persistencia completa y semántica omitido/null pendientes CP1 |
| R05 Implementación incompleta / concurrencia | Modal: `asegurarPedidoEnBd`, tratamiento409/estado guardado; backend control de updated_at | Tener id no garantiza último cambio persistido; adoptar timestamp tras conflicto puede permitir nueva escritura sin resolver. Comparación temporal no es revisión atómica. Reproducciones CP2 antes de diseñar esquema definitivo |
| R06 Implementación incompleta | `CorregirTareaPreparacionService::ejecutar`, `CambiarModalidadPreparacionService` | Creación sucesora no transporta todos los datos municipales/traslado compatibles. Completar snapshot según modalidad y estado permitido; no trasladar ejecución ya realizada |
| R07 Implementación incompleta confirmada | `BusquedaDelegadoPedidoBma::aplicar`, normalización `PedidoBmaDelegadoController`, filtros UI | numero_remision persistido no participa en búsqueda; U3 exige completar consumidor, no retirar mejora |
| R08 Duplicación sin necesidad operativa acreditada | Modal: paquetería pedido y entregaMunicipal; flags de cobro | Dos fuentes editables para mismo transporte/cobro pueden divergir. Una autoridad con snapshot derivado; mantener casos distintos solo si se justifican |
| R09 Campo adicional pendiente justificar | Modal: prioridadMd, tipoReferencia/folioReferencia y datos municipales solapados | Consumidores existentes no prueban requisito; O07. Preservar captura hasta resolver, no volver obligatorios ni eliminar masivamente |
| R10 Riesgo no reproducido | `PedidoBma::tareaPreparacionVigente/requiereSucursalDestino` | Respuesta excluida de relación activa puede alterar obligatoriedad/destino por fallback; H08/H10 prueban después de respuesta y recarga |
| R11 Riesgo no reproducido de integración | `AbrirCustodiaDiferidaPdvService`, `InformarEntregaResguardoPdvService`, `MaquinaEstadosPedidoBma` | Entrega local puede no admitir cierre comercial; H12/H14 sin forzar ENVIADO. O05 |
| R12 Riesgo no reproducido de recuperación | `InformarEntregaResguardoPdvService`, `SincronizarCumplimientoDesdeResguardoPdvService::reflejarEntrega` | Integración comercial marcada completa puede omitir espejo; comprobar fallo intermedio/replay y no solo retorno de servicio |
| R13 Riesgo no reproducido / decisión pendiente | S12 reutilización por pedido+sucursal; S14 devolución/anaquel | Custodia cerrada reutilizada y continuidad inventario requieren fixture y O06/O09; retorno PDV→pendiente anaquel es distinción legítima, no fallo por sí misma |
| L01 Modificaciones legítimas | S09 numero_remision; modal/sistema visual; visibilidad CEDIS; S10–S14; reglas financieras/plazos | U1/U3/U4/U6, D22/P08: conservar. No asociar «reciente» con «incorrecto» |
| O01–O09 Decisiones operativas | Sección 10 | No completar con equivalencias marca=origen=almacén ni estados inventados |

## 8. Inventario ejecutable de pruebas futuras H01–H16

**No se ejecutó ninguna prueba en P1.2.** E = archivo existente a ampliar; N = archivo propuesto, aún no creado. Rutas abreviadas: UC=`tests/Unit/ControlPedidos/`; FC=`tests/Feature/ControlPedidos/`; FP=`tests/Feature/PuntoVenta/`; JS=`resources/js/Pages/ControlPedidos/Partials/`.

Preparación común **B**: entorno testing verificado, conexión efímera aislada, catálogos/roles/sucursal/departamento creados por factories, almacenamiento falso y reloj fijo; impedir conexión operativa antes de cargar fixtures. HTTP con autenticación y rutas reales; servicio interno cuando no existe endpoint propio. No reutilizar ids de producción. **J**: runner frontend actual con almacenamiento, reloj, peticiones y respuestas controladas; para cambio de selector usar montaje real del modal o prueba de navegador dedicada. **M**: MySQL desechable separado para bloqueos/colisiones, dos conexiones y barrera determinista; no afirmar concurrencia por un Unit serial. Preparación de esquema y cualquier ejecución pertenecen al futuro paquete, no a este checkpoint.

Cada fila define condición/acción/oráculo. Para ser corrección válida: primero reproducir el defecto cuando la fila lo acredita; después satisfacer el contrato en rama corregida y controles negativos, sin suavizar expectativas ni fabricar estados que oculten incompatibilidades. Cuando no hay error demostrado, la fila caracteriza un riesgo y puede pasar en el estado actual.

| Id / prioridad; archivo exacto | Entorno y fixture / condición inicial | Acción | Resultado esperado / evidencia actual y criterio específico |
| --- | --- | --- | --- |
| H01 P0; N JS`modalAlmacenContrato.test.jsx`; E UC`ValidarAlmacenSalidaPedidoTest.php` | J+B; origen noPDV, CEDIS elegido; config vacía, una/varias elegibles y ninguna; Recoge hoy como control PDV | Cambiar municipio ida/vuelta, esperar autosave, desmontar/recargar, solicitar | CEDIS/origen intactos; incompatibilidad explícita sin sustitución ni creación. R01 estático. Verde solo si se comprueba payload y BD, no etiqueta visual |
| H02 P1; E UC`ControlPedidosFase22Test.php`, `ControlPedidosFase23Test.php`, `ControlPedidosEnvioDiferidoFase1Test.php`; N FC`CedisMunicipioRecorridoTest.php` | B; pedido municipal CEDIS con consulta/cierre válidos, costos conocidos y diferidos | Consulta→auditoría→empaque→envío parcial/final y regularización | Circuito D07 conservado; sin tarea Tienda/traspaso ficticio; pendiente→enviado solo salida final. Alcance peso local condicionado O02; no prueba de error runtime actual |
| H03 P0; E UC`ControlPedidosCedisVisibilidadTest.php`, `ControlPedidosPesajeTest.php`, `ControlPedidosFlujoParaleloTest.php`; N FC`SeparacionMarcasContratoTest.php` | B; pares Bellaroma/Aromas con mismo transporte pero contexto distinto y roles correctos/ajenos; casos de O01 | Solicitar, listar y responder separación en cada caso | Diferenciación y acceso correctos, no solo cambio de etiqueta; separación interna disponible. R01/R02 estáticos no prueban todos los subcasos; fixture final depende O01 |
| H04 P0; N FC`AromasCapturaDocumentalTest.php` | B; Aromas, soporte válido/ausente, cero campos peso; posterior operación que sí requiere peso | Adjuntar soporte, remitir captura, descargar como Tienda; repetir sin soporte; luego preparar | C2 acepta sin peso y bloquea sin DC; documento ya accesible; C3 mantiene sus exigencias. U2 es oráculo; no existe reproducción HTTP acreditada; selección modalidad O03 |
| H05 P1; E FC`PreparacionTiendaWorkspaceTest.php`; E UC`RecogeTiendaFlujoTest.php`, `CumplimientoFisicoApartadoTest.php` | B+J; PDV Recoge hoy y transferencia como fixtures separadas | Solicitar/tomar/separar/confirmar y autorizar solo modalidad que lo admite | Política actual de apartado conservada; no nueva entrega directa; transferencia abre custodia. Test que excluye CEDIS por default no autoriza excluirlo de todo caso H01 |
| H06 P1; E UC`EnvioBodegaTraspasoTest.php`, `BodegaCedisObservabilidadTest.php`; N FC`TraspasoTiendaCedisRecorridoTest.php` | B+M; 10 solicitadas, 9 disponibles/1 faltante; variante 0; roles y almacenes reales de prueba | Responder/salir/replay; recibir o rechazar por controlador Traspasos existente | Un traspaso con cantidades correctas, cero sin movimiento, una sincronización/historial por hecho. Usar endpoints Traspasos de S05; no evidencia runtime nueva |
| H07 P0 puntual; E UC`EnvioMunicipioCaratulaTest.php`, `PreparacionTiendaP0Test.php`, `Fase5MunicipioEmpaqueTest.php`; N FC`ConfirmarCaratulaIdentidadTest.php` | B; LISTA_PARA_CARATULA, carátula vigente/obsoleta/ajena; handler estricto de errores PHP | Confirmar id recibido; editar dato observado; intentar confirmar previa/replay | Solo vigente coloca; id ajeno/obsoleto falla sin writes; invalidación con historial. R03 léxico seguro. No abrir transición de una colocada fuera del contrato existente |
| H08 P0 captura; E UC`DestinoSucursalPedidoBmaTest.php`, JS`destinoSucursalPedido.test.js`; N JS`modalPersistenciaContrato.test.jsx`; N FC`BorradorPreparacionContratoTest.php` | B+J+M para carrera; todos campos locales, sucursal, false/0/null/omitido; dos revisiones y borrador viejo | Guardar/recargar/corregir; editar antes debounce y solicitar; provocar409 y respuesta tardía | Datos compatibles íntegros, solicitud usa revisión confirmada; conflicto no dice guardado ni adopta revisión sin resolver; sucesora conserva municipal. R04–R06 estáticos; comparar snapshots completos y efectos únicos |
| H09 P0; E FC`RemisionAuditoriaTest.php`, UC`ControlPedidosActualizarGuiasTest.php`; N FC`BusquedaRemisionDelegadoTest.php`; N JS`delegadoRemisionContrato.test.js` | B+J; tres identidades distintas, señuelo y fuera de ámbito §5.2 | Capturar→serializar→buscar por campo/general→asignar/actualizar/importar guía | Resultados por campo correctos, id/folios intactos, ceros conservados, sin fuga de ámbito. R07 confirmado estáticamente; HTTP y opción UI deben pasar, no solo helper |
| H10 P0; E FP`HandoffRecepcionEsperadaPdvTest.php`; N FC`DestinoSucursalHandoffRecorridoTest.php` | B+M; sucursal activa, dos cajas, modalidad que requiere sucursal; tras respuesta y recuperación | Guardar/responder/recargar, enviar una caja y luego todas; repetir/concurrir, revertir transacción, GET | Una recepción correcta solo final; rollback sin evento; GET sin writes; ausencia destino falla antes de envío. R10 riesgo; comprobar id/sucursal y número de filas/eventos |
| H11 P1; E FP`RecepcionFisicaResguardoPdvTest.php`, `RecepcionParcialResguardoPdvTest.php`, `ConsultaBandejasResguardoPdvTest.php`, `VencimientosResguardoPdvTest.php` | B; bultos pendientes, recepciones parciales, rol ajeno, reloj en no hábil | Recibir/pasar/confirmar custodia parcial-total/replay y avanzar reloj | Proyecciones/bultos correctos, permiso negativo sin writes, ancla custodia y días hábiles actuales conservados. No regresión runtime probada; no iniciar plazo desde salida |
| H12 P0; E FP`EntregaResguardoPdvTest.php`; N FC`EntregaCustodiaDiferidaContratoTest.php` | B+M; dos entradas G/H, comercial **real obtenido al autorizar**, CF enlazado; titular/tercero, varios bultos | Parcial/completa/múltiple, replay, dos terminales, fallo proyección; reintentar tras comercial ya integrado | Una firma/hecho, parcial no cierra todo, completa sincroniza pedido+CF o pendiente explícita, sin segundo cobro/captura. R11/R12 riesgos; caso H no debe forzar ENVIADO; oráculo comercial definitivo O05 |
| H13 P0 continuidad; E FP`DevolucionCorreccionResguardoPdvTest.php`, `CancelacionPedidoResguardoPdvTest.php`; N FC`DevolucionCustodiaDiferidaContratoTest.php` | B+M; custodia enlazada cancelada/no cancelada, firma/evidencias previas | Intentar entrega cancelada; devolver/replay; confirmar anaquel con/sin ubicación/foto | Entrega bloqueada según política, devolución registrada una vez, CF pendiente hasta anaquel; evidencia/historia intactas. No inventar reintegro de stock: O09; distinción de hitos no es bug |
| H14 P0; N FC`AbrirCustodiaDiferidaContratoTest.php` | B+M; tarea transferencia SEPARADA, sucursal válida; resguardo inexistente/abierto/cerrado; preparador y autorizador distintos | Autorizar, replay y carrera; sustituir tarea solo en estado permitido | Un vínculo y bulto/fecha sin duplicados; no reutilizar cerrado silenciosamente; autoautorización denegada. O06 define tratamiento cerrado; R13 sin reproducción |
| H15 P1; N FC`CompatibilidadFlagsPreparacionTest.php` | B+J; flags on/off, distintos contextos/almacenes, tarea y custodia ya abiertas | Crear nuevos, consultar/continuar previos, intentar solicitud no elegible | Flag off mantiene CEDIS/local y acceso a pendientes; on no redirige por transporte. D18/D19; no error runtime acreditado |
| H16 P2; N JS`modalContratoOperativo.test.jsx`; E JS`pedidosBmaStyles.validacion.test.js` | J+B; mismo pedido con captura/pagos/documentos, paneles actuales y transportista compartido | Recorrer campos/foco/Escape/previews; cambiar campo una vez y guardar/recargar | Diseño preservado; una fuente editable transporte/cobro; sin nuevos obligatorios; payload equivalente. R08/R09; sustituir expectativa universal de peso solo con matriz aprobada, no borrar protección de casos válidos |

Ejecución futura concreta: con B/M previamente comprobado, ejecutar `vendor/bin/phpunit tests/Feature/ControlPedidos/BusquedaRemisionDelegadoTest.php` para H09 y archivos análogos de la tabla para cada ampliación; Unit se invoca por su ruta UC, PDV por FP. Para J, usar el runner existente del repositorio con la ruta exacta de cada archivo nuevo/ampliado, una vez verificado que soporta montaje JSX; si no lo soporta, el paquete debe proponer una prueba de navegador compatible antes de declarar H01/H08/H16 cubiertos. No instalar infraestructura ni ejecutar comandos de esta sección en P1.2. Fallos previos de bootstrap deben separarse del fallo de contrato; no cuentan como reproducción funcional.

## 9. Reconciliación de P1 y secuencia de implementación revisada

P1 conserva su diagnóstico de captura, concurrencia, carátula y sustitución. Cambia la interpretación de CP6b: CEDIS→municipio **ya existía**; recuperar A es restauración dirigida, no habilitación global nueva de tareas Tienda. CP5 pasa de «política universal por logística» a requisitos por hito (§3); R09 de P1 no puede usarse para exigir siempre pesaje CEDIS al envío documental Aromas. CP8 conserva diseño y simplifica solo duplicaciones acreditadas. Remisión y PDV adquieren paquetes explícitos y pruebas cruzadas; no son apéndices de una refactorización. CP7 se ejecuta solo si una necesidad de administración concreta queda aprobada, no para fabricar un catálogo nuevo.

Cada paquete futuro requiere diff pequeño, reproducción/oráculo, control negativo, compatibilidad de históricos, revisión y reversión. Esta secuencia es propuesta para revisión; P1.2 no autoriza ejecutarla.

| Orden / tipo / relación P1 | Entrega delimitada | Dependencias y aceptación | Reversión y límite |
| --- | --- | --- | --- |
| Q0 Reproducciones, CP0 | Fijar fixtures H01/H03/H04/H09/H10/H12 y separar baseline de bootstrap; resolver O01/O03/O05 para expectativas dependientes | Entorno B/J/M aislado. Casos pendientes quedan como tales; los independientes avanzan | Solo pruebas/fixtures futuras, sin tocar datos reales |
| Q1 Defecto puntual, CP3 | Capturar/validar identidad carátula correctamente | H07 ids vigente/obsoleto/ajeno, versión/permisos/PDF; sin ampliar estados | Revertir parche aislado, conservar historial/evidencia generados |
| Q2 Integridad captura, CP1 | Persistir snapshot completo compatible, omitido≠null/false/0; recuperar municipales/modalidad/prioridad/referencias y destino | H08; no crear nueva tarea por guardar; mantener documentos fuera del JSON de archivos | Lectura compatible de borradores anteriores; no eliminar columnas ni datos; diseño de versión/formato se revisa, no se impone aquí |
| Q3 Integridad concurrente, CP2a/CP2b | Guardado verdadero y barrera antes de solicitar; conflicto explícito, revisión atómica e idempotencia en servicios existentes | Q2, H08 y M; exactamente un escritor/solicitud; P1 es propuesta de diseño, no aprobación automática de una migración | Estrategia compatible de lectores/escritores y rollback documentada antes de esquema; no revertir solo UI dejando escritores incompatibles |
| Q4 Recuperación selección, CP6a y parte CP6b | Eliminar cambio silencioso CEDIS→PDV; preservar A; elegibilidad por combinaciones aprobadas | Q0, H01/H02/H03/H15; O04 para ampliar Tienda; mantener restricción Recoge hoy | Revertir regla dirigida, no todo modal; conservar selección histórica; flag no abandona pendientes |
| Q5 Recuperación documental/requisitos, CP5 | Aromas C1/C2 soporte obligatorio sin peso; distinguir C3, revisión, empaque y despacho | O01/O03, Q2/Q3/Q4 donde aplica; H03/H04 y controles mercancía/cajas/daños. O02 aparte si se cambia local sin peso | Separar parche C2 del alcance local O02; rollback conserva soporte, no elimina EM/EE |
| Q6 Defecto de sustitución, CP4 | Sucesora conserva datos compatibles; datos ejecutados e identidades no se copian como nuevos hechos; vigencia exclusiva | Q2/Q3, H07/H08/H14; solo estados/correcciones permitidos | Lectura de tareas previas, historial anterior intacto; no cancelar tareas masivamente |
| Q7 Completar remisión, U3 | numero_remision en filtro tipado/general, normalización, UI y respuesta; conservar importación por id | H09 y §5, O08 si cambia coincidencia; independiente de recuperación física | Quitar filtro nuevo sin reescribir folios ni referencias; CSV compatible |
| Q8a Integración envío–PDV, U6 | Preservar destino y handoff único por salida final, recepción/custodia y plazos vigentes | H10/H11, Q2/Q4 según caso; no generación en GET | Revertir cálculo/enlace con auditoría de pendientes, sin borrar resguardos ni reiniciar fechas |
| Q8b Integración custodia diferida–entrega, U6 | Cierre comercial compatible y espejo CF recuperable; misma firma/evento/id; política resguardo cerrado | O05/O06, H12/H14; Q3 donde comparte claves; no simular ENVIADO | Separar proyección de hecho físico; rollback mantiene entregas y deja pendientes visibles, no vuelve a abrir entregados |
| Q8c Integración devolución, U6 | Devolución PDV→pendiente→anaquel; recuperación idempotente y trazas | H13, O09 si incluye stock; conservar cancelación/permisos/vencimientos | Hechos físicos/evidencias inmutables; ajuste compensatorio, no deshacer una devolución real |
| Q9 Simplificación, CP8 / CP7 condicionado | Una autoridad transporte/cobro, herencia de contacto/dirección; resolver cada extra O07; conservar layout | Q2–Q8 en ramas afectadas y H16; no ocultar campo con consumidor sin alternativa; CP7 solo necesidad aprobada | Reversión visual y compatibilidad payload, datos previos legibles; no eliminar datos como limpieza |
| Q10 Refactorización técnica, CP8 | Extraer responsabilidades gradualmente, sin añadir campos, rutas físicas ni negocio | Contratos anteriores verdes y revisión de equivalencia; solo cambios mecánicos delimitados | Un componente/servicio por diff; revertible sin alterar estados ni formato persistido |

Prioridad de salida: Q1–Q3 protegen bloqueo/pérdida de información; Q4/Q5 recuperan operación aprobada; Q7 y Q8 prueban continuidad documental y PDV. El orden no obliga a esperar decisiones ajenas para un arreglo independiente; tampoco autoriza resolverlas por conveniencia técnica. No mezclar Q9/Q10 con restauración para ocultar cambios de reglas. No desplegar, limpiar datos, migrar o activar flags globalmente por este plan.

## 10. Decisiones operativas aún no resueltas

| Id | Decisión y responsable a consultar en revisión | Qué queda firme / qué no se implementa por inferencia |
| --- | --- | --- |
| O01 | Operación Bellaroma/Aromas + CEDIS: combinaciones concretas de departamento/origen, destino interno y cierre posterior de separación | U1 conserva distinción y función. No inferir códigos de marca, visibilidad total ni destino sucursal por la etiqueta |
| O02 | Operación CEDIS/logística: alcance vigente de revisión local sin peso de D28 frente al retiro `9493ec9`, por transporte y fase | U2 ya resuelve C2 sin peso. No convertirlo en todos los locales sin peso ni todo CEDIS con peso |
| O03 | Aromas + Tienda: modalidad/estado exactos de envío documental y preparación posterior, responsable y soporte aceptado | C2 exige evidencia, no peso; no crear una modalidad o máquina nueva ni suponer que cualquier tarea municipal representa ese envío |
| O04 | Operación Tienda/CEDIS: almacenes y casos noPDV elegibles; si algún caso requiere CEDIS→Tienda físico y cómo se acredita | Selección válida no se cambia silenciosamente; no hay evidencia de circuito inverso completo. Recoge hoy mantiene política PDV específica |
| O05 | Operación comercial + PDV: transición comercial correcta tras entrega local diferida sin salida CEDIS y comportamiento ante proyección incompleta | Entrega se captura una vez, debe reflejarse y ser recuperable; no fabricar ENVIADO ni habilitar todas las fases hacia ENTREGADO |
| O06 | PDV + Tienda: reabrir, sustituir o rechazar cuando pedido+sucursal ya tiene custodia terminal y llega nueva tarea | No duplicar/reutilizar terminal silenciosamente; debe quedar relación e historial explícitos, sin reiniciar vencimientos por replay |
| O07 | Responsable de captura: utilidad específica de prioridad, folio/tipo referencia adicionales, contactos y municipio repetidos | U4 conserva presentación; U5 exige justificación individual. No todos los extras se eliminan ni todos se convierten en requisitos |
| O08 | Auxiliar/Delegado: coincidencia exacta/parcial y normalización de remisión; eventual columna adicional de exportación | U3 exige búsqueda útil y tres identidades distintas; conservar ceros, ámbito e importación por id; no declarar remisión única global |
| O09 | PDV/Tienda/inventarios: qué hecho y servicio confirman reintegro de stock tras devolución física, y cómo se concilia | DEVUELTO PDV y DEVUELTA_ANAQUEL distintos; `inventario_sincronizado=false` no acredita stock actualizado; no crear movimiento ficticio |

Cierre de P1.2: documento listo para revisión de contrato y paquetes. No se modificó código ni se implementaron correcciones. El siguiente checkpoint debe resolver las decisiones que afecten cada paquete y ejecutar primero sus reproducciones aisladas; esta entrega se detiene antes de implementar.

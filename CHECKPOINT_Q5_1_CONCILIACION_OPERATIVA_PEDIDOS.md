# CHECKPOINT Q5.1 — Conciliación operativa de pedidos

## 1. Conclusión

El recorrido vigente ya separa consulta CEDIS, pesaje CEDIS y preparación de Tienda. Esta conciliación no creó una modalidad documental ni reabrió la exención O02.

Quedaron corregidos tres defectos comprobados:

1. La preparación en Tienda ya no exige anaquel ni ubicación para separarse o confirmarse.
2. Un cambio de piezas pendiente ya no deja vigente el empaque ni la salida sobre la respuesta física anterior.
3. Las alertas de retraso de empaque y de recolección vuelven a distinguirse, y la marca de “ya alertado” puede persistir.

Lo demás de la aclaración de negocio se contrastó con el código. Donde el contrato actual ya coincide, se conserva. Donde falta una definición, queda una pregunta concreta y no se inventó la regla.

No hay `AGENTS.md` en el repositorio. Se usaron las reglas de Cursor del proyecto, los checkpoints Q1–Q5, Q7, P1.1 y P1.2, y el código. La rama de partida es `main` en `99ff3b0`, con árbol limpio. No hay commit, push ni migración.

## 2. Matriz de escenarios

| Escenario | Origen y destino | Preparación | Consulta, peso, guía y costo | Traslado, recepción y salida | Clasificación |
|---|---|---|---|---|---|
| Envío con logística | Origen `requiere_logistica`; salida CEDIS | CEDIS | Pesaje con kilos, caja, volumétrico y foto de contenido. Guía comercial y costo, salvo guía del cliente, por cobrar o tarifa local por peso aún sin peso | Empaque y guía pueden ocurrir en cualquier orden. La salida espera empaque y, si hay rastreo, guía | Ya correcto. Q5 y `ControlPedidosFlujoParaleloTest` |
| Local sin logística | Origen sin logística | CEDIS si la salida no es PDV de preparación | Consulta de mercancía: piezas, condición y evidencia. Sin kilos ni guía comercial | No crea tarea de Tienda por el destino | Ya correcto. Q4/Q5 |
| Local con logística y `requiere_peso = false` | Origen con logística; paquetería local | CEDIS | Sigue pidiendo pesaje CEDIS. La tarifa no exime | Igual que el envío con logística | Ambiguo. Es O02; no se convirtió en regla |
| CEDIS → PDV | Salida CEDIS y sucursal destino, origen sin logística | CEDIS. No se crea tarea de Tienda | Sin guía comercial, porque no hay logística | Recepción y custodia PDV son otro circuito | Ya correcto en la elegibilidad Q4. Falta definir si el transporte propio debe existir también con origen que sí requiere logística |
| Preparación y salida desde PDV | Salida en almacén de Tienda | Tienda | Peso, caja y carátula solo si `efectivos()` los marcan. Sin filas de pesaje CEDIS | El traslado a CEDIS, cuando existe, abastece; no define la modalidad | Ya correcto en Q5, salvo el anaquel, que se retiró de este cierre |
| Recoge en Tienda | PDV | Tienda | Separación y evidencia. Sin kilos CEDIS | Confirmar apartado no es entrega ni liberación de inventario | Corregido: se completa sin anaquel |
| Municipio en Tienda | PDV y modalidad municipal | Tienda | Carátula, bulto y entrega. Báscula solo si `peso_real_obligatorio` | Costo diferido si el transporte tiene `permite_costo_diferido` | Ya correcto en Q5 |
| Resguardo en CEDIS | Operación `RESGUARDO_ABIERTO` | No autoriza empaque | Pago y remisión pueden existir | Empaque bloqueado hasta liberar | Ya correcto |
| Custodia en PDV | Recepción de mercancía en sucursal | No es preparación de Tienda | Distinta de confirmar recepción, entregar y devolver | La devolución al anaquel sigue siendo un hecho posterior | Ya correcto como distinción. El retorno físico no se reescribió |
| Crédito | Cualquier origen | No cambia la preparación | Sin comprobante ni banco. No entra al ingreso bancario | Cubre el pedido; no crea un pago de banco o efectivo | Ya correcto con el contrato actual |
| Cambio de piezas | Consulta ya respondida | CEDIS debe volver a confirmar | La respuesta anterior se conserva como precarga, no como autorización de salida | Empaque y envío quedan bloqueados mientras `consulta_actualizacion_pendiente` | Corregido |
| Atraso de paquetería | Con pago validado y fase CEDIS o pendiente de envío | CEDIS | El plazo usa días hábiles desde `pago_validado_at`, con corte y temporada alta | Alerta e historial. La voz ya distingue empaque y recolección | Mecanismo existente corregido. El ancla de 24/48 h sigue sin definirse |

## 3. Evidencia

- Aclaración de negocio: secciones 2.B, 2.F y 2.I de este encargo. O02 y O03 se citan solo como pendientes de la sección 11 de Q5.
- Q4: `usaPreparacionTienda()` solo con tarea vigente; la consulta CEDIS no se bloquea por el nombre comercial Tienda ni por la sucursal destino. `SolicitarPesajePedidoBmaService::consultaCedisBloqueadaPorSalidaTienda()`.
- Q5: `PedidoBma::consultaCedisModoRevisionMercancia()` es verdadero solo sin logística. `ValidacionCamposPedidoBma` no pide detalle de envíos CEDIS en preparación de Tienda. El peso de esa preparación sale de `CalcularRequisitosPreparacionService::efectivos()`.
- Catálogo: `CatalogoPaqueteriaPedido` distingue `requiere_peso`, `requiere_caja`, `permite_costo_diferido` y `modalidad_tarifa`. `permite_costo_diferido` convierte la operación en `MUNICIPIO_DIFERIDO` en `ResuelveDatosPedidoBma::resolverTipoOperacionEnvioId()`.
- Paralelo: `AsignarGuiaPedidoBmaService` deja la fase en CEDIS; `MarcarEmpacadoPedidoBmaService` pasa a pendiente de guía o a pendiente de envío según ya exista rastreo. `MarcarEnviadoPedidoBmaService` exige empaque.
- Traslado: `ConfirmarSalidaTrasladoTiendaService` pasa a `EN_TRASLADO`. `SincronizarTareaDesdeTraspasoService` escribe `recibida_cedis_at` solo cuando el traspaso se recibe.
- Crédito: `PedidoBmaPago::REQUIERE_COMPROBANTE['credito']` es falso. `ClasificacionIngresoBancario` lo clasifica como pago no bancario y `CalcularIngresoBancarioValidadoService` no lo suma al ingreso bancario.
- Plazos: `PlazosRetrasoPedidoBmaConfig` y `EvaluarRetrasosPedidoBmaService`. El ancla de empaque es `pago_validado_at`. El de recolección es `guia_subida_at` en comercial y `empacado_at` en local.

## 4. Diferencias y correcciones

### 4.1 Anaqueles en la preparación de Tienda

Antes, separar mercancía y confirmar Recoge en Tienda exigían una ubicación. El formulario la presentaba como anaquel. El servidor rechazaba el cierre si venía vacía.

Después, `SepararCumplimientoFisicoRequest` acepta ubicación nula. `SepararCumplimientoFisicoService` y `ConfirmarApartadoRecogeHoyService` completan el apartado sin ella. El formulario de preparación ya no tiene ese campo ni lo envía. Si un registro histórico tiene ubicación, se sigue mostrando.

No se borró la columna `ubicacion`, ni el estado `DEVUELTA_ANAQUEL`, ni `ConfirmarDevolucionAnaquelService`. Esa confirmación es el retorno posterior de una recogida no concretada: otro hecho, con ubicación y foto propias. P1.2 lo mantiene separado de la devolución en PDV.

### 4.2 Cambio de piezas y respuesta física

`ActualizarConsultaPedidoBmaService` marcaba `consulta_actualizacion_pendiente` y volvía a pedir la consulta, pero un pedido ya en CEDIS seguía pudiendo empacarse y enviarse con las cajas anteriores.

`puedeMarcarEmpacado()` y `puedeMarcarEnviado()` ahora exigen que no haya actualización pendiente. El empaque y el marcado de enviado lo rechazan en servidor. La tarjeta y el detalle de CEDIS ocultan esas acciones y avisan que hay que confirmar la consulta. La respuesta anterior permanece como precarga; no se borran cajas, fotos ni folios.

### 4.3 Atrasos

El evaluador ya notificaba y escribía historial. Dos fallos impedían usar ese mecanismo:

- `retraso_empaque_alertado_at` y `retraso_recoleccion_alertado_at` no estaban en `fillable`, así que un modelo no podía dejar constancia de la alerta ya emitida.
- La voz de `pedido_retraso_empaque` y `pedido_retraso_recoleccion` caía en el texto genérico y ambos títulos coincidían.

Esas dos alertas ya tienen etiqueta y mensaje propios. No se cambió el ancla ni se inventó un plazo de 24 o 48 horas.

## 5. O02 y O03

O02 sigue pendiente. Una paquetería local con `requiere_peso = false` no exime el pesaje CEDIS cuando el origen requiere logística. Q5 lo fijó y esta aclaración no lo aprueba como regla. El envío local sin logística ya es consulta de mercancía, sin kilos ni guía comercial.

O03 sigue pendiente. No existe una modalidad documental. La ausencia de kilos en una preparación de Tienda no la convierte en operación documental: sigue siendo separación física, con la evidencia que pida la matriz.

## 6. Archivos

Sin migración y sin cambio de catálogos, permisos ni `permite_salida_pedidos`.

- `app/Models/ControlPedidos/PedidoBma.php`
- `app/Services/ControlPedidos/SepararCumplimientoFisicoService.php`
- `app/Services/ControlPedidos/ConfirmarApartadoRecogeHoyService.php`
- `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php`
- `app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php`
- `app/Http/Requests/ControlPedidos/SepararCumplimientoFisicoRequest.php`
- `app/Http/Controllers/ControlPedidos/PedidoBmaTiendaController.php`
- `app/Notifications/AlertaPedidoBma.php`
- `resources/js/Pages/ControlPedidos/Tienda/Partials/PrepararApartadoRecoge.jsx`
- `resources/js/Pages/ControlPedidos/Tienda/Partials/ApartadoFisicoTienda.jsx`
- `resources/js/Pages/ControlPedidos/Tienda/Partials/requisitosRespuesta.js`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis.jsx`
- Pruebas de `RecogeTiendaFlujoTest`, `PreparacionTiendaWorkspaceTest`, `ControlPedidosFlujoParaleloTest` y `PrepararApartadoRecoge.test.jsx`

## 7. Pruebas

Entorno Sail, SQLite `:memory:`. No se usó `ALLOW_DESTRUCTIVE_DB` ni una base operativa.

| Conjunto | Resultado |
|---|---|
| `ElegibilidadPesajePedidoTest` y `ControlPedidosPesajeTest` | 28/28 |
| `ElegibilidadCedisPdvPedidoTest`, flujo paralelo, Recoge, workspace de Tienda, plazos y retrasos | 58/58 en la pasada conjunta |
| Vitest `PrepararApartadoRecoge.test.jsx` | 9/9 |

Cubren: preparación sin ubicación; separación sin ubicación; empaque rechazado con actualización pendiente; empaque antes y después de la guía; voz distinta de los dos retrasos; elegibilidad Q4/Q5 intacta.

No se recorrió la interfaz autenticada en el navegador. La comprobación de pantallas es estática y de Vitest. Sigue pendiente ver en un pedido de prueba, no productivo, que el formulario de Tienda confirme sin anaquel y que CEDIS no ofrezca empaque mientras la consulta está reabierta.

## 8. Compatibilidad, riesgos y reversión

- Q1–Q4, Q5 y Q7 no se reescribieron. Las pruebas de elegibilidad y pesaje siguen verdes.
- Un apartado histórico con ubicación se conserva. Uno nuevo puede quedar con `ubicacion` nula.
- La devolución al anaquel sigue exigiendo ubicación y foto. No es el cierre de la preparación.
- La guía ya cargada no se invalida sola al reabrir la consulta. Solo se impide empacar y marcar enviado hasta la nueva respuesta.
- Revertir es volver estos archivos. No hay datos de producción que migrar.

## 9. Preguntas pendientes

1. **O02.** ¿Una paquetería local con `requiere_peso = false` debe eximir los kilos de CEDIS cuando el origen sí requiere logística? Hoy no. Cambiarlo depende de esa autorización.
2. **Transporte propio CEDIS → PDV.** Si el origen requiere logística, el sistema pide paquetería. ¿Existe un identificador de catálogo para el transporte propio, o ese recorrido debe seguir siendo origen sin logística y sucursal destino?
3. **Costo diferido local.** `permite_costo_diferido` convierte cualquier paquetería marcada así en operación `MUNICIPIO_DIFERIDO`. ¿Ese es el costo posterior del transporte local, o solo el municipal?
4. **Ancla de 24/48 h.** El código cuenta días hábiles desde el pago validado, con hora de corte y un día extra si `temporada_alta` está activa. ¿El plazo debe seguir anclado al pago, o a otro hecho, y quién habilita la ampliación?
5. **Retorno al anaquel.** La preparación ya no lo pide. La confirmación de devolución física sigue pidiendo “ubicación final en el anaquel”. ¿Ese texto y ese requisito también deben retirarse, conservando foto y motivo?

## 10. Trabajo que queda para Q6

- Recorrido CEDIS → PDV con transporte propio como contrato explícito, si la respuesta 2 lo define. Criterio: no pedir guía comercial ni crear tarea de preparación en Tienda, y sí abrir recepción y custodia.
- Política de 24/48 horas, si la respuesta 4 fija el ancla y quién amplía. Criterio: una sola configuración, visible para Ventas, Auxiliar y bitácora, sin un segundo calendario.
- Exención O02, solo con autorización. Criterio: `requiere_peso` del transporte gobierna el pesaje CEDIS sin una rama por nombre de departamento, y los envíos que sí pesan siguen exigiendo caja, kilos y evidencia.
- Revisión del retorno físico si la respuesta 5 retira el anaquel de la devolución. Criterio: no perder historial ni confundir esa acción con liberar inventario.

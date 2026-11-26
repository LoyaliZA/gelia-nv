# GELIA — Gestión de Pedidos | Guía operativa maestra V3.1

**Versión:** 3.1 · **Fecha:** 21 de noviembre de 2026  
**Propósito:** contrato de comportamiento operativo para personal, responsables funcionales, Claude y otros agentes de desarrollo/QA.  
**Ámbito:** Tienda, Envíos Municipales, Envíos Nacionales, resguardos, complementos, saldos, crédito, pagos y movimientos físicos.  
**Naturaleza:** especificación funcional consolidada. **No certifica que cada función esté implementada ni aprobada en navegador.**

> **Regla rectora:** GELIA debe reproducir cómo se realiza una venta y cómo fluye entre personas y áreas, con variantes según hechos reales. Los casos N-02, T-06, S-09, etc. **no son 53 flujos obligatorios paralelos**: son decisiones, incidencias o complementos que se insertan en alguno de los recorridos operativos principales. Solo se ejecutan cuando se presenta su condición de entrada.

## 0. Control documental y precedencia

### 0.1 Fuentes y criterio de conciliación

1. **`Analisis_de_Requerimientos_Gestion_de_pedidos_V02.1_Dudas_resueltas.md`**, y su copia DOCX: catálogo de 53 fichas, procedimientos, cuestionario, aclaraciones **AC-01 a AC-15 respondidas** y trazabilidad histórica. Para decisiones de negocio, la respuesta `R:` de cada AC prevalece sobre la redacción anterior de la misma ficha cuando difieren.
2. **`PROCESO PARA PEDIDOS.docx`**: explicación de las prácticas reales, nomenclatura de los puestos, tipos de atención y situaciones frecuentes.
3. **`ENVIOS NACIONALES Y RESGUARDOS.docx`** y fichas N-01…N-15/R-04…R-06: relato detallado de los intercambios y limitaciones en Nacional.
4. **Decisiones funcionales acordadas durante las revisiones de GELIA 2.0:** captura compartida con modalidades específicas; consultas iterativas; no confundir área de preparación con finanzas; diferenciación de un pedido principal, complemento y traslado físico; independencia de documentos y cajas; conservar compatibilidad histórica.
5. **Código/repo y reportes de agentes:** indican *cómo está o podría estar implementado*, no tienen autoridad para redefinir lo que personal/cliente debe hacer. Un test verde no cambia la regla de negocio.

**Si hay divergencia real:** describir la regla confirmada, señalar la divergencia y detener **solo la acción irreversible o peligrosa**. No inventar importes, facultades, horarios, aprobaciones ni automatismos. Una propuesta de interfaz no es una regla de operación confirmada.

### 0.2 Qué cambia respecto a V02.1

- Se reorganizan los 53 casos en **tres recorridos principales y capacidades compartidas**; los casos eventuales quedan como bifurcaciones con disparador, responsable, regla de continuación y límites.
- Se define qué se espera **desde cada bandeja y quién entrega el trabajo al siguiente rol**.
- Se explicita la diferencia entre *pedido Wizerp, consulta de mercancía, versión aceptada, pago, remisión, resguardo, recepción física, caja, guía y salida*.
- Se distingue el **orden de captura comercial** del orden de **ejecución** de la operación. Un dato puede capturarse temprano sin que su acción esté habilitada todavía.
- Las aclaraciones AC respondidas se elevan a reglas vigentes o decisiones que aún requieren precisión, sin convertir inferencias técnicas en políticas.
- Se proporciona una matriz de trazabilidad para que un agente no omita casos ni convierta cada excepción en una pantalla nueva.

**Fuera de alcance de este documento:** arquitectura exacta, esquema SQL obligatorio, comandos de migración, políticas nuevas por inferencia, aceptación visual efectivamente ejecutada y una promesa de entrega final de paquetería sin evidencia de recepción.

## 1. Modelo mental común: una venta, varios estados simultáneos

Una operación de GELIA tiene **dimensiones independientes**:

| Dimensión | Pregunta operativa | No confundir con… |
| --- | --- | --- |
| **Pedido comercial (Wizerp)** | ¿Qué pidió el cliente y cuál es su importe vigente? | Pago, remisión o mercancía realmente apartada. |
| **Consulta/aceptación** | ¿CEDIS/PDV respondió piezas, condiciones, alternativas y el cliente decidió? | Cierre de Ventas: una respuesta del almacén no equivale a aceptación del cliente. |
| **Pago/cobertura** | ¿Qué conceptos están pagados, aprobados a crédito o pendientes? | Existencia de comprobante o guía; el costo de transporte puede diferirse. |
| **Remisión** | ¿La auxiliar comprobó y documentó la operación en Wizerp? | Folio del pedido inicial; una remisión no se edita informalmente por anexos. |
| **Compromiso/ubicación** | ¿Dónde están las piezas, apartadas, en espera, en custodia o en traslado? | Registrar una referencia no significa que la mercancía llegó físicamente. |
| **Empaque/cajas** | ¿Cuántas unidades físicas se preparan, con qué peso/medidas/contenido? | Número de pedidos o remisiones; una caja no es una remisión. |
| **Guías/transporte** | ¿Qué guía vigente corresponde a qué caja y qué transportista la recibió? | Remisión, cotización o entrega final al destinatario. |
| **Cierre** | ¿Qué actividad terminó y qué continúa pendiente? | Un estado universal de “todo terminado”. |

### 1.1 Invariantes no negociables

1. **Primer documento:** la vendedora genera un pedido *sin pago* en Wizerp, captura cliente, folio, modalidad/destino según proceda, importe preliminar y **sube PDF o capturas**. Puede identificar SKU/unidades adicionalmente, pero **no exigir captura manual de toda la mercancía si el documento permite solicitar consulta**.
2. **Consulta iterativa:** CEDIS/PDV responde unidades/solicitudes, cantidades, observaciones y evidencia por pieza cuando aplica; Ventas devuelve al cliente y decide si continuar, sustituir, anexar, retirar o esperar. El área que responde **no cierra comercialmente** la consulta de Ventas.
3. **Separación de responsabilidades:** Ventas no valida su propio ingreso bancario, no emite ficticiamente una remisión ni puede confirmar recepción física en PDV/CEDIS. Un responsable no se suplanta por un botón o un cambio de estado.
4. **Pagos:** transferencia requiere comprobación de ingreso; crédito depende de Wizerp y no se combina con transferencia para salvar un bloqueo. Varias transferencias sí pueden completar un pedido; nota de crédito más diferencia es una aplicación específica documentada.
5. **Identidad:** mismo pedido/cliente, documentos, remisiones, origen y destino se conservan en cambios; nadie crea pedidos fantasma para simular movimiento ni cobra dos veces mercancía pagada.
6. **Mundo físico:** salida de origen ≠ recepción en destino ≠ integración al conjunto ≠ entrega al cliente. Cada evento necesita actor y evidencia adecuados.
7. **Salidas:** Nacional **solo desde CEDIS**; Municipal según ruta CEDIS → PDV → terminal; Tienda termina en PDV/custodia/entrega. Nunca extender reglas de carátula/terminal/Jaguar al envío nacional.
8. **Parcialidad:** una caja recogida no habilita marcar como despachadas las cajas pendientes; una guía reemplazada no puede seguir vigente; documentos anulados se conservan para auditoría.
9. **Trazabilidad:** autor, fecha, motivo, versión de consulta, documento/evidencia, importe, remisión, ubicación y acción de cada intervención. Repetir una solicitud no puede duplicar una venta, anexo, cobro o movimiento.
10. **Cierre condicionado:** no hay despacho con crédito bloqueado, complemento sin pagar, recepción pendiente, envío por cotizar, cobertura incompleta, guía inválida ni orden de espera vigente.

## 2. Personas, bandejas y relevos obligatorios

| Actor | Inicia / ve / hace | Entrega o confirma | Nunca debe afirmar por otro |
| --- | --- | --- | --- |
| **Cliente** | Decide mercancía, destino, paquetería, seguro, cambios, recolección y pago. | Instrucción/aceptación a vendedora. | No se presume aceptación por silencio. |
| **Vendedora / Ventas** | Crea pedido Wizerp, adjunta documento, consulta, presenta respuesta, captura datos y cargos, reporta pago, pide cambios y libera esperas autorizadas. | CEDIS/PDV → cliente → Auxiliar/Delegado según fase. | No confirma depósito, crédito, recepción de custodia ni despacho. |
| **CEDIS** | Responde disponibilidad, detalles, imágenes; pesa, determina cajas, separa, empaca, recibe complementos, coloca guías y entrega transporte. | Ventas; Delegado; PDV por traslado de tienda/municipal. | No cierra consulta comercial; no marca recibido un complemento solo por aviso de salida. |
| **Auxiliar de Ventas** | Verifica ingreso real, importe, método, exhibiciones, nota/saldo y crédito Wizerp; tramita remisión. | Ventas, CEDIS, Delegado. | No inventa aprobación de crédito ni cobra de nuevo mercancía pagada. |
| **Encargado/Delegado de guías** | Revisa destinatario/domicilio, paquetería, cajas/peso, seguro/cobertura y documentación; genera o registra guía y PDF por caja. | CEDIS: etiqueta vigente utilizable. | No usa guía anulada ni da por entregado al cliente. |
| **Auxiliar de PDV / sucursal** | Prepara/recibe bolsas, atiende piezas de tienda, traslados, cambios y complementos. | Custodia, CEDIS, cliente según autorización. | No convierte su recepción en salida nacional ni modifica automáticamente remisión. |
| **Recepción/custodia PDV** | Identifica bolsas pagadas, registra recepción, resguarda, entrega al cliente con foto o libera a traslado autorizado. | Evidencia a Ventas/PDV. | No entrega sin retiro de custodia y sin autorización correspondiente. |
| **Gerencia/Administración** | Aprueba excepciones, saldos/devoluciones y controles financieros posteriores; autoriza segundos traslados cuando aplica. | Resolución auditable al área que sigue. | No confundir autorización de reembolso con pago realizado. |
| **Traslado/repartidor** | Mueve mercancía entre áreas y/o a terminal; comparte costo y evidencia del evento que realizó. | Receptor físico; Ventas para costo posterior. | No acredita entrega final con una foto de traslado intermedio. |
| **Cajera PDV** | Efectivo del proceso Jaguar de terminal conforme caja PDV. | Repartidor/caja PDV. | No sustituye pago de cliente por gestionar efectivo del transportista. |
| **Rafael / gestión excepcional** | Recibe solicitudes posteriores a entrega a transporte nacional. | Resultado confirmado o pendiente. | Solicitar ≠ recuperar, cancelar ni corregir efectivamente el envío. |

**Regla de bandejas:** una acción se considera operativamente integrada solo si el rol correcto puede **encontrar la solicitud, identificar el contexto, ejecutar/registrar su parte y pasar el resultado al siguiente rol**. Tener un servicio o endpoint sin superficie utilizable **no completa** el proceso.

## 3. Esqueleto compartido de cualquier pedido (sin imponer wizard)

**A — Captura de solicitud.** Vendedora recoge decisión inicial y crea el pedido sin pago en Wizerp. En GELIA: cliente, departamento, folio, modalidad, destino o paquetería preliminar, importe preliminar de mercancía, PDF/capturas. Puede registrar SKU y capturas específicas si hacen falta. Se guarda sin convertir importe preliminar en pago confirmado.

Si al guardar el formulario compartido GELIA detecta un folio que usa otro pedido activo, marca en rojo el borde de **Folio del pedido** y muestra arriba el pedido que ya lo usa. Cuando Ventas cambia el valor, desaparece la marca y el siguiente guardado valida el folio corregido.

**B — Consulta al área física.** Enviar documento e instrucción clara a CEDIS o PDV. Área responde por piezas/cantidad, estado, evidencias y alternativas; para Nacional también cajas y pesaje. La solicitud queda identificable en la bandeja destinataria.

**C — Decisión y cierre comercial.** Vendedora comunica al cliente. Si faltan piezas o hay detalles, se espera/consulta. Si anexa o retira, se conserva versión y se repite la parte afectada. **Ventas cierra la consulta** cuando el cliente aceptó composición vigente; las respuestas previas no habilitan cierre tras cambio material.

**D — Definición logística y cobertura financiera.** Solo ahora se cierran datos de entrega, flete, seguro, reexpedición y total a pagar que corresponda; excepciones de mercancía ya pagada permiten esperar el transporte sin falsear el pago. Ventas reporta ingreso o crédito; Auxiliar verifica y remisiona en Wizerp cuando proceda.

**E — Preparación / movimientos.** Área correcta empaca según modalidad; si hay material en otra ubicación se solicita y confirma retiro, traslado y recepción antes de integración. Custodia y esperas se mantienen identificadas hasta liberación.

**F — Guía/salida/entrega.** Nacional: Delegado → CEDIS → transportista. Municipal: CEDIS/PDV → terminal y ficha según operador. Tienda: PDV → custodia/cliente. Se registra **evento físico** y pendientes financieros de manera independiente.

**Los bloques A–F describen el orden del proceso, no seis pantallas.** Una captura contextual puede mostrar datos ya conocidos y ocultar o deshabilitar acciones todavía improcedentes, sin impedir consultas iterativas.

## 4. Recorrido principal T — Tienda y recolección en sucursal

### T-F01. Pedido por transferencia para recoger en tienda (trayecto común)

**Entrada:** cliente quiere comprar, pagar por transferencia y recoger posteriormente en una sucursal; **documentos:** Wizerp sin pago, PDF/capturas, sucursal, comprobante, remisión; **referencias:** T-02 y R-01.

1. **Cliente → Vendedora.** Indica piezas, cantidades y sucursal. Vendedora crea pedido sin pago en Wizerp y lo sube a GELIA; selecciona Tienda y sucursal/área de consulta correspondiente. No registra pago ni remisión todavía.
2. **Vendedora → CEDIS (preferente) o PDV según disponibilidad.** Envía documento e instrucción de consulta. No pide al personal de PDV que facture el pedido por el solo hecho de consultar allí.
3. **CEDIS/PDV → Vendedora.** Responde la disponibilidad del conjunto y registra las piezas con detalle o sin existencias, con fotos cuando corresponda. Al enviar, CEDIS ve el resumen y espera el cierre de Ventas. En Editar pedido, la tabla de detalles y faltantes de CEDIS muestra las piezas reportadas y el PDF o la imagen con que se pidió el anexo con detalle queda en Movimientos. Ventas confirma los detalles aceptados, retira o solicita sustituir las piezas pendientes antes de pedir otro anexo.
4. **Cliente/Vendedora.** Acepta composición; Vendedora confirma el importe comercial y cierra la consulta. Comparte comprobante(s) de transferencia al pagar.
5. **Auxiliar de Ventas.** Comprueba depósito e importe, aplica nota/saldo cuando proceda y tramita remisión Wizerp. **Administración** realiza posteriormente su propia revisión; no exige esperar esa revisión para preparar cuando el ingreso inicial ya quedó aprobado.
6. **CEDIS o PDV.** Aparta, prepara en **bolsas**, conserva identidad de remisión, evidencias y condición de piezas. Cuando la consulta se hizo en Preparación Tienda, el auxiliar prepara la composición aceptada y usa **Confirmar bolsas preparadas** para registrar también la separación de los anexos documentales ya resueltos, con responsable, fecha y evidencia. Si procede desde CEDIS, traslado lleva a sucursal de recolección.
7. **Auxiliar PDV → recepción/custodia.** Recibe físicamente, verifica bolsas y las entrega a custodia; queda ubicada la mercancía pagada. No se crea una segunda venta ni un nuevo cobro por custodiar.
8. **Cliente → sucursal.** Personal autorizado libera custodia, entrega y registra fotografía. Ventas da seguimiento a la recolección desde fecha de remisión conforme regla de 20 días; ver AC-01.

**Resultado válido:** `entregado con evidencia` **o** `en custodia identificada y pendiente de cliente`. No afirmar entrega si solo hubo traslado a PDV.

### T-F02. Apartado para pagar y recoger físicamente en PDV

**Entrada:** cliente quiere apartar una pieza disponible en la tienda y pagar al llegar; **referencias:** T-01.

1. **Auxiliar PDV** recibe solicitud (directa o canalizada), identifica cliente, producto y cantidad y confirma existencia **local en PDV**; CEDIS no participa en el apartado ordinario.
2. Responde detalles/fotos. Cliente acepta o mantiene decisión pendiente; no se sustituye unilateralmente.
3. Auxiliar aparta y señala vigencia: **lunes-viernes hasta 17:00; sábado entre 09:00 y 13:00**. Excepción VIP solo cuando gerente la autoriza.
4. Al acudir, cliente **paga y recoge**. Personal PDV confirma el cobro conforme a la operación local y registra evidencia de entrega.
5. Si vence **sin pagar**, liberar piezas salvo autorización VIP. **No aplicar** esta liberación automática a mercancía ya pagada.

### T-F03. Recolección de prioridad mismo día (TIENDA MD)

**Entrada:** solicitud de transferencia y recolección ese mismo día; **referencias:** T-02.1/AC-04.

- El inicio consulta, aceptación y validación de pago es el del T-F01, pero se marca **prioridad mismo día** con sucursal definida.
- Corte para reportar el pago: **13:00 de lunes a viernes**; **sábado no aplica** TIENDA MD. Los departamentos/usuarios autorizados deben depender de configuración, no de inventar una lista rígida (AC-04).
- CEDIS confirma disponibilidad, prepara bolsa y coordina traslado. PDV recibe y entrega el mismo día si las condiciones se cumplen.
- Si llega después del corte o no reúne condiciones, **no confirmar promesa de mismo día**. Registrar y comunicar que debe acordarse otra continuación; no cambiar automáticamente sin decisión.
- Si la pieza ya está en PDV, verificar el recorrido aplicable y evitar prometer TIENDA MD por mera proximidad.

### T-F04. Custodia y recolección en otra sucursal

**Referencias:** T-03/R-01.

- Antes del primer traslado, Ventas puede corregir la sucursal instruida y avisar a origen/destino, conservando pago, remisión y evidencia.
- Después del traslado inicial, mover de una sucursal a otra requiere **autorización de gerente**. La autorización **no equivale a traslado ni recepción**: origen prepara/sale, destino confirma entrada y custodia.
- Cliente **no recoge en CEDIS** por defecto; se le indica sucursal efectivamente confirmada.
- En entrega, custodio registra foto y libera identificación de custodia.

### Bifurcaciones del recorrido Tienda

| Disparador | Insertar comportamiento | Regla de regreso |
| --- | --- | --- |
| Falta una pieza, hay daño o disponibilidad parcial | **T-08:** respuesta por unidad, fotos, alternativa y decisión de cliente. | Reanudar T-F01/T-F02 solo con composición aceptada. |
| Cliente cambia o retira pieza **antes de pago/remisión** | **T-07:** evidencia, corrección de Wizerp y consulta nueva; liberar compromiso anterior. | Actualizar importe, respuesta y aceptación. |
| Cliente agrega piezas | **T-06:** consultar disponibilidad; si no hay remisión y aplica ventana operativa, ajustar el mismo pedido; con remisión, **nuevo pedido complementario vinculado y pagado**. | Solo integrar físicamente si aún es viable; no duplicar venta. |
| Cliente desea otra modalidad | **T-04:** revisar pago, remisión, ubicación, traslado y destino; sustituir **solicitud de entrega**, no borrar automáticamente la compra. | Continuar M-F01 o N-F01 con las piezas y pagos reconocidos. |
| Ya tiene compras pagadas bajo custodia para integrar a envío | **T-05/R-05/R-06:** retirar de custodia, salida PDV, llegada a CEDIS si es nacional, integración. | Esperar recepción física; cobrar solo conceptos nuevos. |
| Cliente paga con crédito | **T-09/AC-03:** Auxiliar consulta Wizerp; aprobado → remisión; bloqueado → espera y seguimiento. | No transferencia de sustitución; cancelación al vencimiento según AC-03 con guarda de inventario/pagos. |
| Cumple 20 días sin recoger pedido pagado | **R-01/AC-01:** seguimiento y procedimiento de **reintegro al inventario** tras vencer plazo; conservar trazabilidad y tratar por separado el dinero. | No equiparar reintegro físico con reembolso o extinción de saldo. |

## 5. Recorrido principal M — Envío municipal a terminal

### M-F01. Municipal ordinario (no entrega a domicilio)

**Entrada:** cliente pide entrega a terminal de su municipio. **No confundir con DHL/Estafeta/FedEx de entrega nacional.** **Referencias:** M-01/M-06.

1. **Cliente → Vendedora.** Indica pedido, municipio, terminal, destinatario/consignatario, teléfono, condiciones y documentación municipal requerida. Vendedora crea pedido Wizerp sin pago; sube PDF/capturas a GELIA.
2. **Vendedora → CEDIS.** Solicita disponibilidad con destino municipal; CEDIS responde cantidades, detalles y fotografías. Vendedora resuelve faltantes, anexos y aceptación comercial con el cliente; no remite todavía una compra discutida.
3. **Vendedora.** Identifica **terminal, carátula, datos de quien recoge, INE y condición PAGADO/POR COBRAR**; confirma si flete se conoce en ese momento o se obtendrá al llegar a terminal (Jaguar). El estado de pago ante terminal no prueba el pago del cliente.
4. **Cliente/Vendedora → Auxiliar.** Reporta comprobantes y remisión a emitir, o crédito que Wizerp autorice. Auxiliar verifica ingreso/alcance de mercancía y conceptos conocidos; no inventa costo desconocido.
5. **CEDIS.** Empaca con referencias y carátula (elige con identificación o sin ella); en el mismo registro de evidencia indica caja o bolsa por bulto. Realiza traslado hacia PDV en la rutina documentada. **Ese movimiento interno no es salida a terminal ni entrega al cliente.**
6. **PDV / gerencia PDV.** Recibe/organiza paquetes, registra salida municipal cuando el repartidor recoge para llevar a terminal; este evento delimita ciertos cambios de modalidad.
7. **Repartidor → terminal.** Entrega y conserva fotografía/comprobante del evento; comparte costo final si la terminal lo determina después.
8. **Vendedora.** Comunica ficha/recogida; si el transporte se cobra después, ejecutar M-F02/P-01. No llamar `entregado al cliente` a la mera foto de terminal.

**Resultado válido:** mercancía enviada a terminal con comprobante y condición de cobro explícita, o una espera identificada; el cliente debe poder conocer cómo recoger.

Si el flujo usa **Empaque y salida municipal** sobre un cumplimiento agrupado, el auxiliar registra cuántas cajas y bolsas preparó. GELIA calcula el total de bultos y conserva la evidencia y la confirmación de despacho en sus acciones existentes. Comisiones recibe ese desglose y el de los bultos individuales. Los empaques históricos que solo tienen un total siguen en revisión.

### M-F02. Terminal con costo conocido después: Jaguar

**Referencias:** M-05/P-01. Es una bifurcación del paso 3 y del paso 7 del M-F01, **no otra venta**.

1. Vendedora informa que el **flete no se conoce antes de terminal**. Cliente paga mercancía; Auxiliar verifica y remisiona mercancía, sin dar por pagado un flete sin importe.
2. CEDIS → PDV → repartidor → terminal según M-F01. Cajera PDV y repartidor resuelven el efectivo ante terminal en **operación de caja separada**.
3. Repartidor comparte evidencia del costo comunicado por terminal; se identifica paquete, cliente y remisión.
4. Vendedora solicita exactamente el importe al cliente; cliente paga y envía comprobante.
5. Vendedora reporta **PAGO ENVÍO** ligado a remisión; Auxiliar verifica ingreso y Administración contempla el registro posterior; no se duplica cobro de mercancía.
6. Vendedora comparte ficha después del pago según el procedimiento Jaguar. Si no paga, mantener **cobro pendiente**, sin inventar sanción/plazo (AC-08).

### M-F03. Espera municipal: fecha de envío o acumulación

Son dos *motivos distintos* de mantener mercancía en resguardo, no dos modalidades diferentes.

- **Fecha posterior, mercancía y envío pagados** (**M-09/R-02**): pedido con remisión y cargos cubiertos; Ventas comunica fecha y orden de no despachar; CEDIS/PDV lo identifica **azul**. En la fecha pertinente **Ventas instruye liberar** tras decisión del cliente; cambia a condición de preparar, no automáticamente por el reloj.
- **Acumulación de compras** (**M-10/R-03**): cliente paga mercancía de distintas remisiones y seguirá comprando. CEDIS/PDV conserva pedidos comprometidos con sus identidades; cajas `N/A` mientras no corresponde definir número físico. Ventas reúne remisiones definitivas y autoriza consolidación; área confirma paquetes reales, luego se determina/cobra flete y se libera.
- Una ubicación de complemento no se vuelve “recibida” porque exista el folio. La custodia y los traslados deben registrar origen y destino.

### M-F04. Municipal con mercancía en un PDV: se empaca en Tienda y concluye ahí

**Entrada:** Ventas consultó disponibilidad a un almacén de tipo PDV (el sistema lo clasifica como consulta de **Tienda**) y el Auxiliar aprobó el pedido con pago validado y remisión. **Decisión del 23 de noviembre de 2026.**

1. **Ventas** cierra la consulta con la sucursal PDV; **Auxiliar** aprueba. El pedido **no se envía a la bandeja de CEDIS** ni se traslada desde CEDIS: la mercancía ya está en el PDV consultado.
2. **Tienda (PDV consultado).** El pedido aparece en **Preparación Tienda** como fila «Aprobado: por empacar» del almacén consultado, para quien tiene la facultad de responder consultas municipales en esa sucursal (o administración). **Ver** muestra consignatario, remisión y el resto del pedido; el folio no empaca.
3. **Tienda** empaca físicamente y pulsa **Marcar empacado** (con confirmación). Queda el registro de quién y cuándo en el historial del pedido.
4. **Fin del flujo en Tienda.** Marcar empacado **concluye** este recorrido: no hay traslado, recepción, custodia ni guía. La fila **sale de Pendientes** y queda en **Atendidos** con Ver. El pedido conserva estatus «Pendiente de recolección o envío» para Ventas.

**No hace:** no crea tarea de preparación (no reabre la consulta), no registra salida a terminal ni entrega al cliente; esos hechos, si se requieren, se registran por su propio camino. Los municipales cuyo almacén consultado es CEDIS siguen M-F01 sin cambios.

### Bifurcaciones del recorrido Municipal

| Disparador | Insertar comportamiento | Regla de regreso |
| --- | --- | --- |
| Pedido que estaba destinado a Tienda ahora irá a terminal | **M-02/T-04:** preservar pedido, remisión y pagos; según ubicación CEDIS o PDV adapta empaque y custodia. | Completar datos municipales y cargos pendientes de M-F01. |
| Nuevo anexo de piezas | **M-03:** sin remisión y vigente → ajustar; con remisión → complemento pagado relacionado; si ya salió a terminal → otro envío. | Reconfirmar composición y empaque; no penalizar personal si demora documentada por solicitud de cliente. |
| Compras pagadas en PDV se unen a envío municipal nuevo | **M-04/T-05:** retirar custodia, transportar si corresponde, confirmar recepción e integrar; mantener remisiones. | Cobrar solo mercancía nueva + envío pendiente. |
| Recolección en Tienda en vez de terminal | **M-07:** solo antes de recogida para terminal; cancelar instrucción municipal, conservar compra y coordinar custodia. | S-06: saldo restringido a envío si procede; después de salida, rechazar cambio ordinario. |
| Crédito | **M-08:** validar en Wizerp; envío de costo posterior puede cargarse a crédito después conforme AC-14. | Sin aprobación, no remisión ni salida; no mezclar métodos. |
| Nota de crédito + transferencia de diferencia | **M-11/S-04:** remisión con nota válida y comprobante del resto. | No registrar como mezcla prohibida de crédito comercial con transferencia. |
| Varias transferencias para un pedido | **M-12:** evidencias de pagos **distintos**, misma compra, suma validada. | No duplicar remisión ni interpretar como pago doble S-07. |

## 6. Recorrido principal N — Envíos Nacionales 2.0 desde CEDIS

### N-F01. Nacional ordinario: de solicitud inicial a despacho

**Entrada:** cliente recibirá a domicilio en otro estado, con empresa comercial o paquetería aún por decidir; **referencia principal:** N-01. **No pasar por terminal ni simular entrega en Tienda.**

**Inicio de captura / Vendedora (antes de CEDIS):**

1. Cliente comunica piezas, destino/destinatario, paquetería si la conoce y seguro si lo desea. Vendedora genera el pedido **sin pago en Wizerp** y obtiene PDF/capturas.
2. GELIA registra cliente, departamento, folio Wizerp, **modalidad Nacional**, **paquetería elegida o `PAQ. PENDIENTE`**, **importe preliminar de mercancía** y documento. La paquetería y el importe pueden mostrarse **juntos en la captura**, pero el importe no representa cobro verificado. No exigir primero la lista de SKU: ofrecer identificación por SKU/capturas **como precisión opcional**.
3. Vendedora envía **consulta nacional a CEDIS**. La instrucción contiene pedido/documentos, ubicación, piezas reconocibles y solicitud de **disponibilidad, evidencia y pesaje**. La falta de paquetería definitiva **no cancela la consulta** ni autoriza omitir peso.

**Consulta / CEDIS y cliente:**

4. CEDIS abre la solicitud en su bandeja y responde el conjunto: **Todo OK** o solo las piezas afectadas (detalle o faltante), sin recapturar el resto. Después registra **empaque, número de cajas, medidas, peso real y peso a cobrar por caja**; ejemplo documentado de redondeo: 3.8 kg → 4 kg. No inventar por este ejemplo una nueva regla de peso volumétrico.
5. CEDIS responde a Ventas. **No cierra por sí solo la consulta** ni marca pagada/remisionada la compra.
6. Vendedora comunica respuesta al cliente. Si el cliente cambia piezas, anexa, retira o necesita otra foto, ejecuta la variante N-06/T-08 pertinente; CEDIS revalida el conjunto y pesaje afectados. El proceso se repite cuanto sea necesario.
7. Cuando cliente acepta, **Ventas cierra consulta** y fija la composición/pesaje vigente para cotizar. No cerrar si hay revisión material pendiente o una respuesta obsoleta.

**Cierre de importes y documentación:**

8. Vendedora verifica o registra **domicilio y destinatario**: dirección existente, nueva (N-07), adicional (N-09) o corrección (N-08); no suponer que comprador y receptor son la misma persona. Si el cliente no tiene dirección principal ni adicional, el modal de cotización lo avisa y ofrece **Agregar dirección** o **Enviar enlace al cliente** (formulario público, que registra una principal o una adicional sin exigir primero la principal); **Actualizar direcciones** recarga la lista sin reabrir el pedido.
9. Con **paquetería definida** y cajas/peso de CEDIS, establece **tipo de guía**, **reexpedición separada** y **seguro opcional** con la fórmula ya vigente del servidor. La paquetería del alta (DHL, FedEx, Estafeta) **no se recaptura**; el selector posterior solo aparece si el alta fue **PAQ. PENDIENTE**. El número de cajas del pesaje se muestra como **No. de caja**. En **Envío (MXN)** la Vendedora captura el flete real de la paquetería, por caja si hay varias. El conteo se usa solo como referencia. La cotización separa **Dirección de entrega** y **Costo de envío**; el seguro nace **apagado**, y antes de guardar se ve el desglose (mercancía, envío, reexpedición, seguro y total) calculado por el servidor: Estafeta y FedEx 2.5 % y DHL 2 % + $51 sobre mercancía + flete (p. ej. $1,000 de mercancía y $0 de flete → DHL $71.00). Las paqueterías sin cobertura no ofrecen seguro. No se calculan nuevos porcentajes por inferencia; no se reparte arbitrariamente el cargo fijo de una fórmula de seguro.
10. Cliente acepta importe. Vendedora reporta **pago de mercancía, transporte y conceptos aplicables** o señala crédito; Auxiliar verifica ingreso y documentos, tramita remisión cuando corresponde. Una compra ya pagada no vuelve a cobrarse al añadir solo flete. Mientras el pago esté pendiente de revisión, Ventas puede guardar el envío faltante desde **Revisar cotización**. Al guardar, el total exigible incluye el envío y las exhibiciones mantienen su estado de revisión.
11. Vendedora/Auxiliar comparten con **Encargado/Delegado de guías**: domicilio y destinatario vigentes, paquetería, cajas, peso, tipo de guía, valores/cobertura y pagos válidos. Delegado verifica, genera/adjunta **guía PDF de la caja correcta**, conserva histórico de guías sustituidas y comparte la vigente con CEDIS.
12. Mientras se genera guía, CEDIS puede preparar empaque según su estado autorizado, pero **no despacha sin guía válida ni sin cobertura financiera**. Coloca guía por caja y registra entrega efectiva de cada paquete a paquetería/repartidor con evidencia/referencia.
13. El resultado confirmado en GELIA es **despachado desde CEDIS** con documentación y referencias; no inferir que llegó al domicilio final por la sola evidencia de recolección del transportista.

**Condiciones de interrupción legítima:** respuesta del cliente pendiente, peso obsoleto, paquetería pendiente, complemento en tránsito, crédito bloqueado, guía inválida, caja sin peso, cobro no cubierto o pedido/parte del conjunto bajo resguardo. Son **esperas del mismo proceso**, no nuevos flujos obligatorios.

### N-F02. Paquetería pendiente / mercancía pagada en espera

**Referencias:** N-04/R-04. El cliente quiere conocer peso o comprar mercancía antes de elegir empresa.

1. Vendedora registra **`PAQ. PENDIENTE` desde el alta**, importe preliminar y documento, solicita consulta a CEDIS.
2. CEDIS responde disponibilidad y **peso/cajas** igual que el nacional ordinario. Ventas resuelve piezas y cierra consulta comercial cuando cliente decidió composición.
3. No hay flete definitivo, seguro/cobertura definitiva ni guía mientras la empresa siga pendiente; el costo desconocido **no se sustituye por cero pagado**.
4. Si cliente paga **solo mercancía**, Auxiliar verifica y emite la remisión por ese alcance. CEDIS conserva piezas pagadas e identificadas en resguardo sin despachar.
5. Cliente elige después DHL/Estafeta/FedEx u otra empresa logística válida; Ventas comunica selección, revisa tarifa, reexpedición y seguro, solicita **solo lo pendiente**. Auxiliar confirma cobertura, Delegado genera guía y CEDIS libera/empaca/sale.

**Resultado válido:** mercancía pagada y **retenida legalmente** a falta de elección, o envío final correctamente cubierto. **Prohibido:** salida o guía por “PAQ. PENDIENTE”, o tratar flete indefinido como pagado.

### N-F03. Consolidar compras, remisiones y cajas

**Referencias:** N-11/N-12/N-13/T-05/R-05/R-06.

1. Cliente indica al nuevo pedido nacional qué compras anteriores pagadas/remisiones o resguardos desea incorporar. Ventas verifica cliente, identidad, referencia y **ubicación física**; esos importes ya pagados no se recapturan como mercancía nueva.
2. Si las piezas están en PDV/custodia, se ordena **retiro autorizado**, se prepara traslado y se registra salida del origen. CEDIS confirma **recepción**, revisa referencias/contenido y finalmente **integra**; mientras falten piezas, el principal sigue esperando.
3. CEDIS determina número real de cajas, dimensiones, pesos y evidencias. Aunque exista una sola compra, **dos cajas son dos unidades físicas** con su guía/costo cuando corresponda.
4. **SIN seguro (N-12):** se permite distribuir físicamente mercancía de una o varias compras en varias cajas sin el detalle obligatorio de remisión completa por caja; se cobra envío del conjunto conforme cotización y se mantienen referencias de remisiones ya pagadas.
5. **CON seguro (N-11/N-03):** CEDIS identifica **qué remisiones completas están en cada caja**, evidencia de contenido, valor asegurado y peso; **seguro por caja** conforme fórmula vigente. No fraccionar parte de una misma remisión entre cajas por conveniencia ni asegurar mercancía sin identidad física verificable.
6. **Caso difícil (AC-12):** si una remisión físicamente no cabe completa en una caja, la aclaración posterior insiste en *no partir una remisión*, incluso si exige propio envío. La forma documental de resolver un único pedido enorme no quedó plenamente demostrada. **Detener despacho asegurado y escalar para solución documental autorizada; no partir ni inventar remisiones automáticamente.** Sin seguro se permite el reparto N-13/N-12.
7. Auxiliar valida **únicamente conceptos pendientes**, Delegado documenta guía por caja, CEDIS despacha conforme a contenido declarado. Una recolección de una caja no cierra las restantes.

### N-F04. Complementar un nacional que ya se consultó

**Referencias:** N-06/R-05/R-06.

- **Antes de remisión y sin pago validado:** mismo pedido, nueva versión de piezas y documento vigente; CEDIS recibe solicitud de ajuste y vuelve a responder existencia/empaque/peso; Ventas cierra sobre versión vigente.
- **Con remisión o pago validado, antes de salida:** generar **pedido/proceso complementario relacionado** con piezas reales, cantidades, evidencia, importe nuevo y **pago propio verificado**. No mutar silenciosamente remisión del principal ni cobrar mercancía ya pagada. CEDIS confirma si físicamente puede integrarlo y revisa cajas/guías; mientras falte pago o recepción, **espera**.
- **Principal ya entregado a transporte:** el anexo **no se incorpora** a esa salida. Crear **envío nuevo** con su flujo y cargos.
- **Complemento en otro almacén:** vendedora identifica origen/remisión, Auxiliar valida pago, PDV prepara/sale, CEDIS recibe y confirma integración. La salida de PDV no levanta la espera por sí sola.

### Bifurcaciones del recorrido Nacional

| Disparador | Acción que debe existir | Barrera / regreso |
| --- | --- | --- |
| Zona extendida | **N-02:** registrar reexpedición de fuente paquetería como concepto separado de flete y seguro; informar, cobrar diferencia. | No restarla dos veces ni meterla automáticamente a base de seguro. |
| Cliente solicita seguro | **N-03:** valor, cajas, contenido y seguro por caja si hay varias; es opcional. | Si cambia distribución o paquetería, revisar cobertura. |
| Cambio de paquetería | **N-05:** antes de entrega a transporte, conservar remisión, registrar motivo/evidencia, recotizar, invalidar guía y revisar cargos/saldo. | **Después de salida: prohibido** mutar paquetería ordinaria. |
| Primera dirección / dirección adicional | **N-07/N-09:** alta completa y destinatario distinto, elegir explícitamente dirección del pedido. | No sobreescribir otras direcciones sin decisión. |
| Dirección corregida y guía emitida | **N-08:** antes de salida invalidar guía, conservar PDF histórico, gestionar cancelación ante empresa y emitir otra. | Después de salida: canalizar con Rafael; solicitud no garantiza corrección. |
| Cliente prefiere recoger en Tienda | **N-10:** antes de salida solicitar recolección, cancelar instrucción de transporte no usada, conservar pedido/remisión/pagos, PDV recibe→custodia→entrega con foto. | Ventas **no confirma recepción/custodia/entrega PDV**; gastos de guía y saldo de transporte requieren resolución. |
| Pedido grande de una sola compra | **N-13:** CEDIS responde cada caja, sin suponer caja única; con seguro aplicar restricción N-11/AC-12. | Sin seguro puede dividirse físicamente; con seguro no fraccionar remisión sin instrucción válida. |
| Cancelación / error / falta de pago | **N-14:** registrar causa y evidencias, avisar CEDIS, revisar ubicación, pago, guía y remisión, gestionar liberación o saldo/reembolso. | Tras salida física no prometer recuperación ni cancelación consumada. |
| Crédito comercial | **N-15:** Auxiliar valida Wizerp; aprobado→remisión; bloqueado→comunicación y espera. | No mezclar transferencia; no guía/salida con bloqueo. AC-03 aplica vencimiento. |


## 7. Operaciones transversales: se insertan cuando corresponden

### 7.1 Consulta de disponibilidad, fotos, SKU y anexos

- **Documento general primero:** el PDF/capturas del pedido comercial son fuente de solicitud. Si identifica piezas, Ventas puede consultar sin volver a transcribir manualmente toda la orden. Si una pieza necesita precisión, se añade por **SKU de catálogo o imagen/captura/documento**. Ambos identificadores pueden convivir en un mismo pedido.
- **Cada pieza conserva identidad:** cantidad solicitada, encontrada, condición, comentario, evidencia individual y estado de respuesta. Una captura no absorbe otra ni se responde en conjunto a falta de trazabilidad. No se inventan SKU para imágenes.
- **Número de pieza en Tienda:** Cambios del pedido y Movimientos muestran el mismo número que la lista de disponibilidad de Editar pedido y Preparación Tienda. El vínculo es la revisión de la pieza y también se aplica al consultar movimientos ya guardados cuando su revisión sigue en la respuesta.
- **Sustitución en Tienda, Municipal y Nacional:** el área responsable ve juntas la pieza a sustituir y la alternativa solicitada, con SKU o foto disponible. En Preparación Tienda la fila original indica la solicitud de sustitución. En Nacional, el repesaje conserva el nombre que Ventas vio al elegir la pieza.
- **Faltante o detalle:** almacén reporta cantidad real, foto y observación; Ventas consulta al cliente. No aceptar por silencio, no liberar por error una pieza todavía comprometida.
- **Anexo antes de remisión:** misma solicitud comercial actualizada y versionada, si cumple condiciones. En modalidad Tienda, tanto en consulta CEDIS como en Preparación Tienda, Ventas resuelve las piezas ya reportadas antes de pedir más: confirma el detalle acordado con el cliente, retira o solicita sustitución. Quien prepara atiende las unidades abiertas y conserva las respuestas enviadas. La composición nueva necesita respuesta del área física y pesaje nacional actualizado cuando aplique.
- **Anexo con remisión:** nuevo pedido/proceso complementario **relacionado**, con pago verificado; no alterar el documento cerrado ni incorporar impagado al envío.
- **Retiro:** registrar pieza y motivo, conservar evidencia, instruir al almacén y reflejar compromisos físicos. Si ya hubo pago/remisión, separar decisión comercial/financiera de la instrucción física; no reutilizar la edición sencilla de preventa.
- **Concurrencia:** misma solicitud no duplica; versión obsoleta debe recuperarse/revisarse, no sobrescribir decisión de otra persona.

### 7.2 Pago, remisión y método de crédito

**Pago por transferencia:** vendedora reporta banco, referencia, monto y comprobante(s); Auxiliar verifica ingreso real, compara con total aplicable y remisiona. La suma de exhibiciones verificadas puede cubrir una operación. Comprobante repetido no genera segundo ingreso.

**Crédito comercial:** el método se declara y Auxiliar confirma en Wizerp si permite remisión. La compra puede incluir mercancía y conceptos de envío conocidos; **no mezclar con transferencia para evadir bloqueo**. Si el sistema bloquea, comunicar regularización y sostener espera. **AC-03 respondida:** si sigue bloqueado o no reporta remisión tras tolerancia hasta el día siguiente, proceder a cancelación por evento programado o marca controlada, con aviso y tratamiento de apartado; **no asumir que el cron ya existe** ni liberar automáticamente piezas pagadas.

**Nota de crédito y diferencia:** se presenta nota válida del importe de mercancía más comprobante por faltante, según M-11/S-04. No confundir con mezclar **crédito comercial** y transferencia. Registro del consumo/remanente y autorización de saldo cuando aplique.

**Remisión:** la emite/tramita Auxiliar en Wizerp tras controles; número de folio de **pedido** inicial ≠ **remisión**. Remisión define el corte de ciertas modificaciones; su existencia no acredita salida física ni recepción en otra ubicación.

**Administración:** revisión bancaria posterior y autorizaciones de saldo/reembolso son otra capa. Si detecta retorno de pago después de salida/entrega, el tratamiento sigue requiriendo decisión específica; no deshacer físicamente entregas por algoritmo.

### 7.3 Saldos a favor y devoluciones (S-01 a S-09)

| Situación | Operación y responsable | Restricción clave |
| --- | --- | --- |
| **S-01:** excedente de depósito | Auxiliar comprueba exceso real; Administración autoriza saldo; Ventas comunica y lo deja disponible para **compra posterior**. | **AC-02:** mínimo **$10 inclusive**. No aplicarlo al pedido que originó el excedente. |
| **S-02:** pieza recién pagada retirada | Ventas consulta viabilidad física; Administración/Auxiliar resuelven documentos, importe y saldo/reembolso. | No retirar sin estado de salida y sin evidencia; saldo no cancela por sí mismo remisión. |
| **S-03:** depósito sin pedido | Verificar ingreso e identidad y resolver saldo/devolución con Administración. | **No crear pedido ficticio** ni fecha de remisión inventada. |
| **S-04:** devolución y nota de crédito | Devolución aceptada por responsable; nota documental, uso posterior en mercancía y remanente. | Aceptación física de devolución tiene su propio procedimiento; no equivale a saldo de envío. |
| **S-05:** saldo originado en PDV | Verificar registro PDV/Drive, autorización, tipo, disponible, consumo y remanente. | No aceptar saldo por palabra del cliente ni consumirlo dos veces. |
| **S-06:** envío pagado que cambia a recolección | Administración determina transporte no utilizado y autoriza saldo. | Solo **envío**, no mercancía; gastos ya consumidos pendientes de resolución. |
| **S-07:** depósito duplicado por error | Auxiliar comprueba **dos ingresos reales**, aplica uno a pedido y trata segundo como excedente/saldo/devolución. | No confundir con exhibiciones voluntarias M-12 ni duplicar envío. |
| **S-08:** cobro excesivo por error humano | Identificar responsable real, monto correcto y diferencia; Administración resuelve. | No atribuir al cliente un error de la vendedora y no añadir productos sin consentimiento. |
| **S-09:** flete final menor / cambio de paquetería | Cotejar importe pagado y costo real, registrar diferencia favorable con origen y autorización. | Solo transporte; si aumenta, es cargo pendiente, no saldo negativo. |

**Regla AC-13 respondida:** el saldo se genera cuando se **registra y verifica** el pago; se usa en el **siguiente pedido**, no en el que lo originó. Saldo de mercancía solo para mercancía; saldo de envío solo para envío. V02.1 también menciona **20 días** para utilización; el efecto contable exacto de todos los orígenes y reembolsos no se resuelve automáticamente aquí. No eliminar registros ni mover dinero por vencimiento sin procedimiento autorizado.

**Umbral AC-02:** se eligió posteriormente “**a partir de $10**”, corrigiendo la narración antigua que decía “más de $10”. No extrapolar el mínimo a toda diferencia de envío sin confirmar la categoría correspondiente; el expediente debe distinguir origen/uso.

**Reembolsos:** Administración autoriza; solicitud, aprobación y devolución efectuada son eventos distintos. Para cancelación se informó un plazo de **3 a 5 días hábiles**; otros casos mencionan 3 a 5 días sin el mismo cómputo, de modo que no se promete universalmente el plazo de cancelación.

### 7.4 Resguardo y custodia: un motivo de espera, no otra venta

| Motivo / ficha | Donde se encuentra | Quién libera y bajo qué hecho |
| --- | --- | --- |
| **R-01** Tienda pagada | Bolsas verificadas en PDV/custodia. | Cliente llega y custodio entrega con foto; si vence 20 días **AC-01 dispone reintegración al inventario**, registrando físicamente la liberación y conservando el tratamiento financiero pendiente. |
| **R-02** Municipal pago+envío cubierto, fecha futura | CEDIS/PDV, identificación azul. | Ventas comunica instrucción por decisión de cliente; no solo porque llegó fecha; luego preparación (amarillo). |
| **R-03** Municipal acumulación | Pedidos/remisiones pagadas separados, cajas N/A. | Ventas reúne lista final; área física confirma conjunto y cajas reales; flete pendiente se cubre según operador. |
| **R-04** Nacional sin paquetería | Mercancía pagada/identificada en **CEDIS**. | Cliente decide empresa, Ventas comunica y se cubren conceptos pendientes; Delegado guía, CEDIS sale. |
| **R-05** Nacional principal espera complemento | Principal retenido en CEDIS, con relación a origen externo. | No liberar por promesa; esperar recepción e integración confirmada. |
| **R-06** Complemento en traslado | Origen PDV/otra ubicación → CEDIS. | Origen registra salida, **CEDIS registra recepción y finalmente integración**; entonces se actualiza empaque y peso. |

**Azul / amarillo:** son convenciones operativas de *espera* y *puede preparar* donde las fuentes las utilizan. No implican automáticamente que el pago está completo, que el paquete está en tránsito o que ya salió. **N/A = no aplica**; no equivale a 0 bultos.

**AC-01 respondida:** al vencimiento de **20 días naturales desde remisión** en custodia pagada de Tienda, la mercancía **se libera/reintegra a inventario** y puede volver a venta. La aclaración **no describe automáticamente qué sucede con el dinero**; registrar disposición física sin inventar reembolso, saldo o confiscación. Esto reemplaza la frase anterior que dejaba sin resolución el destino físico.

### 7.5 Cambio de modalidad: sustituir instrucción, no perder la venta

**Se comprueba:** pedido, evidencia del cliente o error propio, estado de pago, remisión, ubicación, custodia, caja recolectada/salida y cargos. Gerencia/Administración intervienen donde corresponde.

- **Tienda → Municipal (T-04/M-02):** nuevo destino/terminal y carátula, con empaque desde ubicación física real; retira de custodia si ya está en PDV; conserva pago/remisión reconocidos, cobra solo transporte faltante.
- **Tienda → Nacional (T-04/N-01):** nueva solicitud relacionada, con origen **CEDIS** obligatorio. Si mercancía está en PDV/custodia, exigir retiro, salida de PDV y recepción en CEDIS antes de preparar nacional.
- **Municipal → Tienda (M-07):** solo antes de que repartidor recoja hacia terminal. Cambia orden de entrega y custodia; si envío ya fue pagado, saldo **exclusivo de envío** autorizado por Administración.
- **Nacional → Tienda (N-10):** Editar pedido del Panel de Vendedora omite el acceso para iniciar la solicitud y su resumen. Para solicitudes ya registradas se conserva la instrucción de no despachar por paquetería, el tratamiento de la guía emitida y el traslado a sucursal, con recepción, custodia y entrega registradas por PDV. El aviso de cancelación de guía se conserva mientras esté pendiente. Después de salida, la excepción se gestiona con Rafael.
- **Entrar a Nacional desde otra modalidad:** capacidad funcional descrita por T-04; **no activarla automáticamente** solo porque exista una política técnica deshabilitada. Requiere diseño y autorización específica del cambio, preservando el origen CEDIS.

### 7.6 Cambios, cancelaciones y guías

- **Antes de remisión y pago:** la vendedora puede reconsultar y actualizar comercialmente el mismo pedido, sin perder evidencia/versionado.
- **Después de remisión:** un anexo no se añade informalmente al documento emitido; crear complemento pagado relacionado si antes de salida y físicamente posible.
- **Después de entrega al transporte nacional:** paquetería no se cambia; dirección/cancelación se **solicita** por gestión excepcional con Rafael y su resultado puede seguir pendiente.
- **Cambio de dirección con guía y sin salida:** invalidar vigente, conservar PDF/histórico y rastro, requerir guía nueva y avisar a CEDIS para reemplazar etiqueta; registrar quién causó el gasto.
- **Cancelación sin pago y sin salida:** notificar área física y liberar la reserva según su procedimiento. **Cancelación con pago:** involucrar Auxiliar/Administración para saldo/devolución, sin borrar remisión. **Con guía:** registrar gasto/estado de cancelación ante empresa separadamente; **con caja recolectada:** no editar ni devolver automáticamente.
- **AC-07 respondida:** el motivo puede ser cambio solicitado por cliente o error de vendedora/encargado de guías; la evidencia y responsabilidad se registran. Si aplica diferencial por nuevo destino se cobra o reconoce saldo conforme a revisión. **No hay regla universal que determine automáticamente quién absorbe la guía ya consumida.**

### 7.7 Cajas, guías, seguros y costos

- CEDIS determina **número real de cajas**, dimensiones y pesos; la paquetería utiliza los datos vigentes para cotizar. Cada caja conserva identidad, estado, evidencia y guía.
- **Con seguro:** asociar **remisiones completas**, contenido, valor asegurado, fotos y prima a cajas; cambios materiales invalidan la versión anterior de cálculo/documentación.
- **Sin seguro:** puede haber varias cajas sin exigir el mismo detalle de distribución de remisiones, aunque sí se preservan remisiones que componen el envío.
- El **importe de mercancía** no equivale a flete. Reexpedición es concepto independiente; el seguro depende de regla existente y proveedor. **AC-09:** las fórmulas de paquetería logística ya existen en GELIA y deben reutilizarse, sin recrearse en cliente.
- Si la caja fue recolectada, su guía o contenido no puede sustituirse como si siguiera en preparación. Si solo algunas cajas salieron, el pedido conserva pendientes por caja.
- **Guía de empresa vs guía del cliente:** preservar la alternativa que admita el contrato vigente; cualquier guía aportada debe estar asociada a caja/dirección/estado, y la entrega a transportista exige guía válida según el proceso aplicable.

## 8. Decisiones AC-01 a AC-15: lectura **vigente** de las respuestas V02.1

Estas aclaraciones están respondidas en el archivo `.md` de V02.1. Se presentan aquí para impedir que un agente use la ficha narrativa previa como última versión. **Estado “respondida” no significa que el código ya cumple la decisión.**

| AC | Decisión vigente de operación | Precisión y límite que no se inventa |
| --- | --- | --- |
| **01** | Tras 20 días naturales desde remisión para recolección en Tienda, **liberar/reintegrar mercancía al inventario**. | No se especificó destino automático del dinero pagado. |
| **02** | Mínimo de excedente **$10 inclusive**. | No generalizar automáticamente a cualquier categoría de saldo de transporte. |
| **03** | Crédito que sigue bloqueado/sin remisión después de tolerancia hasta día siguiente **procede a cancelación**, por tarea programada o marca controlada. | No automatizar con disparador/hora inventados ni suponer cron existente; conservar avisos, pagos y estado físico. |
| **04** | TIENDA MD se habilita por **departamento(s) o usuario(s) configurables**. | No inventar lista exacta; corte 13:00 lunes-viernes; sin sábado. |
| **05** | Con remisión y modificación extraordinaria, exigir evidencia y, cuando corresponda, **nuevo pedido complementario**. | No reescribir remisión ni elegir automáticamente observación/cancelación contable sin regla. |
| **06** | El anexo debe pagarse; los de Tienda día siguiente solo se incorporan cuando su finalidad es entregar conjunto en sucursal conforme a la respuesta. | No usar anexo como atajo universal después de remisión o salida. |
| **07** | Identificar quién causó cambio de guía/dirección, con evidencia; resolver diferencias de cobro/saldo. | Gastos de guía consumida no tienen un responsable pagador universal. |
| **08** | Jaguar impagado queda como caso por comprobar; garantizar flujo del pedido y registrar pendiente. | No fabricar sanción, plazo de cobro ni sistema de caja PDV nuevo. |
| **09** | Reutilizar **fórmulas de GELIA** para tarifas/seguros logísticos. | No cambiar porcentajes ni introducir cálculo duplicado en frontend. |
| **10** | Integrar evidencia de CEDIS/PDV a custodia; PDV no necesita registrar manualmente otra vez todo el resguardo recibido. | Traslado/recepción/entrega siguen siendo eventos distintos y con actor propio. |
| **11** | **Vendedora comunica la liberación** tras decisión del cliente. | No liberar por fecha o saldo automáticamente salvo regla expresa. |
| **12** | Remisiones **no se dividen** entre cajas aseguradas; si no caben, se requiere documentación/envío apropiado y decisión de distribución. | El texto responde “propio envío” y simultáneamente insiste en no partir remisión; ante imposibilidad física, bloquear seguro y resolver con responsable, no suponer una división autorizada. |
| **13** | Saldo se genera tras registro/verificación de pago; se usa en **siguiente pedido**, separado en mercancía o envío. | El plazo de 20 días y consecuencias particulares requieren aplicación según origen, sin borrado automático. |
| **14** | Un costo de envío conocido **después** puede cargarse más tarde al crédito, registrando total final. | Requiere aprobación/registro de Wizerp, no se presume aprobado de inicio. |
| **15** | GELIA contempla campos de dirección nacional; **“lleva nota”** significa decisión de incluir nota impresa para *dropshipping*. | No asumir que todas las direcciones llevan nota ni generar una por defecto. |


## 9. Matriz de trazabilidad: **53 fichas** de V02.1 → un recorrido o bifurcación de V3.1

**Leer “rama” como una capacidad disponible cuando ocurre la condición, no como un nuevo pedido o pantalla que debe abrirse en todas las ventas.** En todos los casos, la prueba de una acción debe mostrar quién la inicia y cómo la recibe el área siguiente.

| Ficha V02.1 | Inserción V3.1 | Carácter | Disparador / prueba operacional mínima |
| --- | --- | --- | --- |
| **T-01** | T-F02 | Principal | Apartado PDV para pagar en tienda, con vigencia y entrega. |
| **T-02** | T-F01 | Principal | Transferencia, preparación en bolsas, recepción PDV, custodia, entrega. |
| **T-02.1** | T-F03 | Variante | Recolección mismo día con corte/permiso configurable. |
| **T-03** | T-F04 | Variante | City Center o segundo traslado autorizado; recepción distinta de aprobación. |
| **T-04** | §7.5 | Excepción de modalidad | Convertir sin duplicar mercancía/pagos; nueva solicitud relacionada. |
| **T-05** | §7.4/§7.5 y N-F03 | Integración | Retirar pagado de custodia; CEDIS recibe y une si sale nacional. |
| **T-06** | §7.1 y T-F01 | Anexo | Consulta y complemento según remisión y viabilidad. |
| **T-07** | §7.1 y T-F01 | Corrección | Retirar/sustituir antes de pago; actualizar compromiso físico y versión. |
| **T-08** | §7.1 y A–C | Consulta | Respuesta individual, fotos, espera y decisión del cliente. |
| **T-09** | T-F01/§7.2 | Pago alterno | Crédito aprobado por Wizerp o bloqueo trazable. |
| **M-01** | M-F01 | Principal | Terminal municipal, carátula, CEDIS→PDV→terminal. |
| **M-02** | §7.5/M-F01 | Cambio de modalidad | Tienda a municipal, ubicación/custodia y pagos conservados. |
| **M-03** | §7.1/M-F01 | Anexo | Sin remisión ajustar; con remisión complemento pagado; tras salida otro envío. |
| **M-04** | M-F03/§7.4 | Integración | Remisiones PDV pagadas a envío nuevo y recepción de traslado. |
| **M-05** | M-F02 | Variante logística | Jaguar costo posterior, evidencia, PAGO ENVÍO y ficha. |
| **M-06** | M-F01 | Documento contextual | Carátula, destinatario, teléfono, terminal, INE y condición cobro. |
| **M-07** | §7.5/M-F01 | Cambio | Municipal a Tienda solo antes de salida a terminal; saldo envío. |
| **M-08** | M-F01/§7.2 | Pago alterno | Crédito Wizerp; costo posterior según AC-14. |
| **M-09** | M-F03 | Espera | Envío+mercancía pagados, fecha posterior, liberación Ventas. |
| **M-10** | M-F03 | Acumulación | Remisiones pagadas en azul; cajas N/A hasta cierre físico. |
| **M-11** | §7.2/§7.3 | Aplicación financiera | Nota de crédito y comprobante por diferencia validada. |
| **M-12** | §7.2 | Exhibiciones | Dos o más transferencias reales completan un solo pedido. |
| **N-01** | N-F01 | Principal | Pedido Wizerp → consulta CEDIS/peso → aceptación → pago → guía → salida. |
| **N-02** | N-F01/§7.7 | Cargo contextual | Zona extendida documentada y reexpedición separada. |
| **N-03** | N-F01/N-F03 | Cobertura opcional | Seguro solicitado, por caja si procede; no obligatorio para todos. |
| **N-04** | N-F02 | Espera | `PAQ. PENDIENTE` desde captura y peso CEDIS antes de elegir. |
| **N-05** | §7.6/N-F01 | Cambio | Paquetería distinta antes de salida; nueva guía y costo si aplica. |
| **N-06** | N-F04 | Anexo | Misma solicitud antes de remisión; complemento pagado; tras salida nuevo envío. |
| **N-07** | N-F01 | Dirección contextual | Alta de primera dirección y destinatario posiblemente distinto. |
| **N-08** | §7.6/N-F01 | Corrección | Dirección cambiada, invalidación de guía; tras salida solicitud a Rafael. |
| **N-09** | N-F01 | Dirección contextual | Añadir dirección sin borrar anteriores, elegir vigente para guía. |
| **N-10** | §7.5 | Cambio | Nacional a Tienda antes de salida y custodia PDV con evidencia. |
| **N-11** | N-F03 | Consolidación asegurada | Remisiones completas por caja, valor/evidencia/seguro por caja. |
| **N-12** | N-F03 | Consolidación sin seguro | Varias cajas sin desglose exigible de contenido como N-11. |
| **N-13** | N-F03 | Variante física | Una compra grande, varias cajas; guardas AC-12 con seguro. |
| **N-14** | §7.6/N-F01 | Cancelación | Motivo, estado de pago/salida, guía y tratamiento físico/financiero. |
| **N-15** | §7.2/N-F01 | Pago alterno | Crédito Wizerp aprobado o bloqueado, sin salida ficticia. |
| **R-01** | §7.4/T-F01 | Custodia | Pagado en PDV, entrega con foto o reintegro AC-01 al vencer. |
| **R-02** | M-F03 | Espera | Municipal totalmente pagado, fecha futura y liberación Ventas. |
| **R-03** | M-F03 | Acumulación | Municipal acumula remisiones; cajas N/A hasta integrar. |
| **R-04** | N-F02 | Espera | Nacional mercancía pagada sin empresa; resguardo CEDIS. |
| **R-05** | N-F03/N-F04 | Espera física | Principal nacional no sale hasta recibir complemento de otro almacén. |
| **R-06** | N-F03/N-F04 | Traslado físico | Origen sale, CEDIS recibe y verifica, luego integra. |
| **S-01** | §7.3 | Incidencia financiera | Excedente mínimo $10 y saldo para siguiente pedido. |
| **S-02** | §7.3/§7.1 | Incidencia | Retiro recién pagado, validación física y resolución de diferencia. |
| **S-03** | §7.3 | Incidencia | Depósito sin pedido no crea venta falsa. |
| **S-04** | §7.3 | Incidencia | Devolución aceptada y nota usada posteriormente. |
| **S-05** | §7.3 | Aplicación | Saldo PDV verificado/consumido sin duplicidad. |
| **S-06** | §7.3/§7.5 | Diferencia de envío | Cambio a recolección, saldo solo transporte. |
| **S-07** | §7.3 | Incidencia | Dos ingresos duplicados, no dos exhibiciones de una compra. |
| **S-08** | §7.3 | Incidencia | Cobro excesivo por error; causa y resolución. |
| **S-09** | §7.3/N-F01 | Diferencia de envío | Tarifa menor o cambio empresa, saldo exclusivo de envío. |
| **P-01** | M-F02/§7.2 | Cobro diferido | Evidencia costo Jaguar → transferencia → verificación → ficha. |

**Control de cobertura:** 10 fichas Tienda (incluye T-02.1), 12 Municipal, 15 Nacional, 6 Resguardos, 9 Saldos, 1 Pago Envío = **53**. Cada ficha aparece una vez en la matriz, aunque una operación pueda relacionarse con varias fichas.

## 10. Condiciones de aceptación humana: recorridos de punta a punta

**La revisión funcional la hacen personas operadoras del equipo.** Las pruebas de servicios y componentes son controles previos, no sustitutos de los relevos reales. Un estado PASS requiere ver la acción en el rol correcto y el efecto en el siguiente. No adivinar cuentas, datos ni botones.

| Recorrido | Acciones indispensables | Resultado observable |
| --- | --- | --- |
| **QA-T01** Apartado presencial | Auxiliar PDV aparta→cliente paga y recoge / vence sin pagar. | Evidencia, vigencia y liberación conforme regla. |
| **QA-T02** Tienda transferencia | Ventas documento→CEDIS responde→Auxiliar valida→CEDIS bolsa→PDV recibe→custodia→cliente recoge. | Misma mercancía/remisión y foto final; sin entrega ficticia. |
| **QA-T03** Tienda MD | Venta antes/después de 13:00, permisos, lunes-viernes vs sábado. | Prioridad solo autorizada y sin promesa indebida. |
| **QA-T04** Cambio Tienda | Tienda a Municipal/Nacional con mercancía en distintas ubicaciones. | Solicitud sustituta, pago conservado y traslado real. |
| **QA-M01** Municipal ordinario | PDF→CEDIS→carátula→Auxiliar→CEDIS→PDV→repartidor→terminal. Variante PDV (M-F04): consulta a PDV→Auxiliar aprueba→aparece en Tienda→Ver/Marcar empacado→Atendidos. | Evento de terminal y destino correcto; no declarar entrega domicilio. |
| **QA-M02** Jaguar | Mercancía pagada, flete indeterminado→terminal da costo→PAGO ENVÍO→ficha. | Mercancía no se cobra doble y flete no queda ficticiamente pagado. |
| **QA-M03** Municipal espera/acumulación | Azul por fecha o compra acumulada→liberación Ventas→cajas reales. | No salida adelantada; N/A no se toma como cero. |
| **QA-N01** Nacional ordinario | Wizerp/PDF→paquetería+importe preliminar→CEDIS individual/peso→Ventas cierra→dirección→cotiza→Auxiliar→Delegado→CEDIS. | Caja/guía/pago/remisión coherentes; salida solo CEDIS. |
| **QA-N02** Nacional paquetería pendiente | `PAQ. PENDIENTE`→consulta/peso→solo mercancía pagada→resguardo→elección posterior. | Sin flete/guía/salida falsos; no duplicar mercancía. |
| **QA-N03** Nacional complemento | Antes de remisión y después con remisión; origen PDV→CEDIS→integración. | Misma compra vs nuevo complemento correctamente separados; recepción real. |
| **QA-N04** Varias cajas | Sin seguro; con seguro y remisiones pagadas; remisión físicamente imposible. | Valores, guías, cobertura y guardas correctas por caja. |
| **QA-N05** Nacional cambia dirección/empresa | Antes y después de entregar transporte; guía anterior existente. | Guía nueva vigente o gestión excepcional, sin sobrescribir histórico. |
| **QA-N06** Nacional a Tienda | Con una solicitud ya registrada, CEDIS mantiene la instrucción de no despachar y PDV recibe, custodia y entrega con foto. | Editar pedido omite el panel de sucursal y conserva el aviso de guía pendiente cuando corresponde. Pagos y remisiones conservados. |
| **QA-F01** Crédito bloqueado | En tienda, municipal y nacional: Wizerp bloquea y después regulariza o vence. | Sin remisión/guía/salida indebidas; cancelación AC-03 con guarda comprobada. |
| **QA-F02** Saldos | Excedente $9.99/$10, duplicidad real, saldo mercancía vs envío, nota PDV. | Sin duplicar ingresos y sin aplicar al pedido de origen. |
| **QA-E01** Cancelaciones | Sin pago, con remisión, con guía y después de salida. | Acción física y dinero separados, evidencia y responsable. |

**Clasificación de resultados:** `PASS` (comprobado de extremo a extremo), `FAIL` (defecto reproducible), `BLOCKED` (dependencia de entorno/datos/permiso/decisión), `NOT_RUN` (aún no intentado). Un caso puede quedar parcialmente ejecutado y registrarse como no completado, nunca “PASS parcial” que oculte un relevo no recorrido.

**Evidencia por escenario:** folio de prueba, cliente/empresa o sucursal de prueba, rol que actuó, paso, resultado esperado/observado, imagen o archivo, respuesta HTTP si relevante, estado en banda/bandeja siguiente, remisión y, cuando aplique, caja/guía/ubicación. No usar operaciones financieras o transporte **reales** en pruebas.

## 11. Instrucciones de interpretación para Claude y demás agentes

### 11.1 Qué tomar como contrato, qué no

1. **Contrato operativo = V3.1 + fuentes citadas.** No convertir una estructura de React, un test de PHPUnit o un estado de base de datos en requisito de negocio sin contrastarlo.
2. **Jerarquía de decisiones:** reglas explícitas AC respondidas > descripción narrativa V02 cuando contradice > práctica descrita en proceso original > propuesta de mejora claramente etiquetada. Los cambios de usuario posteriores pueden elevar una regla nueva, pero deben quedar documentados con fecha y alcance.
3. **No crear 53 pantallas:** implementar tres recorridos principales, capacidades compartidas y bifurcaciones por condición. Proteger transiciones y habilitar comandos por rol y estado, evitando wizards forzosos y módulos paralelos.
4. **No duplicar motores:** reutilizar servicios existentes de pago, remisión, seguro, direcciones, caja, guía, custodia y trazabilidad. Mejorar integración operativa antes de inventar otra tabla o módulo.
5. **No degradar Nacional:** el nuevo formulario debe recuperar toda capacidad útil del Nacional anterior; el legado se consulta como inventario funcional histórico, **no como UI que se restaura**. Distinguir nueva captura 2.0 de pedidos históricos.
6. **No dañar Tienda/Municipal:** reglas nacionales de seguro, cajas y Delegado no se trasladan a municipal; terminal y carátula no se trasladan a nacional. Un cambio en componente compartido exige regresión dirigida de las tres modalidades.
7. **No fingir trabajo de otras personas:** ni Ventas confirma recepciones, ni CEDIS cierra consulta comercial, ni Delegado valida pago, ni una instrucción de traslado se convierte en recepción.
8. **No fingir aceptación:** automatización verifica contratos aislados; el equipo humano valida recorridos de bandejas. Mantener clasificación `BLOCKED` si falta entorno MySQL/sesión física/servicio comercial seguro.
9. **No tocar datos productivos:** evitar `migrate:fresh`, comandos destructivos, envío de guías reales, cobros bancarios o automatismos de salida en pruebas. No merge/rebase/push sin instrucción expresa del equipo.
10. **Al proponer cambios:** presentar `Caso fuente → estado inicial → rol que actúa → comando/UX → guarda → estado/evento → rol receptor → prueba → riesgo`. Ante ambigüedad, formular la pregunta de negocio, no completar con suposiciones.

### 11.2 Contrato de una capacidad integrada

Para cada caso eventual verificar, en ese orden: **disparador** → quién tiene permiso → qué pedido/remisión/ubicación/caja afecta → datos/evidencia → acción visible → validación de servidor → efecto en dinero y físico (separados) → comunicación al rol siguiente → condición de cierre/bloqueo → auditoría → prueba automatizada + manual pendiente o ejecutada. **Un botón visible sin ruta operativa completa no es una capacidad terminada.**

### 11.3 Decisiones de experiencia confirmadas para Nacional 2.0

- En el **inicio** se seleccionan modalidad Nacional, paquetería o pendiente e **importe de mercancía preliminar** junto al documento Wizerp. El selector de paquetería y el importe preliminar pueden compartir fila/área contextual; el importe existente es **un solo origen de verdad**, no otro campo financiero duplicado.
- La mercancía por SKU/captura **no ocupa obligatoriamente el primer plano ni sustituye el PDF**. Permite aclarar piezas, anexarlas o retirarlas cuando se necesite.
- Se distingue **consulta comercial** de continuación: información de cotización/seguro/guías/cambios solo cuando haya estado que lo habilite. El formulario es contextual, no un wizard rígido.
- La vista debe ser compacta, clara y consistente con Tienda/Municipal. No mostrar desde el alta avisos de guía anulada, costo recuperable o custodia cuando todavía no existen esos hechos.
- La continuación tras CEDIS incluye domicilio nuevo/adicional/cambio, destinatario, número de cajas del pesaje (sin cobro de flete), seguro opcional, reexpedición, pago/crédito, remisión, Delegado, CEDIS y despacho. Si una capacidad ya existe en backend pero no desde bandeja/UX, **sigue incompleta**.

## 12. Cuestiones que siguen requiriendo definición o validación específica

1. **AC-01:** destino económico exacto de mercancía pagada cuya custodia venció y fue reintegrada a inventario. La devolución física ya está respondida, el dinero no se deduce de ella.
2. **AC-03:** momento de corte exacto para cancelar crédito vencido, notificaciones y política transaccional al liberar compromisos. Se acordó cancelar; **no se especificó el minuto ni que ya exista una ejecución segura**.
3. **AC-07:** tratamiento contable de guía/seguro ya consumidos y asignación de responsabilidad según evidencia. No imputación automática a un actor.
4. **AC-08:** manejo del impago posterior de Jaguar y conciliación interna de caja PDV; se requiere proceso separado, sin bloquear lo ya definido.
5. **AC-10:** evidencia uniforme de ciertos movimientos de traslado, segundo traslado autorizado y confirmación de recepción final nacional. Se acordó **reutilizar evidencia PDV existente**, no simular recepción.
6. **AC-12:** solución documental autorizada cuando una única remisión físicamente no cabe completa en caja asegurada, sin romper regla de remisión íntegra. Mantener guarda y escalar.
7. **AC-13:** fechas de vigencia y destino contable de ciertos saldos según origen; verificar aplicación de plazo de 20 días y política de saldos sin remisión. No borrar por inferencia.
8. **N-01:** entrega final en domicilio por transportista no cuenta con política universal documentada. Despacho de CEDIS es evento confirmado distinto.
9. **N-07…N-09:** validaciones finas de dirección/nota/dropshipping se apoyan en campos existentes, pero no se deduce ninguna impresión forzada ni proveedor de datos externo.
10. **T-06:** detalle de anexos del día siguiente que no estén destinados a entregar conjunto en tienda; no dar permiso universal.
11. **T-F03:** departamentos autorizados y alternativa operativa precisa tras corte TIENDA MD se obtienen de configuración/decisión real.
12. **Saldos:** alcance de umbral mínimo de $10 a tipos especiales de transporte u otros orígenes necesita confirmación antes de aplicarlo transversalmente.

**Regla de manejo de dudas:** mantener identificable el pedido y bloqueada solamente la operación cuyo requisito no está resuelto; no dejar sin registrar todo el caso ni imponer una restricción global ajena.

## 13. Glosario operativo

- **Pedido Wizerp:** solicitud comercial preliminar con folio y piezas, aun sin pago.
- **Remisión:** documento posterior a revisión, utilizado como referencia de venta/pago; distinto del folio inicial.
- **Consulta:** interacción Ventas ↔ almacén ↔ cliente hasta composición aceptada.
- **CEDIS:** origen exclusivo de todo despacho de **Nacional**, y origen frecuente de otros pedidos.
- **PDV:** punto de venta/sucursal; puede preparar, recibir, custodiar y entregar en Tienda; apoya salida municipal.
- **Apartado:** piezas reservadas, no necesariamente pagadas.
- **Resguardo:** mercancía identificada en espera por motivo específico, no nueva venta.
- **Complemento:** piezas/pedido/remisión adicional relacionada con compra principal; requiere cobertura, origen y recepción real para integrarse.
- **Caja:** unidad física empacada con identidad de peso, dimensiones, guía y cobertura propia cuando aplica.
- **Guía:** documento de transportista asociado a paquete; puede invalidarse y conservar historial.
- **`PAQ. PENDIENTE`:** decisión de empresa logística aún no tomada, no servicio de transporte con costo 0.
- **`PAGO ENVÍO`:** reporte separado de transporte cobrado después, en especial Jaguar, ligado a remisión.
- **Azul / amarillo:** espera / puede preparar según operación; no equivalen a estados financieros.
- **`N/A`:** número de cajas aún no aplicable; no es cero.
- **Entrega física final:** evento propio, no inferible del traslado a otra área.

---

## 14. Fuentes y vigencia

**Archivos de referencia:**

- `PROCESO PARA PEDIDOS.docx` — procesos de campo, Tienda/municipal/nacional, resguardos, saldos y pago envío.
- `Analisis de Requerimientos Gestión de pedidos - V02.1 Dudas resueltas.docx` — fichas completas V02 y respuestas narrativas.
- `Analisis_de_Requerimientos_Gestion_de_pedidos_V02.1_Dudas_resueltas.md` — **AC-01 a AC-15 respondidas** y ampliaciones de trazabilidad; fuente crítica cuando cambia una decisión previa.
- `ENVIOS NACIONALES Y RESGUARDOS.docx` — fichas específicas N-01…N-15/R-04…R-06 con transferencia de información y límites.
- Revisiones funcionales de GELIA 2.0 desarrolladas entre el 14 y el 21 de noviembre de 2026: consulta por unidad, formularios compartidos, continuidad de captura, separación CEDIS/PDV, nacional nuevo vs histórico, cajas/guías y QA humano.

**Versionado:** V3.1 **reorganiza** y aclara el contrato; **no elimina** las fichas V02.1. Si un requerimiento futuro cambia, editar esta guía con **fecha, regla sustituida, decisión humana y casos afectados**. No modificar un proceso confirmado simplemente para hacer que pase un test.

## 15. Registro de cambios operativos posteriores a V3.1

Cada cambio indica fecha, regla sustituida, decisión humana y casos afectados. Mantener esta lista al día al corregir el flujo.

| Fecha | Cambio | Regla sustituida | Decisión | Casos |
|---|---|---|---|---|
| 23-nov-2026 | Un municipal aprobado con almacén consultado PDV se muestra en Preparación Tienda como «Aprobado: por empacar», se empaca ahí y **Marcar empacado** concluye el flujo. Sale de la bandeja de CEDIS. | M-F01 paso 5 (todo municipal se empaca en CEDIS y se traslada a PDV) y el diseño del 21-nov que descartó la tarea de Tienda para municipal. | Usuario: el pedido «desapareció» tras auditar; lo prepara Tienda y los municipales empacados en Tienda no pasan a custodia. | M-F01, M-F04, QA-M01 |
| 24-nov-2026 | Observaciones de un municipal consultado en PDV dicen **Observaciones de Tienda**. En Preparación Tienda, **Ver** muestra consignatario y remisión; el folio no empaca. Tras **Marcar empacado** la fila sale de Pendientes y queda en Atendidos (solo Ver). Sigue sin CEDIS, custodia ni guía. | M-F04 paso 4 (la fila salía de la bandeja) y el badge de observaciones que usaba la tarea de preparación. | Usuario: pedido 26560, observación CEDIS en Ventas; en Auxiliar PDV faltaba Ver y el empacado desaparecía. | M-F04, QA-M01 |
| 24-nov-2026 | Nacional permanece en CEDIS TODOS desde la consulta hasta que Ventas cierra. El destino es quien recibe y el domicilio, no sucursal (salvo recolección en sucursal). La cotización prerellena la dirección principal. | CEDIS solo listaba `pendiente_pesaje`; tarjetas y detalle trataban sucursal como destino. | Usuario: nacionales desaparecían con pesaje abierto; destino es domicilio del cliente. | N-F01, QA-N01 |
| 24-nov-2026 | En Destino de CEDIS, un municipal muestra consignatario y dos carátulas carta (con o sin identificación) para previsualizar, descargar o imprimir. | Spec 10: la carátula no imprimía INE y CEDIS no la usaba. | Usuario: pedido 26563, no aparecían datos del consignatario; se necesitan ambas carátulas al empacar. | M-F01, QA-M01 |
| 24-nov-2026 | En pesaje nacional, CEDIS responde **Todo OK** o solo las piezas afectadas y después cajas/peso en el mismo envío. | N-F01 paso 4 (CEDIS verifica mercancía pieza por pieza). | Usuario: alinear pesaje nacional al patrón de consulta 2.0. | N-F01, QA-N01 |
| 24-nov-2026 | Nacional, vista de Ventas: cada pieza con detalle o sin existencias ofrece **Confirmar estado** (no en faltantes), **Retirar** y **Sustituir** (retiro + alternativa por SKU o captura, un solo ajuste que pide nuevo peso a CEDIS). El resumen de CEDIS muestra por envío tipo de caja, medidas y pesos; Ventas ve la preview de la evidencia vigente de cada envío: si CEDIS sube fotos nuevas, las anteriores pasan a histórico; si solo se retiran piezas, se conserva la misma evidencia sin recaptura. La foto vigente del mismo envío basta al responder tras añadir o retirar piezas, y se recupera al recargar; una pieza con detalle conserva su foto vigente al actualizar (no se pide de nuevo); al responder una actualización CEDIS ya no ve las piezas que Ventas retiró o sustituyó. | Las acciones rápidas solo existían en Tienda/Municipal y nacional las dejaba en «Modificar pedido»; el pesaje nacional exigía foto nueva si cambiaban las piezas. | Usuario: pedido nacional 2233, recorrido de añadir pieza. | N-F01. Recorrido verificado en testgelia con el pedido 9901 (nacional, con detalle y sin existencias, retiro, añadir pieza, sustitución y actualización); sin pruebas de backend ejecutadas. Al actualizar una consulta nacional (anexo, retiro o sustitución), CEDIS ve la misma revisión que Tienda: «Cambios por revisar» con Anexo/Retiro solicitado, Todo OK o Registrar detalle por cambio (obligatorio antes de continuar), registro rápido de productos y después cajas, peso y evidencia por envío (pedido 5545). |
| 24-nov-2026 | Registrar detalle (Tienda, Municipal y Nacional comparten componente): el diálogo permite elegir por pieza Única pieza, Mejor ejemplar o Sin existencias, un comentario opcional y su foto (tomar o galería); la fila de cada pieza muestra siempre los tres interruptores y deja el comentario y las fotos en un desplegable que conserva lo escrito. | Los interruptores quedaban dentro de la pieza plegada y el diálogo solo elegía el producto. | Usuario: agilizar el registro de detalle. | CEDIS y Tienda al responder consultas y actualizaciones. |
| 24-nov-2026 | La bandeja de CEDIS se actualiza sola cuando Ventas manda otra consulta del mismo pedido, incluso después de abrir y cerrar un modal (antes había que recargar). Los tres interruptores de una pieza (Única pieza, Mejor ejemplar, Sin existencias) van siempre en una sola fila. | El estado «pausado» de la bandeja usaba un valor atrasado tras cerrar un modal y descartaba las actualizaciones siguientes. | Usuario: re-consulta de un mismo pedido sin recargar CEDIS. | CEDIS (y Auditar, con el mismo ajuste); Tienda y Municipal comparten los interruptores. |
| 24-nov-2026 | Pesaje nacional de CEDIS: una pieza con detalle cuya foto sigue vigente (Evidencia vigente) ya no se rechaza con «Adjunta una foto de cada producto con detalle»; basta la foto guardada o una nueva. En una nueva consulta o anexo, las piezas con detalle de la consulta anterior pasan a «Consulta anterior», un resumen plegado de solo lectura que se conserva en la respuesta; solo se editan las piezas del ciclo en curso. | La validación del modal solo contaba fotos nuevas, y la captura listaba todas las piezas previas como editables. | Usuario: pedido 5545 (PBMA-2026-00043), evidencias vigentes marcadas como error y consulta anterior editable por accidente. | N-F01, QA-N01 |
| 25-nov-2026 | El empaque con evidencia individual registra caja o bolsa. El cumplimiento agrupado municipal captura cajas y bolsas y conserva el total calculado. | Evidencia individual sin tipo y captura agrupada con solo total. | Ola 1 de Comisiones de Empaque. Conservar evidencias, remisiones y recorridos legados. | M-F01 y cumplimiento agrupado municipal. CEDIS: una caja y una bolsa con sus fotos. Tienda: dos cajas y una bolsa suman tres bultos. |



**Fin de la guía operativa maestra V3.1.**
| 24-nov-2026 | En Nacional el bloque de cambio de paquetería se llama **Seleccionar paquetería** (título y botón). En CEDIS, un pedido en **resguardo en tienda** muestra **Enviado a sucursal** en lugar de **Marcar enviado** (tarjeta y detalle); el envío regular conserva **Marcar enviado**. Solo cambia la etiqueta. | «Cambiar paquetería / Actualizar paquetería» y «Marcar enviado» para todo pedido. | Usuario: evitar confusión entre entrega a sucursal y envío regular. | N-F01, T-F04, QA-N01 |
| 24-nov-2026 | La cotización nacional de Ventas separa en contenedores propios **Dirección de entrega** y **Costo de envío**. El seguro es opcional y está apagado por defecto; muestra el desglose en vivo con el cálculo del servidor (vista previa sin guardar; misma fórmula que el guardado) y se oculta en paqueterías sin cobertura. | Un solo bloque con casilla «Incluir seguro» y desglose solo después de guardar. | Usuario: la vendedora no veía el cálculo del seguro ni el desglose de montos; la fórmula (Estafeta/FedEx 2.5 %, DHL 2 % + $51 sobre mercancía + flete) ya existía en el servidor y no se recrea en cliente. Reexpedición se muestra aparte y no entra a la base del seguro. | N-F01 pasos 8-9, AC-09 |
| 24-nov-2026 | Cliente sin dirección principal ni adicional: aviso dentro del modal de cotización con **Agregar dirección**, **Enviar enlace al cliente** (según permisos) y **Actualizar direcciones**. El formulario público valida por campo al salir: teléfono con formato 55 1234 5678 (10 dígitos, acepta +52, opcional), código postal de 5 dígitos, obligatorios con mensaje claro; el servidor aplica las mismas reglas. | Solo texto «agregue una dirección» y recarga manual del pedido; teléfono libre de hasta 30 caracteres. | Usuario: volver a ofrecer el enlace-formulario, que registre principal o adicional sin exigir principal previa, y hacer el formulario más cómodo y validado. El formulario no tiene campo de correo, no se añadió. | N-07, N-09, QA-N01 |
| 24-nov-2026 | En Preparación Tienda, una consulta municipal ya respondida por Tienda (o cerrada por Ventas) que aún no tiene pago validado sigue visible en **Atendidos** como «Consulta respondida» / «Consulta cerrada»; **Ver** la abre en solo lectura. Al validarse el pago pasa a «Aprobado: por empacar». | M-F04 y la bandeja: la fila existía solo mientras la consulta estaba pendiente o ya aprobada y por empacar; entre ambos momentos el pedido no aparecía en ninguna pestaña. | Usuario: pedido municipal 9797 a Tienda «desaparece» después de responder disponibilidad. | M-F04, QA-M01 |
| 24-nov-2026 | En el modal de respuesta de disponibilidad (CEDIS/Tienda) el comentario del detalle es **opcional**; lo único obligatorio es el estado de la pieza (mejor pieza, última pieza o sin existencias). La foto del producto con detalle se conserva. | Descripción obligatoria del defecto cuando no había estado marcado. | Usuario: el comentario seguía exigiéndose. | Consulta de disponibilidad CEDIS/Tienda |
| 24-nov-2026 | **Registrar detalle** responde por fotos: cada foto (cámara o galería, sin tope) abre una pieza con detalle sin buscar SKU, con los interruptores Única pieza / Mejor ejemplar y comentario opcional; en CEDIS el estado es obligatorio. Buscar por nombre/SKU (o «Sin catálogo») queda solo para **Sin existencias**, que entra en ese estado automáticamente, sin foto. Se retira el límite de 5 fotos por pieza y el de 50 piezas pasa a 200. | Búsqueda en catálogo como paso obligatorio para cada pieza; máximo 5 fotos por producto. | Usuario: buscar en catálogo quita mucho tiempo al empacador. | Consulta de disponibilidad CEDIS/Tienda |
| 24-nov-2026 | La **Respuesta de disponibilidad** de Tienda, Municipal y Nacional es una sola tabla: cada fila lleva color y texto de estado (**verde** = OK o confirmado con el cliente; **rojo** = sin existencias o retiro; **amarillo** = pendiente, p. ej. anexo «Por revisar»). Los anexos y retiros pedidos son filas de esa tabla (también antes de la primera respuesta, con el título «Cambios solicitados»); una decisión de Ventas sobre una pieza ya listada solo colorea su fila; los movimientos del ciclo anterior se pliegan al pie. Se retira el panel «Cambios del pedido» de esos tres seguimientos; el panel de respuesta de CEDIS/Tienda no cambia. | Dos paneles con información repetida («Respuesta de disponibilidad» y «Cambios del pedido»). | Usuario: reducir información redundante y ver de un vistazo qué salió bien o mal; amarillo para pendiente y no confundirlo con el verde de confirmado. | Respuesta OK, con detalle + confirmado, retiro, anexo pendiente, reapertura, sin cambios |
| 24-nov-2026 | En el panel de respuesta de Tienda/CEDIS (actualización de consulta), las piezas de un cambio ya respondido (unidad guardada con detalle o sin existencias) pasan a un historial plegado «Ya respondidas · N» y siguen yendo en el envío; solo quedan a la vista las piezas del cambio que se está trabajando. | Todas las piezas, respondidas o no, en «Productos con detalle». | Usuario: no abrumar al empacador; mantener solo lo que necesita ver y responder. | Actualización de consulta con anexos y retiros |
| 24-nov-2026 | La **primera respuesta** de CEDIS/Tienda con «Con detalle o faltante» (sin anexos ni retiros) usa el mismo diálogo que las actualizaciones: **Agregar productos con detalle** → una foto por pieza con sus interruptores (Única pieza / Mejor ejemplar) y comentario opcional; la búsqueda por nombre/SKU/escaneo queda solo para **Sin existencias**. Se retira la búsqueda por catálogo y «Capturar sin catálogo» de ese panel. | Buscar cada pieza en catálogo antes de marcar su estado. | Usuario: el empacador responde por fotos; buscar en catálogo toma mucho tiempo. | Primera respuesta con detalle, con foto, sin existencias, escaneo en el diálogo |
| 24-nov-2026 | En la actualización de consulta de Tienda, el panel **no vuelve a guardar** las unidades de una respuesta ya enviada (anexos con estado disponible/con detalle/rechazado, retiros aplicados o líneas con `pendiente_revision` en falso): se omiten del guardado de avance y del reintento previo al envío. Así no aparece «Esta unidad ya pertenece a una respuesta enviada» ni se bloquea el envío correcto. | El panel reintentaba guardar todas las unidades con resultado, incluidas las ya enviadas (con piezas recuperadas), y el servidor las rechazaba. | Usuario: error molesto que no frenaba el avance pero sí el envío final. | Actualización con anexos ya respondidos + un anexo nuevo |
| 25-nov-2026 | En la **Respuesta de disponibilidad** las piezas y anexos que salieron **Todo OK** ya no son filas: se muestran como minis (miniatura, nombre, SKU y «Todo OK») junto al estado de la respuesta, para que Ventas vea lo que está bien sin abrir la tabla. La tabla conserva solo lo que requiere atención (con detalle, sin existencias, retiro, por revisar). | Las piezas OK se mostraban como filas verdes en la tabla. | Usuario: que las vendedoras vean también las respuestas en OK, en mini. | Respuesta con OK y detalle mezclados; solo OK; solo detalle |
| 25-nov-2026 | En **Registrar detalle** y en las piezas con detalle ya no existe el interruptor «Sin existencias»: una pieza con detalle implica que hay existencia, y una sin existencias solo entra buscándola en el catálogo (queda sin interruptores, foto ni comentario). En el diálogo la búsqueda de sin existencias se oculta tras el botón **Sin existencias**. | Interruptor Sin existencias en toda pieza y búsqueda siempre visible. | Usuario: redundante, confuso y peligroso; modal más limpio. | Foto con detalle; pieza buscada; abrir/ocultar búsqueda |
| 25-nov-2026 | Un **anexo o retiro nuevo se suma a la consulta abierta** (p. ej. una sustitución aún sin responder): la consulta conserva su identidad (`actualizacion_id`) mientras siga pendiente, de modo que el empacador conserva lo que tiene en curso, ve juntas la sustitución y el anexo nuevo, y su respuesta no se rechaza ni se descarta por «otros cambios». Solo un ciclo nuevo o la reapertura renuevan la identidad. El envío sigue exigiendo revisar cada unidad pendiente. | Cada anexo/retiro nuevo regeneraba la identidad: el panel se reiniciaba y la respuesta en curso se rechazaba, por lo que la sustitución parecía reemplazada o concluida. | Usuario: lo nuevo debe sumarse, no encimarse. | Sustitución pendiente + anexo; retiro con anexo pendiente; reapertura |
| 25-nov-2026 | La numeración de **«Pieza con detalle N»** continúa tras las ya registradas, incluidas las respondidas y recuperadas de consultas anteriores (4, 5, 6…), en lugar de reiniciar en 1. | El contador solo contaba piezas creadas en el diálogo actual. | Usuario: nueva consulta volvía a «Pieza con detalle 1». | Consulta con 3 respondidas + 1 nueva |
| 25-nov-2026 | En nacional cerrado, la paquetería del alta (p. ej. DHL) permanece; el segundo selector **Seleccionar paquetería** solo aparece si el alta fue **PAQ. PENDIENTE** y en ese menú sí sale DHL. La cotización muestra **No. de caja** (conteo del pesaje) y no cobra flete: `caja.costo_envio = 0`; el cobro es mercancía + reexpedición + seguro; el desglose oculta Flete. El seguro se calcula aunque el flete sea 0. | Recaptura de paquetería tras el cierre (el menú ocultaba la vigente) y «Flete de caja N (MXN)» sumado al total. | Usuario: pedido 26736, DHL desaparecía y el 1 de cajas se cobraba. | N-F01 paso 9, N-F02, QA-N01 |
| 25-nov-2026 | **Borrador de respuesta (Tienda/CEDIS):** una pieza con detalle guardada como avance pero **no enviada** permanece en «Productos con detalle» al cerrar y reabrir la consulta, o al sumarse un anexo con la consulta abierta. «Ya respondidas · N» solo recibe piezas de líneas ya enviadas (`ya_atendida`). | Toda unidad guardada con detalle se archivaba al reabrir, aunque no se hubiera pulsado «Enviar respuesta». | Usuario: la pieza pendiente aparecía en «Ya respondidas» y desaparecía de la lista. | Capturar pieza → cerrar para atender otro pedido → reabrir: sigue editable; tras enviar pasa al historial |
| 25-nov-2026 | **Número de pedido repetido al editar (todas las modalidades):** al guardar una edición con un «Folio del pedido» distinto del guardado, el servidor rechaza el que ya pertenece a otro pedido activo (error en `folio_remision` con el folio interno que lo tiene). Un duplicado heredado no bloquea mientras el número no cambie. | Sin detección de duplicados. | Usuario: detectar número de pedido repetido en Editar pedido. | Cambiar el folio al de otro pedido → rechazo; conservar el propio o uno nuevo → guarda |
| 25-nov-2026 | **Folio repetido en la captura compartida:** el campo queda con borde rojo y vinculado al aviso del panel cuando el servidor lo rechaza. Al cambiar el folio se retira el aviso y el borde vuelve a su estado habitual. | El rechazo solo se mostraba como texto en el panel. | Usuario: resaltar el campo de folio en Editar pedido. | Editar con el folio activo de otro pedido → aviso y borde rojo. Cambiar a un folio libre → se retira la marca y se guarda. |
| 25-nov-2026 | **Nacional · Cotización y pago, dirección de entrega:** las opciones **Agregar dirección**, **Enviar enlace al cliente** (con permiso) y **Actualizar direcciones** se muestran siempre, también cuando el cliente ya tiene direcciones, para poder ajustarlas en cualquier momento. | Con una dirección registrada solo quedaba «Agregar dirección»; enlace y actualización aparecían únicamente sin direcciones. | Usuario: tras agregar una dirección no se podía agregar otra ni ver las opciones. | Cliente con una dirección: se ven las tres opciones; agregar otra la deja elegida |
| 25-nov-2026 | En Preparación Tienda, Ventas resuelve todos los detalles o faltantes publicados antes de solicitar más piezas. El auxiliar envía solo las unidades abiertas. Las piezas por foto se muestran en secuencia continua y Ventas puede desplazar la lista de incidencias. | Se permitía otro anexo con decisiones pendientes, el envío final podía repetir unidades publicadas y la numeración arrastraba saltos o duplicados. | Usuario: corrección del recorrido mostrado en PBMA-2026-00062. | Responder anexo con detalle y faltante → bloquear agregado → confirmar detalle y retirar faltante → solicitar anexo → responder solo las unidades abiertas. |
| 25-nov-2026 | Cambios del pedido y Movimientos en Preparación Tienda muestran el número visible de la revisión afectada. | El rótulo guardado de la acción podía reflejar un número anterior a la secuencia que ve Ventas. | Usuario: hacer coincidir las piezas de Editar pedido y Preparación Tienda, como en PBMA-2026-00064. | Confirmar la #2 y retirar la #3 entre varias piezas por foto; verificar la #2 y la #3 en Preparación Tienda. |
| 25-nov-2026 | La ficha de sustitución muestra la pieza original y el reemplazo con evidencia individual disponible en Tienda, Municipal y Nacional. | La ficha solo nombraba la alternativa y en Nacional el retiro aparecía separado. | Usuario: quien prepara debe identificar qué pieza sustituir. | Sustituir la #4 por captura en Tienda; revisar el cambio pendiente y el movimiento histórico; repetir con SKU en Municipal y en el repesaje Nacional. |
| 25-nov-2026 | Nacional cotizado suma el **Envío** capturado a mercancía, reexpedición y seguro. El conteo de cajas sigue informativo. En auditoría pendiente, guardar el costo faltante recalcula la cobertura de las exhibiciones existentes. | El flete se enviaba en cero y se ocultaba en el desglose. | Usuario: pedido 26771, los $206 de envío aparecían como excedente. | N-F01 pasos 9–10, QA-N01. Mercancía $2,000 + envío $206 + reexpedición $150 = $2,356. Pago por $2,356 sin excedente y pendiente de revisión. |
| 25-nov-2026 | En modalidad Tienda con consulta CEDIS, la tabla de detalles y faltantes muestra las piezas registradas por CEDIS. El documento con que Ventas pidió un anexo con detalle queda en Movimientos. Ventas resuelve las piezas reportadas antes de agregar más; el retiro y la sustitución se pueden usar para resolverlas. Al enviar, CEDIS ve el resumen con la evidencia vigente y el aviso de espera de Ventas, como en Preparación Tienda. | El documento del anexo podía aparecer como pieza del resultado, el bloqueo de anexos se limitaba a Preparación Tienda y CEDIS dejaba visible la captura tras enviar. | Usuario: corrección de Editar pedido y Respuesta CEDIS en modalidad Tienda. | T-F01 y T-06. Anexo PDF o imagen, respuesta con detalle o faltante, intento de agregar por documento o catálogo, retiro/confirmación y envío de la respuesta desde CEDIS. |
| 25-nov-2026 | Editar pedido del Panel de Vendedora omite la sección de recolección en sucursal para Envío Nacional. | El formulario ofrecía el selector y la solicitud después del cierre con cobertura, o mostraba un resumen si el cambio ya estaba registrado. | El retiro se limita a esa vista. El despacho ordinario sigue por paquetería desde CEDIS. Las solicitudes previas conservan sus restricciones y el aviso de guía se muestra mientras corresponda. | N-F01 con paquetería definida, N-F02 con paquetería pendiente, QA-N06 con solicitud registrada y cancelación de guía pendiente o confirmada. |
| 26-nov-2026 | Transferencia con consulta en Preparación Tienda: **Confirmar bolsas preparadas** registra en la misma operación la separación de los anexos documentales resueltos de la composición aprobada. | Se exigía un registro de separación del anexo antes de habilitar la preparación. | Corrección autorizada tras comparar los pedidos 03 y 04. Las decisiones de Ventas, los compromisos de inventario y los controles de salida y entrega se conservan. | T-F01 con y sin anexos, detalles aceptados, retiros, sustituciones, anexo pendiente y reintento de la confirmación. |

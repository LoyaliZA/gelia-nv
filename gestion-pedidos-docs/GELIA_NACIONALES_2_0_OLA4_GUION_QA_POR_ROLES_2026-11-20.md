# GELIA — Nacionales 2.0 | Ola 4: guion de QA operativo por roles

**Fecha de preparación:** 20 de noviembre de 2026  
**Estado:** guion preparado; ejecución NO acreditada.  
**Documento normativo:** *Análisis de Requerimientos Gestión de pedidos — V02.1 Dudas resueltas*, especialmente capítulos 2, 5C (N-01 a N-15), 5D (R-04 a R-06) y 7 (CV-01, CV-06, CV-09, CV-12 a CV-15 y CV-18).  
**Contraste técnico:** plan Nacionales 2.0 del 20/11/2026 y cierre correctivo de Ola 3.  
**Propósito:** ejecutar clics, campos, adjuntos y relevos reales en el navegador, sin deducir que un servicio PHP probado implica que la pantalla o bandeja es operable.

## 0. Dictamen de entrada y límites

La Ola 3 tiene **12 pruebas específicas/217 aserciones en verde** y regresión dirigida **49 verdes, 1 roja**. El fallo del snapshot también se reprodujo en una revisión anterior y la exigencia de cierre de consulta no se eliminó. El fallo municipal fue clasificado aparte. Estas cifras provienen del informe de Cursor; este guion **no** acredita una ejecución independiente.

**Compuertas para ejecutar sin falsos resultados:**

- **G-01 Identidad del complemento:** en el cierre correctivo, `FormularioRegistrarNacional` envía una pieza genérica llamada `Complemento`, importe y soporte. N-06 exige identificar *cada pieza*, cantidades y evidencia. QA debe exigir identificación inequívoca de las piezas solicitadas por el cliente, sin sustituirla por un importe o una imagen genérica. Si no se logra: `FAIL`, no `PASS`.
- **G-02 Esquema N-11:** la migración `2026_11_20_191500_create_pedido_bma_caja_remisiones_table` pasó comprobaciones aisladas en MySQL con tablas padre mínimas. **La base `gelianv` no tiene la tabla**. La base `testing` contiene tablas padre simuladas y residuos de prueba; **no** usarla como esquema integral ni lanzar `migrate` completo allí. Probar N-11 en un entorno aislado con esquema real y migraciones verificadas; de lo contrario `BLOCKED`.
- **G-03 Bandeja de Tienda:** se crearon eventos `pendiente_recepcion`, `recibido`, `en_custodia` y `entregado`, con `ResguardoPdv`. No consta que todas estas operaciones sean accesibles en la UI de auxiliares de tienda: comprobarlo con clics; si el usuario de tienda no puede continuar, `FAIL` si la UI debía estar integrada, o `BLOCKED` por falta de configuración/cuenta.
- **G-04 Cuenta y transporte:** ningún escenario usa pagos reales, crédito comercial real, mensajería o guías reales sin sandbox/simulador autorizado. Si la integración real carece de ambiente seguro, marcar el paso `BLOCKED`. No inventar pago, remisión o guía final.
- **G-05 Entorno:** trabajar sobre base de QA con respaldo y datos sintéticos, nunca producción. No hacer `migrate:fresh`, `ALLOW_DESTRUCTIVE_DB`, merge, rebase, push ni manipulación destructiva. Los datos de clientes y rutas se proporcionan por el entorno; no solicitar credenciales en informes.

**Dictamen:** se puede iniciar **preparación y QA del recorrido ordinario**. El caso N-11 asegurado y cualquier flujo sin bandeja o credencial quedan condicionados por las compuertas, no declarados aprobados.

## 1. Contrato de ejecución para Cursor

1. **Consultar el procedimiento indicado** antes de cada caso; no inventar botones, campos, secuencias, rutas URL ni roles. Ubicar la pantalla por el flujo real y registrar su nombre/ruta observada. Si falta la acción requerida, detener ese caso y documentar el defecto.
2. **Actuar como usuario:** abrir menús, seleccionar opciones, hacer clic, escribir, adjuntar PDF/imagen de prueba y observar cambios. No usar endpoints directos para sustituir interacciones que se pretenden validar en navegador. Se permite leer red/console y consultar datos en modo lectura para corroborar efectos.
3. **Un solo hilo QA por caso:** anotar folio GELIA, folio Wizerp si existe, identificadores de remisión, caja (`uuid_operativo`), cliente sintético, usuario/rol y evidencias. No confundir pedido principal, hijo complementario y envío independiente.
4. **Cada relevo:** cerrar o cambiar la sesión como corresponda; verificar que el siguiente rol encuentra la solicitud en su bandeja con el estado y documentación correctos. Si se dispone de dos sesiones, comprobar actualización y conflictos de versión.
5. **Resultado:** `PASS` solo si acciones visibles, persistencia, autorizaciones, finanzas y salida física corresponden al documento. `FAIL` si la UI permite una acción prohibida o no permite una obligatoria. `BLOCKED` si falta entorno/cuenta/servicio/datos; `NOT_RUN` si no se ejecutó. **Un backend test verde no convierte un caso bloqueado en PASS.**
6. **No corregir mientras se hace la primera pasada:** registrar defectos reproducibles con pasos, datos, resultado real/esperado y evidencia. La corrección y re-prueba requieren un ciclo separado.
7. **Evidencia mínima por caso:** captura de entrada, captura de resultado por rol, folio/revisión, referencia de archivo adjunto, HTTP status cuando sea relevante, errores de consola/red, estado final y observación. No capturar secretos ni información real de clientes.

### Mapa de actores del análisis V02.1

| Actor operativo | Responsabilidad que debe demostrar el QA |
|---|---|
| Cliente (interpretado por QA) | Define piezas, destino, destinatario, seguro, cambios y autoriza costos; no se presupone cuenta de cliente |
| **Vendedora** | Da de alta, adjunta PDF/capturas, dialoga con cliente, solicita CEDIS, anexa/retira, cierra consulta, selecciona paquetería y comunica cambios |
| **CEDIS** | Confirma existencias por unidad, evidencia, pesos/medidas/cajas, recibe complementos, empaca, coloca guías y acredita salida nacional |
| **Auxiliar de Ventas** (Bellaroma/CallCenter) | Verifica ingreso/pago o crédito, importe/cobertura y tramita remisión Wizerp; no confundir comprobante con pago validado |
| **Encargado de guías / Delegado** | Verifica domicilio, destinatario, paquetería y cajas; genera/carga PDF por caja; reemplaza guía invalidada |
| **Auxiliar de Tienda/PDV** | Identifica, prepara y entrega complementos para trasladarlos a CEDIS; recibe mercancía reconducida a sucursal |
| **Recepción/Custodia Tienda** | Confirma recepción física, custodia y entrega al titular con evidencia; son eventos distintos |
| **Gerente / Administración** | Interviene en autorizaciones, saldo de envío, reembolsos/cancelaciones y excepciones; sin asignar facultades supuestas a auxiliares |
| **Traslado / Paquetería** | Reporta salida/recepción física mediante evidencias; no equiparar entrega al transportista con entrega final al cliente |

### Datos sintéticos que se deben preparar sin improvisar

- `QA-C1`: cliente de pruebas con dos direcciones (una propia y otra de tercero) y un tercero destinatario. `QA-C2`: cliente de pruebas con crédito aprobado/bloqueado en simulador Wizerp.
- `QA-P1`: pedido nacional nuevo con **dos unidades diferenciables** por SKU o captura, una con detalle solicitado; PDF/imagen de prueba legible. `QA-P2`: complemento de **dos piezas diferentes**, con SKU/cantidad/evidencia e importe verificables. `QA-P3`: mercancía pagada en custodia PDV. `QA-P4`: envío grande de dos cajas.
- Dos cajas con peso, medidas, contenido y valores verificables; dos remisiones de prueba sin duplicación. Paqueterías de ensayo incluida `PAQ. PENDIENTE`, DHL y otra comercial válida; direcciones con y sin reexpedición.
- Comprobantes de prueba, remisiones Wizerp simuladas, dos PDF de guías de prueba distintos, permisos de cada rol. **Registrar los valores exactos de fixtures antes de empezar**; los montos de ejemplo no sustituyen tarifas/catálogos activos.
- Si alguna fixture o cuenta no existe, registrar `BLOCKED — precondición ausente`; **no crear datos con SQL para hacer pasar el caso**, salvo preparación autorizada y documentada del entorno antes del QA.

## 2. Recorrido central completo — QA-N01 (N-01; CV-01)

**Objetivo:** demostrar desde la interfaz la cadena *Vendedora → CEDIS → Vendedora → Auxiliar Ventas → Delegado → CEDIS*, sin duplicar captura, remisión ni roles.

| Paso | Actor | Acción por navegador | Resultado obligatorio / evidencia |
|---|---|---|---|
| 01 | Vendedora | Abrir alta de pedido, seleccionar **Nacional**, capturar cliente, folio/documento Wizerp, dos piezas (SKU y/o imagen) y dirección/destinatario de prueba | Alta nacional 2.0 sin wizard ni salto a legado; conserva PDF, piezas y cliente; folio único |
| 02 | Vendedora | Solicitar a CEDIS revisión/consulta y pesaje | Pedido visible en bandeja correcta de CEDIS, con piezas/evidencias individualizadas y origen de despacho CEDIS |
| 03 | CEDIS | Abrir el folio, responder solo una pieza con disponibilidad, detalle/foto; dejar la hermana pendiente | No se autocompleta la otra pieza; historial registra autor, respuesta y evidencia individual |
| 04 | CEDIS | Completar respuesta de cada unidad y registrar peso real/cobrado, medidas y número real de cajas | Vendedora recibe respuestas separadas; conservar correspondencia pieza/caja/pesaje; no cerrar Ventas automáticamente |
| 05 | Vendedora | Revisar detalle, hacer una consulta adicional de una pieza y **esperar sin cerrar** | Cliente simulado puede no contestar; consulta permanece pendiente, sin sustitución automática |
| 06 | Vendedora | Añadir una pieza **sin remisión**, con SKU/cantidad o evidencia diferenciada | Sigue mismo pedido; nueva respuesta/pesaje requerida, cotización previa no utilizable |
| 07 | CEDIS | Responder el ajuste, revisar pesos/cajas y evidencias | Nueva revisión identificada; no se reutiliza silenciosamente respuesta anterior |
| 08 | Vendedora | Retirar otra pieza antes de remisión, intentar cerrar antes de respuesta, luego recibir respuesta y cerrar voluntariamente | Cierre prematuro bloqueado; cierre válido tras respuesta vigente; el botón muestra éxito real |
| 09 | Vendedora | Seleccionar paquetería definitiva, destinatario, seguro opcional, reexpedición si aplica; revisar totales | Mercancía, flete, reexpedición y seguro desglosados; no cotizar una sola caja cuando son varias |
| 10 | Auxiliar Ventas | Acceder al pedido, verificar pago/transferencia de prueba o crédito aprobado y emitir remisión del alcance correcto | No confundir comprobante con ingreso confirmado; remisión visible y consistente con importes |
| 11 | Delegado | Revisar dirección vigente, cajas y cobertura; cargar PDF y rastreo de cada guía de prueba | Cada PDF activo corresponde a una caja/documentación correcta; no aceptar guía de caja retirada |
| 12 | CEDIS | Empacar, colocar guías, registrar recolección/salida física desde CEDIS | Solo envío con guía activa y cobertura vigente; folio/paquetes/historial concuerdan |
| 13 | Vendedora | Consultar el mismo pedido final | Estado refleja salida real, no entrega final del cliente; consulta, pagos y evidencias permanecen |

**Resultado:** `PASS` solamente con el relevo de todas las áreas realmente ejecutado; si falta una interfaz o rol, `BLOCKED` en el paso identificado. **Trazabilidad:** N-01, N-06, N-07, E01–E03, E06–E07, CV-01.

## 3. Variantes nacionales — escenarios independientes

### QA-N02 — Zona extendida / reexpedición (N-02)

1. **Vendedora:** partir de dirección de prueba con reexpedición documentada por la paquetería. Mostrar flete normal, cargo de reexpedición separado, posible seguro y total antes de confirmar.
2. **Cliente simulado / Auxiliar Ventas:** aceptar el adicional y verificar la cobertura total; si surge después de un pago previo, dejar diferencia pendiente hasta comprobación. No asumir que la empresa absorbe el cargo.
3. **Delegado / CEDIS:** constatar que guía y destino coinciden con la condición cotizada. **Esperado:** reexpedición contabilizada una vez, sin aumentar indebidamente la base del seguro; sin despacho con diferencia impaga. Evidencia: desglose antes/después.

### QA-N03 — Seguro opcional (N-03)

1. **Vendedora:** realizar una cotización sin seguro y otra con seguro voluntario; comparar conceptos, no activar seguro por defecto.
2. **CEDIS:** confirmar cajas, valores y evidencias asociadas; si son varias, no permitir seguro sin distribución completa de remisiones por caja.
3. **Auxiliar Ventas / Delegado:** comprobar pago de seguro y correspondencia con guía. **Esperado:** la fórmula vigente se aplica en servidor; mercancía previamente pagada cuenta como valor cubierto, no como deuda nueva. Si son varias cajas, pasar por QA-N11.

### QA-N04 — Paquetería pendiente (N-04; R-04)

1. **Vendedora:** seleccionar `PAQ. PENDIENTE`, solicitar pesaje y compartir resultado al cliente simulado sin elegir transportista.
2. **Auxiliar Ventas:** validar solo mercancía (si se paga) y remisión de mercancía; no marcar transporte desconocido como $0 pagado.
3. **CEDIS / Delegado:** comprobar que mercancía queda en resguardo, no hay guía habilitada ni salida.
4. **Vendedora:** elegir después una paquetería; mostrar flete, seguro y reexpedición; **Auxiliar** confirma diferencia; **Delegado** emite guía; **CEDIS** continúa. **Esperado:** no se vuelve a cobrar la mercancía.

### QA-N05 — Cambio de paquetería (N-05; CV-13)

1. **Vendedora:** sobre pedido no despachado y con guía de prueba, solicitar otra paquetería; registrar motivo/instrucción.
2. **Delegado / CEDIS:** comprobar que la guía anterior pasa a inactiva y su PDF/historial se conservan; no puede ser usada para despacho. Nueva guía solo tras datos actualizados y diferencias cubiertas.
3. **Auxiliar / Administración:** identificar diferencias por flete/seguro/reexpedición, y saldo de **solo envío** si aplica; no reiniciar el cobro de mercancía ni borrar remisión.
4. Repetir sobre pedido **ya entregado a transporte** o con una caja recolectada. **Esperado:** rechazo sin cambiar guía, rastreo, costo ni identidad.

### QA-N06 — Anexo antes de remisión, con remisión y después de salida (N-06; CV-06)

1. **Sin remisión:** Vendedora añade **dos piezas distinguibles** por SKU/cantidad o capturas; CEDIS confirma por separado y re-pesa; sigue el **mismo** pedido y no nace tarea Tienda.
2. **Con remisión y sin salida:** Vendedora solicita otras piezas, registra soporte e importe; **verificar nombre/identidad/cantidad de cada pieza** en solicitud y bandeja CEDIS, no solo rótulo genérico `Complemento`. Auxiliar confirma pago nuevo; se genera pedido hijo relacionado; original conserva total y remisión.
3. **CEDIS:** antes de empaque, revisar integración física, cajas, pesos y guías del conjunto; no despachar una composición antigua.
4. **Tras salida:** repetir solicitud de anexo; debe generar envío y folio independientes, sin editar caja recolectada ni la guía anterior.
5. **Idempotencia:** repetir el botón/solicitud desde sesión con revisión vieja; no duplicar hijo, pago, evidencia ni remisión. **Si el detalle de piezas se pierde, reportar FAIL de N-06/E04.**

### QA-N07 — Primera dirección (N-07)

1. **Vendedora:** agregar primera dirección de prueba, identificando comprador diferente del destinatario. Capturar los datos indicados por el procedimiento; no inventar interior o referencia inexistente.
2. **Auxiliar / Delegado:** revisar la dirección seleccionada y la indicación de nota; corregir ambigüedad antes de guía. **Esperado:** guía con destinatario real, no nombre del comprador por defecto; snapshot de la dirección usada.

### QA-N08 — Cambio de dirección con guía (N-08; CV-12)

1. **Vendedora:** sobre pedido sin salida y con PDF/guía activa, registrar domicilio cambiado y motivo; verificar si cambian reexpedición/cobertura.
2. **Delegado:** comprobar que la guía anterior **y cada guía de caja afectada** quedan inactivas sin perder PDF, y debe cargarse nueva guía de prueba antes de despacho.
3. **CEDIS:** intentar despachar con guía anterior: debe bloquear. Cargar la nueva y comprobar destino vigente.
4. **Después de la salida:** solicitar cambio de dirección; no editar silenciosamente rastreo/etiqueta. Registrar solicitud excepcional a Rafael solo si la UI y autorización existen; no prometer aceptación del transporte.

### QA-N09 — Dirección adicional (N-09)

1. **Vendedora:** cliente con una dirección existente agrega otra, con persona distinta que recibe. Verificar que ambas quedan identificables.
2. Elegir explícitamente dirección nueva para el pedido, revisar con **Auxiliar/Delegado** y producir guía solo para la elegida. Si había guía para la anterior, aplicar QA-N08. **Esperado:** no sustituir domicilio anterior por inferencia.

### QA-N10 — Recoger en tienda un nacional todavía no despachado (N-10)

1. **Vendedora:** pedir cambiar el destino a sucursal antes de la salida y documentar motivo/selección. Comprobar autorización de gerente/Administración si el procedimiento la exige.
2. **Delegado:** invalidar guía nacional pendiente, conservar PDF y diferenciar cancelación en GELIA de confirmación de la paquetería. **Auxiliar/Administración:** conservar remisión/pagos de mercancía y resolver el componente de transporte por separado.
3. **CEDIS:** preparar y registrar traslado autorizado a la sucursal, sin marcar que el cliente recibió mercancía.
4. **Auxiliar PDV:** abrir bandeja operativa, localizar **el mismo folio** y confirmar recepción física; verificar evento `recibido`. **Custodia:** registrar `en_custodia`. **Tienda:** entregar al titular con evidencia/fotografía, registrar `entregado`.
5. **Después de salida nacional:** intentar igual cambio; debe rechazarse o quedar solo como gestión excepcional documentada, sin modificar guía ni fingir recepción de tienda.
6. **Esperado:** flujo visible de recepción, custodia y entrega; ninguna terminal municipal, carátula ni recobro de mercancía. Si solo aparece `ResguardoPdv` en backend y no hay actuación posible desde Tienda, el recorrido es `FAIL/BLOCKED` con motivo exacto.

### QA-N11 — Consolidación asegurada en varias cajas (N-11; CV-14)

**Precondición obligatoria:** esquema MySQL de QA real con tabla de remisión–caja, no tablas padre ficticias ni `gelianv` sin migración. Si no existe: **BLOCKED sin ejecución**.

1. **Vendedora:** vincular dos remisiones anteriores pagadas/resguardadas al envío nacional, solicitar seguro e identificar origen de cada una.
2. **Auxiliar PDV/CEDIS:** si alguna está en PDV, confirmar salida de origen, recepción real e integración antes de empacar; no equiparar vínculo con recepción.
3. **CEDIS:** construir caja A y caja B, registrar UUID, pesos, medidas, evidencias, mercancía y **remisión completa** por caja. Intentar poner la misma remisión en dos cajas: rechazo.
4. **Vendedora:** revisar valor asegurado/costo individual de cada caja y la suma; el cargo DHL fijo se calcula conforme al contrato vigente **por cada caja asegurada**; no multiplicar un seguro global ni dividir cargo fijo entre cajas.
5. **Auxiliar:** comprobar que el valor ya pagado se declara al seguro, pero no reaparece como mercancía pendiente de pago.
6. **Delegado:** generar dos guías distintas, cada una con caja y cobertura correctas. **CEDIS:** despachar las dos con trazabilidad; si una fue recolectada y otra no, el pedido no se declara íntegramente enviado.
7. **Negativo:** probar remisión indivisible demasiado grande para la caja; no se asigna ni despacha asegurada. **Esperado:** la UI explica bloqueo sin crear relación ni alterar seguro previo.

### QA-N12 — Varias cajas sin seguro (N-12; CV-15)

1. **Vendedora/CEDIS:** reunir mercancía pagada de varias remisiones, sin seguro; CEDIS informa dos cajas con peso/medidas y evidencias.
2. **Auxiliar:** comprobar solo mercancía nueva + envíos pendientes; permitir cobro conjunto según contrato.
3. **Delegado:** una guía por caja. **CEDIS:** ejecutar recolección parcial y verificar que no se cierra el conjunto; después completar la última caja. **Esperado:** no exigir asignación remisión-caja propia del seguro.

### QA-N13 — Pedido grande sin resguardos previos (N-13)

1. **Vendedora:** crear compra grande que CEDIS divide físicamente en varias cajas, sin remisiones previas.
2. **CEDIS:** responder medidas/peso real por caja. **Vendedora:** presentar costo por número real de cajas, no por un paquete ficticio.
3. **Sin seguro:** aplicar QA-N12. **Con seguro:** intentar asegurar sin demostrar remisión completa por caja; si una sola remisión no cabe, bloquear según regla AC-12. **Esperado:** ninguna partición asegurada silenciosa.

### QA-N14 — Cancelación con/sin pago y después de salida (N-14)

1. **Vendedora:** cancelar solicitud aún sin pago, indicando causa; **CEDIS** recibe aviso, verifica estado y solo entonces libera reserva donde el proceso vigente lo permita.
2. Con mercancía pagada: **Auxiliar/Administración** revisan remisión y pago; resolución de saldo/reembolso requiere autorización y registro. No declarar que un reembolso fue realizado por haberlo solicitado.
3. Con guía creada: **Delegado** conserva evidencia, motivo y responsable del gasto sin atribuir automáticamente quién paga.
4. **Después de entrega a transporte:** no liberar stock ni declarar cancelación física; documentar gestión excepcional cuando corresponda.
5. **Esperado:** solicitud, mercancía, pago, remisión y guía no se convierten en un solo estado ambiguo. El cron de crédito al día siguiente está expresamente fuera de esta entrega; registrarlo `NOT_IMPLEMENTED`, no PASS.

### QA-N15 — Crédito Wizerp (N-15; CV-09)

1. **Vendedora:** registrar método crédito para un pedido nacional de prueba con mercancía, envío, seguro si aplica y reexpedición separados. No adjuntar comprobante de transferencia como sustituto.
2. **Auxiliar:** consultar el resultado de aprobación Wizerp de prueba. **Bloqueado:** no remisión, guía ni despacho; aviso visible a Vendedora, seguimiento a regularización; no mezclar métodos para saltar bloqueo.
3. **Aprobado:** remisión y cargo en estado de cuenta del entorno simulado, guía y salida solo con restantes condiciones cumplidas. **Esperado:** el vencimiento al día siguiente no se automatiza en esta entrega; no decir que se probó.

## 4. Resguardos y complementos físicos — recorridos por PDV y CEDIS

### QA-R04 — Mercancía pagada con paquetería pendiente (R-04)

1. **Vendedora → CEDIS:** pedido confirmado y pesado, paquetería aún pendiente.
2. **Auxiliar:** verificar pago **solo de mercancía**; emitir remisión de mercancía.
3. **CEDIS:** custodiar en CEDIS, sin guía ni despacho ni envío ficticiamente pagado.
4. **Vendedora:** recibir elección posterior del transportista; **Auxiliar:** verificar costo adicional; **Delegado/CEDIS:** guía y salida. **Esperado:** no duplicar cobro de mercancía.

### QA-R05 — Principal esperando un complemento de otra ubicación (R-05; CV-18)

1. **Vendedora:** identificar pedido principal y remisión del complemento pagado; registrar ubicación de origen PDV, mantener el principal en espera.
2. **Auxiliar PDV:** ver solicitud y mercancía específica a preparar; **Auxiliar Ventas** constata pago.
3. **Origen/traslado:** registrar salida hacia CEDIS. **Esperado:** principal sigue bloqueando empaque, guía y despacho: `en_traslado` no equivale a `recibido_cedis`.
4. **CEDIS:** recibir piezas y revisar coincidencia con remisión y evidencia. Con recepción solamente, intentar empacar: **bloqueo** hasta integración confirmada.
5. **CEDIS / responsable autorizado:** confirmar integración; re-pesar/re-cotizar si cambia conjunto, liberar preparación. **Esperado:** tránsito/recepción/integración como hechos separados, sin pérdida de historial.

### QA-R06 — Preparación, traslado y recepción del complemento (R-06)

1. **Vendedora:** enviar a PDV la relación entre pedido principal, piezas y remisión complementaria.
2. **Auxiliar Ventas / PDV:** comprobar pago antes de salida; **Auxiliar PDV:** separar piezas, registrar evidencia y entrega a traslado.
3. **Traslado:** dejar registro de salida de origen, nunca registro de recepción CEDIS simultáneo por defecto.
4. **CEDIS:** confirmar la **recepción física**, comparando piezas/remisión; si hay diferencia, reportarla y mantener principal pendiente.
5. **CEDIS:** confirmar integración solo con conjunto coincidente y completar nuevo peso/cajas/evidencia. **Esperado:** no aparece habilitación anticipada para guía o despacho.

## 5. Pruebas transversales y regresión

| ID | Actor / acción | Resultado que debe comprobarse |
|---|---|---|
| TR-01 Históricos | Vendedora abre un nacional anterior a captura 2.0 | Abre y guarda en legado sin marca `captura_nacional` ni migración implícita |
| TR-02 Concurrencia | Dos sesiones de Vendedora sobre el mismo folio; sesión B cambia una unidad mientras A intenta cerrar o anexar | A recibe 409 o conflicto vigente; no pisa la respuesta nueva ni duplica documento |
| TR-03 Seguridad | Roles sin autorización intentan crear complemento, cerrar consulta, relacionar caja, cambiar paquetería, custodiar o cancelar | Acción ausente o rechazada; estado sin mutación; evidencia de 403 donde corresponda |
| TR-04 Recolección parcial | CEDIS registra caja A recolectada; intenta cambiar largo, peso o guía de A mientras B sigue pendiente | A es inmutable, B conserva su estado, pedido no marcado íntegramente enviado |
| TR-05 Guías históricas | Vendedora cambia dirección/paquetería antes de salida y Delegado revisa guías | PDF anterior preservado pero inactivo; nuevo PDF activo por caja; no uso de etiqueta vieja |
| TR-06 Responsive | Abrir modal Nacional a **375 px y 1280 px**: alta, seguimiento, complemento, dirección, detalle de cajas y error | No desbordamiento/ocultamiento de acciones; foco y teclado utilizables; capturas por viewport |
| TR-07 Consola/red | Navegación por cada rol y acciones negativas | Sin errores JS no controlados; mensajes correctos 403/409/422; no falsear éxito tras respuesta fallida |
| TR-08 No regresión | Recorrido dirigido Tienda, Municipal M-01/M-02 y resguardos municipales afectados por archivos compartidos | Conservar funcionamiento; fallos previos de M01/snapshot listados y no «corregidos» alterando reglas |
| TR-09 Sin entrega ficticia | CEDIS registra salida al transporte y Vendedora consulta historial | No aparece «recibido por cliente» sin evento correspondiente; salida de CEDIS ≠ entrega final |
| TR-10 Datos financieros | Auxiliar revisa remisiones, pagos parciales, crédito, flete, seguro y reexpedición | Comprobante ≠ ingreso validado; crédito no se mezcla con transferencia; saldo de envío no paga mercancía |

## 6. Orden de ejecución recomendado

1. **Bloque A — Smoke operativo:** QA-N01 completo con roles; QA-N04/R04; TR-01 y TR-02. Si falla una transición central, parar y documentar antes de diversificar.
2. **Bloque B — Logística/finanzas:** QA-N02, N03, N05, N07, N08, N09, N15; TR-03, TR-05 y TR-10.
3. **Bloque C — Complementos y sucursal:** QA-N06, N10, R05 y R06. Priorizar identidad de piezas y relevo de bandejas PDV/CEDIS.
4. **Bloque D — Cajas:** QA-N12 y N13 sin seguro; QA-N11 asegurado solo tras la compuerta MySQL real.
5. **Bloque E — Cancelación, cierre y visual:** QA-N14, TR-04, TR-06, TR-07, TR-08 y TR-09. Repetir recorrido normal tras arreglos.

**No ejecutar los 18 casos en una sola sesión de agente.** Dar a Cursor un bloque por vez, conservar un solo registro de defectos y no ampliar alcance automáticamente.

## 7. Plantilla de evidencia por caso

```text
ID caso / procedimiento: QA-N__ / N-__ / CV-__
Fecha y entorno: 
Git HEAD / versión frontend / migraciones presentes: 
Rol y usuario sintético: 
Pedido principal / complemento / remisión / cajas: 
Pantalla y ruta observadas (NO inventadas): 
Precondiciones reales verificadas: 
Pasos ejecutados por clic (con orden): 
Resultado esperado del análisis V02.1: 
Resultado observado (UI + estado persistido): 
HTTP / consola relevantes: 
Capturas o evidencias (rutas): 
Estado: PASS / FAIL / BLOCKED / NOT_RUN
Motivo del bloqueo o defectos, severidad y pasos para reproducir: 
Responsable de revisión y siguiente acción: 
```

### Reglas de dictamen final de Ola 4

- **Aprobación técnica**: pruebas backend/React pertinentes, migración real ensayada cuando aplique, permisos, idempotencia y bloqueos vigentes.
- **Aprobación operativa**: cada relevo de la secuencia central realizado por UI y confirmado en la bandeja del siguiente rol; escenarios críticos negativos protegidos.
- **Liberación de producción**: decisión separada; no declarar por un build, una suite SQLite en verde o la presencia de un servicio PHP.
- **Pendientes conocidos fuera de alcance**: cron de crédito bloqueado al día siguiente y conversión *entrante* a Nacional; mantener guardas y documentación sin decir que quedaron implementados.

## 8. Prompt operativo para Cursor (usar SOLO cuando autoricen ejecutar QA)

> Ejecuta el **Bloque A** del archivo `GELIA_NACIONALES_2_0_OLA4_GUION_QA_POR_ROLES_2026-11-20.md` mediante el navegador integrado de Cursor, en entorno seguro. El archivo traduce el análisis V02.1 a pasos por rol: respétalo literalmente en objetivos, roles, datos, bloqueos y resultados esperados; no inventes pantallas ni pasos. Antes de cada caso confirma usuarios de prueba, pedido, adjuntos, paqueterías y estado de la base. Navega mediante clics y adjuntos, cambia de sesión para cada relevo, registra evidencia visual y de red. Sin cuentas, catálogo, migración o bandeja requerida: BLOCKED, no PASS. No suplas el recorrido visual con una llamada PHP directa. No toques código durante la primera pasada. Entrega el cuadro PASS/FAIL/BLOCKED/NOT_RUN y evidencias, y detente antes del Bloque B. No merge/rebase/push ni operaciones destructivas.

---

**Fuentes principales:** *Análisis de Requerimientos Gestión de pedidos — V02.1 Dudas resueltas*, N-01–N-15, R-04–R-06, CV-01/CV-06/CV-09/CV-12–CV-15/CV-18; cierre correctivo de Ola 3 del 20/11/2026. Los nombres concretos de botones distintos de los ya confirmados y las rutas de menús deben observarse en la interfaz real, no fabricarse.

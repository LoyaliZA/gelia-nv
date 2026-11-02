# Analisis de Requerimientos Gestión de pedidos - V02

Flujos operativos redactados · 29 de octubre de 2026

Operación actual de tienda, envíos municipales y nacionales, resguardos, saldos y pago de envío.

| **Control del documento** | **Descripción**                                                                                             |
|---------------------------|-------------------------------------------------------------------------------------------------------------|
| Versión                   | V02 — desarrollo narrativo completo de los casos.                                                           |
| Base de trabajo           | Cuatro documentos operativos, V01 revisada, cuestionario respondido y aclaraciones posteriores del usuario. |
| Cobertura                 | 53 escenarios y 346 pasos de operación.                                                                     |
| Alcance                   | Análisis de la operación real y de sus requisitos; sin comparación con la implementación de Gelia.          |
| Estado                    | Para revisión operativa. Los puntos abiertos están identificados; no se inventan reglas para cerrarlos.     |

## Guía de lectura

Cada ficha se lee como un diagrama de flujo redactado: causa inicial → datos → intercambio entre involucrados → decisión → continuación o excepción → resultado. Los códigos permiten seguir una ramificación sin perder el caso de origen.

Los capítulos de resguardo, saldo y pago de envío desarrollan situaciones que también aparecen como ramificaciones de los procesos de tienda o envío. No representan ventas o movimientos adicionales por sí mismos.

Las menciones a registro, relación entre documentos, fecha o responsable son requisitos de trazabilidad derivados cuando se identifican como propuesta. No afirman que hoy exista una captura automática, una pantalla o una integración en Gelia. Los campos explícitamente pendientes no se consideran validados.

## Contenido

1\. Contexto y límites del proceso

2\. Involucrados y circulación de información

3\. Documentos, datos y reglas transversales

4\. Mapa general de decisiones

5A. Tienda: 10 casos

5B. Envíos municipales: 12 casos

5C. Envíos nacionales: 15 casos

5D. Resguardos y complementos: 6 casos

5E. Saldos a favor: 9 casos

5F. Pago de envío: 1 caso

6\. Puntos abiertos y límites de interpretación

7\. Escenarios de validación

8\. Glosario y fuentes

## 1. Contexto y límites del proceso

El módulo de gestión de pedidos quedó en espera mientras se precisaban las condiciones reales de la operación. Esta versión describe esa operación sin apoyarse en lo que el sistema existente permite o deje de permitir. No incluye pantallas, tablas de base de datos, permisos técnicos ni un plan de implementación.

Actualmente la vendedora consulta con el cliente y genera el pedido en Wizerp sin pago. Descarga PDF o capturas y los comparte con CEDIS o auxiliar de PDV. El área responde disponibilidad, detalles y fotografías; la vendedora vuelve al cliente para confirmar o ajustar. La selección puede necesitar varias rondas y el pedido se detiene mientras falta decisión.

Después se reporta el pago o crédito. Auxiliares de ventas verifican el ingreso inicial y tramitan remisiones; Wizerp indica si permite el crédito. Administración revisa pagos en días posteriores, incluyendo que no se hayan regresado. La revisión posterior no sustituye la comprobación inicial.

La preparación y entrega dependen de la modalidad. Tienda recibe bolsas y puede entregarlas a custodia. Municipal se recoge en terminal y en la rutina aclarada circula de CEDIS a PDV y de PDV a terminal. Nacional sale exclusivamente de CEDIS y requiere guía. Parte de la mercancía puede esperar una fecha, otra compra, la elección de paquetería o un complemento.

El proceso registra y coordina resultados de otros procesos sin absorberlos automáticamente: Wizerp resuelve remisiones y crédito; caja PDV entrega dinero al repartidor en Jaguar; paquetería presta el transporte; la recepción física y aceptación de devoluciones no tiene aquí una política completa.

La evidencia disponible no describe uniformemente el seguimiento de paquetería hasta el domicilio del cliente. Por ello, despacho desde CEDIS, entrega a terminal y recepción final del cliente son eventos distintos, aunque en la práctica haya fotografías de algunos de ellos.

### Criterio para resolver diferencias entre fuentes

Una aclaración explícita posterior corrige la interpretación anterior en ese punto. Una respuesta parcial no elimina una regla previa que no contradiga. Si dos formulaciones no se pueden conciliar, se conservan como límite abierto; el documento no decide por la operación. Ejemplos: mayor a \$10 frente al importe exacto de \$10, vencimiento de mercancía pagada y tratamiento de costos de guías canceladas.

### Qué representa el cierre

Cada caso declara un resultado operativo alcanzable. Un pedido puede terminar su preparación y conservar un cobro de envío pendiente; una solicitud de cambio puede terminar como no viable; un resguardo puede seguir abierto sin ser un error. No se impone un único estado de “finalizado” a mercancía, dinero y transporte.

## 2. Involucrados y circulación de información

| **Involucrado**                           | **Recibe / responde / ejecuta**                                                                                                                                                                                           |
|-------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Cliente                                   | Define piezas, destino, receptor, cambios y fecha; recibe detalles/costos y proporciona decisión, documentos y pago.                                                                                                      |
| Vendedora                                 | Captura pedido y datos del cliente; comparte PDF/capturas con CEDIS/PDV, recibe evidencias, consulta al cliente, reporta pagos/notas, comunica cambios y libera resguardos según el proceso.                              |
| Auxiliares de ventas Bellaroma/CallCenter | Revisan pedido, monto y comprobantes; comprueban ingreso inicial a cuentas; tramitan remisión y consultan crédito/estado de cuenta en Wizerp; comunican bloqueos.                                                         |
| CEDIS                                     | Recibe solicitud, confirma existencia y condición con evidencias; aparta, pesa y empaca. Recibe complementos; prepara bolsas o paquetes; recibe guías para colocarlas. Origen obligatorio del nacional.                   |
| Auxiliar de PDV / tienda                  | Confirma y aparta piezas de tienda; prepara y libera apartados; recibe bolsas, coordina custodia, prepara cambios municipales y complementos de tienda.                                                                   |
| Recepción / custodia                      | Conserva pedidos pagados de tienda y participa en su retiro para entrega o movimiento autorizado. No se describieron todos los comprobantes internos.                                                                     |
| Gerentes PDV / gerente                    | Registran salida municipal en su grupo. Gerente autoriza excepciones VIP y nuevos traslados después del inicial. Gerente/Administración gestionan cambios/cancelaciones según tabla respondida.                           |
| Administración                            | Autoriza saldos y devoluciones; verifica pagos posteriormente y cajas cobradas, contempla ingresos de transporte en cortes y gestiona su control financiero.                                                              |
| Encargado de guías                        | Recibe datos seleccionados por vendedora/auxiliar, revisa domicilio y genera guías; comparte con CEDIS las utilizables y sustituye guía cuando cambia dirección.                                                          |
| Repartidor / traslado                     | Don Eduardo lleva en la rutina aclarada de CEDIS a PDV por la mañana. El reparto desde PDV lleva a terminal; informa costos/evidencias y comparte foto de la entrega que realiza. Son movimientos que deben distinguirse. |
| Cajera PDV                                | Proporciona al repartidor el dinero de Jaguar como proceso separado de caja. No se asigna aquí a la cajera todo pago o reembolso de la operación.                                                                         |
| Rafael                                    | Recibe la canalización de ciertas solicitudes nacionales después de entrega al transporte y las gestiona sin garantía de respuesta a tiempo. Su puesto formal y facultades no se detallaron.                              |
| Paquetería / terminal                     | Determina el costo en casos de terminal, recibe paquetes y ejecuta el transporte. Proporciona condiciones/cargos de seguro y reexpedición cuando corresponden.                                                            |

### Regla de intercambio

Quien solicita una acción identifica pedido/remisión y motivo; quien responde identifica mercancía, estado o costo que confirma. Las flechas “A → B” de las fichas indican quién comparte información con quién; no implican mensajería automática. Las autorizaciones expresamente documentadas se conservan, sin asignar poderes adicionales a todos los auxiliares.

## 3. Documentos, datos y reglas transversales

| **Elemento**                       | **Significado y separación necesaria**                                                                                                                                                                                                            |
|------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Pedido Wizerp                      | Solicitud comercial con piezas e importe que la vendedora genera incluso sin pago y comparte como PDF/capturas. No prueba por sí sola pago ni despacho.                                                                                           |
| Solicitud de clasificación/entrega | Instrucción operativa para una modalidad. Puede cancelarse y sustituirse en un cambio conservando pedido/remisión/pagos; no es sinónimo de cancelar toda la venta.                                                                                |
| Remisión                           | Documento tramitado por auxiliares en Wizerp después de los controles aplicables. Marca el límite entre ciertas modificaciones y un nuevo pedido complementario. Su emisión inicia los 20 días naturales de recolección aclarados por el usuario. |
| Comprobante del cliente            | Evidencia reportada de un pago. Debe comprobarse ingreso real; dos copias no son dos transferencias.                                                                                                                                              |
| Nota de crédito                    | Documento por devolución con importe aplicable a mercancía y posible remanente. Se presenta con pedido y comprobante de la diferencia cuando corresponda.                                                                                         |
| Saldo a favor                      | Importe disponible con origen y restricción. Saldo exclusivo de envío no se mezcla con mercancía. PDV lleva control propio en Drive.                                                                                                              |
| Caja / paquete                     | Unidad física con peso, medidas y eventualmente seguro/guía propios. Un envío reúne una o varias cajas y puede incorporar varias remisiones.                                                                                                      |
| Guía                               | Documento de transporte nacional. Una corrección de dirección con guía emitida cancela la anterior y genera otra. Su validez no implica recepción final del cliente.                                                                              |
| Carátula municipal                 | Consignatario, teléfono, destino, PAGADO/POR COBRAR e INE según la regla documentada. El pago ante terminal no prueba el pago del transporte por el cliente.                                                                                      |
| Ficha de terminal                  | Referencia para recoger. En la secuencia Jaguar se comparte al cliente después del pago de envío.                                                                                                                                                 |
| Foto/evidencia                     | Respalda la condición de piezas, contenido o un evento de entrega. Debe indicarse qué evento acredita; no se le atribuye un alcance mayor al fotografiado.                                                                                        |

### Reglas de operación confirmadas y su alcance

| **Regla**                               | **Aplicación**                                                                                                                                                                                                                             |
|-----------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Entrega y pago separados                | Municipal, nacional y tienda son modalidades; transferencia y crédito son métodos. Ubicación, custodia y pago de transporte pueden avanzar de forma distinta.                                                                              |
| Disponibilidad con decisión del cliente | CEDIS/PDV responde detalles y fotografías. La vendedora consulta al cliente y el pedido queda detenido mientras no decide.                                                                                                                 |
| Pagos y revisión posterior              | Auxiliares verifican inicialmente ingreso y documentos; Administración revisa después posibles retornos. Qué hacer con un retorno detectado cuando ya se entregó no está definido.                                                         |
| Crédito                                 | Wizerp permite o bloquea remisión. Puede cubrir mercancía, envío, seguro y reexpedición. Tolerancia hasta el día siguiente para regularización; sin regla de cierre después si continúa bloqueado.                                         |
| Sin mezcla de métodos                   | No combinar métodos en ninguna clasificación. Varias transferencias pueden completar un pedido. La nota de crédito más transferencia está expresamente descrita como aplicación de saldo y diferencia; no se elimina ese caso.             |
| Apartado y TIENDA MD                    | Apartados lunes-viernes hasta 17:00 y sábado 9:00-13:00; VIP con excepción del gerente. TIENDA MD lunes-viernes, corte 13:00, no sábado; fuente inicial restringe a Bellaroma.                                                             |
| Recolección y segundo traslado          | Horario lunes-viernes 9:00-17:00 y sábado 9:00-13:00. Después de un traslado, otro requiere autorización de gerente. Cliente no recoge en CEDIS.                                                                                           |
| 20 días para recoger                    | Naturales desde la remisión. Vendedora da seguimiento y CEDIS comunica atrasos. No se definió qué ocurre con mercancía pagada al vencer.                                                                                                   |
| 20 días para saldo                      | Cuestionario indica 20 días para uso y que después no hay devolución. No se convierte en borrado o apropiación automática. Aplicación de la fecha de remisión a todos los orígenes de saldo, en especial sin remisión, requiere precisión. |
| Umbral de excedente                     | Última aclaración: mayor a \$10. Menor a \$10 puede usarse ese mismo día con observación de vendedora y revisión de auxiliar; exacto \$10 y alcance a otros tipos de saldo quedan abiertos.                                                |
| Reembolsos                              | Administración autoriza. En cancelaciones la respuesta indica de 3 a 5 días hábiles; en otra respuesta solo dice 3 a 5 días. No se declara realizado por estar autorizado.                                                                 |
| Resguardos                              | Azul indica espera y amarillo que puede prepararse en los casos descritos. N/A significa no aplica. Resguardo de tienda se libera al acudir el cliente; otras liberaciones las instruye vendedora con condiciones aún por detallar.        |
| Salida municipal                        | Rutina CEDIS → PDV → terminal. Gerentes PDV registran la salida; recogida del repartidor hacia terminal impide cambiar a recolección. No equiparar traslado interno con recepción final.                                                   |
| Salida nacional y guías                 | Solo desde CEDIS. Antes de salida se gestionan cambios; después ciertas solicitudes se canalizan con Rafael sin garantía. Cambio de paquetería no permitido después de salida.                                                             |
| Cajas y seguro                          | Seguro opcional. Con seguro: contenido/evidencia por caja, cobertura separada y remisiones completas por caja. Sin seguro: distribución en varias cajas y cobro conjunto permitido.                                                        |
| Jaguar                                  | Costo conocido en terminal; repartidor informa evidencia; cajera aporta dinero en proceso PDV; cliente paga después, se reporta PAGO ENVÍO y se comparte ficha.                                                                            |

### Datos mínimos de trazabilidad propuestos

Conservar referencia del cliente y pedido, fecha y actor de cada instrucción, documento vigente y documentos sustituidos, piezas/cantidades, ubicación, evidencia del cambio, comprobantes aplicados, remisiones incorporadas y resultado de cada entrega. Para cajas aseguradas, asociar contenido, peso, valor, cobertura y guía. Es una organización de requisitos; no afirma un modelo de datos existente.

### Datos del cliente y del destino

Municipal: consignatario, teléfono, terminal/destino, condición de cobro e INE, conforme a fuentes. Nacional: calle, colonia, código postal, referencias, interior/exterior, consignatario e indicación de si lleva nota, según respuesta. Municipio/ciudad, estado y teléfono deben confirmarse como campos del formato nacional completo; se identifican como necesidad, no como lista ya validada. No se decide por el cliente una dirección faltante.

## 4. Mapa general de decisiones

**01.** Inicio: cliente solicita mercancía y forma de recibirla. Vendedora genera pedido sin pago y determina si la atención corresponde a CEDIS o PDV.

**02.** Confirmación: CEDIS/PDV responde existencia, condición y evidencias. ¿Cliente acepta? Si no hay decisión, esperar; si modifica, repetir consulta; si desiste, comunicar cierre y resolver reserva/pago si existe.

**03.** Definición logística: ¿tienda, municipal o nacional? Tienda requiere sucursal/fecha; municipal requiere terminal y carátula; nacional requiere domicilio y pesaje desde CEDIS.

**04.** Complementos: ¿hay mercancía previa pagada o piezas en otro almacén? Identificar remisiones, solicitar retiro/traslado y esperar recepción física antes de declarar completo el conjunto.

**05.** Condición de espera: ¿fecha futura, acumulación, paquetería pendiente o complemento pendiente? Mantener resguardo con motivo; la mercancía comprometida no queda libre para otra venta.

**06.** Cobro/documentación: ¿transferencia, crédito o aplicación de nota/saldo? Verificar comprobantes o resultado Wizerp; identificar lo que falta. Jaguar separa mercancía y costo de transporte posterior.

**07.** Preparación: ¿sin remisión o con remisión? Las modificaciones abiertas se tramitan según el caso; con remisión, los anexos generan nuevo pedido/proceso relacionado. Cajas con seguro requieren distribución de remisiones completas.

**08.** Documentos de transporte: municipal usa carátula/ficha; nacional guía. Si cambia dirección con guía emitida, cancelar guía anterior y generar otra. Revisar costos y pagos pendientes.

**09.** Salida: tienda → entrega o custodia; municipal → traslado a PDV y reparto a terminal; nacional → despacho desde CEDIS. Registrar el evento real y su evidencia.

**10.** Cierre: ¿se entregó, sigue custodiado, el envío está en transporte o falta cobrar Jaguar? Cerrar únicamente el componente resuelto y mantener explícito el pendiente. Saldos/reembolsos conservan control separado.

### Índice de los casos

#### Tienda

[<u>T-01 — Apartado para recoger y pagar en tienda</u>](#bookmark=id.ok45cbdhmphr)

[<u>T-02 — Transferencia para recoger posteriormente en tienda</u>](#bookmark=id.dehual6ybcbw)

[<u>T-02.1 — Transferencia para recoger el mismo día — TIENDA MD</u>](#bookmark=id.joi094iqmp4n)

[<u>T-03 — Recoger en City Center y cambio de sucursal</u>](#bookmark=id.3w494m6g6ikq)

[<u>T-04 — Cambio de clasificación o modalidad de entrega</u>](#bookmark=id.u4bkzeiyzt1y)

[<u>T-05 — Complementar un envío con pedidos pagados en custodia</u>](#bookmark=id.hj0c4wjcfldi)

[<u>T-06 — Anexar piezas a un pedido de tienda</u>](#bookmark=id.92hms3dt2j8)

[<u>T-07 — Cambiar o retirar piezas antes del pago</u>](#bookmark=id.fm1c8lz5btut)

[<u>T-08 — Faltantes, disponibilidad parcial o piezas con detalles</u>](#bookmark=id.nv7kxwfj91jp)

[<u>T-09 — Recoger en tienda con crédito comercial</u>](#bookmark=id.ngdc2p8lhyps)

#### Municipales

[<u>M-01 — Envío municipal ordinario a terminal</u>](#bookmark=id.sumzys7owxob)

[<u>M-02 — Convertir un pedido de tienda a envío municipal</u>](#bookmark=id.4rbv51p0s9rx)

[<u>M-03 — Anexar mercancía a una solicitud municipal</u>](#bookmark=id.d70lhc7n6pek)

[<u>M-04 — Incorporar pedidos pagados en tienda a un envío municipal nuevo</u>](#bookmark=id.l7ssfuvoh6jk)

[<u>M-05 — Transporte Jaguar o terminal con costo posterior</u>](#bookmark=id.q61yr148rqb9)

[<u>M-06 — Carátula municipal de cliente nuevo o existente</u>](#bookmark=id.9pofpaz9kkwg)

[<u>M-07 — Dejar sin efecto el envío municipal y recoger en tienda</u>](#bookmark=id.wiu2ajgext10)

[<u>M-08 — Envío municipal a crédito</u>](#bookmark=id.tj2jmmf404bz)

[<u>M-09 — Resguardo municipal con mercancía y envío pagados</u>](#bookmark=id.a765j4ldr52l)

[<u>M-10 — Resguardo municipal para acumular mercancía</u>](#bookmark=id.gpvo5e83szqc)

[<u>M-11 — Aplicar nota de crédito por devolución al pago municipal</u>](#bookmark=id.5nhuby6sf4sy)

[<u>M-12 — Cubrir un pedido con dos o más transferencias</u>](#bookmark=id.70xko9u5umx1)

#### Nacionales

[<u>N-01 — Envío nacional ordinario desde CEDIS</u>](#bookmark=id.ha4xfo9lv3c)

[<u>N-02 — Cargo por zona extendida o reexpedición</u>](#bookmark=id.32z4sxgtn0pl)

[<u>N-03 — Seguro opcional de envío nacional</u>](#bookmark=id.xy171nbcoov7)

[<u>N-04 — Paquetería pendiente de elección del cliente</u>](#bookmark=id.pukqcce9ohm6)

[<u>N-05 — Cambio de paquetería</u>](#bookmark=id.g84ju06rm6c9)

[<u>N-06 — Anexar piezas al envío nacional</u>](#bookmark=id.mygbk2qwkow8)

[<u>N-07 — Registrar la primera dirección nacional</u>](#bookmark=id.jv6blyay743e)

[<u>N-08 — Modificar dirección y sustituir guía cuando ya existe</u>](#bookmark=id.byd5i3bvvekv)

[<u>N-09 — Añadir una dirección nacional adicional</u>](#bookmark=id.ywfhdjewtez5)

[<u>N-10 — Cambiar un envío nacional por recolección en tienda</u>](#bookmark=id.omw5wv9d31zy)

[<u>N-11 — Consolidar remisiones y resguardos en varias cajas con seguro</u>](#bookmark=id.7gj2ku6y1n9u)

[<u>N-12 — Consolidar pedidos en varias cajas sin seguro</u>](#bookmark=id.1w1it6m9qc62)

[<u>N-13 — Pedido nacional grande sin resguardos previos</u>](#bookmark=id.btjxurnqz5oe)

[<u>N-14 — Cancelar un pedido por falta de pago, error o solicitud del cliente</u>](#bookmark=id.qm4biveo3t5n)

[<u>N-15 — Envío nacional con crédito comercial</u>](#bookmark=id.x502banb1rhl)

#### Resguardos

[<u>R-01 — Custodia de pedidos de tienda pagados</u>](#bookmark=id.b1dlow9jbvfa)

[<u>R-02 — Espera de fecha para envío municipal ya pagado</u>](#bookmark=id.w51z5sdt9gyw)

[<u>R-03 — Acumulación municipal con número de cajas no aplicable</u>](#bookmark=id.mznk7l2j2w5d)

[<u>R-04 — Mercancía nacional pagada sin paquetería definida</u>](#bookmark=id.ebcszumrpjuo)

[<u>R-05 — Pedido nacional principal esperando complemento de otro almacén</u>](#bookmark=id.qwtajyaeg1iu)

[<u>R-06 — Preparar, trasladar y recibir el complemento nacional</u>](#bookmark=id.ts5l5b6c7jwq)

#### Saldos

[<u>S-01 — Depósito superior al importe solicitado</u>](#bookmark=id.x1y8h6bayeh8)

[<u>S-02 — Retirar una pieza de un pedido recién pagado</u>](#bookmark=id.o36b5ml3nu87)

[<u>S-03 — Depósito por error sin pedido asociado</u>](#bookmark=id.ytakxbcahe2a)

[<u>S-04 — Devolución de mercancía con nota de crédito</u>](#bookmark=id.a89nvr1rdn3m)

[<u>S-05 — Aplicar saldo a favor originado en PDV en otra compra</u>](#bookmark=id.1g642e4ih68t)

[<u>S-06 — Saldo de envío por cambio a recolección</u>](#bookmark=id.umtt6qercyof)

[<u>S-07 — Transferencia duplicada por error</u>](#bookmark=id.tblia8j831yj)

[<u>S-08 — Cobro excesivo por error de la vendedora</u>](#bookmark=id.9ghsxqpgetmv)

[<u>S-09 — Saldo de envío por costo final menor o cambio de paquetería</u>](#bookmark=id.1csdzlu8tpjf)

#### Pago envío

[<u>P-01 — Reportar y verificar un PAGO ENVÍO separado</u>](#bookmark=id.35m9r3cxcgwo)

## 5A. Tienda

Las fichas siguientes detallan los intercambios, decisiones y resultados. Las referencias a otro caso indican la continuación aplicable; no duplican el cobro ni el movimiento físico.

<a id="bookmark=id.ok45cbdhmphr"></a>

### T-01 — Apartado para recoger y pagar en tienda

**Causa y caso de entrada:** El cliente desea apartar piezas disponibles en PDV-Aromas Carmen para pagarlas y recogerlas presencialmente.

**Involucrados y alcance:** Cliente; auxiliar de PDV. La vendedora interviene cuando canaliza la solicitud o recibe el pago según la rutina aclarada; CEDIS no participa en este caso.

**Datos/documentos que recibe o comparte el proceso:** Cliente y contacto; piezas y cantidades solicitadas; PDV de recolección; fecha del apartado; observaciones de condición y evidencias de las piezas. Como control propuesto: hora límite y autorización de excepción, si existe.

#### Flujo operativo

**01.** Cliente → auxiliar de PDV, directamente o por conducto de la vendedora: comunica qué piezas y cantidades quiere apartar y que no pagará por transferencia bajo esta modalidad.

**02.** Auxiliar de PDV: revisa la existencia física en tienda. Responde con cantidades disponibles, detalles visibles y fotografías del pedido; no solicita surtido a CEDIS.

**03.** Vendedora o auxiliar → cliente: comunica faltantes o detalles. Si el cliente no ha decidido, el apartado queda pendiente de su respuesta; no se sustituye una pieza por decisión unilateral del personal.

**04.** Auxiliar de PDV: separa las piezas aceptadas y conserva su identificación para el cliente. La vigencia ordinaria termina a las 17:00 de lunes a viernes y a las 13:00 el sábado, dentro del horario sabatino de 9:00 a 13:00.

**05.** Cliente: acude a pagar y recoger. El personal que recibe el pago comunica su confirmación al auxiliar; la fuente no especifica un puesto único para todos los cobros presenciales.

**06.** Auxiliar de PDV: entrega las piezas pagadas. Se toma la fotografía de entrega descrita para pedidos de tienda y se identifica el pedido entregado; la asociación de foto, responsable y fecha es un control de trazabilidad propuesto.

**07.** Si el cliente no acude dentro de la vigencia: el auxiliar de PDV libera las piezas, salvo excepción VIP autorizada por gerente. No se aplica esta liberación por falta de pago a mercancía que ya quedó pagada.

**Decisiones y ramificaciones:** Faltante: T-08. Pago por transferencia: T-02 o T-02.1. Cambio de entrega: T-04. Anexo: T-06. Excepción VIP: conservar autorización del gerente.

**Resultado y condición de cierre:** Piezas entregadas al cliente con evidencia, o apartado vencido con piezas liberadas; si hay un cambio de modalidad, queda enlazado el proceso que lo sustituye.

**Trazabilidad de la ficha:** Tienda P1; cuestionario 1, 6 y 26; aclaración posterior de horario.

<a id="bookmark=id.dehual6ybcbw"></a>

### T-02 — Transferencia para recoger posteriormente en tienda

**Causa y caso de entrada:** El cliente quiere dejar pagada la mercancía y recogerla al día siguiente o en una fecha posterior en una sucursal.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas; auxiliar de PDV; recepción/custodia; personal que realiza el traslado; Administración en revisión posterior.

**Datos/documentos que recibe o comparte el proceso:** Cliente; pedido de Wizerp con piezas, cantidades e importe; sucursal destino; condiciones y fotografías; comprobantes de transferencia; remisión; bolsas trasladadas e identificación de custodia.

#### Flujo operativo

**01.** Cliente → vendedora: comunica mercancía y sucursal de recolección. La vendedora genera en Wizerp el pedido sin pago y descarga el PDF o capturas.

**02.** Vendedora → CEDIS: comparte el pedido y solicita confirmación de piezas para tienda. Se prefiere surtir desde CEDIS; si no hay existencia, se consulta disponibilidad a PDV.

**03.** CEDIS o auxiliar de PDV → vendedora: responde cantidades disponibles, detalles y fotografías. La vendedora transmite la respuesta al cliente y recibe su aceptación o solicitud de ajuste; mientras decide, el pedido se mantiene detenido.

**04.** Cliente → vendedora: transfiere el importe confirmado y comparte comprobante. Vendedora → auxiliar de ventas: entrega pedido, importe y comprobantes para revisión.

**05.** Auxiliar de ventas: comprueba el ingreso a la cuenta, la correspondencia de comprobantes e importe y emite la remisión en Wizerp cuando procede. Administración verifica posteriormente que el pago no se haya regresado; no se describe su revisión posterior como requisito de espera para preparar.

**06.** CEDIS: prepara la mercancía en bolsas con evidencias y observaciones; la envía a la sucursal elegida. Si se surte desde PDV, el auxiliar prepara allí y se conserva esa procedencia.

**07.** Auxiliar de PDV: recibe y verifica bolsas y observaciones; después las entrega a recepción para custodia. Debe quedar identificado qué pedido está en custodia y dónde; el comprobante exacto de cada entrega interna sigue pendiente.

**08.** Vendedora: da seguimiento a la recolección dentro de los 20 días naturales contados desde la emisión de la remisión. CEDIS comunica el atraso si es necesario. Al acudir el cliente, el personal de tienda libera la custodia, entrega y toma fotografía de entrega.

**Decisiones y ramificaciones:** Destino City Center: T-03. Mismo día: T-02.1. Crédito: T-09. Resguardo en tienda: R-01. Incorporación a envío: T-05. No recoger en 20 días: registrar pendiente de gestión, sin asumir cancelación ni liberación automática.

**Resultado y condición de cierre:** Pedido pagado y documentado, localizado en la sucursal y finalmente entregado al cliente; hasta la recolección permanece bajo custodia identificable.

**Límite aún no definido:** No se ha definido la consecuencia operativa al vencer los 20 días de un pedido pagado ni el comprobante interno de recepción/retiro de custodia.

**Trazabilidad de la ficha:** Tienda P2; cuestionario 2, 3, 7 y 26; aclaración del inicio desde remisión.

<a id="bookmark=id.joi094iqmp4n"></a>

### T-02.1 — Transferencia para recoger el mismo día — TIENDA MD

**Causa y caso de entrada:** El cliente solicita pago por transferencia y recolección el mismo día bajo la modalidad prioritaria TIENDA MD.

**Involucrados y alcance:** Cliente; vendedora de Bellaroma conforme a la restricción inicial; CEDIS; auxiliar de ventas; auxiliar de PDV; personal de traslado.

**Datos/documentos que recibe o comparte el proceso:** Pedido de Wizerp; cliente; sucursal; piezas y evidencias; fecha y hora de reporte del pago; comprobante; remisión; confirmación de disponibilidad y traslado.

#### Flujo operativo

**01.** Cliente → vendedora: solicita recoger ese día. La vendedora identifica expresamente TIENDA MD y la sucursal, en lugar de prometer entrega ordinaria al día siguiente.

**02.** Vendedora → CEDIS: envía PDF o capturas del pedido sin pago y solicita surtido para traslado del mismo día. CEDIS responde disponibilidad, detalles y evidencias; la vendedora consulta cualquier ajuste con el cliente.

**03.** Vendedora: verifica que sea de lunes a viernes y que el pago pueda reportarse dentro del corte de la 1:00 p. m. Los sábados no se realiza esta modalidad.

**04.** Cliente → vendedora → auxiliar de ventas: entrega comprobante y pedido definitivo. La auxiliar verifica ingreso, monto y documentos y emite la remisión si procede.

**05.** CEDIS: prepara las bolsas y coordina el traslado a PDV. El auxiliar de PDV recibe, verifica e identifica el pedido para recolección ese día.

**06.** Cliente: recoge dentro del horario de atención. Tienda entrega y toma fotografía. Si no recoge, el personal debe conservar identificada la mercancía pagada; no se trata como apartado sin pagar.

**07.** Si el pago se presenta después del corte: no se procesa bajo TIENDA MD. La vendedora comunica el impedimento y acuerda cómo continuar; no se presume reprogramación automática.

**Decisiones y ramificaciones:** Fecha posterior: T-02. Piezas ya disponibles en PDV: la fuente inicial admite recolección ese día, pero falta precisar si se tramita como TIENDA MD o apartado con otro pago. Sábado: usar la modalidad que sí corresponda, sin crear TIENDA MD.

**Resultado y condición de cierre:** Pedido validado, trasladado y entregado el mismo día cuando cumple las condiciones; en caso contrario, queda informado y pendiente de acuerdo de continuación.

**Límite aún no definido:** Se conserva la exclusividad de Bellaroma de la fuente inicial; no se explicó cómo se aplica frente a pedidos de otras vendedoras ni la alternativa exacta tras el corte.

**Trazabilidad de la ficha:** Tienda P2.1 y tipos; cuestionario 5; aclaraciones de horario.

<a id="bookmark=id.3w494m6g6ikq"></a>

### T-03 — Recoger en City Center y cambio de sucursal

**Causa y caso de entrada:** El cliente elige City Center como destino de recolección o pide cambiarlo cuando el pedido ya está en otra sucursal.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas; auxiliares de las sucursales; recepción/custodia; gerente para segundo traslado.

**Datos/documentos que recibe o comparte el proceso:** Pedido; sucursal solicitada; ubicación actual; comprobante y remisión; bolsas; solicitud y autorización de cambio cuando aplique.

#### Flujo operativo

**01.** Cliente → vendedora: elige City Center. La vendedora captura esa sucursal en la solicitud y comparte pedido sin pago con CEDIS, o consulta PDV si procede por disponibilidad.

**02.** CEDIS/PDV → vendedora → cliente: comunica piezas, condiciones y evidencias. El cliente confirma; después paga y la auxiliar de ventas verifica el ingreso y emite la remisión.

**03.** CEDIS y traslado: preparan y envían las bolsas a City Center. La sucursal recibe, verifica y entrega a custodia hasta la recolección.

**04.** Si el cliente cambia de sucursal antes del traslado: la vendedora comunica la nueva instrucción a quienes preparan y trasladan, conservando la identificación del pedido y su pago.

**05.** Si el traslado ya ocurrió: la vendedora informa que el cliente debe recoger donde lo solicitó. Solo un gerente puede autorizar un nuevo traslado.

**06.** Con autorización: origen y destino coordinan el movimiento y confirman recepción. La ruta física de ese traslado excepcional debe definirse; no se asume que sea directa ni vía CEDIS. El cliente no pasa a recoger a CEDIS.

**07.** Cliente: recoge en la sucursal finalmente autorizada. Tienda libera custodia y conserva fotografía de entrega.

**Decisiones y ramificaciones:** Sin autorización de segundo traslado: permanece el destino original. Incorporación posterior a envío: T-05. Solicitud de envío municipal: M-02.

**Resultado y condición de cierre:** Pedido entregado en la sucursal elegida o excepcionalmente autorizada, con ubicación final y movimiento identificables.

**Límite aún no definido:** Ruta y comprobantes del traslado excepcional entre sucursales no quedaron definidos.

**Trazabilidad de la ficha:** Tienda P3; cuestionario 8 y 26.

<a id="bookmark=id.u4bkzeiyzt1y"></a>

### T-04 — Cambio de clasificación o modalidad de entrega

**Causa y caso de entrada:** El cliente ya no quiere o no puede recibir el pedido de la forma inicialmente solicitada y pide otra modalidad.

**Involucrados y alcance:** Cliente; vendedora; gerente/Administración según la autorización del cambio; CEDIS o PDV; auxiliar de ventas; custodia; encargado de guías si afecta envío nacional.

**Datos/documentos que recibe o comparte el proceso:** Solicitud original; modalidad anterior y nueva; motivo; evidencia del cliente o identificación de error operativo; pedido, remisión y pagos existentes; ubicación; nueva dirección o terminal cuando corresponda.

#### Flujo operativo

**01.** Cliente → vendedora: solicita la nueva modalidad. Si la corrección proviene de un error de la vendedora, se registra como tal, sin atribuirlo al cliente.

**02.** Vendedora: identifica ubicación, pago, remisión y avance de la preparación. Comunica el cambio para la gestión de gerente/Administración y a CEDIS/PDV; si hay custodia, coordina su retiro autorizado.

**03.** Vendedora: cancela únicamente la solicitud con la clasificación anterior y crea o presenta la solicitud correspondiente a la nueva modalidad. Esta operación no implica por sí sola cancelar la remisión ni cobrar nuevamente las piezas.

**04.** Vendedora → área ejecutora: comparte pedido y remisión relacionados, pagos reconocidos, evidencia, nuevo destino y solicitud sustituta. Como trazabilidad propuesta, ambas solicitudes conservan referencia mutua.

**05.** CEDIS/PDV: responde si las piezas siguen disponibles para el cambio y qué traslado o nuevo empaque requiere. Si el pedido municipal ya salió hacia terminal, no procede convertirlo a recolección conforme a M-07.

**06.** Auxiliar de ventas y vendedora: revisan cargos pendientes o diferencias. Si queda saldo de envío, se restringe a envío; si falta un cargo, se solicita y verifica el pago antes del paso que lo requiere.

**07.** Área del nuevo proceso: continúa desde el estado físico y documental real, aplicando sus reglas de preparación, custodia, guía o salida.

**Decisiones y ramificaciones:** A municipal: M-02. A nacional: N-01, siempre con salida CEDIS. A tienda: T-02 o N-10/M-07 según origen. Guía con dirección distinta: N-08. Cargos a ajustar: S-06/S-09/P-01 según el caso.

**Resultado y condición de cierre:** Modalidad actualizada mediante una solicitud sustituta relacionada con la anterior, sin perder mercancía, pagos, remisión ni causa del cambio.

**Trazabilidad de la ficha:** Tienda P4; cuestionario 1, 9 y 10; municipales P7.

<a id="bookmark=id.hj0c4wjcfldi"></a>

### T-05 — Complementar un envío con pedidos pagados en custodia

**Causa y caso de entrada:** El cliente realiza un nuevo envío y pide incorporar pedidos pagados que dejó para recoger en tienda.

**Involucrados y alcance:** Cliente; vendedora; recepción/custodia; auxiliar de PDV; traslado; CEDIS; auxiliar de ventas; encargado de guías si es nacional.

**Datos/documentos que recibe o comparte el proceso:** Remisiones anteriores y nueva solicitud; cliente; piezas y bolsas; ubicación de custodia; evidencia de incorporación; destino; peso/cajas posteriores; cargos de envío pendientes.

#### Flujo operativo

**01.** Cliente → vendedora: identifica qué pedidos pagados desea incorporar al envío nuevo. La vendedora reúne sus remisiones y comprueba su ubicación, sin registrarlos como nueva compra de las mismas piezas.

**02.** Vendedora → auxiliar de PDV y custodia: solicita retirar los pedidos identificados y destinarlos al complemento. Se separan de la espera de recolección en tienda para no entregarlos por duplicado.

**03.** Auxiliar de PDV: verifica bolsas y remisiones, responde qué mercancía prepara para traslado y conserva las observaciones de condición. En este caso aclarado, las piezas se envían de PDV a CEDIS.

**04.** Traslado → CEDIS: lleva el complemento. CEDIS confirma su recepción e integración con el pedido principal; si no ha llegado, el principal queda en espera de complemento.

**05.** CEDIS → vendedora: responde empaque, peso, número de cajas y evidencias que correspondan al conjunto. Con seguro nacional identifica contenido por caja y respeta remisiones completas.

**06.** Vendedora y auxiliar de ventas: cobran únicamente mercancía nueva o cargos pendientes, reconociendo las remisiones pagadas. Si el pago es a crédito, se aplica su validación sin mezclar métodos.

**07.** CEDIS: termina el empaque y continúa el envío municipal o nacional. La nueva salida debe seguir relacionada con todas las remisiones incorporadas.

**Decisiones y ramificaciones:** Complemento aún en tránsito: R-05/R-06. Nacional con seguro: N-11; sin seguro: N-12. Municipal: M-04. Cambio completo de modalidad de un único pedido: T-04.

**Resultado y condición de cierre:** Pedidos antes custodiados integrados físicamente al envío, sin duplicar el cobro de mercancía y conservando la relación entre sus remisiones.

**Trazabilidad de la ficha:** Tienda P5; V01 revisada T-05; cuestionario 11, 23 y 28.

<a id="bookmark=id.92hms3dt2j8"></a>

### T-06 — Anexar piezas a un pedido de tienda

**Causa y caso de entrada:** El cliente pide más piezas después de que la vendedora ya solicitó el pedido.

**Involucrados y alcance:** Cliente; vendedora; CEDIS o auxiliar de PDV; auxiliar de ventas; custodia y traslado si el anexo se integra a un pedido ya preparado.

**Datos/documentos que recibe o comparte el proceso:** Pedido original; piezas adicionales, cantidades y evidencia de solicitud; disponibilidad; existencia o no de remisión; pago adicional; referencias del nuevo pedido cuando corresponda.

#### Flujo operativo

**01.** Cliente → vendedora: indica las piezas adicionales. La vendedora conserva la conversación y la identificación del perfume, y consulta el estado del pedido original.

**02.** Vendedora → CEDIS/PDV: comparte las piezas, cantidades, pedido de referencia y evidencia; solicita disponibilidad y detalles. El área responde con piezas disponibles y fotografías.

**03.** Si no existe remisión y el caso cumple la condición inicial del mismo día: la vendedora ajusta el pedido en Wizerp, comparte la versión vigente y consulta al cliente cualquier faltante o cambio de importe.

**04.** Si ya existe remisión: se genera un nuevo pedido por las piezas adicionales y se relaciona como complemento del anterior, sin alterar informalmente la remisión emitida.

**05.** Cliente → vendedora → auxiliar de ventas: paga el anexo; la auxiliar comprueba ingreso y monto y emite la remisión aplicable. Ningún anexo o complemento se envía sin estar pagado según la aclaración recibida.

**06.** CEDIS/PDV: integra físicamente las piezas cuando aún puede hacerlo y confirma la preparación. Si el principal está en custodia o en otro almacén, se coordina retiro o traslado en lugar de asumir que ya está unido.

**07.** Tienda: recibe y entrega el conjunto al cliente con sus referencias y evidencia, o lo mantiene en custodia si la recolección es posterior.

**Decisiones y ramificaciones:** Pedido original ya documentado: nuevo pedido más T-05 cuando se integra a envío. Falta de piezas: T-08. Anexo al día siguiente sin remisión: no hay regla cerrada; no se autoriza ni rechaza automáticamente por inferencia.

**Resultado y condición de cierre:** Piezas adicionales confirmadas, pagadas y entregadas junto al pedido cuando procede, o nuevo pedido relacionado pendiente de integración.

**Límite aún no definido:** No quedó resuelto el anexo al día siguiente sin remisión ni el momento exacto de corte de integración de un complemento de tienda.

**Trazabilidad de la ficha:** Tienda P6; cuestionario 11.

<a id="bookmark=id.fm1c8lz5btut"></a>

### T-07 — Cambiar o retirar piezas antes del pago

**Causa y caso de entrada:** El cliente pide sustituir o eliminar piezas de un pedido solicitado pero todavía sin pago y sin remisión.

**Involucrados y alcance:** Cliente; vendedora; CEDIS o auxiliar de PDV. Gerente/Administración intervienen si el caso requiere gestión de una corrección o cancelación.

**Datos/documentos que recibe o comparte el proceso:** Pedido vigente; piezas retiradas y sustitutas; cantidades; diferencia de importe; motivo; evidencia del cliente o error operativo; respuesta de disponibilidad.

#### Flujo operativo

**01.** Cliente → vendedora: comunica qué retira o sustituye. La vendedora verifica que el pedido no esté pagado ni tenga remisión y conserva evidencia de la instrucción.

**02.** Vendedora → CEDIS/PDV: solicita retirar la reserva de las piezas excluidas y confirmar las sustitutas; identifica la versión del pedido que se modifica.

**03.** CEDIS/PDV → vendedora: responde disponibilidad, detalles y evidencias de las sustitutas. Mientras falta decisión del cliente, el pedido permanece detenido.

**04.** Vendedora → cliente: presenta el nuevo conjunto y total. Tras aceptación, actualiza el pedido en Wizerp y comparte el archivo vigente con el área de preparación.

**05.** CEDIS/PDV: ajusta las piezas separadas conforme a la instrucción recibida; no mantiene como comprometidas para ese pedido las piezas retiradas.

**06.** Cliente y auxiliar de ventas: continúan con pago, comprobación y remisión del pedido definitivo; después se prepara para la modalidad acordada.

**Decisiones y ramificaciones:** Si ya pagó o existe remisión: no se aplica directamente este cambio previo; gestionar modificación documentada o cancelación según T-04/N-14 y saldos S-02/S-08. Criterio exacto entre observación y cancelación permanece pendiente.

**Resultado y condición de cierre:** Pedido previo al pago corregido, con selección y total aceptados y reserva física coherente con su versión vigente.

**Trazabilidad de la ficha:** Tienda P7; cuestionario 4 y 10.

<a id="bookmark=id.nv7kxwfj91jp"></a>

### T-08 — Faltantes, disponibilidad parcial o piezas con detalles

**Causa y caso de entrada:** CEDIS o PDV no encuentra todas las piezas solicitadas, solo tiene parte de la cantidad o detecta condiciones que el cliente debe conocer.

**Involucrados y alcance:** Vendedora; cliente; CEDIS o auxiliar de PDV. En esta consulta inicial no interviene todavía otra área por el solo hecho del faltante.

**Datos/documentos que recibe o comparte el proceso:** Pedido sin pago; piezas solicitadas y disponibles; cantidades; detalles y fotos; alternativas consultadas; respuesta del cliente y versión final del pedido.

#### Flujo operativo

**01.** Vendedora → CEDIS/PDV: comparte PDF o capturas del pedido de Wizerp y solicita confirmación de existencias y condiciones.

**02.** CEDIS/PDV → vendedora: especifica qué falta, qué cantidad sí tiene y qué detalles presenta la mercancía; acompaña las evidencias correspondientes.

**03.** Vendedora → cliente: transmite la información y recibe la decisión. La comunicación es iterativa: pueden producirse varias consultas antes de cerrar la selección.

**04.** Mientras no hay decisión: la preparación del pedido queda detenida. No se da por aceptada una pieza con detalles ni una sustitución por el silencio del cliente.

**05.** Si el cliente retira o sustituye: la vendedora actualiza el pedido y vuelve a consultar lo necesario. Si solicita otra ubicación, se coordina un surtido/complemento con sus propios responsables. Si desiste, se comunica el cierre al área que apartó.

**06.** Una vez aceptadas piezas, cantidades y condiciones: la vendedora presenta el importe definitivo y continúa con pago, verificación y remisión según la modalidad.

**Decisiones y ramificaciones:** Sustitución/retiro: T-07. Complemento de otro almacén para nacional: R-05/R-06. Cancelación: N-14 cuando corresponda al caso documentado. La espera de reposición no tiene plazo acordado.

**Resultado y condición de cierre:** Cliente informado y pedido definido para continuar, o solicitud cerrada/pendiente con motivo explícito y sin sustituciones no autorizadas.

**Trazabilidad de la ficha:** Tienda P8; cuestionario 3 y 4.

<a id="bookmark=id.ngdc2p8lhyps"></a>

### T-09 — Recoger en tienda con crédito comercial

**Causa y caso de entrada:** El cliente desea recoger mercancía en tienda y cargar la compra a su crédito comercial, sin comprobante de transferencia.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; CEDIS/PDV; custodia; Wizerp como sistema que determina si permite emitir la remisión.

**Datos/documentos que recibe o comparte el proceso:** Cliente y cuenta de crédito; pedido, importe y sucursal; método crédito; resultado de validación de Wizerp; remisión o bloqueo; comunicación de regularización.

#### Flujo operativo

**01.** Cliente → vendedora: solicita piezas, sucursal y pago a crédito. La vendedora genera el pedido en Wizerp y comparte el archivo con CEDIS o PDV para disponibilidad y evidencias.

**02.** CEDIS/PDV → vendedora → cliente: comunica piezas y condiciones; se resuelven ajustes hasta cerrar el conjunto. La vendedora identifica crédito como método y reporta el importe, sin adjuntar comprobante de transferencia.

**03.** Auxiliar de ventas: recibe el pedido y tramita su remisión en Wizerp. Wizerp indica si el crédito permite continuar; ahí se consulta también el estado de cuenta.

**04.** Si Wizerp permite: la auxiliar emite la remisión a crédito. El importe queda en el estado de cuenta del cliente; CEDIS/PDV prepara y traslada a tienda según corresponda.

**05.** Si Wizerp bloquea por situación de crédito: la auxiliar informa a la vendedora; esta comunica al cliente lo que necesita regularizar. El pedido queda en espera; la fuente inicial no permite cambiar el método dentro de este caso.

**06.** Cliente → vendedora → auxiliar: comunica regularización. La auxiliar la comprueba en Wizerp y solo entonces continúa; la tolerancia descrita es hasta el día siguiente al pedido, no más de un día.

**07.** Tienda y custodia: reciben el pedido documentado y lo entregan cuando el cliente acude, conservando fotografía de entrega. No se solicita un comprobante de transferencia como requisito de una remisión aprobada a crédito.

**Decisiones y ramificaciones:** Si no regulariza dentro de la tolerancia: AC-03, pendiente de regla sobre cierre y liberación; no se emite una remisión por el mero transcurso del día. Crédito municipal: M-08. Nacional: N-15.

**Resultado y condición de cierre:** Pedido con remisión a crédito y entrega en tienda, o pedido detenido con bloqueo comunicado y sin aprobación ficticia.

**Límite aún no definido:** Falta definir la consecuencia después de la tolerancia si el crédito continúa bloqueado y su efecto sobre las piezas apartadas.

**Trazabilidad de la ficha:** Tienda P9; cuestionario 13, 14 y 15.

## 5B. Envíos municipales

Las fichas siguientes detallan los intercambios, decisiones y resultados. Las referencias a otro caso indican la continuación aplicable; no duplican el cobro ni el movimiento físico.

<a id="bookmark=id.sumzys7owxob"></a>

### M-01 — Envío municipal ordinario a terminal

**Causa y caso de entrada:** El cliente solicita mercancía y quiere recogerla en la terminal de su municipio, sin entrega a domicilio.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas; traslado CEDIS-PDV; auxiliar y gerentes de PDV; repartidor; terminal; Administración.

**Datos/documentos que recibe o comparte el proceso:** Pedido de Wizerp; cliente; municipio/destino y terminal; consignatario; teléfono; INE para carátula; condición PAGADO/POR COBRAR; mercancía e importe; envío cuando se conoce; comprobantes; remisión; paquetes y evidencia de salida.

#### Flujo operativo

**01.** Cliente → vendedora: solicita piezas y destino o paquetería. La vendedora captura el pedido sin pago en Wizerp, obtiene datos municipales y comparte PDF/capturas con CEDIS para confirmar mercancía.

**02.** CEDIS → vendedora: responde existencias, detalles y fotografías. La vendedora comunica al cliente, recibe aceptación o ajustes y deja el pedido detenido mientras falta decisión.

**03.** Vendedora: identifica si el costo de envío ya se conoce o si se cobrará posteriormente, como Jaguar. Captura datos para carátula y condición de cobro sin confundir pago del transporte con pago de la mercancía.

**04.** Cliente → vendedora → auxiliar de ventas: proporciona comprobante por lo que corresponde pagar en esta etapa. La auxiliar comprueba ingreso e importe y emite la remisión; con crédito se aplica M-08.

**05.** CEDIS: prepara y empaca la mercancía con su carátula y referencias. En la rutina aclarada, por la mañana la manda con Don Eduardo a PDV.

**06.** PDV: recibe y organiza la distribución de paquetes a las terminales. El gerente de PDV registra la salida en el grupo utilizado para ese control.

**07.** Repartidor: recoge el paquete en PDV y lo lleva a la terminal. Esta recogida con destino a terminal es el evento de salida que limita el cambio a recolección en tienda. Comparte fotografía de la entrega que realiza.

**08.** Terminal y vendedora: continúan con ficha y comunicación de recolección al cliente. Si el costo se conoce en terminal, se activa P-01 antes de entregar la ficha al cliente conforme a Jaguar.

**Decisiones y ramificaciones:** Costo posterior: M-05/P-01. Espera de fecha: M-09. Acumulación: M-10. Cambio antes de salir: M-07. Pago dividido: M-12. El traslado CEDIS-PDV no se registra como si ya fuera entrega final al cliente.

**Resultado y condición de cierre:** Mercancía documentada y entregada a terminal, con salida de PDV y referencias para recolección; el cobro de envío posterior puede seguir abierto. La recepción final por el cliente no queda probada solo por la foto del repartidor.

**Trazabilidad de la ficha:** Municipal P1; cuestionario 3, 25-28; aclaraciones de salida.

<a id="bookmark=id.4rbv51p0s9rx"></a>

### M-02 — Convertir un pedido de tienda a envío municipal

**Causa y caso de entrada:** El cliente ya no recogerá en tienda y pide enviar el pedido a la terminal de su municipio.

**Involucrados y alcance:** Cliente; vendedora; gerente/Administración; CEDIS; auxiliar de PDV; recepción/custodia; auxiliar de ventas; repartidor.

**Datos/documentos que recibe o comparte el proceso:** Solicitud anterior y nueva; pedido y remisión si existe; pagos; evidencia del cliente; ubicación; destino/terminal; destinatario y carátula; costo pendiente y estado de custodia.

#### Flujo operativo

**01.** Cliente → vendedora: comunica el cambio. La vendedora conserva evidencia y reúne pedido, remisión, pago, ubicación y avance del traslado.

**02.** Vendedora: gestiona el cambio con gerente/Administración cuando corresponde, cancela la solicitud de modalidad anterior y comunica a CEDIS/PDV la nueva solicitud municipal relacionada.

**03.** Si la mercancía sigue en CEDIS: CEDIS cambia la preparación al empaque municipal, reporta sus condiciones y coordina su envío a PDV para distribución.

**04.** Si ya está en PDV: la vendedora y el auxiliar solicitan a recepción retirar el pedido de custodia; si aún no entró, comunican el cambio antes de entregarlo a custodia. El auxiliar de PDV empaca para municipal.

**05.** Vendedora → auxiliar de ventas: comparte remisión y pagos reconocidos, nuevo destino y cargo de envío por cubrir. El cliente paga el cargo pendiente; la auxiliar verifica, evitando cobrar otra vez las piezas ya pagadas.

**06.** PDV y repartidor: continúan con carátula, registro de salida y entrega en terminal según M-01. Si no existe remisión aún, primero se completa el pago o aprobación de crédito y la emisión correspondiente.

**Decisiones y ramificaciones:** El texto “sin permiso” se corrige como error de escritura; la condición documental a revisar es la remisión. Incorporación a un envío nuevo de otros pedidos: M-04. Cargo Jaguar: P-01.

**Resultado y condición de cierre:** Pedido de tienda reconducido a municipal, con solicitud sustituta, mercancía retirada correctamente de custodia y pago de envío identificado.

**Trazabilidad de la ficha:** Municipal P2; cuestionario 9, 16 y 28.

<a id="bookmark=id.d70lhc7n6pek"></a>

### M-03 — Anexar mercancía a una solicitud municipal

**Causa y caso de entrada:** El cliente pide agregar piezas después de que la vendedora solicitó el envío municipal.

**Involucrados y alcance:** Cliente; vendedora; CEDIS o auxiliar de PDV según ubicación; auxiliar de ventas; gerente/Administración cuando el cambio lo requiere.

**Datos/documentos que recibe o comparte el proceso:** Pedido y solicitud vigentes; anexo con piezas y cantidades; evidencia; remisión existente o pendiente; nuevo importe; pago del anexo; efecto sobre paquetes y salida.

#### Flujo operativo

**01.** Cliente → vendedora: solicita el anexo. La vendedora conserva evidencia de que lo pidió el cliente y verifica si el pedido sigue vigente, si tiene remisión y si ya salió hacia terminal.

**02.** Vendedora → CEDIS/PDV: comparte identificación del pedido, piezas adicionales y evidencia; solicita existencia, condiciones y posibilidad de integración. El área responde con detalles y fotografías.

**03.** Si no hay remisión y el pedido está vigente: la vendedora actualiza el pedido de Wizerp, confirma el nuevo total con el cliente y comparte su versión vigente para preparar.

**04.** Si ya hay remisión: se genera un nuevo pedido/proceso por el anexo y se relaciona con el envío; no se agrega mercancía informalmente a una remisión cerrada.

**05.** Cliente → vendedora → auxiliar de ventas: paga las piezas nuevas; la auxiliar comprueba y documenta el pago. No se envía un anexo o complemento impagado.

**06.** CEDIS/PDV: integra el anexo cuando el paquete no ha salido y revisa si cambia empaque o cargo de envío. La vendedora comunica al cliente cualquier diferencia y retraso atribuible a su solicitud.

**07.** Gerente/Administración y responsables de seguimiento: identifican el retraso como solicitud del cliente cuando tiene evidencia; el documento original excluye penalización a colaboradores en ese supuesto.

**Decisiones y ramificaciones:** Si el principal ya salió: no se promete anexarlo físicamente; se tramita otro envío. Nuevo costo pendiente: P-01 cuando aplique. Resguardo para seguir acumulando: M-10.

**Resultado y condición de cierre:** Anexo pagado, documentado e integrado antes de la salida, o nuevo proceso independiente; permanece identificada la causa del retraso.

**Trazabilidad de la ficha:** Municipal P3; cuestionario 11.

<a id="bookmark=id.l7ssfuvoh6jk"></a>

### M-04 — Incorporar pedidos pagados en tienda a un envío municipal nuevo

**Causa y caso de entrada:** El cliente hace un nuevo pedido municipal y pide incluir piezas de remisiones pagadas que dejó en tienda.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de PDV; custodia; CEDIS y traslado cuando corresponde; auxiliar de ventas; repartidor.

**Datos/documentos que recibe o comparte el proceso:** Nuevo pedido; remisiones a incorporar; ubicación y bolsas; destino municipal; solicitud del cliente; pagos previos; nuevo pago y costos de envío.

#### Flujo operativo

**01.** Cliente → vendedora: identifica las compras pagadas que ya no recogerá en tienda y pide incorporarlas al nuevo envío.

**02.** Vendedora: reúne remisiones, pagos y ubicación; comparte con CEDIS y/o PDV el pedido nuevo y las referencias que se incorporarán. No vuelve a capturar las mismas piezas como venta nueva.

**03.** Auxiliar de PDV y custodia: localizan y retiran los pedidos para integración. Si el principal se prepara en CEDIS, coordinan el traslado a CEDIS y esperan confirmación de recepción; si se integra municipal en PDV, el auxiliar gestiona el empaque allí.

**04.** CEDIS/PDV → vendedora: confirma qué remisiones quedaron físicamente unidas, condiciones, paquetes y costo conocido o pendiente. La vendedora completa carátula y destinatario.

**05.** Vendedora → auxiliar de ventas: reporta solo mercancía nueva y cargos pendientes junto con las remisiones previas pagadas. La auxiliar verifica el pago nuevo o crédito y emite la documentación que corresponde.

**06.** CEDIS/PDV y repartidor: completan el recorrido municipal, registrando salida hacia terminal y evidencia de entrega. Si falta una bolsa o complemento, no se da por integrado.

**Decisiones y ramificaciones:** Espera de traslado: R-05/R-06 en su lógica de complemento, sin convertir por ello el envío en nacional. Acumulación municipal: M-10. Si toda la operación cambia de tienda a envío: M-02.

**Resultado y condición de cierre:** Nuevo envío municipal que incluye remisiones anteriores pagadas y mantiene trazabilidad de su origen y aplicación, sin duplicar cobro.

**Trazabilidad de la ficha:** Municipal P4; tienda P5; cuestionario 11 y 28.

<a id="bookmark=id.q61yr148rqb9"></a>

### M-05 — Transporte Jaguar o terminal con costo posterior

**Causa y caso de entrada:** La paquetería no determina el costo del transporte hasta recibir el paquete en terminal.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; PDV; repartidor; cajera de PDV para su proceso de efectivo; auxiliar de ventas; Administración; terminal.

**Datos/documentos que recibe o comparte el proceso:** Mercancía pagada y remisión; terminal; carátula; aviso de cobro posterior; evidencia del costo; pago de envío del cliente; ficha de recolección y observación que relaciona la remisión.

#### Flujo operativo

**01.** Vendedora → cliente: informa antes de la salida que el envío no tiene costo definitivo todavía y se cobrará cuando la paquetería lo comunique.

**02.** Cliente y auxiliar de ventas: completan pago o crédito de mercancía y remisión. La vendedora reporta por separado que el envío está pendiente de determinar/cobrar; no lo marca como ya liquidado por el cliente.

**03.** CEDIS → PDV: prepara y traslada el paquete. PDV registra su salida hacia terminal y el repartidor realiza la entrega.

**04.** En PDV, la cajera proporciona al repartidor el dinero para el proceso de transporte. Este movimiento de efectivo pertenece a caja de PDV; la gestión del pedido conserva su relación y no sustituye la liquidación de caja.

**05.** Terminal → repartidor → vendedora: determina y comunica el costo. El repartidor comparte por WhatsApp comprobante o fotografía del importe del paquete.

**06.** Vendedora → cliente: solicita exactamente el monto informado para ese envío. Cliente → vendedora: paga y comparte comprobante.

**07.** Vendedora → auxiliar de ventas/Administración: reporta el comprobante como PAGO ENVÍO y anota la remisión de referencia. La verificación del ingreso sigue la revisión inicial y posterior documentada.

**08.** Vendedora → cliente: comparte la ficha para recoger después del pago, según la secuencia aclarada. Se conserva la evidencia del costo separada del comprobante del pago del cliente.

**Decisiones y ramificaciones:** Si el cliente no paga: el cobro sigue pendiente y no se da por cerrado; no hay regla acordada de plazo o escalamiento. Si hay diferencia respecto de un cobro previo: S-09. Registro de pago separado: P-01.

**Resultado y condición de cierre:** Envío entregado a terminal y cobro posterior documentado cuando el cliente paga; ficha compartida y pedido relacionado con el pago. El cierre de caja del repartidor sigue siendo externo al proceso.

**Límite aún no definido:** No se definió tratamiento del impago ni documentación de liquidación interna de caja. No se propone una automatización de caja en esta V02.

**Trazabilidad de la ficha:** Municipal P5; situaciones Pago envío P1; cuestionario 21 y 22; aclaración de cajera PDV.

<a id="bookmark=id.9pofpaz9kkwg"></a>

### M-06 — Carátula municipal de cliente nuevo o existente

**Causa y caso de entrada:** Es necesario identificar al consignatario y la terminal, especialmente cuando el cliente es nuevo o no tenía datos de envío municipal.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de apoyo/ventas; CEDIS o auxiliar de PDV que coloca la carátula; repartidor.

**Datos/documentos que recibe o comparte el proceso:** Nombre del consignatario o receptor; teléfono; destino/terminal; condición PAGADO o POR COBRAR; INE adjunta según regla documentada; referencias del pedido.

#### Flujo operativo

**01.** Vendedora → cliente: solicita datos de quien recibe, teléfono, destino y la INE contemplada para todos los envíos municipales. Si existen datos anteriores, confirma cuáles usará en este pedido.

**02.** Cliente → vendedora: proporciona información y documentos. La vendedora registra los datos del destinatario, que puede no ser la misma persona que compra.

**03.** Vendedora → auxiliar de apoyo/ventas y área de empaque: comparte la carátula Word o los datos para prepararla, vinculándolos con pedido y remisión.

**04.** Vendedora y auxiliar: identifican si la leyenda debe ser PAGADO o POR COBRAR según la condición del transporte. En Jaguar, “pagado” ante la terminal puede coexistir con cobro al cliente pendiente.

**05.** CEDIS/PDV: utiliza la carátula correspondiente al pedido al empacar y la entrega con el paquete para la terminal; cualquier corrección de receptor o destino se comunica antes de la salida.

**06.** Como requisito derivado para futura implementación: permitir generar/descargar la carátula con los datos registrados, sin dar por hecho que hoy existe generación automática ni cambiar los controles actuales.

**Decisiones y ramificaciones:** Datos nuevos: obtenerlos del cliente. Cambio de destino municipal antes de salir: coordinar nueva carátula y solicitud. Pago posterior: M-05. No reutilizar automáticamente una carátula anterior sin verificar destinatario.

**Resultado y condición de cierre:** Paquete con datos de recolección e identificación y condición de transporte correspondientes al envío actual.

**Límite aún no definido:** No se detalló el responsable final de revisar cada dato ni el efecto de una corrección cuando el paquete ya salió.

**Trazabilidad de la ficha:** Municipal P6; V01 revisada M-06.

<a id="bookmark=id.wiu2ajgext10"></a>

### M-07 — Dejar sin efecto el envío municipal y recoger en tienda

**Causa y caso de entrada:** El cliente pide recoger en tienda un pedido que iba a enviarse a municipio y puede haber pagado ya el envío.

**Involucrados y alcance:** Cliente; vendedora; gerente/Administración; gerente y auxiliar de PDV; CEDIS según ubicación; repartidor; custodia.

**Datos/documentos que recibe o comparte el proceso:** Solicitud municipal; pedido y remisión; pago de mercancía y envío; evidencia de cambio; ubicación; registro de salida hacia terminal; saldo exclusivo de envío si procede.

#### Flujo operativo

**01.** Cliente → vendedora: solicita recoger en tienda. La vendedora reúne evidencia y verifica con CEDIS/PDV la ubicación y el registro de salida, no solo si existe remisión.

**02.** Si el repartidor ya recogió el paquete y lo lleva a terminal: no se acepta el cambio a recolección conforme a la regla municipal. La vendedora informa la limitación sin prometer recuperación del paquete.

**03.** Si no ha salido hacia terminal: vendedora y gerente/Administración gestionan cancelar la solicitud de envío, conservar el pedido y remisión relacionados e iniciar la solicitud de recolección en tienda.

**04.** CEDIS/PDV: detiene la distribución a terminal, localiza el paquete y lo destina a la sucursal autorizada. Si requiere traslado o custodia, se coordina antes de darlo como disponible para el cliente.

**05.** Administración: reconoce como saldo a favor exclusivo de envío el monto de envío pagado que corresponde al cambio, con evidencia del origen. No lo aplica a mercancía.

**06.** Vendedora → cliente: comunica sucursal y disponibilidad de recolección, así como el saldo de envío reconocido. Tienda libera custodia cuando acude y toma fotografía de entrega.

**Decisiones y ramificaciones:** Salida ya registrada: continúa M-01; no se cambia automáticamente. Saldo restringido: S-06. Si ya existe un costo no recuperable del transporte, su tratamiento no está definido y requiere resolución administrativa.

**Resultado y condición de cierre:** Antes de salir, pedido reconducido a tienda y saldo de envío registrado cuando procede; después del límite, solicitud de cambio rechazada/informada con motivo.

**Límite aún no definido:** El uso o reembolso de gastos ya consumidos por el transporte no tiene regla cerrada.

**Trazabilidad de la ficha:** Municipal P7; V01 revisada M-07; cuestionario 9, 18 y 28.

<a id="bookmark=id.tj2jmmf404bz"></a>

### M-08 — Envío municipal a crédito

**Causa y caso de entrada:** El cliente elige crédito comercial para su pedido municipal, en lugar de transferir.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; CEDIS; PDV; repartidor; Administración en sus controles.

**Datos/documentos que recibe o comparte el proceso:** Cliente y crédito; pedido de mercancía; destino y carátula; cargos de envío conocidos; importe total a crédito; remisión o resultado de bloqueo.

#### Flujo operativo

**01.** Cliente → vendedora: solicita mercancía, terminal y crédito. La vendedora genera el pedido y comparte con CEDIS para disponibilidad, detalles y fotografías.

**02.** Vendedora: resuelve con el cliente las observaciones y registra crédito como único método. La gestión municipal identifica ese método; la decisión de aprobación se realiza en Wizerp por la auxiliar.

**03.** Auxiliar de ventas: recibe el pedido y compara el importe reportado a crédito con el documento. Wizerp indica si permite la remisión; no solicita comprobante de transferencia por el crédito aprobado.

**04.** Si se aprueba: emite la remisión. Pueden cargarse a crédito mercancía, envío, seguro y reexpedición cuando correspondan y se conozcan; no se mezclan métodos en la operación.

**05.** Si se bloquea: informa a la vendedora para que el cliente regularice; se espera como máximo hasta el día siguiente según la tolerancia descrita y solo se continúa cuando Wizerp permite.

**06.** CEDIS y PDV: empacan, trasladan y distribuyen a terminal igual que el municipal ordinario. El crédito no elimina la carátula, las evidencias ni el registro de salida.

**Decisiones y ramificaciones:** Costo posterior Jaguar: M-05/P-01; falta definir cómo se documenta su cargo a crédito cuando se conoce después. Bloqueo persistente: AC-03. No combinar crédito con transferencias para completar el mismo pedido.

**Resultado y condición de cierre:** Envío municipal con remisión aprobada a crédito y recorrido ordinario, o pedido detenido por bloqueo comunicado.

**Límite aún no definido:** Falta resolver la documentación de un envío de costo posterior que también se quiera cargar a crédito.

**Trazabilidad de la ficha:** Municipal P8; tienda P9; cuestionario 13-15.

<a id="bookmark=id.a765j4ldr52l"></a>

### M-09 — Resguardo municipal con mercancía y envío pagados

**Causa y caso de entrada:** El cliente paga mercancía y envío, pero pide que no se despache hasta una fecha posterior.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; CEDIS/PDV según ubicación; responsable de distribución.

**Datos/documentos que recibe o comparte el proceso:** Pedido y remisión; pagos de mercancía y envío; fecha solicitada; ubicación; piezas comprometidas; motivo del resguardo y posterior instrucción de salida.

#### Flujo operativo

**01.** Cliente → vendedora: define mercancía, terminal y fecha en la que quiere que se envíe. La vendedora comparte pedido con CEDIS/PDV para disponibilidad y evidencias y comunica la espera solicitada.

**02.** Cliente y auxiliar de ventas: completan pago de mercancía y envío, verificación e emisión de remisión. Este caso exige ambos pagados; no se confunde con acumulación con envío pendiente.

**03.** Vendedora → CEDIS/PDV: instruye resguardo, indica fecha y referencias. El personal identifica el pedido en azul y mantiene separadas las piezas, que no pueden contemplarse para otros pedidos.

**04.** CEDIS/PDV: responde ubicación y condiciones de resguardo. Tener remisión y pago no autoriza a repartirlo antes de la instrucción aplicable.

**05.** Vendedora: da la instrucción de liberación según el proceso y la fecha acordada. No se presume liberación automática al llegar el día, pues no se definió si requiere reconfirmación del cliente.

**06.** Área de preparación/distribución: deja constancia de que puede continuar, cambia la identificación operativa que corresponda y ejecuta empaque, traslado a PDV y salida a terminal según M-01.

**Decisiones y ramificaciones:** Si comienza a acumular piezas: M-10 con revisión de pagos y empaque. Si decide recoger: M-07 antes de salir. Si cambia el costo por la modificación, tratar la diferencia sin alterar silenciosamente lo pagado.

**Resultado y condición de cierre:** Pedido pagado conservado sin despacho prematuro y enviado después de una instrucción de liberación identificable.

**Límite aún no definido:** La responsable es la vendedora, pero no se cerró si la fecha basta o requiere confirmación adicional.

**Trazabilidad de la ficha:** Municipal P9; situaciones Resguardos P2; cuestionario 27.

<a id="bookmark=id.gpvo5e83szqc"></a>

### M-10 — Resguardo municipal para acumular mercancía

**Causa y caso de entrada:** El cliente quiere pagar piezas de varios pedidos y reunirlas antes de solicitar un envío municipal conjunto.

**Involucrados y alcance:** Cliente; vendedora; CEDIS/PDV; auxiliar de ventas; traslado cuando hay piezas en otra ubicación; repartidor al cierre.

**Datos/documentos que recibe o comparte el proceso:** Cliente; pedidos y remisiones pagadas; ubicación de cada grupo; terminal prevista; instrucción de seguir acumulando; número de cajas N/A mientras no aplica; costo de envío posterior.

#### Flujo operativo

**01.** Cliente → vendedora: solicita las piezas y comunica que seguirá comprando, por lo que todavía no debe enviarse. La vendedora comparte cada pedido con CEDIS/PDV para disponibilidad y evidencias.

**02.** Cliente → vendedora → auxiliar de ventas: paga las piezas confirmadas de cada compra; se comprueba el ingreso y se emiten sus remisiones. No se cobra necesariamente el envío en esta etapa.

**03.** Vendedora → CEDIS/PDV: identifica el cliente y los pedidos que se acumulan; instruye resguardo azul. En número de cajas se usa N/A, cuyo significado es NO APLICA, no cero ni un peso o cantidad calculados.

**04.** CEDIS/PDV: conserva los pedidos pagados y sus remisiones identificados, sin considerar esas piezas disponibles para otra venta ni despacharlas solo porque se emitió la remisión.

**05.** Cliente → vendedora: indica que terminó de acumular y qué pedidos integrará. La vendedora solicita integración y, si hay piezas en tienda u otra ubicación, coordina su traslado/recepción.

**06.** CEDIS/PDV → vendedora: confirma conjunto físico, empaque y cajas reales. La vendedora define el costo pendiente de envío cuando se conoce y solicita pago; la auxiliar verifica el cargo.

**07.** Vendedora: libera el resguardo para salida. CEDIS/PDV continúa el recorrido municipal. En Jaguar, se despacha mercancía pagada y el cobro del envío se realiza por P-01 al conocerse el costo.

**Decisiones y ramificaciones:** Piezas nuevas impagadas: no enviarlas. Incorporación de tienda: M-04. Envío de costo posterior: M-05. Si aún no cierra la acumulación, mantiene resguardo; no se calcula una cantidad de cajas como si fuera definitiva.

**Resultado y condición de cierre:** Compras pagadas consolidadas en el envío elegido, con cada remisión reconocida y cargo de transporte independiente cuando corresponde.

**Límite aún no definido:** No existe plazo máximo documentado para esta acumulación ni instrucción detallada de liberación distinta de la decisión canalizada por vendedora.

**Trazabilidad de la ficha:** Municipal P10; situaciones Resguardos P3; cuestionario 11 y 27.

<a id="bookmark=id.5nhuby6sf4sy"></a>

### M-11 — Aplicar nota de crédito por devolución al pago municipal

**Causa y caso de entrada:** El cliente tiene una nota de crédito por devolución y quiere usarla para pagar mercancía del pedido actual.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; Administración; PDV si el saldo se originó allí; CEDIS/PDV para preparación.

**Datos/documentos que recibe o comparte el proceso:** Pedido; nota de crédito y origen; importe disponible; monto aplicado; diferencia a cubrir; comprobante de la diferencia; remisión nueva; remanente si existe.

#### Flujo operativo

**01.** Cliente → vendedora: presenta la nota de crédito o informa que tiene saldo por devolución. La vendedora identifica documento, importe y pedido al que quiere aplicarlo.

**02.** Vendedora: solicita el pedido a CEDIS/PDV y recibe disponibilidad, detalles y evidencias antes de cerrar el importe de mercancía con el cliente.

**03.** Vendedora y auxiliar/Administración: comprueban la existencia y disponibilidad de la nota. Si nació en PDV, se verifica con PDV y su Drive que no se haya utilizado y se deja referencia de aplicación.

**04.** Vendedora → cliente: informa el monto de mercancía cubierto por la nota y la diferencia que falta. La nota de devolución no se sustituye por saldo exclusivo de envío.

**05.** Cliente → vendedora → auxiliar de ventas: entrega el comprobante por la diferencia cuando existe; la vendedora adjunta pedido, nota y evidencia de pago. La auxiliar considera la nota al verificar el total y emitir remisión.

**06.** Administración/auxiliar responsable: registra el importe utilizado y, si existe, el remanente. La designación exacta de quién actualiza remanentes de todas las notas sigue pendiente.

**07.** CEDIS/PDV: prepara el pedido y continúa con carátula y envío municipal; pago de envío conserva sus propias referencias y restricciones.

**Decisiones y ramificaciones:** Saldo PDV: S-05. Nota insuficiente: cobrar diferencia. Nota superior al importe: conservar remanente identificable sin inventar consumo total. La prohibición de mezclar métodos no debe interpretarse automáticamente como prohibición de nota más transferencia: este caso está expresamente documentado.

**Resultado y condición de cierre:** Nota aplicada a mercancía y diferencia comprobada cuando procede, con remisión actual y control para no reutilizar el mismo importe.

**Trazabilidad de la ficha:** Municipal P11; situaciones Saldos P4 y P5; cuestionario 19.

<a id="bookmark=id.70xko9u5umx1"></a>

### M-12 — Cubrir un pedido con dos o más transferencias

**Causa y caso de entrada:** El cliente necesita varios movimientos bancarios por límites, bancos distintos o un importe faltante.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; Administración; CEDIS/PDV.

**Datos/documentos que recibe o comparte el proceso:** Pedido e importe objetivo; cada comprobante, monto y referencia; explicación del pago dividido; suma aplicada y diferencia pendiente; remisión.

#### Flujo operativo

**01.** Cliente → vendedora: comunica que pagará en varias transferencias y por qué. La vendedora mantiene un solo pedido de referencia y su importe definitivo.

**02.** Cliente → vendedora: entrega cada comprobante. La vendedora reúne dos o más evidencias y la explicación de los bancos, límites o diferencia faltante, sin tratar cada depósito como compra nueva.

**03.** Vendedora → auxiliar de ventas: presenta pedido y todos los movimientos, indicando qué importe de cada uno se aplica. Son varias transferencias del mismo método, no autorización para mezclar crédito y transferencia.

**04.** Auxiliar de ventas: verifica el ingreso de cada pago y la suma. Si falta importe, comunica a la vendedora la diferencia; no se da por liquidado el pedido por recibir solo el primer comprobante.

**05.** Si la suma cubre lo requerido: la auxiliar emite la remisión y CEDIS/PDV prepara y distribuye según M-01. Si excede, se identifica el origen de la diferencia y se aplica el caso de saldo que corresponda.

**06.** Administración: revisa posteriormente los pagos y posibles retornos, conservando la relación de todos los comprobantes con el pedido.

**Decisiones y ramificaciones:** Crédito más transferencia: no permitido según aclaración general. Depósito duplicado por error: S-07, no confundir con dos pagos intencionales que completan un importe. Excedente: S-01.

**Resultado y condición de cierre:** Pedido cubierto mediante transferencias verificadas y relacionadas, o pendiente por diferencia claramente comunicada.

**Trazabilidad de la ficha:** Municipal P12; cuestionario 15.

## 5C. Envíos nacionales

Las fichas siguientes detallan los intercambios, decisiones y resultados. Las referencias a otro caso indican la continuación aplicable; no duplican el cobro ni el movimiento físico.

<a id="bookmark=id.ha4xfo9lv3c"></a>

### N-01 — Envío nacional ordinario desde CEDIS

**Causa y caso de entrada:** El cliente quiere recibir su compra en un domicilio de otro estado.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas; encargado de guías; paquetería/repartidor; Administración. PDV participa solo si debe trasladar piezas a CEDIS.

**Datos/documentos que recibe o comparte el proceso:** Pedido sin pago; piezas, cantidades y valor; domicilio y destinatario; paquetería definida o pendiente; observaciones y evidencias; medidas y peso real/cobrado por caja; seguro opcional y reexpedición si aplica; comprobantes; remisión; guías.

#### Flujo operativo

**01.** Cliente → vendedora: comunica mercancía, domicilio y destinatario, paquetería si ya decidió y si desea seguro. La vendedora genera el pedido sin pago en Wizerp y descarga PDF/capturas.

**02.** Vendedora → CEDIS: comparte pedido y solicita confirmación de piezas, detalles, evidencias y pesaje para envío nacional. Los envíos nacionales únicamente salen desde CEDIS.

**03.** CEDIS → vendedora: responde disponibilidad y fotografías, tipo de empaque, peso real y peso a cobrar, medidas y número de cajas. Se documenta redondeo al siguiente entero en el ejemplo 3.8 kg → 4 kg; no se agrega una regla de peso volumétrico que no fue proporcionada.

**04.** Vendedora → cliente → CEDIS: comunica los detalles y tramita sustituciones, anexos o retiros. El ciclo se repite hasta que el cliente deja de consultar y se cierra la composición y el pesaje aplicable; si cambia mercancía, se revisa el empaque afectado.

**05.** Vendedora: con paquetería definida, establece cargos de envío, seguro solicitado y reexpedición cuando corresponde. Si no decide paquetería, se detiene el cálculo según N-04. No se inventan tarifas o porcentajes ausentes.

**06.** Cliente → vendedora → auxiliar de ventas: paga el importe confirmado y presenta comprobante con pedido y cargos. La auxiliar comprueba ingreso y monto y emite remisión; con crédito se sigue N-15.

**07.** Vendedora y auxiliar → encargado de guías: comparten la información seleccionada y revisada de domicilio, receptor, paquetería, cajas, peso y cobertura. Los datos los verifican vendedora, auxiliar y encargado de guías.

**08.** Encargado de guías: genera las guías y las comparte con CEDIS. Mientras se elaboran, CEDIS continúa empacando y espera las guías para colocarlas; CEDIS recibe la guía para empaque, no sustituye la revisión de datos domiciliarios.

**09.** CEDIS: coloca las guías correspondientes y entrega los paquetes al transporte. Se conserva la evidencia de la entrega que realice el repartidor y sus referencias. No se presume recepción en domicilio final a partir de una foto de un traslado intermedio.

**Decisiones y ramificaciones:** Sin paquetería: N-04/R-04. Faltantes: T-08 aplicado a CEDIS. Complementos: R-05/R-06. Más cajas con seguro: N-11/N-13; sin seguro: N-12/N-13. Cambio de dirección: N-08. Modificación antes de salida: caso específico aplicable.

**Resultado y condición de cierre:** Pedido nacional documentado, empacado y despachado desde CEDIS con guías y cargos relacionados. La entrega final de paquetería al domicilio no tiene un procedimiento de cierre completo descrito.

**Límite aún no definido:** Tarifas/seguro nacional, cargos de guías canceladas y prueba de recepción final no se encuentran totalmente especificados.

**Trazabilidad de la ficha:** Nacional P1; cuestionario 2-4, 15, 25 y 26.

<a id="bookmark=id.32z4sxgtn0pl"></a>

### N-02 — Cargo por zona extendida o reexpedición

**Causa y caso de entrada:** El domicilio requiere un cargo adicional por la cobertura o accesibilidad de la paquetería.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; encargado de guías; paquetería; CEDIS informado del envío.

**Datos/documentos que recibe o comparte el proceso:** Domicilio y código postal; paquetería; evidencia o cotización del cargo; importe de reexpedición separado de envío y seguro; aceptación, comprobante y remisión.

#### Flujo operativo

**01.** Cliente → vendedora: proporciona el domicilio. La vendedora identifica con la información de la paquetería si aplica zona extendida/reexpedición; la fuente del importe es la paquetería.

**02.** Vendedora → cliente: informa el cargo como concepto adicional. No modifica por ello la base del seguro ni confunde el importe con el costo ordinario del envío.

**03.** Vendedora → auxiliar de ventas y encargado de guías: comunica condición e importe para que ambos los contemplen. El cargo se añade al cobro según la aclaración.

**04.** Cliente → vendedora → auxiliar: paga el total que incluye el adicional o utiliza crédito aprobado por el total. La auxiliar verifica lo reportado y emite la remisión que corresponda.

**05.** Encargado de guías: utiliza la condición de destino al generar la guía. CEDIS recibe la guía correcta y completa el empaque y despacho nacional.

**06.** Si el domicilio cambia o el cargo se detecta después del pago: se comunica la diferencia al cliente y se registra el ajuste antes de considerar cubierto el nuevo importe; no se presume absorción de la diferencia.

**Decisiones y ramificaciones:** Si el cliente cambia domicilio: N-08 y nueva guía si ya existía. Si decide recoger en tienda: N-10. Si cuestiona el cargo: queda pendiente de decisión, sin sustituir el domicilio unilateralmente.

**Resultado y condición de cierre:** Reexpedición identificada, comunicada y cobrada/aprobada a crédito como concepto adicional del envío correcto.

**Límite aún no definido:** No se definió quién confirma la vigencia de cada cotización nacional ni los efectos económicos de detectar el cargo con guía emitida.

**Trazabilidad de la ficha:** Nacional P2; V01 revisada N-02; cuestionario 22.

<a id="bookmark=id.xy171nbcoov7"></a>

### N-03 — Seguro opcional de envío nacional

**Causa y caso de entrada:** El cliente solicita asegurar su compra ante robo o extravío.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas; encargado de guías; paquetería.

**Datos/documentos que recibe o comparte el proceso:** Solicitud de seguro; valor de mercancía cubierta; paquetería; cajas y contenido cuando corresponde; importe de seguro y fuente del cálculo; pago/crédito; guías relacionadas.

#### Flujo operativo

**01.** Cliente → vendedora: solicita seguro. Si no lo requiere, el proceso sigue sin esa cobertura; no se agrega seguro como paso obligatorio.

**02.** Vendedora → CEDIS: comunica la cobertura solicitada y pide identificar cajas y mercancía que irá en cada una cuando el envío tiene varias cajas.

**03.** CEDIS → vendedora: responde distribución, valor de las remisiones incluidas, evidencias y peso de cada caja. Con seguro, una remisión viaja completa en una caja y no se reparte entre cajas.

**04.** Vendedora: obtiene el costo conforme a la paquetería y valor asegurado; lo informa al cliente separado de envío y reexpedición. Las fórmulas, porcentajes y mínimos no fueron proporcionados.

**05.** Cliente → vendedora → auxiliar de ventas: acepta y paga el seguro o lo incluye en crédito aprobado; la auxiliar verifica el importe y su correspondencia con el pedido/cajas.

**06.** Encargado de guías: utiliza cobertura, valores y cajas informados. CEDIS coloca cada guía en la caja correspondiente y continúa despacho; la evidencia de contenido queda relacionada con esa caja.

**07.** Si el cliente solicita seguro después de la distribución preliminar: CEDIS debe proporcionar el desglose requerido antes de dar por terminada la documentación de cobertura.

**Decisiones y ramificaciones:** Una caja: cobertura del conjunto que contiene. Varias con resguardos: N-11. Pedido grande: N-13. Cambio de paquetería: N-05 con revisión del seguro.

**Resultado y condición de cierre:** Cobertura solicitada identificada por caja cuando corresponde, importe reconocido y evidencia del contenido asegurado.

**Límite aún no definido:** No se define cómo resolver una remisión que físicamente no cabe completa en una caja con seguro; requiere decisión operativa, sin dividirla por inferencia.

**Trazabilidad de la ficha:** Nacional P3 y P11; cuestionario 22.

<a id="bookmark=id.pukqcce9ohm6"></a>

### N-04 — Paquetería pendiente de elección del cliente

**Causa y caso de entrada:** El cliente quiere conocer el peso o seguir valorando opciones antes de decidir la paquetería.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas si se paga solo mercancía; encargado de guías cuando se defina la paquetería.

**Datos/documentos que recibe o comparte el proceso:** Pedido y domicilio; paquetería marcada pendiente; piezas y evidencias; pesos/medidas/cajas; pago y remisión de mercancía si existe; instrucción de espera.

#### Flujo operativo

**01.** Cliente → vendedora: comunica que no ha elegido paquetería. La vendedora registra expresamente PENDIENTE y genera el pedido sin inventar una selección definitiva.

**02.** Vendedora → CEDIS: comparte pedido y solicita confirmar mercancía, detalles y peso. CEDIS responde con evidencias, cajas y pesos para que el cliente pueda decidir.

**03.** Vendedora → cliente: presenta la respuesta. Las consultas de piezas pueden continuar hasta cerrar el pesaje; después el envío espera la elección del cliente.

**04.** Mientras la paquetería esté pendiente: la vendedora no calcula el envío ni solicita una guía como si ya estuviera definido el servicio.

**05.** Si el cliente quiere pagar únicamente las piezas: la vendedora reporta ese alcance; la auxiliar verifica pago y remisión de mercancía y CEDIS conserva el pedido en resguardo sin despacharlo.

**06.** Cuando el cliente elige: la vendedora comunica la paquetería a auxiliar y encargado de guías, obtiene el cargo, seguro y reexpedición que correspondan, y solicita pago de conceptos pendientes sin volver a cobrar mercancía.

**07.** Encargado de guías y CEDIS: elaboran guía, completan empaque y despachan desde CEDIS cuando se reúnen las condiciones del envío.

**Decisiones y ramificaciones:** Mercancía ya pagada en espera: R-04. Cliente sigue acumulando o falta complemento: R-05/R-06 según motivo. Revisión de peso si se agregan piezas: N-06.

**Resultado y condición de cierre:** Pedido detenido con causa identificada y, cuando el cliente decide, envío continuado con cargos correctos y pagos previos reconocidos.

**Trazabilidad de la ficha:** Nacional P4; situaciones Resguardos P4.

<a id="bookmark=id.g84ju06rm6c9"></a>

### N-05 — Cambio de paquetería

**Causa y caso de entrada:** El cliente solicita otra paquetería o una corrección operativa obliga a cambiar la previamente seleccionada.

**Involucrados y alcance:** Cliente; vendedora; gerente/Administración; auxiliar de ventas; encargado de guías; CEDIS; paquetería.

**Datos/documentos que recibe o comparte el proceso:** Paquetería anterior y nueva; motivo y evidencia; pedido y remisión; salida física; guías existentes; pesos/cajas; diferencia de envío y seguro; cobro o saldo de envío.

#### Flujo operativo

**01.** Cliente → vendedora: solicita el cambio; la vendedora conserva evidencia. Si es corrección propia, registra el motivo real y gestiona la autorización correspondiente.

**02.** Vendedora → CEDIS: comprueba que el paquete no haya salido a entrega. Si ya salió, no se acepta el cambio de paquetería conforme a la respuesta del cuestionario.

**03.** Si aún no salió: la vendedora comunica a CEDIS, auxiliar y encargado de guías la nueva selección. No se cancela la remisión por este cambio; se deja la observación relacionada.

**04.** Vendedora y encargado de guías: revisan envío, seguro y reexpedición para la nueva paquetería. Si afecta preparación o documentación, CEDIS espera la instrucción corregida antes de despachar.

**05.** Si hay diferencia a cobrar: cliente → vendedora → auxiliar paga y verifica el cargo adicional. Si resulta importe a favor de envío: se registra en la tabla de saldos, con su restricción exclusiva para envío.

**06.** Encargado de guías → CEDIS: proporciona la documentación utilizable para la nueva paquetería. La cancelación y costo de una guía anterior por cambio de paquetería no se detallaron; no se reutiliza una guía de la paquetería previa como si fuera válida.

**07.** CEDIS: coloca la guía correcta y despacha, conservando remisión, observación y cargos relacionados.

**Decisiones y ramificaciones:** Salida ya realizada: cambio de paquetería no admitido. Corrección de domicilio con misma paquetería: N-08. Saldo por diferencia: S-09.

**Resultado y condición de cierre:** Antes de salida, envío actualizado sin cancelar su remisión y con diferencia económica registrada; después de salida, limitación informada.

**Límite aún no definido:** No se definió recuperación o cobro de una guía anterior por cambio de paquetería. La excepción de gestión posterior con Rafael no autoriza este cambio de paquetería.

**Trazabilidad de la ficha:** Nacional P5; cuestionario 24.

<a id="bookmark=id.mygbk2qwkow8"></a>

### N-06 — Anexar piezas al envío nacional

**Causa y caso de entrada:** El cliente pide piezas adicionales después de que se compartió el pedido nacional con CEDIS.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas; encargado de guías si ya se informó el envío.

**Datos/documentos que recibe o comparte el proceso:** Pedido/remisión principal; anexo y evidencia; estado de salida; nuevas piezas y cantidades; pago; peso/cajas revisados; diferencia de cargos; remisión complementaria si corresponde.

#### Flujo operativo

**01.** Cliente → vendedora: pide las piezas adicionales. La vendedora comparte a CEDIS la identificación de cada pieza, cantidades y captura de la solicitud del cliente.

**02.** CEDIS → vendedora: confirma existencia, detalles y si puede integrar físicamente el anexo. La vendedora revisa si el pedido tiene remisión y si ya salió.

**03.** Sin remisión: la vendedora modifica el pedido aún abierto, obtiene aceptación del cliente y comparte la nueva versión. Con remisión y sin salida: genera un pedido/proceso nuevo por el anexo y lo relaciona con el principal.

**04.** Cliente → vendedora → auxiliar: paga las piezas nuevas y se comprueba/documenta el pago. Un complemento impagado no se envía.

**05.** CEDIS: incorpora las piezas, revisa medidas, cajas y pesos y responde con evidencias actualizadas. Si hay seguro, revisa la distribución de remisiones completas y los valores por caja.

**06.** Vendedora y auxiliar: resuelven diferencias de envío/seguro; encargado de guías recibe el conjunto actualizado antes de elaborar o corregir la documentación. CEDIS no despacha con una composición anterior a la última instrucción aceptada.

**07.** Si el paquete principal ya salió: no se aplica el anexo a ese envío; se tramita un envío nuevo por las piezas adicionales con su propio recorrido.

**Decisiones y ramificaciones:** Múltiples cajas con seguro: N-11; sin seguro: N-12. Mercancía de otro almacén: R-05/R-06. Guía ya generada: gestionar revisión; sus costos de sustitución no están definidos para todos los cambios.

**Resultado y condición de cierre:** Anexo pagado integrado antes de salida con pesos y cargos revisados, o nuevo envío independiente cuando no puede unirse.

**Trazabilidad de la ficha:** Nacional P6; cuestionario 11.

<a id="bookmark=id.jv6blyay743e"></a>

### N-07 — Registrar la primera dirección nacional

**Causa y caso de entrada:** El cliente no tiene una dirección previa de envío y proporciona la primera, para sí mismo o para un tercero.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; encargado de guías; CEDIS como receptor de la guía terminada.

**Datos/documentos que recibe o comparte el proceso:** Calle, colonia, código postal, referencias, número interior/exterior, consignatario/persona que recibe e indicación de si lleva nota. Municipio/ciudad, estado y teléfono se identifican como datos necesarios de destino/contacto a confirmar, no como campos obligatorios ya aprobados por la última respuesta.

#### Flujo operativo

**01.** Cliente → vendedora: proporciona domicilio y persona que recibirá. La vendedora distingue al comprador del destinatario para no asumir que son la misma persona.

**02.** Vendedora: captura los campos explícitamente respondidos y solicita aclaración de datos incompletos o ambiguos. Interior/exterior se registra según aplique al domicilio; no se inventa un número.

**03.** Vendedora → auxiliar y encargado de guías: comparte los datos elegidos para ese pedido y la indicación de si el paquete lleva nota. Las tres partes indicadas revisan la información.

**04.** Encargado de guías: utiliza la dirección revisada cuando el pedido está listo para documentar el envío. Si encuentra un dato insuficiente, lo devuelve a la vendedora para confirmarlo con el cliente.

**05.** Encargado de guías → CEDIS: entrega la guía generada. CEDIS la coloca en el paquete correspondiente; no sustituye al cliente o a ventas al decidir un domicilio.

**06.** Como requisito de trazabilidad propuesto: conservar la dirección utilizada en ese envío aunque después se actualice la dirección del cliente; no afirmar que hoy existe un historial automático.

**Decisiones y ramificaciones:** Dirección existente corregida: N-08. Dirección adicional sin reemplazar otra: N-09. Destino con reexpedición: N-02.

**Resultado y condición de cierre:** Primera dirección identificada y revisada para elaborar la guía correcta del pedido.

**Límite aún no definido:** La lista final de campos obligatorios debe completar municipio/ciudad, estado y contacto; tampoco se definió qué significa operativamente “lleva nota” en todos los casos.

**Trazabilidad de la ficha:** Nacional P7; cuestionario 25.

<a id="bookmark=id.byd5i3bvvekv"></a>

### N-08 — Modificar dirección y sustituir guía cuando ya existe

**Causa y caso de entrada:** El cliente corrige o cambia una dirección que ya había proporcionado para el envío.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; encargado de guías; CEDIS; Rafael si se gestiona una solicitud posterior a entrega al transporte.

**Datos/documentos que recibe o comparte el proceso:** Dirección anterior y nueva; pedido/remisión; evidencia de cambio; estado físico; guía anterior y nueva; pesos/cargos si cambia cobertura; motivo y resultado de la solicitud.

#### Flujo operativo

**01.** Cliente → vendedora: comunica la dirección nueva o los campos a corregir. La vendedora registra la instrucción e identifica exactamente qué pedido se afecta.

**02.** Vendedora → auxiliar y encargado de guías: comparte la dirección completa corregida; revisan datos y si hay guía emitida. También comunica a CEDIS que el paquete tiene información pendiente de corregir.

**03.** Si no existe guía y no salió: encargado de guías utiliza la dirección nueva y continúa el flujo nacional; la vendedora revisa si cambia reexpedición o costo de envío.

**04.** Si ya existe guía y no salió: se cancela la guía anterior y se genera una nueva. El encargado de guías comparte la nueva con CEDIS para sustituir la etiqueta y evitar que despache con la anterior.

**05.** Vendedora y auxiliar: documentan cualquier diferencia conocida y su pago o saldo. La respuesta no define quién absorbe el costo de cancelar una guía; no se presume gratuidad.

**06.** Si el paquete ya fue entregado al transporte: la vendedora canaliza con Rafael. Él realiza la solicitud, pero no siempre recibe respuesta a tiempo; el cambio queda solicitado, no garantizado.

**07.** CEDIS o responsable del seguimiento: utiliza únicamente la instrucción confirmada. Si no hay confirmación posterior del transportista, se conserva que el envío ya salió con la guía previa, sin declarar una corrección realizada.

**Decisiones y ramificaciones:** Cambio de paquetería: N-05 con su prohibición después de salida. Cambio de modalidad a tienda: N-10. La gestión posterior con Rafael se trata como excepción, no como edición directa de un paquete en tránsito.

**Resultado y condición de cierre:** Antes de salida, dirección revisada y guía correcta colocada; después de entrega al transporte, solicitud excepcional trazable con resultado pendiente o confirmado.

**Límite aún no definido:** Costos de sustitución y procedimiento exacto de confirmación posterior con Rafael no se detallaron.

**Trazabilidad de la ficha:** Nacional P8; cuestionario 12 y 25; aclaración de sustitución de guía.

<a id="bookmark=id.ywfhdjewtez5"></a>

### N-09 — Añadir una dirección nacional adicional

**Causa y caso de entrada:** El cliente ya tiene domicilios registrados y quiere usar otro, propio o de un tercero, sin necesariamente eliminar los anteriores.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; encargado de guías; CEDIS para empaque.

**Datos/documentos que recibe o comparte el proceso:** Cliente; dirección adicional; destinatario; campos de N-07; dirección seleccionada para el pedido; confirmación del cliente y guía resultante.

#### Flujo operativo

**01.** Cliente → vendedora: informa una dirección distinta y quién recibirá. La vendedora identifica si realmente agrega una opción o está corrigiendo la utilizada en el envío actual.

**02.** Vendedora: registra la dirección adicional y reúne los datos de N-07, manteniendo identificables las direcciones previas; no sustituye una existente por inferencia.

**03.** Cliente → vendedora: confirma cuál domicilio debe usarse en este pedido. La vendedora comparte la selección con auxiliar y encargado de guías.

**04.** Auxiliar, vendedora y encargado de guías: revisan los datos de esa selección y las condiciones de cobertura. El costo y la guía se preparan con la dirección elegida, no con una predeterminada por costumbre.

**05.** Encargado de guías → CEDIS: entrega la guía de ese destino para empaque y despacho nacional.

**06.** Si ya existía guía para otra dirección: se sigue N-08, cancelando la anterior y generando una nueva antes de salir, o canalizando la solicitud excepcional si ya está en transporte.

**Decisiones y ramificaciones:** Corrección de domicilio: N-08. Primera dirección: N-07. Tercero destinatario: conservar su identidad separada del comprador.

**Resultado y condición de cierre:** Dirección adicional disponible y dirección del envío actual inequívocamente seleccionada y revisada.

**Trazabilidad de la ficha:** Nacional P9; cuestionario 25.

<a id="bookmark=id.omw5wv9d31zy"></a>

### N-10 — Cambiar un envío nacional por recolección en tienda

**Causa y caso de entrada:** El cliente ya no quiere envío a domicilio y solicita recoger la mercancía en una sucursal.

**Involucrados y alcance:** Cliente; vendedora; gerente/Administración; CEDIS; auxiliar de ventas; encargado de guías; PDV/custodia; Rafael si ya se entregó al transporte.

**Datos/documentos que recibe o comparte el proceso:** Solicitud nacional; pedido/remisión; pagos y cargos; ubicación; existencia de guía; evidencia del cambio; nueva sucursal y solicitud de recolección.

#### Flujo operativo

**01.** Cliente → vendedora: solicita recoger en tienda. La vendedora registra evidencia y consulta con CEDIS el avance y si ya entregó el paquete al transporte.

**02.** Antes de salida: la vendedora gestiona el cambio con gerente/Administración, notifica a CEDIS y cancela la solicitud de envío de la clasificación anterior, conservando pedido/remisión y pagos reconocidos.

**03.** Vendedora → encargado de guías: informa que no debe despacharse con la guía nacional. Si ya existe guía, su baja y costo requieren tratamiento confirmado; no se asume que el cambio de modalidad sea sin gasto.

**04.** Administración y auxiliar de ventas: determinan importe de envío no utilizado, saldo exclusivo de envío o devolución que corresponda; no convierten saldo de transporte en descuento de mercancía.

**05.** CEDIS: prepara bolsas y traslado a la sucursal autorizada. PDV recibe, verifica y entrega a custodia; la vendedora informa al cliente cuándo y dónde recoger.

**06.** Cliente: recoge en tienda; se libera custodia y se toma fotografía. Si ya estaba en transporte cuando solicitó el cambio, se canaliza con Rafael y no se promete retorno o recolección en tienda sin confirmación.

**Decisiones y ramificaciones:** Cambio general de modalidad: T-04. Recolección: T-02. Saldo de envío: S-06. Después de despacho: gestión excepcional descrita en N-08/N-14, sin garantía.

**Resultado y condición de cierre:** Cuando aún es viable, mercancía reconducida a tienda con nueva solicitud y pagos conservados; si ya salió, excepción pendiente de respuesta.

**Límite aún no definido:** No se resolvió el tratamiento económico de guía/seguro ya consumidos ni la devolución física posterior al despacho.

**Trazabilidad de la ficha:** Nacional P10; cuestionario 9, 12 y 18.

<a id="bookmark=id.7gj2ku6y1n9u"></a>

### N-11 — Consolidar remisiones y resguardos en varias cajas con seguro

**Causa y caso de entrada:** El cliente integra pedidos pagados o resguardos y el conjunto necesita más de una caja; solicita seguro.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; PDV/custodia y traslado para complementos; auxiliar de ventas; encargado de guías.

**Datos/documentos que recibe o comparte el proceso:** Pedido nuevo y remisiones anteriores; pagos y ubicación; cajas; remisiones completas asignadas a cada caja; contenido y evidencia por caja; valor asegurado, peso y cargos.

#### Flujo operativo

**01.** Cliente → vendedora: identifica resguardos/remisiones que quiere unir y solicita seguro. La vendedora comparte a CEDIS la lista y coordina traslado de piezas en PDV si es necesario.

**02.** PDV/traslado → CEDIS: entrega complementos pagados. CEDIS confirma recepción; si falta alguno, el principal permanece en espera y no se presenta como consolidado completo.

**03.** CEDIS: organiza las cajas según volumen. Cada remisión debe ir completa en una caja cuando lleva seguro; no reparte parte de una remisión en un envío y parte en otro.

**04.** CEDIS → vendedora: informa expresamente qué mercancía/remisiones van en cada caja y comparte evidencias. Aunque se reúnen pedidos anteriores, se documenta cada caja asegurada por separado con su peso y valor.

**05.** Vendedora → cliente: presenta costos por caja y seguro independiente para cada una, reconociendo mercancía previamente pagada. La auxiliar verifica los pagos o crédito de lo pendiente y sus referencias.

**06.** Vendedora y auxiliar → encargado de guías: entregan información de destinatario, cajas, pesos, valores y cobertura; el encargado prepara la documentación correspondiente.

**07.** CEDIS: coloca cada guía en la caja cuyo contenido se declaró, conserva las evidencias relacionadas y despacha desde CEDIS.

**Decisiones y ramificaciones:** Seguro solicitado después: CEDIS debe completar desglose por caja. Si una remisión no cabe completa: punto AC-12, sin autorizar división. Sin seguro: N-12.

**Resultado y condición de cierre:** Conjunto consolidado en cajas con contenido demostrable, remisiones completas y seguro separado por caja, sin duplicar pago de mercancía.

**Trazabilidad de la ficha:** Nacional P11; cuestionario 11 y 23.

<a id="bookmark=id.1w1it6m9qc62"></a>

### N-12 — Consolidar pedidos en varias cajas sin seguro

**Causa y caso de entrada:** El cliente reúne compras o resguardos y el volumen necesita varias cajas, pero no solicita seguro.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; PDV/custodia y traslado; auxiliar de ventas; encargado de guías.

**Datos/documentos que recibe o comparte el proceso:** Remisiones a integrar; ubicación y pago de cada una; cajas y pesos/medidas; cargos de envío; destino y guías. No se exige el mismo desglose de contenido por caja que con seguro.

#### Flujo operativo

**01.** Cliente → vendedora: solicita integrar pedidos pagados y confirma que no quiere seguro. La vendedora reúne sus referencias y las comparte con CEDIS.

**02.** PDV/custodia y traslado: envían los complementos si están fuera de CEDIS. CEDIS confirma recepción y mantiene en espera si todavía faltan piezas.

**03.** CEDIS: distribuye físicamente según volumen en varias cajas. La respuesta autoriza que el pedido vaya en cajas diferentes y se cobre junto con sus envíos en una misma remisión, sin la restricción de remisión completa por caja del caso asegurado.

**04.** CEDIS → vendedora: informa número de cajas, medidas, peso y evidencias de preparación; no se requiere el mismo detalle de perfumes por caja que cuando lleva seguro.

**05.** Vendedora → cliente: presenta el total pendiente de envíos y mercancía nueva, reconociendo remisiones ya pagadas. La auxiliar verifica pago o crédito y emite la documentación correspondiente.

**06.** Encargado de guías: recibe destino y datos de cada paquete y genera guías. CEDIS las coloca y despacha todos los paquetes relacionados desde CEDIS.

**Decisiones y ramificaciones:** Cliente decide asegurar: cambiar a N-11 y solicitar a CEDIS la distribución detallada obligatoria. Más piezas: N-06 con revisión de cajas/pesos.

**Resultado y condición de cierre:** Pedidos integrados y enviados en varias cajas sin seguro, con cobro conjunto permitido y relación de remisiones incorporadas.

**Trazabilidad de la ficha:** Nacional P12; cuestionario 23.

<a id="bookmark=id.btjxurnqz5oe"></a>

### N-13 — Pedido nacional grande sin resguardos previos

**Causa y caso de entrada:** Una sola compra contiene tantas piezas que necesita dos o más cajas, aunque no incorpore pedidos anteriores.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas; encargado de guías.

**Datos/documentos que recibe o comparte el proceso:** Pedido; piezas y valor; medidas y peso por caja; número de cajas; elección de seguro; destino; cargos; pagos/remisiones y guías.

#### Flujo operativo

**01.** Cliente → vendedora: solicita la compra grande y domicilio. La vendedora genera el pedido en Wizerp y lo comparte con CEDIS.

**02.** CEDIS: confirma existencia y detalles, separa físicamente según capacidad de empaque y responde medidas y peso de dos o más cajas con evidencias.

**03.** Vendedora → cliente: consulta seguro y presenta las opciones/cargos que correspondan al número real de paquetes, sin cotizar como una sola caja por tratarse de un único pedido.

**04.** Si lleva seguro: CEDIS identifica contenido por caja y se revisa la documentación para cumplir remisiones completas por caja. La forma de resolver una sola remisión que no cabe en una caja debe definirse antes de documentar el seguro.

**05.** Si no lleva seguro: se permite repartir el pedido entre cajas y cobrar compra y envíos en una remisión según la aclaración; CEDIS informa peso/medidas por paquete.

**06.** Cliente → vendedora → auxiliar: paga el total o se valida crédito; la auxiliar emite las remisiones aplicables. El encargado de guías recibe datos por paquete y cobertura.

**07.** CEDIS: coloca guías, confirma el conjunto preparado y entrega todos los paquetes al transporte desde CEDIS.

**Decisiones y ramificaciones:** Con seguro: reglas N-03/N-11. Sin seguro: N-12. Si incorpora compras anteriores durante preparación, pasa a consolidación manteniendo sus referencias.

**Resultado y condición de cierre:** Compra grande preparada y despachada como múltiples paquetes con costos y documentación acordes, o pendiente de resolver distribución asegurada.

**Trazabilidad de la ficha:** Nacional P13 y P11-P12; cuestionario 23.

<a id="bookmark=id.qm4biveo3t5n"></a>

### N-14 — Cancelar un pedido por falta de pago, error o solicitud del cliente

**Causa y caso de entrada:** El cliente desiste, no paga, la vendedora solicitó por error o las piezas se pedirán en otro almacén.

**Involucrados y alcance:** Cliente cuando origina la solicitud; vendedora; gerente/Administración; CEDIS; auxiliar de ventas; encargado de guías si existe guía; Rafael después de entrega al transporte.

**Datos/documentos que recibe o comparte el proceso:** Pedido; motivo; evidencia; pagos/remisión; ubicación y avance; piezas apartadas; guías/cargos; resolución de saldo o devolución; nuevo pedido si cambia almacén.

#### Flujo operativo

**01.** Cliente o vendedora: identifica la causa. La vendedora registra si es desistimiento, falta de pago, error propio o nueva solicitud en otro almacén, con evidencia cuando corresponde.

**02.** Vendedora → gerente/Administración y CEDIS: comunica la cancelación a gestionar y las referencias del pedido. CEDIS informa si sigue en preparación o ya entregó al transporte.

**03.** Antes de salida y sin pago: se cierra la solicitud correspondiente y se notifica expresamente a CEDIS para que libere las piezas apartadas. Si se pedirá en otro almacén, se inicia una solicitud nueva relacionada.

**04.** Si ya pagó: Administración autoriza saldo a favor o reembolso según lo elegido/acordado con el cliente. El reembolso informado es de 3 a 5 días hábiles en la respuesta específica de cancelación; no se da por realizado por solicitarlo.

**05.** Auxiliar de ventas y Administración: revisan si existe remisión y qué documentos requieren ajuste. No se confunde cancelar una solicitud de envío con anular toda remisión; el detalle de cancelación contable no está descrito.

**06.** Encargado de guías y CEDIS: reciben la instrucción de no despachar y gestionan documentación afectada. Cargos de guía, seguro o transporte ya consumidos requieren resolución no definida todavía.

**07.** Si ya se entregó el paquete al transporte: se canaliza con Rafael para solicitar la gestión posible; no se declara cancelación física completada ni liberación de piezas mientras no se confirme.

**Decisiones y ramificaciones:** Saldo de mercancía: S-02/S-04 según origen. Reembolso: resolución administrativa. Solo cambio de modalidad conservando pedido: T-04/N-10. Falta de pago a crédito: aplicar su tolerancia y no inventar cierre posterior.

**Resultado y condición de cierre:** Cancelación gestionada con causa, comunicación y tratamiento físico/financiero explícitos, o solicitud excepcional pendiente si el paquete ya está en transporte.

**Trazabilidad de la ficha:** Nacional P14; cuestionario 1, 10, 12 y 18.

<a id="bookmark=id.x502banb1rhl"></a>

### N-15 — Envío nacional con crédito comercial

**Causa y caso de entrada:** El cliente solicita envío nacional y desea cargar el importe a su crédito comercial.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas; encargado de guías; transporte.

**Datos/documentos que recibe o comparte el proceso:** Pedido y domicilio; paquetería; pesos/cajas; cargos de mercancía, envío, seguro y reexpedición; método crédito; resultado Wizerp; remisión; guías.

#### Flujo operativo

**01.** Cliente → vendedora: solicita compra, destino y crédito. La vendedora crea el pedido y solicita a CEDIS disponibilidad, detalles, evidencias y pesaje.

**02.** CEDIS → vendedora → cliente: se resuelven cambios de piezas y empaque. La vendedora identifica paquetería, seguro opcional y reexpedición, y cierra el importe a cargar.

**03.** Vendedora → auxiliar de ventas: comparte pedido y total con método crédito, sin comprobante de transferencia. Puede incluir todos los conceptos respondidos; no se mezcla con otro método de pago.

**04.** Auxiliar de ventas: tramita en Wizerp y comprueba si permite la remisión. Si bloquea, informa a vendedora; esta da seguimiento a regularización con tolerancia hasta el día siguiente.

**05.** Con crédito aprobado: la auxiliar emite remisión y el cargo aparece en el estado de cuenta de Wizerp. Vendedora y auxiliar entregan al encargado de guías los datos seleccionados y revisados.

**06.** Encargado de guías: genera documentación y la envía a CEDIS. CEDIS empaca, coloca guías y despacha desde CEDIS como en el nacional ordinario.

**07.** Sin aprobación: no se continúa por la mera ausencia de comprobante; el pedido queda bloqueado hasta resolución y no se inventa un método alterno para evitar el control.

**Decisiones y ramificaciones:** Crédito regularizado: retomar validación. Tolerancia vencida sin regularizar: AC-03. Varias cajas: N-11/N-12/N-13 según seguro.

**Resultado y condición de cierre:** Envío nacional con cargo aprobado a crédito y documentación logística completa, o pedido detenido con causa y responsable informados.

**Trazabilidad de la ficha:** Nacional P15; tienda P9; cuestionario 13-15.

## 5D. Resguardos y complementos

Las fichas siguientes detallan los intercambios, decisiones y resultados. Las referencias a otro caso indican la continuación aplicable; no duplican el cobro ni el movimiento físico.

<a id="bookmark=id.b1dlow9jbvfa"></a>

### R-01 — Custodia de pedidos de tienda pagados

**Causa y caso de entrada:** Un pedido pagado para tienda llega a la sucursal y el cliente todavía no acude a recogerlo.

**Involucrados y alcance:** CEDIS; traslado; auxiliar de PDV; recepción/custodia; vendedora; cliente.

**Datos/documentos que recibe o comparte el proceso:** Pedido y remisión; bolsas recibidas; pago; ubicación; fecha de remisión; observaciones y evidencia de piezas; fotografía de entrega al cliente.

#### Flujo operativo

**01.** CEDIS o PDV: prepara el pedido pagado y documentado conforme a T-02. Si viene de CEDIS, el traslado entrega las bolsas en la sucursal solicitada.

**02.** Auxiliar de PDV: recibe y verifica las bolsas y observaciones; después las entrega a recepción/custodia. El pedido queda resguardado para recolección, no como mercancía libre.

**03.** Vendedora: sigue el plazo de 20 días naturales desde la remisión y contacta al cliente para que recoja; CEDIS comunica cuando advierte la demora.

**04.** Cliente → tienda: acude a recoger. La llegada del cliente es la condición aclarada que libera este resguardo; el personal localiza el pedido y lo retira de custodia para entrega.

**05.** Tienda: entrega y toma la fotografía de pedido entregado. Como control propuesto, la foto queda relacionada con remisión, fecha y responsable para no confundir otra entrega.

**06.** Si el cliente no recoge al cumplir el plazo: se mantiene identificado el pendiente y se escala a quien corresponda; no se libera mercancía pagada ni se cancela automáticamente por una regla no acordada.

**Decisiones y ramificaciones:** Cliente pide incorporar a envío: T-05/M-04; la liberación ya no es por llegada del cliente sino por el cambio de destino coordinado. Cambio de sucursal tras traslado: autorización de gerente, T-03.

**Resultado y condición de cierre:** Pedido conservado en custodia hasta recolección o movimiento autorizado; entrega final con evidencia.

**Límite aún no definido:** Comprobante de entregas internas y consecuencia al vencer el plazo de recolección siguen pendientes.

**Trazabilidad de la ficha:** Situaciones Resguardos P1; tienda P2; cuestionario 7 y 26; aclaración de liberación por recolección.

<a id="bookmark=id.w51z5sdt9gyw"></a>

### R-02 — Espera de fecha para envío municipal ya pagado

**Causa y caso de entrada:** El pedido y transporte están pagados, pero el cliente pide que se procese o envíe otro día.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; CEDIS/PDV; distribución.

**Datos/documentos que recibe o comparte el proceso:** Remisión; pagos; fecha solicitada; ubicación; instrucción de espera y de liberación; identificación azul/amarillo.

#### Flujo operativo

**01.** Cliente → vendedora: indica la fecha posterior. La vendedora comparte a CEDIS/PDV pedido, pago y la instrucción de no despachar aún.

**02.** Auxiliar de ventas: verifica que mercancía y envío estén cubiertos y que exista remisión; no se confunde con la acumulación cuyo envío sigue pendiente.

**03.** CEDIS/PDV: marca azul y conserva piezas comprometidas en la ubicación informada. La vendedora da seguimiento a la fecha solicitada.

**04.** Vendedora → área de preparación: comunica la liberación conforme al proceso acordado. Como la confirmación adicional no está definida, la fecha no se convierte en disparador automático en esta especificación.

**05.** CEDIS/PDV: identifica que puede procesar —amarillo en la práctica descrita—, empaca y distribuye hacia terminal, conservando registro de salida.

**Decisiones y ramificaciones:** Mismo recorrido detallado: M-09. Si decide añadir compras: M-10/M-03. Si cambia a tienda antes de salida: M-07.

**Resultado y condición de cierre:** Espera respetada y envío continuado solo con instrucción identificada, sin reasignar piezas a otros clientes.

**Trazabilidad de la ficha:** Situaciones Resguardos P2; municipal P9; cuestionario 27.

<a id="bookmark=id.mznk7l2j2w5d"></a>

### R-03 — Acumulación municipal con número de cajas no aplicable

**Causa y caso de entrada:** El cliente seguirá agregando mercancía y no ha solicitado cerrar el envío.

**Involucrados y alcance:** Cliente; vendedora; CEDIS/PDV; auxiliar de ventas.

**Datos/documentos que recibe o comparte el proceso:** Remisiones de compras pagadas; referencia del cliente; ubicación; azul; cajas N/A; terminal prevista; instrucción de cierre.

#### Flujo operativo

**01.** Vendedora: reúne solicitudes de mercancía del cliente y las comparte para disponibilidad y evidencias, informando que no deben despacharse todavía.

**02.** Cliente y auxiliar: completan pago y remisión de cada compra. CEDIS/PDV mantiene piezas pagadas separadas e identificadas por pedido/remisión.

**03.** CEDIS/PDV: registra azul y N/A en cajas mientras no aplica un número al envío aún no cerrado. No se interpreta N/A como cero ni como una cantidad calculada.

**04.** Cliente → vendedora: decide qué compras integrará y que ya puede prepararse el envío. La vendedora comparte la lista definitiva y solicita consolidación física.

**05.** CEDIS/PDV → vendedora: confirma recepción de todos los complementos, empaque y cajas reales. Se sustituye el dato N/A cuando ya corresponde documentar los paquetes del envío, como requisito derivado del cierre de preparación.

**06.** Vendedora y auxiliar: resuelven envío pendiente y liberan al recorrido municipal; en Jaguar el cobro sucede después de determinarlo la terminal.

**Decisiones y ramificaciones:** Flujo principal: M-10. Una compra nueva impagada no se incluye. Complementos en otra ubicación requieren traslado y recepción, no solo una referencia escrita.

**Resultado y condición de cierre:** Compras pagadas mantenidas en espera y luego integradas en paquetes reales con transporte pendiente o pagado según paquetería.

**Trazabilidad de la ficha:** Situaciones Resguardos P3; municipal P10; V01 revisada N/A; cuestionario 27.

<a id="bookmark=id.ebcszumrpjuo"></a>

### R-04 — Mercancía nacional pagada sin paquetería definida

**Causa y caso de entrada:** El cliente quiere asegurar la compra de piezas, pero todavía no decide paquetería ni solicita despachar.

**Involucrados y alcance:** Cliente; vendedora; CEDIS; auxiliar de ventas; encargado de guías al liberarse.

**Datos/documentos que recibe o comparte el proceso:** Pedido y remisión de mercancía; comprobante; paquetería pendiente; ubicación en CEDIS; pesos disponibles; decisión posterior y cargos pendientes.

#### Flujo operativo

**01.** Cliente → vendedora: solicita piezas y comunica la espera. La vendedora genera el pedido y lo consulta con CEDIS para disponibilidad, condiciones y peso.

**02.** Cliente → vendedora → auxiliar: paga solo la mercancía confirmada. La auxiliar verifica ingreso y emite remisión por el alcance reconocido; no se reporta como pagado un envío aún no cotizado.

**03.** CEDIS: conserva mercancía identificada sin despacharla. La vendedora mantiene la paquetería como pendiente y no solicita una guía definitiva.

**04.** Cliente → vendedora: elige paquetería y decide continuar. La vendedora comunica la selección, calcula cargos conocidos y seguro opcional/reexpedición, y pide pago de lo pendiente.

**05.** Auxiliar y encargado de guías: verifican importe y datos, generan documentación del envío y la entregan a CEDIS.

**06.** CEDIS: libera la espera, termina empaque y despacha con guía desde su instalación.

**Decisiones y ramificaciones:** N-04 desarrolla la decisión de paquetería. Más compras antes de salir: N-06/N-11/N-12. Si falta mercancía de otro almacén, sumar motivo de espera R-05.

**Resultado y condición de cierre:** Mercancía pagada conservada sin despacho y enviada después de definir servicio y cubrir los cargos aplicables.

**Trazabilidad de la ficha:** Situaciones Resguardos P4; nacional P4.

<a id="bookmark=id.qwtajyaeg1iu"></a>

### R-05 — Pedido nacional principal esperando complemento de otro almacén

**Causa y caso de entrada:** Las piezas del pedido no están en una sola ubicación y la parte principal espera que llegue el resto a CEDIS.

**Involucrados y alcance:** Vendedora; cliente; CEDIS; auxiliar de PDV u otro almacén; traslado; auxiliar de ventas.

**Datos/documentos que recibe o comparte el proceso:** Pedido principal; remisiones complementarias; piezas faltantes; origen/destino; pago de complementos; identificación azul; salida y recepción del traslado.

#### Flujo operativo

**01.** Vendedora y CEDIS: identifican qué piezas están en CEDIS y cuáles en otra ubicación; se informa al cliente la necesidad de reunirlas antes de despachar.

**02.** Vendedora → ubicación del complemento: comparte pedido/remisión y solicita preparar y trasladar las piezas. El origen responde disponibilidad, detalles y pago/documentación reconocidos.

**03.** Auxiliar de ventas: comprueba pago de los complementos antes de su envío. El principal permanece en resguardo azul mientras no se confirma su llegada.

**04.** CEDIS: conserva las piezas principales identificadas y no las da como conjunto completo por el solo aviso de que el complemento fue solicitado o enviado.

**05.** Ubicación origen/traslado → CEDIS: entrega el complemento. CEDIS confirma contenido recibido, observaciones y relación con el principal.

**06.** Con el conjunto reunido: CEDIS cambia la identificación de espera a condición de preparación —amarillo— y reporta peso, cajas y evidencias revisadas. La vendedora continúa cargos, pago pendiente y guías según seguro.

**Decisiones y ramificaciones:** Traslado del complemento: R-06. Con seguro: N-11. Sin seguro: N-12. Si falta o llega distinto: mantener pendiente y comunicar a vendedora para resolver con origen y cliente.

**Resultado y condición de cierre:** Principal completado físicamente en CEDIS y habilitado para empaque nacional, o espera identificada por complemento faltante.

**Trazabilidad de la ficha:** Situaciones Resguardos P5; cuestionario 11 y 27.

<a id="bookmark=id.ts5l5b6c7jwq"></a>

### R-06 — Preparar, trasladar y recibir el complemento nacional

**Causa y caso de entrada:** Una parte del pedido está en otro almacén y debe enviarse a CEDIS para reunirse con el principal.

**Involucrados y alcance:** Vendedora; auxiliar de origen/PDV; auxiliar de ventas; custodia si estaba resguardado; traslado; CEDIS.

**Datos/documentos que recibe o comparte el proceso:** Pedido principal y remisión del complemento; piezas y evidencia; origen; CEDIS destino; comprobación de pago; identificación azul; confirmación de recepción.

#### Flujo operativo

**01.** Vendedora → auxiliar de origen: identifica principal, piezas y remisión que deben trasladarse a CEDIS; si están en custodia, solicita su retiro con el auxiliar responsable.

**02.** Auxiliar de ventas/origen: comprueba que el complemento esté pagado. La fuente indica que normalmente se envía el mismo día del pago; no se convierte en garantía horaria universal.

**03.** Auxiliar de origen: prepara las piezas, comparte evidencias y las identifica con el principal; conserva azul mientras el principal sigue esperando.

**04.** Traslado: lleva las piezas al almacén donde se reúne el pedido, que para salida nacional debe ser CEDIS. Origen informa que salió, sin equiparar ese aviso con recepción.

**05.** CEDIS: recibe y verifica piezas y referencias. Comunica a la vendedora la recepción e integración o reporta diferencia si no coincide.

**06.** CEDIS: al reunir el conjunto habilita el empaque en amarillo y recalcula peso/cajas cuando cambia el conjunto; la vendedora continúa con cargos y guía del envío final.

**Decisiones y ramificaciones:** Si el origen aún no recibe pago: no despacha complemento. Si el principal sigue incompleto: R-05. Si se trata de incorporar custodia de tienda: T-05.

**Resultado y condición de cierre:** Complemento recibido y unido al principal en CEDIS con trazabilidad desde origen; la simple salida del origen no cierra este proceso.

**Límite aún no definido:** No se ha definido el comprobante interno uniforme de entrega/recepción de todos los traslados.

**Trazabilidad de la ficha:** Situaciones Resguardos P6; cuestionario 11 y 26-27.

## 5E. Saldos a favor

Las fichas siguientes detallan los intercambios, decisiones y resultados. Las referencias a otro caso indican la continuación aplicable; no duplican el cobro ni el movimiento físico.

<a id="bookmark=id.x1y8h6bayeh8"></a>

### S-01 — Depósito superior al importe solicitado

**Causa y caso de entrada:** El cliente transfiere más dinero del que corresponde a su pedido.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; Administración; PDV si allí se registra el saldo.

**Datos/documentos que recibe o comparte el proceso:** Pedido y total; comprobante e ingreso verificado; excedente; destino mercancía/envío; autorización y registro de saldo o aplicación del mismo día.

#### Flujo operativo

**01.** Cliente → vendedora: entrega comprobante. La vendedora compara con el importe confirmado y señala el excedente al auxiliar al reportar el pago.

**02.** Auxiliar de ventas: verifica ingreso y diferencia real, evitando confundir un comprobante repetido con dinero efectivamente recibido dos veces.

**03.** Si el excedente es mayor a \$10: Administración autoriza el saldo y se registra cliente, origen, importe y uso permitido. La vendedora comunica el saldo y su plazo de uso indicado de 20 días.

**04.** Si es menor a \$10 y el cliente lo usará ese mismo día: la vendedora hace la observación al subir el pago; la auxiliar comprueba la aplicación antes de remitir. Si no lo usa ese día, la fuente indica que no queda saldo, pero no detalla su registro interno.

**05.** Vendedora y auxiliar: aplican al pedido únicamente el importe que lo cubre y conservan el excedente identificado; la diferencia no aumenta por sí sola las piezas del pedido.

**06.** Administración: conserva autorización y movimientos para que el mismo dinero no se aplique dos veces. Si el cliente elige reembolso, se tramita con su autorización y plazo informado.

**Decisiones y ramificaciones:** Exactamente \$10: AC-02, discrepancia aún no resuelta expresamente. Excedente del transporte: S-09, restringido a envío. Duplicidad: S-07.

**Resultado y condición de cierre:** Pedido cubierto y excedente con destino explícito —saldo, aplicación del día o devolución autorizada—, sin sumarlo informalmente a la venta.

**Límite aún no definido:** Inicio del plazo de saldos sin remisión y registro de diferencias menores no utilizadas requieren precisión; no se fija una pérdida contable automática.

**Trazabilidad de la ficha:** Situaciones Saldos P1; cuestionario 17-18; aclaración mayor a \$10.

<a id="bookmark=id.o36b5ml3nu87"></a>

### S-02 — Retirar una pieza de un pedido recién pagado

**Causa y caso de entrada:** El cliente acaba de pagar y decide no llevar una de las piezas.

**Involucrados y alcance:** Cliente; vendedora; CEDIS/PDV; auxiliar de ventas; Administración.

**Datos/documentos que recibe o comparte el proceso:** Pedido/remisión; pieza y valor; evidencia del retiro; ubicación/avance; importe pagado; autorización y saldo/reembolso.

#### Flujo operativo

**01.** Cliente → vendedora: pide retirar la pieza. La vendedora identifica la pieza, precio y evidencia y consulta si sigue físicamente en preparación antes de prometer el retiro.

**02.** Vendedora → CEDIS/PDV y Administración: informa el cambio y solicita la gestión. El área física confirma si puede retirar y libera la pieza del conjunto solo con la instrucción documentada.

**03.** Auxiliar de ventas y Administración: revisan el documento existente y deciden el tratamiento de observación/cancelación que corresponda; la regla universal para elegir entre ambos no quedó definida.

**04.** Administración: autoriza saldo o reembolso del importe procedente. La respuesta posterior aclara que no tiene que usarse necesariamente en la siguiente compra, siempre que no se exceda el plazo indicado de 20 días.

**05.** Vendedora → cliente: informa importe y condiciones, da seguimiento para que use el saldo o acepta la solicitud de reembolso conforme al procedimiento administrativo.

**06.** Área de preparación: continúa con las piezas definitivas y evidencia actualizada; si el retiro cambia peso o envío, la vendedora revisa esa consecuencia por separado.

**Decisiones y ramificaciones:** Si el paquete ya salió: no se da por retirado; gestión excepcional nacional N-14 o limitación municipal M-07. Devolución física posterior: S-04. Exactamente \$10/importe menor: no extender sin validar el alcance de la regla de excedentes.

**Resultado y condición de cierre:** Pieza retirada cuando es viable y diferencia de dinero autorizada/documentada, con pedido restante coherente y seguimiento de saldo.

**Trazabilidad de la ficha:** Situaciones Saldos P2; cuestionario 10 y 20.

<a id="bookmark=id.ytakxbcahe2a"></a>

### S-03 — Depósito por error sin pedido asociado

**Causa y caso de entrada:** El cliente transfiere por equivocación a la cuenta sin haber solicitado un pedido.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; Administración.

**Datos/documentos que recibe o comparte el proceso:** Identidad del depositante; comprobante; cuenta receptora, importe y referencia; verificación del ingreso; motivo y resolución autorizada; no existe pedido obligatorio de origen.

#### Flujo operativo

**01.** Cliente → vendedora: informa la transferencia errónea y proporciona comprobante y datos para identificar el ingreso.

**02.** Vendedora → auxiliar de ventas/Administración: reporta el caso como depósito sin pedido y no fabrica una venta para justificar el dinero.

**03.** Auxiliar de ventas: verifica que el ingreso exista y corresponda al cliente. Administración autoriza el destino del importe: saldo permitido o devolución según la solicitud y reglas aplicables.

**04.** Vendedora → cliente: comunica la resolución y, si corresponde, plazo de reembolso informado de 3 a 5 días; la respuesta específica de cancelación precisa hábiles, pero este caso no tiene su propio cómputo detallado.

**05.** Administración: registra origen y resolución; si queda saldo, lo relaciona después con la compra donde se aplique. Como no hay remisión inicial, no se inventa una fecha de remisión para iniciar el plazo.

**06.** Una vez aplicado o reembolsado: se deja referencia del movimiento para evitar uso posterior del mismo importe.

**Decisiones y ramificaciones:** Si hay un pedido real no identificado inicialmente, vincularlo después de verificar. Depósito duplicado de un pedido: S-07, no este caso.

**Resultado y condición de cierre:** Ingreso sin venta reconocido y resuelto administrativamente, o pendiente con causa, sin crear un pedido ficticio.

**Límite aún no definido:** Documento específico, inicio del plazo de saldo sin remisión y cómputo de reembolso para este supuesto no se detallaron.

**Trazabilidad de la ficha:** Situaciones Saldos P3; cuestionario 18 y 20.

<a id="bookmark=id.a89nvr1rdn3m"></a>

### S-04 — Devolución de mercancía con nota de crédito

**Causa y caso de entrada:** Una devolución aceptada origina una nota de crédito para usar su importe en otra compra.

**Involucrados y alcance:** Cliente; vendedora; área que recibe/autoriza la devolución; Administración; auxiliar de ventas; PDV si controla el saldo.

**Datos/documentos que recibe o comparte el proceso:** Remisión original; mercancía devuelta; aceptación administrativa; nota de crédito e importe; saldo disponible; pedido de aplicación y remanente.

#### Flujo operativo

**01.** Cliente → vendedora: solicita la devolución e identifica compra y piezas. La recepción, inspección y criterios para aceptar devoluciones pertenecen a un procedimiento no descrito completamente en estas fuentes.

**02.** Administración/área autorizada: confirma la devolución y su documento; la nota de crédito representa el monto reconocido para mercancía, no un saldo de envío.

**03.** Vendedora: informa al cliente el importe disponible y conserva la nota para la compra en que se use; da seguimiento al plazo de uso indicado sin asumir una fecha no definida para todos los orígenes.

**04.** En la compra nueva: cliente y vendedora seleccionan piezas; CEDIS/PDV confirma disponibilidad. La vendedora adjunta pedido, nota y comprobante de diferencia si existe.

**05.** Auxiliar de ventas: verifica validez e importe disponible y aplica la nota al emitir la remisión del pedido nuevo. Si viene de PDV, primero se comprueba con su Drive y responsables.

**06.** Administración/auxiliar responsable: registra el importe utilizado y remanente; continúa preparación/entrega del pedido nuevo según modalidad. No se declara agotada una nota si solo se usó parte.

**Decisiones y ramificaciones:** Aplicación municipal detallada: M-11. Uso de saldo PDV: S-05. Reembolso solicitado: Administración resuelve conforme al caso, sin inferir que toda devolución física se convierte automáticamente en efectivo.

**Resultado y condición de cierre:** Devolución aceptada respaldada por nota y aplicación trazable en compra posterior; aceptación física de la devolución no se sustituye por este flujo financiero.

**Límite aún no definido:** Política de aceptación de devoluciones y responsable uniforme de remanentes no fueron documentados.

**Trazabilidad de la ficha:** Situaciones Saldos P4; municipal P11; cuestionario 19.

<a id="bookmark=id.1g642e4ih68t"></a>

### S-05 — Aplicar saldo a favor originado en PDV en otra compra

**Causa y caso de entrada:** El cliente quiere utilizar saldo registrado por PDV, incluso en una sucursal distinta.

**Involucrados y alcance:** Cliente; vendedora; responsables de PDV y su Drive; auxiliar de ventas; Administración.

**Datos/documentos que recibe o comparte el proceso:** Cliente; origen PDV; tipo de saldo; importe disponible y vigencia; Drive/registro consultado; nuevo pedido; importe aplicado y remanente.

#### Flujo operativo

**01.** Cliente → vendedora: informa que tiene saldo de PDV. La vendedora identifica al cliente y solicita referencia de origen, sin aceptarlo solo por la afirmación del cliente.

**02.** Vendedora → PDV: pide comprobar que el saldo existe y está disponible. PDV consulta su Drive y responde si está vigente, ya aplicado o utilizado.

**03.** Si no existe o está usado: la vendedora comunica el resultado y no descuenta un importe no confirmado. Si está disponible: confirma el monto y la restricción de uso.

**04.** Vendedora → auxiliar de ventas: presenta pedido nuevo y evidencia de confirmación PDV, señalando expresamente que se aplicará saldo a favor de PDV. Puede usarse en cualquier sucursal según la respuesta.

**05.** Auxiliar: verifica lo aplicado y el pago de la diferencia, y emite la remisión. Si el saldo es de envío, solo cubre cargos de envío; no se mezcla con mercancía.

**06.** PDV/Administración y responsable de registro: dejan constancia del consumo y del remanente en el control correspondiente antes de considerar el importe disponible para otra compra.

**Decisiones y ramificaciones:** Saldo no vigente: no aplicar sin resolución administrativa. Nota de devolución: S-04/M-11. La fuente permite cualquier sucursal, pero no define entidades legales o marcas separadas; no se extiende ese alcance por inferencia.

**Resultado y condición de cierre:** Saldo de PDV verificado y aplicado una sola vez, con actualización del control y referencia en la compra actual.

**Trazabilidad de la ficha:** Situaciones Saldos P5; cuestionario 19.

<a id="bookmark=id.umtt6qercyof"></a>

### S-06 — Saldo de envío por cambio a recolección

**Causa y caso de entrada:** El cliente pagó transporte, pero cambia a recoger en tienda antes del punto en que ya no procede detener el envío.

**Involucrados y alcance:** Cliente; vendedora; CEDIS/PDV; gerente/Administración; auxiliar de ventas.

**Datos/documentos que recibe o comparte el proceso:** Pedido/remisión; pago de envío; evidencia del cambio; estado de salida; importe reconocido; registro de saldo exclusivo de envío.

#### Flujo operativo

**01.** Cliente → vendedora: pide recoger en tienda. La vendedora comprueba con CEDIS/PDV que aún sea viable y reúne evidencia del pago de transporte.

**02.** Área operativa: detiene la solicitud de envío conforme al caso, conserva mercancía y comunica la nueva ubicación/recolección. En municipal, no procede si ya está camino a terminal.

**03.** Administración: autoriza el saldo del monto de envío que corresponde reconocer. Si existen gastos ya incurridos no recuperables, no se asume su tratamiento sin resolución.

**04.** Vendedora/auxiliar: registra cliente, origen, importe y restricción ENVÍO; informa al cliente que no puede usarlo en mercancía.

**05.** En el siguiente envío elegible: la vendedora solicita aplicar el saldo, se comprueba disponibilidad y se registra el consumo y remanente. La mercancía del nuevo pedido conserva su propio pago.

**06.** Si el cliente solicita devolución: Administración tramita lo que corresponda al caso y sus plazos, sin convertir automáticamente el saldo en efectivo ni en descuento de productos.

**Decisiones y ramificaciones:** Municipal a tienda: M-07. Nacional a tienda: N-10. Diferencia por tarifa menor: S-09. No mezclar tipos de saldo.

**Resultado y condición de cierre:** Saldo de transporte identificado y restringido, asociado al envío que dejó de realizarse o cambió de modalidad.

**Trazabilidad de la ficha:** Situaciones Saldos P6; municipal P7; cuestionario 18.

<a id="bookmark=id.tblia8j831yj"></a>

### S-07 — Transferencia duplicada por error

**Causa y caso de entrada:** El cliente paga dos veces el mismo pedido sin intención de dividir el importe.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; Administración.

**Datos/documentos que recibe o comparte el proceso:** Pedido; total; dos referencias bancarias reales; importes y verificación de ambos ingresos; pago aplicado a compra; excedente y resolución.

#### Flujo operativo

**01.** Cliente o vendedora: detecta dos transferencias por el mismo pedido y reúne ambos comprobantes con sus referencias.

**02.** Auxiliar de ventas: verifica que existan dos ingresos distintos. Dos copias de un mismo comprobante no equivalen a dos pagos.

**03.** Vendedora y auxiliar: aplican un pago a la compra y señalan el segundo como duplicado; no generan otro pedido ni otra entrega por el depósito extra.

**04.** Administración: autoriza saldo a favor o devolución según la solicitud del cliente y las reglas aplicables; registra origen y destino del excedente.

**05.** Vendedora → cliente: informa resolución y seguimiento. Si se utiliza después, se verifica saldo disponible y se referencia la compra donde se consume.

**06.** Administración: en su revisión posterior contempla ambos movimientos y posibles retornos bancarios, sin dejar registrado como saldo un ingreso inexistente.

**Decisiones y ramificaciones:** Dos transferencias que completan un solo importe: M-12. Depósito sin pedido: S-03. Excedente parcial: S-01.

**Resultado y condición de cierre:** Pedido pagado una sola vez y segundo ingreso reconocido con resolución independiente, sin entrega duplicada.

**Trazabilidad de la ficha:** Situaciones Saldos P7; cuestionario 2 y 18.

<a id="bookmark=id.9ghsxqpgetmv"></a>

### S-08 — Cobro excesivo por error de la vendedora

**Causa y caso de entrada:** La vendedora cobró más de lo que correspondía a las piezas o conceptos aceptados.

**Involucrados y alcance:** Cliente; vendedora; auxiliar de ventas; Administración; gerente para gestión de corrección.

**Datos/documentos que recibe o comparte el proceso:** Pedido correcto; monto solicitado por error; ingreso comprobado; diferencia; causa operativa; autorización de saldo/reembolso y documentos afectados.

#### Flujo operativo

**01.** Cliente o personal: detecta diferencia entre el importe que debió cobrarse y lo recibido. La vendedora reúne pedido, comprobante y explicación del error.

**02.** Auxiliar de ventas: verifica el ingreso y determina con Administración el importe correcto y excedente real; no atribuye el cambio al cliente si fue error de cobro.

**03.** Gerente/Administración: gestiona la corrección y autoriza saldo o reembolso. Se identifica si la diferencia pertenece a mercancía o envío para respetar restricciones.

**04.** Vendedora → cliente: comunica importe y resolución; si se conserva saldo, informa plazo de uso indicado. Si se devuelve, comunica el plazo de reembolso aplicable.

**05.** Auxiliar y Administración: conservan observación, documentos afectados y movimiento del saldo. Si existe remisión, el procedimiento para corregirla se resuelve conforme al caso, sin una cancelación automática general.

**06.** Pedido físico: continúa con la mercancía realmente aceptada; el excedente no se compensa agregando piezas sin solicitud del cliente.

**Decisiones y ramificaciones:** Depósito de más por iniciativa del cliente: S-01. Pieza retirada por el cliente después de pagar: S-02. Diferencia de transporte: S-09.

**Resultado y condición de cierre:** Error de cobro identificado, importe corregido y diferencia documentada, sin modificar unilateralmente la mercancía.

**Trazabilidad de la ficha:** Situaciones Saldos P8; cuestionario 10 y 18.

<a id="bookmark=id.1csdzlu8tpjf"></a>

### S-09 — Saldo de envío por costo final menor o cambio de paquetería

**Causa y caso de entrada:** El transporte cuesta menos de lo cobrado o el cambio de paquetería genera una diferencia a favor del cliente.

**Involucrados y alcance:** Cliente; vendedora; repartidor/paquetería; auxiliar de ventas; Administración.

**Datos/documentos que recibe o comparte el proceso:** Pago original de transporte; costo definitivo y evidencia; diferencia; pedido/remisión; tabla de saldo de envío; aplicaciones posteriores.

#### Flujo operativo

**01.** Repartidor/paquetería → vendedora: comunica el costo definitivo con comprobante o fotografía, o la vendedora obtiene el nuevo importe al cambiar paquetería.

**02.** Vendedora y auxiliar: comparan costo/cobro y verifican qué importe pagó realmente el cliente. No se genera saldo por un importe que aún no había pagado.

**03.** Administración: autoriza la diferencia procedente. Vendedora/auxiliar la registra en la tabla de saldos con origen, cliente y remisión.

**04.** Vendedora → cliente: informa que el saldo es exclusivo para envíos. No se mezcla con notas o saldos de mercancía.

**05.** En uso posterior: se verifica vigencia y disponibilidad, se aplica solo a transporte y se registra el consumo y remanente junto al nuevo envío.

**06.** Si la diferencia es a pagar en vez de a favor: no se registra saldo negativo ficticio; la vendedora solicita el cargo adicional y la auxiliar verifica ingreso.

**Decisiones y ramificaciones:** Cambio de paquetería: N-05. Jaguar: M-05/P-01. Menor de \$10 o exactamente \$10 en saldo exclusivo de envío: el alcance de la regla de excedentes debe confirmarse, sin trasladarla automáticamente a todas las diferencias.

**Resultado y condición de cierre:** Diferencia favorable de transporte comprobada y controlada con restricción de uso, o diferencia por cobrar comunicada.

**Trazabilidad de la ficha:** Situaciones Saldos P9; cuestionario 24.

## 5F. Pago de envío

Las fichas siguientes detallan los intercambios, decisiones y resultados. Las referencias a otro caso indican la continuación aplicable; no duplican el cobro ni el movimiento físico.

<a id="bookmark=id.35m9r3cxcgwo"></a>

### P-01 — Reportar y verificar un PAGO ENVÍO separado

**Causa y caso de entrada:** El costo de transporte se determina después del pago/remisión de mercancía y debe cobrarse por separado, como Jaguar.

**Involucrados y alcance:** Cliente; vendedora; repartidor; terminal; auxiliar de ventas; Administración; cajera de PDV en su proceso externo.

**Datos/documentos que recibe o comparte el proceso:** Remisión de mercancía; evidencia de costo; importe cobrado al cliente; comprobante del pago de envío; observación de relación; ficha de recolección; estado pendiente/verificado.

#### Flujo operativo

**01.** Terminal/repartidor → vendedora: entrega comprobante o foto del costo final del paquete. La vendedora identifica a qué cliente y remisión corresponde.

**02.** Vendedora → cliente: comunica el monto y solicita pagar el transporte; esta solicitud es distinta de la mercancía ya cubierta. El cliente recibe la información de que el cobro se determinaría después.

**03.** Cliente → vendedora: comparte comprobante. La vendedora lo reporta como PAGO ENVÍO y escribe en observaciones la remisión del pedido al que corresponde, sin rehacer la compra de mercancía.

**04.** Auxiliar de ventas: verifica comprobante e ingreso conforme al control inicial. Administración contempla el ingreso en cortes y realiza la revisión posterior; las fuentes señalan que este monto hoy no se registra en Wizerp.

**05.** Vendedora → cliente: una vez pagado, comparte la ficha para recoger según la secuencia Jaguar. La evidencia de costo de terminal y el comprobante del cliente se conservan diferenciados.

**06.** Caja PDV: la cajera proporciona dinero al repartidor como proceso separado. No se da por liquidado ese movimiento solo porque el cliente pagó; su evidencia y cierre son externos a este flujo.

**07.** Como requisito derivado: poder consultar pedido/remisión, costo, comprobante del cliente y resultado de verificación desde una misma relación de envío; no se prescribe aquí una pantalla ni una integración automática con caja.

**Decisiones y ramificaciones:** Cliente aún no paga: conserva cobro pendiente; no hay plazo o escalamiento definido. Diferencia favorable: S-09. Envío a crédito conocido después de remisión: AC-14, documentación pendiente.

**Resultado y condición de cierre:** Pago separado reportado, verificado y relacionado con la mercancía de origen; el saldo de cobro puede seguir abierto sin confundirlo con la entrega del paquete.

**Límite aún no definido:** No se definieron tratamiento del impago ni forma documental del cargo a crédito cuando el costo se conoce posteriormente.

**Trazabilidad de la ficha:** Situaciones Pago envío P1; municipal P5; cuestionario 21-22; aclaración de caja PDV.

## 6. Puntos abiertos y límites de interpretación

Estos puntos no suspenden los flujos ya confirmados. Impiden convertir una respuesta parcial en una regla inventada. Mientras no exista acuerdo, el resultado de un caso afectado se documenta como pendiente de resolución, sin automatizar su consecuencia.

### AC-01 — Vencimiento de mercancía pagada

Los 20 días naturales para recoger comienzan en la remisión. Falta qué se hace al vencer y desde cuándo se envían avisos; no se libera mercancía pagada automáticamente.<br>
<br>
R: Al vencer los 20 dias naturales, la mercancia procede a reintegrarse en el inventario “Se libera” y puede volver a utilizarse para venta.

**Casos afectados:** T-02, T-03, R-01

### AC-02 — Importe exacto de \$10

Última respuesta confirma mayor a \$10; antes se dijo a partir de \$10. No hay respuesta explícita para exacto \$10 ni alcance del umbral a todos los tipos de saldo.<br>
<br>
R: Conservermos que apartir de \$10 en adelante.

**Casos afectados:** S-01, S-02, S-09

### AC-03 — Crédito fuera de tolerancia

Se espera hasta el día siguiente y Wizerp debe permitir. Falta consecuencia si sigue bloqueado y tratamiento de piezas. No se emite remisión solo por transcurrir tiempo.<br>
<br>
R: En caso de que siga bloqueado o no se haya hecho el pago, el pedido se cancela, por lo que en el sistema seria con un cron o un tag que marque que el pedido de credito no ha reportado ninguna remisión por lo que el pedido procederá a cancelarse.

**Casos afectados:** T-09, M-08, N-15

### AC-04 — Alcance TIENDA MD

Fuente inicial dice solo Bellaroma; falta aplicación frente a otras vendedoras, recolección con existencia directa PDV y alternativa después del corte. No sábado y corte 13:00 están definidos.<br>
<br>
R: Se refiere a que solo un departamento puede hacer uso de esta función, por lo que sería una configuración que debe permitirse gestionar en el sistema los unicos departamentos (o usuarios) que pueden hacer uso de este proceso.

**Casos afectados:** T-02.1

### AC-05 — Modificar con pago/remisión

Se dijo cancelar u observar, pero no criterio completo para elegir ni pasos contables de remisión anulada por cancelación total.<br>
<br>
R: Si se modifica con remision se debe adjuntar la evidencia, o en dado caso generar otro pedido para complementar el pedido afectado por esa causa extraordinaria.

**Casos afectados:** T-04, T-07, S-02, S-08, N-14

### AC-06 — Límite de integración del anexo

Debe estar pagado, pero falta anexo de tienda al día siguiente sin remisión y corte preciso de integración de complementos antes de salida.<br>
<br>
R: Como bien dijimos debe estar pagado, casos como Tienda dia siguiente no deberia porque participar a menos que sea para entregar en la tienda ese conjunto.

**Casos afectados:** T-06, M-03, N-06

### AC-07 — Guías y cargos consumidos

Por cambio de dirección se cancela guía y genera nueva. Falta quién absorbe gastos y qué parte se reconoce ante cancelación/cambio si envío, seguro o guía ya generaron costo.<br>
<br>
R: quien solicita el cambio de dirección muy probablemente siempre será la vendedora, ahi es justo donde entran las evidencias o las causas por las cuales se solicitan, en caso de que sea el cliente solo se complementaria el pago ya sea que necesite cubrir más monto por la distancia o generar un saldo a favor por un monto menor. Puede pasar que incluso el encargado de Guias sea quien se equivoque y tambien ya incurra el costo por error de su parte, o de la vendedora si ella se equivocó.

**Casos afectados:** N-05, N-08, N-10, N-14, S-06

### AC-08 — Jaguar impagado y caja

Cajera aporta dinero como proceso PDV aparte. Falta plazo/escalamiento si el cliente no paga y documentación de liquidación interna, sin trasladar ese proceso a pedidos por defecto.<br>
<br>
R: De momento no contemplamos si el cliente paga o no, serpa un caso por comprobar, nosotros solo debemos asegurarnos que se cumpla el flujo del pedido.

**Casos afectados:** M-05, P-01

### AC-09 — Tarifa y seguro nacional

Respuesta de fotos del repartidor describe terminales. Falta fuente/formato de tarifas nacionales y fórmula, porcentaje o mínimo de seguro; no se extrapolan reglas.<br>
<br>
R: Gelia ya tienen las fórmulas de cálculo de estas tarifas para paqueterias con Logistica.

**Casos afectados:** N-01, N-02, N-03

### AC-10 — Comprobantes y recepción final

Hay fotos de entrega del repartidor y tienda; faltan evidencias uniformes de custodia/traslados, ruta del segundo traslado autorizado y cierre de entrega al domicilio por paquetería.<br>
<br>
R: Sip, las evidencias de custodia deben basarse en las que actualmente gestionan en PDV, en gelia se tienen las evidencias que se toman, seria integrarlas en el proceso que hace cedis o el auxiliar de tienda y esas serian las que Reciban PDV para no tener que capturar manualmente ellos los resguardos.

**Casos afectados:** T-03, R-01, R-06, M-01, N-01

### AC-11 — Liberación de esperas de envío

Vendedora instruye; falta condición exacta por fecha, acumulación y decisión pendiente. No se presume liberación automática ni plazo máximo universal de resguardo.<br>
<br>
R: Por los casos ya descritos la vendedora es la que comunica la salida, ya que es la que esta en comunicacion con el cliente que a fin de cuentas es el que da la orden.

**Casos afectados:** M-09, M-10, R-02 a R-05

### AC-12 — Remisión que no cabe con seguro

Con seguro debe viajar completa por caja. Falta cómo documentar una remisión cuyo volumen no cabe completa, especialmente una sola compra grande.<br>
<br>
R: Cuando es una remision muy grande que no cabe en un solo envio o si son 2 y una de ellas es demasiado para el empaque, entonces se genera su propio envío y se debe cobrar ese seguro por separado. Un ejemplo de caso seria que un cliente puede hacer su compra y tener 2 remisiones, si las 2 remisiones entran en una sola caja por la cantidad de piezas se paga un solo envio con el seguro de ese paquete, si una de las remisiones por la cantidad de piezas no entran en un solo paquete, cada remisión debe ser registrada en los paquetes que CEDIS haya calculado y respondido que serían en las que fueran, pero una remisión no puede ir como partida en dos cajas, siempre una remisión se debe mandar completa, nunca en partes.

**Casos afectados:** N-03, N-11, N-13

### AC-13 — Saldos y retornos posteriores

Se indicó uso de saldo en 20 días y sin devolución después. Falta fecha inicial para todos sus orígenes, registro de diferencias menores no usadas, remanentes uniformes y tratamiento del pago retornado después de entrega.<br>
R: El saldo a favor se genera cuando se registra y verifica el pago, entonces el cliente lo puede disponer PARA SU SIGUIENTE PEDIDO. No puede aplicarlo a su mismo pedido, y los saldos a favor se diferencian, un saldo a favor de envio solo puede usarse para envios, un saldo a favor de productos puede usarse para pagar productos.

**Casos afectados:** S-01 a S-09; controles de pago

### AC-14 — Costo posterior con crédito

Se permite crédito de todos los conceptos, pero no se explicó el documento de un envío cuyo costo se conoce después de emitir remisión de mercancía.<br>
<br>
R: Se hace el cargo posteriormente a su credito, mientras el sistema registre al final el pago total.

**Casos afectados:** M-08, P-01

### AC-15 — Formato de domicilio y nota

Se listaron campos nacionales; falta confirmar ciudad/municipio, estado y contacto como obligatorios, y el significado completo de “lleva nota”.<br>
<br>
R: Para envios nacionales se piden todos los datos para direcciones necesarios, Gelia ya tiene estos campos, el significado de “Lleva nota” es por una practica de DropShopping, el cliente al registrar su direccion o las vendedoras, registran si el cliente decide o no que en su pedido se le comparta la nota impresa.

**Casos afectados:** N-07, N-08, N-09

## 7. Escenarios de validación de la operación

Estos ejemplos permiten revisar el documento con el equipo antes de diseñar la implementación. Son resultados esperados del análisis, no pruebas de software ejecutadas ni afirmaciones sobre Gelia.

### CV-01

**Situación:** Un pedido tiene detalles y el cliente no responde.

**Resultado esperado:** Permanece detenido; vendedora comunica entre cliente y CEDIS/PDV; no se sustituye ni da por aceptado.

### CV-02

**Situación:** Un cliente paga TIENDA MD después de las 13:00 o lo solicita sábado.

**Resultado esperado:** No se procesa bajo TIENDA MD; se informa y acuerda continuación sin reprogramación automática inventada.

### CV-03

**Situación:** El cliente quiere cambiar sucursal cuando las bolsas ya llegaron.

**Resultado esperado:** No se hace otro traslado sin autorización de gerente; cliente no recoge en CEDIS.

### CV-04

**Situación:** El pedido pagado llega al día 20 desde su remisión sin recolección.

**Resultado esperado:** Se identifica pendiente de gestión; no se libera la mercancía ni se cancela por una regla no acordada.

### CV-05

**Situación:** El cliente cambia tienda a municipal con remisión emitida.

**Resultado esperado:** Se cancela solicitud anterior, se vincula nueva, se conserva pago de mercancía y se resuelve transporte sin vender de nuevo las mismas piezas.

### CV-06

**Situación:** Se anexa mercancía después de emitir remisión y antes de salida.

**Resultado esperado:** Nuevo pedido/proceso relacionado, complemento pagado, integración y revisión de empaque/cargos; no agregar informalmente a la remisión.

### CV-07

**Situación:** El repartidor municipal ya recogió para terminal y el cliente quiere recoger en tienda.

**Resultado esperado:** No procede el cambio municipal según el límite documentado; el traslado interno previo se distingue de ese evento.

### CV-08

**Situación:** Jaguar llega a terminal y el cliente aún no pagó envío.

**Resultado esperado:** Mercancía puede estar pagada y enviada; costo/cobro de envío siguen separados. Se cobra, reporta PAGO ENVÍO y comparte ficha después según la secuencia.

### CV-09

**Situación:** El cliente tiene crédito bloqueado en Wizerp.

**Resultado esperado:** Auxiliar informa bloqueo; espera regularización dentro de tolerancia y no evita el control mezclando métodos o emitiendo por tiempo transcurrido.

### CV-10

**Situación:** Dos transferencias intencionales cubren un pedido municipal.

**Resultado esperado:** Se verifican ambos ingresos y la suma; no son dos compras ni mezcla de crédito y transferencia.

### CV-11

**Situación:** El cliente presenta una nota de crédito y paga la diferencia.

**Resultado esperado:** Se comprueba disponible, aplica a mercancía y registra consumo/remanente; no se prohíbe el caso expresamente documentado por confundirlo con mezcla de métodos.

### CV-12

**Situación:** Una dirección cambia cuando ya existe guía y aún no salió.

**Resultado esperado:** Se cancela la guía anterior, se genera nueva y se sustituye en CEDIS; costo de sustitución no se presume cero.

### CV-13

**Situación:** Se pide otra paquetería después de salir.

**Resultado esperado:** No se admite el cambio de paquetería; la canalización de otras solicitudes con Rafael no elimina esta regla específica.

### CV-14

**Situación:** Consolidación asegurada necesita varias cajas.

**Resultado esperado:** CEDIS informa contenido/evidencias por caja, cada caja tiene seguro independiente y cada remisión va completa.

### CV-15

**Situación:** Consolidación sin seguro necesita varias cajas.

**Resultado esperado:** Se permite reparto físico y cobro conjunto en una remisión con información de pesos/medidas por paquete.

### CV-16

**Situación:** Se intenta aplicar saldo de envío a perfumes.

**Resultado esperado:** No se permite; se mantiene su restricción sin mezclarlo con saldo de mercancía.

### CV-17

**Situación:** Cliente declara saldo de PDV.

**Resultado esperado:** Se comprueba con PDV/Drive antes de aplicar y se deja registro del uso en cualquier sucursal para evitar duplicidad.

### CV-18

**Situación:** Complemento sale de PDV hacia CEDIS.

**Resultado esperado:** Principal continúa esperando hasta recepción e integración, no hasta el mero aviso de salida del origen.

### CV-19

**Situación:** Saldo proviene de depósito por error sin pedido.

**Resultado esperado:** Se documenta ingreso y autorización sin crear pedido/remisión ficticios ni inventar inicio de vigencia.

### CV-20

**Situación:** Se tiene fotografía de un repartidor entregando a terminal.

**Resultado esperado:** Se acredita ese evento; no se declara recepción final del cliente con una evidencia de alcance diferente.

## 8. Glosario y fuentes

| **Término**         | **Uso en este documento**                                                                                                                                |
|---------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| CEDIS               | Centro de distribución, lugar obligatorio de salida de envíos nacionales.                                                                                |
| PDV                 | Punto de venta / tienda; en municipal funciona además como punto de distribución a terminales en la rutina aclarada.                                     |
| Anexo               | Piezas adicionales solicitadas para un pedido, sujetas a existencia, pago y condición documental.                                                        |
| Complemento         | Pedido o grupo de piezas relacionado que debe integrarse al principal, a veces mediante traslado. No se considera integrado hasta recibirlo físicamente. |
| Custodia de tienda  | Conservación de pedidos pagados hasta recolección o movimiento autorizado; no es el mismo motivo de espera que un resguardo de envío.                    |
| Resguardo de envío  | Espera por fecha, acumulación, paquetería o complemento. La causa determina su continuación.                                                             |
| PAGO ENVÍO          | Reporte de pago separado del costo de transporte con referencia a remisión de mercancía.                                                                 |
| NC                  | Nota de crédito respaldada por devolución y aplicable a mercancía.                                                                                       |
| PAGADO / POR COBRAR | Condición de transporte en carátula; debe distinguirse de si el cliente ya cubrió el importe.                                                            |
| N/A                 | No aplica. No equivale a cero ni por sí solo a cantidad pendiente.                                                                                       |
| Salida              | Evento específico que debe nombrarse: traslado a PDV, recogida hacia terminal o entrega nacional al transporte; no se utilizan como sinónimos.           |

### Fuentes utilizadas

F-01. PROCESO PARA PEDIDOS - PROCESOS TIENDA.docx: procesos 1 a 9 y subproceso 2.1.

F-02. PROCESO PARA PEDIDOS - PROCESOS ENVIO MUNICIPAL.docx: procesos 1 a 12.

F-03. PROCESO PARA PEDIDOS - PROCESOS ENVIO NACIONALES.docx: procesos 1 a 15.

F-04. PROCESO PARA PEDIDOS - SITUACIONES PROCESOS.docx: catálogo de tienda/municipal/nacional, 6 resguardos, 9 saldos y 1 pago de envío.

F-05. Analisis de Requerimientos Gestión de pedidos - V01 (Revisado con comentarios de respuesta).docx: anotaciones con I y comentarios de Word, leídos como aclaraciones de operación.

F-06. Cuestionario de aclaraciones Gestion de Pedidos - RESPONDIDO.docx: 28 respuestas y correcciones; las preguntas y ejemplos no se toman como reglas si no fueron confirmados.

F-07. Respuestas posteriores del usuario en este chat: inicio desde remisión, saldo mayor a \$10, apartados por día, sustitución de guía, dinero aportado por cajera PDV y liberación de tienda al acudir el cliente.

F-08. V01 del análisis: antecedente estructural, corregido por las fuentes posteriores. No se utilizó código, configuración o funcionamiento de Gelia para completar los casos.

### Cobertura de la versión

Tienda: 10 fichas, incluido el subproceso de mismo día. Municipal: 12. Nacional: 15. Resguardos: 6. Saldos: 9. Pago envío: 1. Total: 53 fichas operativas. Se conservan los casos del catálogo original y se amplían con las aclaraciones, sin sustituir puntos no definidos por decisiones de implementación.

---

## Encabezado y pie del documento original

**Encabezado:** `GESTIÓN DE PEDIDOS  |  FLUJOS OPERATIVOS  |  V02`

**Pie de página:** `V02  ·  Página {PAGE}`

El campo `{PAGE}` representa el número automático de página de Word. El archivo original no almacena un número fijo para ese campo; Markdown no tiene paginación fija.

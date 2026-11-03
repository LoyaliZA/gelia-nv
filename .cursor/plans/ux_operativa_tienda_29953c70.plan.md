---
name: UX operativa Tienda — fase 2.1 integral
overview: Completar conjuntamente 2.1.1 a 2.1.5 para recorrer Tienda de registro a entrega, incluidos cambios y complementos documentados. Reutilizar los avances; validar después por casos con el usuario y actualizar la trazabilidad del análisis V02.1.
todos:
  - id: contrato-compartido
    content: Unificar expediente, instrucción vigente, decisiones, cobertura y acciones entre todas las vistas; resolver las dependencias compartidas necesarias.
    status: completed
  - id: ejecutar-2-1-1
    content: Completar Registrar pedidos con continuidad por secciones, decisiones del cliente, cobertura, seguimiento y solicitudes de cambio o complemento.
    status: completed
  - id: ejecutar-2-1-2
    content: Completar Control Pedidos con revisión por excepciones, preparación, traslado, cambios y recepción de complementos.
    status: completed
  - id: ejecutar-2-1-3
    content: Completar Auditar pedidos con transferencia, crédito, remisión, diferencias y reglas de Tienda MD.
    status: completed
  - id: ejecutar-2-1-4
    content: Completar Preparación Tienda y su integración PDV para apartado, recepción, custodia, entrega y liberación física documentada.
    status: completed
  - id: ejecutar-2-1-5
    content: Integrar autorizaciones y cruces T-04/T-05/T-06; verificar recorridos, concurrencia y regresiones relevantes en una pasada conjunta.
    status: completed
  - id: corregir-t01-y-ajustes
    content: Resolver las incidencias de preparación, respuesta, retiro documental y cierre según correccion_t01_preparacion_respuesta_y_ajustes.plan.md; comprobar cambios presentes antes de reimplementarlos.
    status: completed
  - id: repaso-usuario
    content: Matriz actualizada el 3 nov 2026 con la continuación física. La validación del usuario sigue pendiente; no marcarla como aprobada.
    status: pending
isProject: false
---

# Fase 2.1 — Recorrido integral de Tienda

## 1. Decisión vigente — 3 de noviembre de 2026

**Corrección ejecutada el 3 de noviembre de 2026:** [Preparación T-01, respuesta y ajustes](correccion_t01_preparacion_respuesta_y_ajustes.plan.md). Precisa el flujo y los adjuntos de retiros; sus criterios prevalecen en ese alcance. Los estados completed registran la entrega y las pruebas, no la aceptación operativa. T-01 sigue pendiente de esa aceptación.

El usuario autoriza trabajar **todas las subfases 2.1.1 a 2.1.5 como una sola entrega integrada**. Las subfases organizan responsabilidades; no son puertas que requieran aprobación entre vistas. Completar primero el camino de Tienda y después entregar los casos para el repaso del usuario. Corregir las incidencias encontradas por recorrido, sin volver a planificar cada pantalla.

Este documento sustituye los cortes anteriores de este mismo plan: ya no se limita el trabajo a la captura inicial, no se pide detenerse antes de 2.1.2 y T-04/T-05/T-06 dejan de estar excluidos. No restaurar el bloqueo provisional de «Solicitar todavía no está disponible». La ampliación incluye los servicios, permisos, tareas y conexiones indispensables para que las acciones funcionen.

El encargo actual es actualizar el plan. Su ejecución en Cursor debe realizarse en modo Agent, siguiendo el alcance completo y actualizando avances sin solicitar otra aprobación por cada subfase. No confundir este plan actualizado con una ejecución ya realizada.

## 2. Fuentes y precedencia

- Instrucciones y observaciones más recientes del usuario en esta sesión: una entrega integral, agilidad y validación posterior por casos.
- `AGENTS.md` y `GUIA_DISENO_GELIA.md`: dirección visual y trabajo con el repositorio.
- `gestion-pedidos-docs/Analisis_de_Requerimientos_Gestion_de_pedidos_V02.1_Dudas_resueltas.md`: fichas, respuestas de aclaración AC y escenarios CV. Leer las respuestas `R:`: varias corrigen límites que el cuerpo de las fichas aún presenta como abiertos.
- Decisiones vigentes del chat «Definir flujos operativos del sistema»: captura compacta, continuidad por secciones, documento como referencia, revisión por excepciones y conexión real con Tienda o CEDIS.
- Código y mecanismos actuales: punto de partida para reutilizar, no evidencia suficiente de cumplimiento.

Cuando una respuesta AC resuelva un punto, no copiar como regla vigente el párrafo anterior que lo declaraba abierto. Cuando falte una definición real, aislar ese caso con causa y resolución pendiente; continuar con los demás. Un conflicto con una decisión humana posterior debe quedar identificado y consultarse puntualmente, sin suspender la entrega entera.

### Avances que se conservan

El plan anterior reportó conexión inicial a dos destinos, plazo T-01 y revisión por excepciones. La lectura del 3 de noviembre confirma en código los dos destinos y `PlazoApartadoTienda`; no se reejecutaron aquí las pruebas ni se verificó el recorrido renderizado.

Conservar y completar ese trabajo. Los estados pendientes de arriba corresponden a la ampliación integral, no significan rehacer lo existente. No declarar aprobadas por el usuario las pruebas reportadas por una implementación anterior.

## 3. Alcance y límites

Incluye T-01, T-02, T-02.1, T-03, T-04, T-05, T-06, T-07, T-08, T-09 y R-01. Incluye los controles financieros y logísticos que esas fichas necesitan: pagos y remisiones reconocidos, saldos aplicables, autorización, traslado, custodia y evidencia de entrega.

Los cruces con Municipal y Nacional deben funcionar hasta su incorporación y continuación real en el circuito existente, conservando referencias, pagos, ubicación e instrucción. Corregir las dependencias necesarias de esos circuitos para ejecutar el cruce; no rediseñar íntegramente ambas modalidades en esta fase. Si una dependencia impide continuarlo, el caso queda parcial con bloqueo concreto, nunca completo por mostrar un enlace.

No hay extracción automática del documento ni nueva integración con Wizerp. La auxiliar registra en GELIA el resultado y la remisión que obtuvo en Wizerp. No crear otro sistema de inventario, pagos, saldos o resguardos en paralelo.

No usar `migrate fresh`, `ALLOW_DESTRUCTIVE_DB`, reinicialización de datos ni cambios masivos sobre pedidos reales para las pruebas. No hacer commit, push ni despliegue remoto sin instrucción expresa. Los ajustes de esquema indispensables deben ser incrementales y conservar datos.

## 4. Contrato común para todas las vistas

Cada pedido conserva un expediente compartido: cliente y número, departamento, folio de pedido, remisión o remisiones, modalidad, destino, ubicación física real, instrucción vigente, revisión, decisiones, cobertura, pendientes y documentos. Cada rol ve lo necesario para actuar y quién debe continuar.

- Distinguir modalidad Tienda, área que revisa, forma de cobertura y condición Mismo día. Una selección no acredita disponibilidad, pago, remisión, traslado ni entrega.
- Mantener una instrucción ejecutable vigente. Una sustituida permanece en historial y no puede ejecutarse. El cambio de instrucción no devuelve físicamente mercancía al anaquel.
- Proteger las mutaciones en servidor, dentro de transacciones y con coordinación consistente de bloqueos. Doble clic y reintento no duplican tareas, pagos, saldos, traslados ni entregas.
- Una decisión del cliente conserva causa, piezas/cantidades afectadas, evidencia, responsable y resultado. Resolver una excepción no exige repetir la revisión de piezas no afectadas.
- Una salida no es una recepción; una recepción no es una entrega al cliente. Registrar cada evento por su responsable y ubicación.
- Reutilizar documentos y datos entre vistas. No pedir otra vez cliente, archivo, remisión o evidencia que ya está disponible y sigue vigente.
- Mostrar la siguiente acción y el motivo de un bloqueo en lenguaje operativo. Evitar códigos internos, explicaciones técnicas y promesas de entrega sin confirmar.

## 5. Trabajo por subfase, dentro de la misma entrega

### Aclaración vigente del usuario — primera corrección de separación y anexos, 3 de noviembre de 2026

Esta aclaración precisa la implementación de las subfases siguientes y prevalece sobre interpretaciones que sustituyan controles operativos existentes por formularios simplificados.

- **Una sola superficie de preparación.** Integrar la respuesta de disponibilidad en el espacio existente de `PrepararApartadoRecoge`, con documento de referencia, escáner/buscador, estado físico, cantidades, atributos Única pieza/Mejor ejemplar/Sin existencias y evidencias. No apilar encima un segundo formulario que tape o desplace esos controles. Disponibilidad y apartado son eventos distintos, pero no requieren dos capturas incompatibles.
- **Revisión por excepciones.** Permitir confirmar explícitamente el conjunto sin incidencias y detallar únicamente lo afectado. Conservar la captura mediante escáner/buscador cuando el auxiliar la necesita y los datos físicos existentes, sin exigir que la vendedora transcriba todo el documento ni que el auxiliar duplique la captura. Los atributos Única pieza y Mejor ejemplar se conservan como datos independientes; no son por sí mismos daño o faltante ni impiden declarar un conjunto sin excepciones.
- **Evidencia integrada.** Conservar galería, vista previa, carga/pegado y captura desde celular ya disponibles. Diferenciar documento solicitado, evidencia general de mercancía y evidencia por pieza. Una evidencia válida persistida debe verse en preparación y en la respuesta de la vendedora sin volver a subirla para la misma revisión. Una captura/PDF del pedido no sustituye una foto obligatoria del conjunto físico. No mostrar PDF como miniatura de foto ni asociar evidencia de otra instrucción o versión.
- **Anexos: dos entradas.** La principal es adjuntar el PDF o captura del pedido de anexo sin SKU, descripción por producto ni cantidad transcrita obligatorios. La alternativa es seleccionar productos del catálogo mediante SKU o nombre y sus cantidades, reutilizando el buscador existente. Ambas crean una solicitud de anexo pendiente de revisión del ejecutor; la vendedora no certifica su disponibilidad ni integración. El contrato HTTP y los servicios deben admitir la entrada documental sin SKU ficticio; el ejecutor resuelve los productos físicos necesarios antes de reservar, preparar o integrar. No implementar extracción automática ni anticipar imágenes de catálogo aún no disponibles.
- **Retiro.** Conservar la selección de piezas y cantidades que el usuario consideró adecuada; pulir su presentación sin sustituir ese recorrido. Mantener sus restricciones según pago/remisión y su comunicación al ejecutor.
- **Comprobación de esta corrección.** Recorrer T-01 hasta separación y comprobar que las evidencias y atributos llegan a Registrar pedidos; anexo documental sin SKU y anexo por catálogo; retiro parcial. Revisar el modal real, incluido móvil, y la respuesta receptora. No declarar T-01 validado ni reabrir la implementación completa de 2.1 por este ajuste.

#### Corrección aplicada — 3 de noviembre de 2026

La revisión de disponibilidad quedó dentro de `PrepararApartadoRecoge`. Ya no se apila `PanelRespuestaDisponibilidad` encima de la preparación. El apartado físico sigue siendo la acción «Confirmar apartado». Única pieza y Mejor ejemplar no se tratan como incidencia. La foto del conjunto es obligatoria; el PDF del pedido no la sustituye. Una foto ya guardada en la tarea se reconoce sin volver a cargarla y aparece en la respuesta de Registrar pedidos. El anexo documental no exige SKU; el de catálogo usa el buscador y conserva cantidades. El retiro de piezas conserva su recorrido.

Comprobado en automático: `SolicitudRegistrarTiendaTest` (17 pruebas) y los dos recorridos de anexo ya existentes; Vitest del modal y de la preparación (18 pruebas); build del entorno. En pantalla: preparación del pedido 5645 en escritorio y a 390 px, con escáner, buscador, foto, documento ampliable y la confirmación del conjunto; en Registrar pedidos, PBMA-2026-00082 muestra la respuesta, el anexo documental, el buscador de productos y el retiro. No se envió revisión, anexo ni retiro sobre pedidos reales. La validación del usuario sigue pendiente.

### 2.1.1 — Registrar pedidos y seguimiento de la vendedora

Conservar la captura actual: cliente, departamento principal precargado, folio, modalidad sin selección automática y archivo. Corregir el departamento solo cuando exista necesidad y facultad. Guardar borrador permite datos incompletos y no crea tarea; solicitar exige cliente, departamento, folio, archivo listo y receptor definido. Conservar formatos PDF/JPG/JPEG/PNG/WEBP y máximo de 10 MB.

Para Tienda mantener los dos destinos actuales: «Revisar y apartar en tienda» y «Consultar disponibilidad en CEDIS». Pedir sucursal/almacén únicamente cuando haga falta dirigir el trabajo, y distinguir el almacén que revisa de la sucursal final de recolección. No derivar cobertura ni MD de esos destinos. T-01 no solicita surtido a CEDIS; T-02/T-02.1/T-09 prefieren CEDIS y admiten el origen PDV documentado cuando corresponda. No convertir intenciones antiguas de MD silenciosamente en apartado impago.

Completar las secciones posteriores del mismo expediente:

1. Resultado de disponibilidad con evidencia y pendientes.
2. Decisión expresa del cliente ante faltantes o detalles: aceptar lo disponible, aceptar una condición, retirar, sustituir, solicitar alternativa o desistir según el caso. Una pieza inexistente no se vuelve disponible por aceptarla.
3. Pedido definitivo y archivo actualizado para T-07; conservar versiones y reservas coherentes, sin edición directa informal de una remisión.
4. Cobertura: pago al recoger, transferencia o crédito. Mostrar Mismo día cuando se necesita solicitar esa condición, antes del compromiso logístico; no obligar a esperar a que haya pago para expresar la intención MD.
5. Reporte de comprobantes, envío a auditoría y resultado con causa cuando se devuelve.
6. Seguimiento de preparación, traslado, recepción, custodia y entrega; acciones de cambio de sucursal, modalidad o complemento cuando correspondan.

La inspección se responde en la vista ejecutora. Registrar pedidos recibe el resultado y obtiene la decisión del cliente; no duplica la inspección. Municipal y Nacional conservan su alta existente y los datos ya capturados.

### 2.1.2 — Control Pedidos / CEDIS

Mostrar claramente que el pedido es para recoger en Tienda, la sucursal destino, origen real, revisión pendiente, cobertura y condición MD cuando aplique. Eliminar dependencias de pesaje/cotización de envío que no correspondan a Tienda; conservar el funcionamiento que otras modalidades sí necesitan.

Tomar revisión, consultar el archivo y responder por excepciones. Sin excepciones exige confirmación explícita y evidencia del pedido completo. Con excepciones, identificar pieza/cantidad/condición y esperar resolución del cliente. Ni un autoguardado ni la ausencia de marcas cierran la revisión.

Preparar lo aceptado y documentado según la ficha; no confundir la consulta previa al pago con autorización de despacho. Coordinar bolsas, evidencias, salida a sucursal y recepción separada. Respetar cobertura y remisión antes de los pasos que las requieren.

Permitir ajuste antes del traslado, aplicar la instrucción vigente y procesar el segundo traslado solo después de resolución de gerencia. Recibir físicamente complementos desde PDV, relacionarlos con principal y remisiones y dejar espera explícita mientras no lleguen. La salida del origen no confirma integración en CEDIS.

### 2.1.3 — Auditar pedidos / auxiliar de ventas

Adaptar bandeja y revisión al caso Tienda. Presentar documento definitivo, importe, cobertura, comprobantes, diferencias, remisión y decisiones pendientes. No exigir pesos, guías o cargos de transporte a una recolección que no los requiere.

- Transferencia: comprobar ingreso, correspondencia e importe; admitir varias transferencias del mismo método con aplicación trazable, sin contar dos veces un comprobante ni un ingreso.
- Crédito: registrar resultado de Wizerp, aprobado o bloqueado con causa. No pedir comprobante de transferencia. No aprobar por el mero paso del tiempo ni cambiar automáticamente el método.
- Pago presencial T-01: usar la confirmación del cobro existente por el personal autorizado; no forzar una transferencia ni un paso por auditoría central que la ficha no exige.
- Registrar la remisión pertinente y devolver correcciones a la vendedora con causa. La revisión posterior de Administración no introduce otra espera previa a preparación.
- Aplicar excedentes desde $10 conforme a AC-02. El saldo nuevo nace al registrar y verificar el pago y se dispone para el siguiente pedido, no para cubrir el mismo que lo originó (AC-13). Distinguir saldo de productos y de envío y reutilizar autorización/movimientos existentes.
- Distinguir comprobante repetido de segundo ingreso real; aplicar S-07 sin duplicar venta ni entrega. No inventar pérdida contable de diferencias menores a $10.

**Tienda MD:** transferencia y facultad configurable por departamento o usuario; lunes a viernes, sin sábado. La intención puede estar prevista antes del pago. El corte de las 13:00 usa el primer reporte de pago aceptado por el servidor (`pedido_bma_pagos.created_at` en el mecanismo actual), no fecha declarada, apertura de pantalla, revisión ni emisión de remisión. Conservar el instante original y su trazabilidad al sustituir soporte. Verificar después de las 13:00 no invalida un reporte oportuno. Reporte oportuno no equivale por sí solo a cobertura verificada ni garantiza entrega: además requiere disponibilidad, documentación, preparación y traslado. Reporte tardío queda pendiente de acuerdo, sin reprogramación ni cambio automático de modalidad.

### 2.1.4 — Preparación Tienda e integración con PDV

Separar recolección Tienda de las tareas municipales que comparten vista. Usar la misma revisión por excepciones que CEDIS, sin volver a capturar todas las piezas correctas. Mantener los datos físicos necesarios para reserva, traslado y entrega mediante los mecanismos existentes.

T-01: revisión, decisión sobre detalles, apartado físico de lo aceptado, vigencia, pago confirmado y entrega con fotografía. Conservar 17:00 de lunes a viernes y 13:00 el sábado en la zona configurada. Una revisión sin `fecha_limite` no es un apartado vencido; asignar/persistir el plazo cuando corresponda sin renovar reservas antiguas. Diferenciar vencimiento, falta de configuración y cierre operativo. No confundir T-01 con Tienda MD.

T-02/T-02.1/T-09: verificar procedencia, remisión y cobertura correspondiente; recibir y cotejar bolsas/evidencias, ubicar en custodia y entregar al acudir el cliente con fotografía y responsable. La remisión a crédito aprobada es cobertura válida sin transferencia.

Integrar las evidencias existentes de CEDIS/auxiliar con resguardos PDV conforme a AC-10. No hacer que PDV registre manualmente de nuevo un resguardo que el pedido ya puede generar. Sincronizar estados de pedido y custodia en ambos sentidos y evitar doble entrega.

El apartado impago y la mercancía pagada tienen reglas distintas de vencimiento. Para pagados implementar el seguimiento de 20 días naturales desde la remisión y el proceso físico previsto por AC-01, con trazabilidad y sin borrar la obligación o inventar el destino del dinero. La liberación de custodia por entrega, complemento, cambio de destino o vencimiento conserva causas diferentes.

### 2.1.5 — Autorizaciones, cambios, complementos e integración

- **T-03:** cambio de sucursal antes del traslado actualiza la instrucción de quienes preparan. Si ya se trasladó, crear solicitud pendiente y resolución real de gerencia. Usar facultad de gerencia existente o un permiso específico integrado al esquema actual; el permiso genérico de salida no equivale por sí solo a gerencia. No rechazar solo por ser quien vendió. Autorizar no mueve mercancía ni confirma recepción; registrar origen/destino y recorrido confirmado, sin inventar ruta directa o vía CEDIS. El cliente no recoge en CEDIS.
- **T-04:** sustituir la solicitud de modalidad con causa, evidencia y referencias mutuas. Conservar pedido, remisión y pagos reconocidos; no cancelar automáticamente la venta ni cobrar otra vez mercancía. Comprobar ubicación y límites de salida; conectar el circuito receptor y los cargos/saldos aplicables.
- **T-05:** identificar remisiones pagadas en custodia, retirar de espera de recolección, trasladar PDV→CEDIS, confirmar recepción e integración con el principal. Evitar doble entrega y doble cobro. Continuar en envío existente con referencias completas; cobrar solo mercancía nueva o cargos pendientes.
- **T-06:** consultar disponibilidad del anexo. Sin remisión, ajustar el pedido solo en la condición documentada; con remisión, nuevo pedido relacionado y su cobertura/remisión. No enviar complementos impagos ni asumir integración por añadir una línea. Reconocer recepción e integración física antes de entregar el conjunto.
- **T-07/T-08:** las decisiones de la vendedora deben modificar la instrucción/reserva correcta y regresar a quien ejecuta; no dejar una lista de excepciones sin acción para resolverla.

Resolver en esta entrega las dependencias de concurrencia de la fase 1 para las acciones implementadas. Verificar dos operaciones realmente simultáneas en un entorno aislado que soporte los bloqueos utilizados: doble solicitud y sustitución frente a ejecución. Un reintento secuencial no acredita esa prueba. Conservar la protección al incorporar cambios y complementos.

## 6. Aclaraciones resueltas y límites reales

El plan anterior mantenía algunas reglas resueltas como si siguieran abiertas. Aplicar esta distinción:

| Tema | Regla de referencia para implementar | Lo que no debe inventarse |
| --- | --- | --- |
| 20 días, AC-01 | La respuesta autoriza reintegrar físicamente la mercancía para venta tras 20 días naturales desde remisión. Corregir la contradicción con el cuerpo antiguo de T-02/R-01. Registrar movimiento físico y mantener trazabilidad. | Destino del dinero pagado, devolución o creación automática de saldo. Reutilizar inventario existente; no marcar disponible solo por cambiar una etiqueta. |
| Crédito, AC-03 | Si sigue bloqueado o sin regularizar al vencer la tolerancia hasta el día siguiente, procede cancelación y gestión física correspondiente, sin aprobación ficticia. | Hora/zona exacta del vencimiento si no existe definición vigente. Dejar el corte parametrizado/inactivo hasta resolver ese dato, con pendiente visible; no congelar el flujo aprobado de crédito. |
| Saldo, AC-02/AC-13 | Desde $10; pago verificado; siguiente pedido; productos y envío con usos separados. | Pérdida de diferencias menores o uso del saldo nuevo en el mismo pedido. |
| Facultad MD, AC-04 | Configuración por departamento o usuario, no nombre de persona/departamento fijo en código. | Recolección directa PDV como TIENDA MD o alternativa automática tras el corte. |
| Modificación documentada, AC-05 | Adjuntar evidencia; complemento relacionado cuando corresponda. | Edición informal de remisión emitida o cancelación contable automática. |
| Custodia, AC-10 | Reutilizar e integrar evidencias PDV existentes desde CEDIS/auxiliar. | Nuevo circuito manual duplicado. |

Siguen necesitando definición puntual: duración VIP y su autorización cuando no haya regla vigente; tratamiento del dinero tras liberación de pagados; corte exacto del crédito; continuación tras las 13:00 y piezas ya en PDV para MD; condición no cerrada del anexo al día siguiente e integración excepcional. Resolver lo documentado y mostrar el pendiente del caso afectado, sin darlo por completado ni impedir operar los casos ordinarios.

No reinterpretar todos los puntos AC como pendientes ni resolverlos mediante valores arbitrarios. Si código o documentos revelan una decisión humana posterior distinta, registrar la discrepancia concreta para validarla con el usuario.

## 7. Lineamientos comunes de interfaz

Aplicar `GUIA_DISENO_GELIA.md` a las cinco subfases. Referencias: Registrar pedido actual para captura/seguimiento y resguardos PDV para recepción/custodia. Inspeccionar sus estilos efectivos antes de recomponer cada tipo de superficie.

- Mantener identidad GELIA de cabeceras, iconos, jerarquía, densidad y botones reconocibles con hover/foco/carga. Respetar tema, fuente y acento configurables; no imponer la apariencia de una captura ni una fuente fija nueva.
- Identificar cliente, número, folios, modalidad y sucursal sin repetir la ficha completa por todas partes. Explicar qué atender, qué falta y quién continúa.
- Bandejas y filtros deben distinguir pendientes reales por rol: revisión, decisión del cliente, cobertura, preparación, traslado, recepción, custodia y entrega. Reutilizar estados reales antes de añadir nuevos.
- Mostrar acciones por etapa y permiso; un botón deshabilitado explica la causa. Una acción disponible debe persistir y reflejar su resultado en las vistas relacionadas.
- Documentos legibles, ampliación, sustitución permitida y autoguardado con estados reales. Un guardado tardío no sobrescribe decisiones recientes.
- Escritorio y móvil, claro/oscuro, foco visible y acciones accesibles con desplazamiento. Conservar lo que ya funciona de Registrar pedidos; no iniciar otra exploración estética ni instalar dependencias por defecto.

## 8. Ejecución y comprobación conjunta

1. Revisar estado local y reutilizar avances. Identificar las dependencias concretas y fijar el contrato común sin abrir otra ronda de planificación.
2. Implementar servicios y continuidad entre las cinco vistas. Se puede trabajar en un orden técnico por dependencias; no detenerse al terminar una pantalla para solicitar autorización de la siguiente.
3. Integrar cambios/complementos, permisos, tareas programadas pertinentes y sincronización con resguardos.
4. Ejecutar una pasada funcional conjunta con pedidos ficticios y las pruebas pertinentes. Corregir los fallos que cortan el recorrido antes de entregar. No repetir suites ya satisfactorias sin nuevos cambios o una razón concreta.
5. Comprobar el resultado renderizado de las superficies modificadas en escritorio/móvil y claro/oscuro. Ejecutar el build del entorno y comprobar que la aplicación sirve los assets vigentes.
6. Entregar instrucciones breves para que el usuario recorra los casos; actualizar plan y trazabilidad. Los defectos menores de presentación van a pendientes; los de cobertura, datos, permisos, ubicación, entrega o continuidad impiden marcar el caso completo.

Pruebas críticas: T-01 con pago/entrega y vencimiento; T-02 con recepción/custodia; MD oportuno verificado después del corte, tardío y sin facultad; crédito aprobado/bloqueado; faltantes aceptados/retirados/sustituidos; cambio de sucursal antes/después del traslado con autorización/rechazo; cambio de modalidad; complemento desde custodia; anexo con/sin remisión; 20 días con la consecuencia física diferenciada de la financiera; duplicados y concurrencia. Probar crédito con/sin remisión al momento de cancelar y recepción/entrega reintentadas. Conservar controles de productos de otro almacén y corregir ese fallo conocido si afecta al recorrido.

No hace falta crear un pedido por cada variante si pueden comprobarse con datos de prueba y pruebas de servicio. La evidencia de código, prueba automática y recorrido por interfaz se informa por separado.

## 9. Matriz para el repaso del usuario

Estado inicial al ampliar el alcance. «Pendiente» significa pendiente de completar/acreditar en esta entrega; no elimina avances anteriores. Cursor sustituirá la columna de entrega por evidencia real y el usuario validará después la operación.

| Caso | Recorrido que debe poder probarse | Entrega integral | Validación del usuario |
| --- | --- | --- | --- |
| T-01 | Consulta PDV → aceptación → apartado → pago presencial → foto/entrega; variante vencida | Corrección de disponibilidad, retiro y cierre probada el 3 nov 2026: 23 pruebas PHP y 19 de interfaz en verde. El pago presencial hasta ENTREGADA sigue en `test_el_apartado_se_cobra_y_entrega_con_fotografia`. La bandeja mostró la respuesta pendiente del folio 45265236 sin enviarla. | Pendiente |
| T-02 | Consulta → transferencia/remisión → preparación → traslado/recepción → custodia → entrega | Completa. `test_la_transferencia_se_recibe_en_custodia_y_se_entrega`: remisión REM-T02, recepción en custodia, apartado de inventario y entrega con fotografía. La entrega baja existencia y libera el apartado. | Pendiente |
| T-02.1 | Intención MD → facultad/corte → cobertura/remisión → preparación/traslado → entrega o pendiente con causa | El corte sigue en `CorteMismoDiaTiendaTest` y no confirma la modalidad. La preparación, el traslado y la entrega posteriores usan el mismo recorrido de T-02. Siguen abiertas la duración VIP y la alternativa cuando la pieza ya está en PDV o el reporte llega después de las 13:00. | Pendiente |
| T-03 | Destino/cambio previo → solicitud de segundo traslado → resolución → movimiento/recepción | Completa la autorización y el movimiento. `InstruccionEntregaVigenteTest` impide simular la recepción al autorizar. `test_el_segundo_traslado_se_mueve_despues_de_autorizarlo` exige la salida y después confirma la recepción en destino. | Pendiente |
| T-04 | Cambio autorizado → solicitud sustituta → pagos/remisión reconocidos → continuidad receptora | Completa. `test_cambiar_modalidad_exige_archivo_y_conserva_pago_y_remision`: sin archivo la petición responde 422; con archivo la instrucción anterior queda sustituida, la nueva queda pendiente, se conservan pago y remisión REM-MOD y el circuito receptor es envío. | Pendiente |
| T-05 | Remisiones custodiadas → retiro → traslado → recepción/integración CEDIS → continuidad de envío | Completa. `test_el_retiro_de_pdv_se_traslada_y_cedis_lo_integra`: remisión REM-PDV, salida PDV → CEDIS con el traspaso existente y recepción que marca `integrado_cedis` en el envío principal. Antes de la recepción el principal espera. | Pendiente |
| T-06 | Anexo consultado → ajuste permitido o nuevo pedido → cobertura → integración/entrega | Completa cuando hay remisión. `test_el_anexo_se_cubre_se_remisiona_y_se_integra`: principal REM-PRI, anexo REM-ANX, cobertura y pago propios, preparación respondida e integración física. El anexo del día siguiente sin remisión sigue sin regla. | Pendiente |
| T-07 | Retirar/sustituir antes del pago → archivo/instrucción vigentes → reserva coherente | Completa. `test_retirar_libera_el_compromiso_y_la_sustituta_exige_disponibilidad`: sin disponible no hay sustitución; con disponible se compromete la sustituta y la pieza anterior deja de estar apartada. | Pendiente |
| T-08 | Excepción → decisión del cliente → respuesta al ejecutor → continuidad sin aceptación implícita | La decisión escribe `continuacion_ejecutor` en la tarea vigente y no vuelve disponible una pieza inexistente. La misma decisión no se duplica. La carrera MySQL `decidir` sigue sin ejecutarse: el usuario `sail` no puede crear `gelia_solicitud_211_test`. | Pendiente |
| T-09 | Crédito Wizerp aprobado/bloqueado → remisión o regularización/cancelación → preparación/entrega | Completa la remisión, la custodia y la entrega: remisión REM-CRED. La cancelación AC-03 corre al iniciar el día posterior al de tolerancia y no cancela un crédito del día anterior. La hora dentro del día siguiente no está definida y no se inventó. | Pendiente |
| R-01 | Recepción → ubicación → custodia → entrega o movimiento autorizado/vencimiento trazable | Completa el reintegro físico. A los 20 días, remisión REM-20, el apartado vuelve a 0, la existencia no se inventa, el cumplimiento queda en devolución pendiente y no se crea saldo. El destino del dinero sigue sin definir. | Pendiente |
| S-01/S-07 | Excedente/segundo ingreso → verificación → aplicación correcta y saldo separado sin duplicidad | Completa la comprobación. `ReglasSaldosAFavorTest` pasa con mínimo de $10. `test_el_excedente_menor_a_diez_no_genera_saldo_y_no_se_aplica_al_mismo_pedido` rechaza $9.99 y excluye el mismo pedido. La referencia repetida no crea otro ingreso. | Pendiente |

Por caso anotar al entregar: estado (implementado y probado / parcial / bloqueado), folio ficticio o prueba reproducible, resultado esperado/obtenido y pendiente concreto. El estado de aceptación del usuario permanece separado, con fecha y observación cuando lo revise.

## 10. Documentación y cierre

Actualizar este plan al terminar la implementación y después de cada repaso de casos con el usuario. No declarar «Tienda completa» mientras existan recorridos documentados sin cierre o dependencias que impidan continuarlos.

En el análisis V02.1 conservar las fichas y los requisitos de negocio. Añadir trazabilidad de implementación/validación junto a cada ficha o en una sección final enlazada, sin reescribir una regla para que coincida con un defecto. Registrar caso, estado de implementación, prueba, aceptación del usuario, fecha y observaciones. La aprobación del usuario no se presume por una prueba automática.

Al reconciliar aclaraciones del propio documento, hacer explícita la respuesta AC aplicada y conservar la referencia; no borrar el antecedente ni presentar un pendiente financiero como prohibición de una operación física ya definida. Las decisiones nuevas del usuario se incorporan con fecha y casos afectados.

La entrega de Cursor debe indicar: recorridos listos y cómo probarlos; archivos y cambios relevantes; pruebas/concurrencia/build y revisión visual; pendientes concretos con rol y causa. El siguiente paso del usuario será su repaso operativo de Tienda, no otra aprobación para iniciar la siguiente vista de 2.1.

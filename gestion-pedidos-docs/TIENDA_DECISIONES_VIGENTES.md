# Tienda — decisiones vigentes y estado verificado

Actualizado el 4 de noviembre de 2026. Referencia estable del módulo; no es una lista de tareas para volver a ejecutar.

## Autoridad y alcance

Esta referencia conserva las decisiones operativas vigentes y la evidencia del trabajo realizado en las fases 2.1.1–2.1.5. Las solicitudes explícitas posteriores del usuario prevalecen. Consultar el código y las pruebas para comprobar el estado actual antes de modificarlo; una anotación de cierre no demuestra por sí sola un recorrido operativo correcto.

Este es el punto de entrada vigente para Tienda. La [guía de diseño](../GUIA_DISENO_GELIA.md) rige su composición; el [análisis V02.1](Analisis_de_Requerimientos_Gestion_de_pedidos_V02.1_Dudas_resueltas.md) aporta los requisitos que no hayan sido sustituidos por decisiones explícitas posteriores. Los tres planes de esta entrega fueron sustituidos por este documento y retirados de la carpeta de planes por solicitud del usuario. No recuperar instrucciones antiguas para restaurar flujos retirados.

Se retiran las instrucciones anteriores que limitaban esta entrega al prototipo, bloqueaban toda solicitud hasta una comprobación nunca actualizada, fijaban JetBrains Mono como fuente obligatoria o iniciaban una cadena de skills. Conservar personalización de tema, fuente y acento. No restaurar el panel «Decisión del cliente» ni su servicio: el usuario lo eliminó en la revisión del 4 de noviembre. La vendedora gestiona cambios mediante anexo/retiro y cierra la consulta; no debe responder una inspección paralela.

El cierre técnico registrado se limita a las cinco fases descritas; no acredita toda la operación de Tienda. Municipal y nacional mantienen su formulario y segmento propios. No activar políticas financieras, VIP ni automatismos pendientes como consecuencia de este cierre técnico.

## Decisiones vigentes

- El alta no preselecciona modalidad. Guardar borrador no crea tarea ni exige pago. La solicitud usa el documento del pedido, cliente, departamento y almacén receptor ya definidos en la implementación revisada; no volver al prototipo antiguo sin documento.
- T-01 consulta existencia física en PDV, sin pedir surtido a CEDIS. Transferencia puede consultar CEDIS; seleccionar cobertura no cambia retroactivamente el origen físico.
- La respuesta registra solo excepciones; si todo está correcto, confirmación explícita y foto del conjunto. Guardar avance no responde ni aparta.
- Después de enviar disponibilidad, el auxiliar ve el resultado y espera el cierre de la vendedora. No puede modificar ni volver a responder el conjunto sin trabajo pendiente. El cierre permite continuar la separación física; un anexo o retiro abre únicamente la atención de ese ajuste.
- Un ajuste pendiente invalida el cierre vigente. Se conserva la respuesta anterior, los documentos y los pagos. La vendedora recibe el resultado del ajuste y puede cerrar de nuevo cuando no hay pendientes.
- Anexo: PDF/captura como entrada principal, catálogo como alternativa. El auxiliar debe poder identificar y responder; solicitar no demuestra disponibilidad ni integración física. La respuesta identifica responsable, fecha, resultado y evidencia cuando corresponde.
- Retiro: documentos o catálogo, motivo de catálogo y evidencia opcional de la petición del cliente, sin límite funcional de cantidad de capturas. El auxiliar asignado puede atenderlo incluso después del apartado; aplicar libera únicamente lo comprometido que corresponde, rechazar conserva las piezas e informa la razón.
- Importes después del cierre vigente: mercancía, costos aplicables, SAF, total exigible, pagos y pendiente/excedente. Primero persistir y después registrar pago. Un pendiente de cero no bloquea las correcciones autorizadas.
- La vendedora registra el cobro real: transferencia al banco permitido, cobro presencial a Caja PDV conservando el medio real, crédito sin exhibición ni comprobante ficticio. El botón rápido llena el pendiente neto, no registra automáticamente.
- Registrar pago, verificar ingreso, emitir remisión, salir, recibir y entregar son acciones distintas con responsables y permisos propios. No habilitar entrega solo por capturar un comprobante.
- MD acompaña únicamente transferencia: facultad por usuario/departamento, lunes a viernes y corte a las 13:00 sobre el primer reporte aceptado por el servidor. La revisión o remisión posterior al corte no vuelve tardío un reporte oportuno. Un reporte tardío queda pendiente de acuerdo, sin reprogramar automáticamente. Conservar el instante al sustituir comprobantes.
- Apartado impago vence a las 17:00 de lunes a viernes y a las 13:00 el sábado, salvo VIP autorizado conforme a la configuración vigente. No liberar mercancía pagada como si fuera impaga.
- Custodia de transferencia: pedido y ubicación identificables; entrega con evidencia. Veinte días implica seguimiento y gestión, no cancelación ni devolución automática de dinero.
- Segundo traslado: permiso propio de gerencia; ser quien vendió no impide ejercer esa facultad. Autorizar no equivale a salida ni recepción. El cliente no recoge en CEDIS.

## Detalles que deben conservarse al ajustar el módulo

### Preparación, respuesta y evidencia

- Reutilizar el espacio de documento, escáner/buscador, atributos, evidencias y cierre contextual. La foto física del conjunto es obligatoria para responder sin excepciones; el PDF del pedido no la sustituye. Reconocer evidencia válida ya guardada en la misma revisión, sin exigir otra carga.
- Disponibilidad no obliga a transcribir las piezas correctas. La separación posterior sí necesita identificar los productos y cantidades que exige el contrato de inventario. No inventar productos ni interpretar la ausencia de excepciones como confirmación implícita.
- Conservar productos capturados y atributos independientes «Única pieza» y «Mejor ejemplar». Sin existencias y daños son excepciones, no disponibilidad aceptada.
- La respuesta identifica quién/cuándo, estado del conjunto, excepciones, cantidades conocidas, atributos y evidencias de la revisión vigente. Si solo se capturaron excepciones, su suma representa piezas afectadas, no el total del pedido. No mezclar evidencia de instrucciones sustituidas, otras revisiones o peticiones del cliente.

### Anexos y retiros

- El retiro documental no exige transcribir SKU para solicitarlo. El auxiliar identifica productos y cantidades comprometidas antes de aplicarlo; no usar OCR ni SKU ficticios. La alternativa por catálogo permite buscar piezas aunque no tengan existencias libres, pero el servidor comprueba pertenencia y cantidad reservada.
- Distinguir documentos de «Piezas a retirar» de «Evidencia petición cliente», que es opcional. Sin límite funcional de cantidad de capturas; mantener formato y tamaño por archivo y no descartar archivos ni piezas silenciosamente.
- Conservar motivo de catálogo; solo «Otro» exige detalle libre. Cada petición y resultado conserva versión, documentos, motivo, responsable y estado. Solicitar no libera inventario. Un retiro parcial libera solo la cantidad correspondiente, una sola vez.
- No aplicar ajustes informales a pedidos pagados/remisionados: conservar referencias y usar su gestión documentada. Retirar piezas no equivale a retirar la custodia para traslado a CEDIS.

### Importes, saldo a favor y pago

- T-01 puede seguir apartado sin pagar hasta que acuda el cliente. «Pago al recoger» expresa intención y no crea un ingreso ni exige pago para separar. En captura nueva sin cargos, envío y reexpedición parten de cero; no imponer campos de paquetería. Conservar los cargos existentes que sí apliquen, sin inventar tarifas ni sumar dos veces la reexpedición.
- El resumen canónico del servidor determina cobertura y bloqueos: mercancía más cargos aplicables, menos SAF aplicado y pagos activos computables. No llamar verificados a pagos que siguen pendientes de revisión. La interfaz puede mostrar una proyección, diferenciada del resultado guardado.
- SAF respeta cliente y alcance real de almacén/área, incluida la reserva del propio pedido al editar. No basta con un descuento local. Primero confirmar importes y SAF persistidos; después habilitar pago. Leer nuevamente el resumen al guardar o modificar pagos, sin usar «Guardar modalidad» como actualización artificial.
- Reutilizar autoguardado, revisión e idempotencia. Un conflicto conserva captura y bloquea pago sobre montos desactualizados; el reintento con la misma clave recupera la exhibición existente. No sobrescribir la solicitud con una versión anterior ni enviar nulos de campos ajenos.
- Caja PDV se resuelve en servidor desde el catálogo activo y permitido, sin fijar ID ni crear duplicados. Normalizar mayúsculas y espacios exteriores. Si falta o es ambigua, informar la configuración y no guardar como si se hubiera resuelto. Conservar asociación al editar/sustituir y el medio real y su clasificación contable; efectivo no se convierte en transferencia por su destino.
- El botón «Cubrir pendiente» llena el neto restante y no registra automáticamente. Deshabilitarlo si no hay resumen confirmado, hay guardado pendiente o el pendiente es cero. Permitir correcciones autorizadas, otra exhibición y monto real con excedente aun con pendiente cero; nunca crear una exhibición de $0.00.
- Mostrar faltante, cobertura completa o excedente calculados con la tolerancia vigente. No usar un checkbox manual de pago completo. No duplicar créditos por excedente ni aplicarlos al pedido que los genera.

## 2.1.1 — Registrar pedidos

Entrada: `Partials/ModalFormPedido.jsx` → `FormularioRegistrarTienda.jsx`; municipal/nacional y pedidos legados conservan `ModalFormPedidoLegado.jsx`.

Mantener datos del cliente y documento, enviar consulta sin pago, presentar respuesta y ajustes, cerrar consulta y continuar con importes/pago. El estado debe propagarse entre Seguimiento, Continuidad y formulario; una respuesta JSON no puede quedar solo en un hijo mientras otro sigue ofreciendo cobrar. Mantener cola de autoguardado, revisión, huella e idempotencia. Un conflicto conserva captura y bloquea pago sobre totales no confirmados.

Referencias: `SolicitudRegistrarTiendaTest`, `ImportesPagoTiendaTest`, pruebas de componentes de captura/pago. Verificar recarga, reapertura por ajuste, errores, ausencia de panel retirado y restricciones de Caja PDV.

## 2.1.2 — Control Pedidos

Entrada: `Cedis/Index.jsx`, `PanelRespuestaDisponibilidad.jsx` y componentes de detalle/salida. Usar el documento existente y responder por excepciones. La revisión no debe repetirse por abrir el detalle o reenviar la misma solicitud. Una salida a sucursal no marca recepción ni custodia.

Conservar controles físicos y documentales de preparación/traslado existentes. MD no se confirma por el checkbox ni por una prioridad visual; necesita reporte oportuno y validaciones/documentación que correspondan. No introducir un requisito de hora de validación distinto del reporte original.

Referencias: `SolicitudRegistrarTiendaTest`, `TrasladosInternosQ6Test`, `InstruccionEntregaVigenteTest`, `RecorridoTiendaIntegralTest` y pruebas de CEDIS.

## 2.1.3 — Auditar pedidos

Entrada: `Auditar/Partials/ModalRevisarPedido.jsx` y servicios de pago/remisión/crédito. Revisar importe, banco y comprobantes sin exigirlos para guardar o consultar. Crédito exige resultado vigente de Wizerp, no un ingreso ficticio. Mantener devolución con causa y registro independiente de número/documento de remisión.

Conservar conciliación SAF y excedentes desde $10, sin duplicar ni aplicar al propio pedido. El menor a $10 no se automatiza. Mantener clasificación del medio de pago aunque Caja PDV sea su destino.

Referencias: `RemisionAuditoriaTest`, `ImportesPagoTiendaTest`, `RecorridoTiendaIntegralTest` y pruebas pertinentes de `SaldosAFavor`.

## 2.1.4 — Preparación Tienda

Entradas: `Tienda/Partials/ModalPreparacionTienda.jsx` y `Tienda/Show.jsx`. Conservar documento, escáner/búsqueda, atributos y galería. Distinguir revisión inicial, espera de Ventas, atención puntual de ajuste, separación física y resultado; mostrar solo las acciones del momento actual.

El auxiliar asignado puede atender anexos/retiros pendientes sin reabrir indiscriminadamente la captura. Una solicitud atendida no puede aplicarse dos veces. No cambiar o sustituir piezas unilateralmente. Responder disponibilidad no declara integración física del anexo.

T-01 entrega solo después del pago confirmado; transferencia/crédito conservan recepción y custodia identificadas. Mantener municipal separado y no mostrar sus campos logísticos en T-01.

Referencias: `PreparacionTiendaWorkspaceTest`, `SolicitudRegistrarTiendaTest`, `RecogeTiendaFlujoTest`, `RecorridoTiendaIntegralTest` y pruebas de `PrepararApartadoRecoge`, modal y ajustes.

## 2.1.5 — Criterios de verificación

- Ejecutar pruebas de servidor en PHP 8.4 del contenedor, con SQLite en memoria para Feature/Unit. No usar la versión PHP 8.2 del host para PHPUnit 12.
- Concurrencia: `InstruccionRegistrarConcurrenciaMysqlTest` ejecuta procesos independientes sobre `gelia_solicitud_211_test`, nunca sobre la base operativa. Comprobar duplicación de solicitud e intento de ejecutar instrucción sustituida. No usar `migrate:fresh` ni banderas destructivas sobre datos reales.
- Verificar facultad de gerencia y movimientos separados de autorización/salida/recepción; roles, visibilidad, entrega bloqueada sin pago y datos preservados en cambios de modalidad.
- Revisar renderizado de las cuatro vistas pertinentes a escritorio y móvil, claro/oscuro y personalización. Distinguir comprobación aislada con datos ficticios de integración autenticada.
- Compilar los assets al terminar cuando se necesite que la aplicación consuma el resultado. La compilación no sustituye las pruebas ni acredita despliegue remoto.

## Evidencia registrada el 4 de noviembre de 2026

Cierre técnico del 4 de noviembre de 2026: las cinco fases de este alcance quedaron implementadas y comprobadas con las verificaciones siguientes. No equivale a aceptación de usuario, homologación general ni despliegue remoto.

- Servidor PHP 8.4: **159 pruebas, 1298 aserciones**, sin fallos. Incluye `SolicitudRegistrarTiendaTest`, `ImportesPagoTiendaTest`, `RecorridoTiendaIntegralTest`, `RemisionAuditoriaTest`, `TrasladosInternosQ6Test`, `InstruccionEntregaVigenteTest`, `PreparacionTiendaWorkspaceTest`, `AbastecimientoPreparacionQ61Test`, `RecogeTiendaFlujoTest` y `ControlPedidosErroresPorDuenoTest`. En los casos antiguos de reenvío se completó la preparación de prueba: cierre, documento inicial, costo por caja, pago coherente e historial de envío inicial; no se relajaron las validaciones del servidor.
- Concurrencia MySQL: **2 pruebas, 26 aserciones**, dos procesos independientes sobre `gelia_solicitud_211_test`. El soporte fija explícitamente esta conexión aun con configuración cacheada; no consulta ni limpia la base operativa.
- Interfaz: pruebas de importes, preparación, modal, ajustes, entrega, CEDIS y auditoría. La ejecución final de nueve archivos pasó **50 pruebas**, incluida la conservación de captura pendiente y la liberación del bloqueo de actualización cuando se resuelve el ajuste.
- Renderizado: **36 capturas válidas** de nueve vistas en escritorio/móvil y claro/oscuro; claro incluye personalización de fuente y acento. Se abrieron referencias de importes, anexo y entrega, y se corrigieron el recorte del anexo y el desbordamiento de entrega en móvil. Las capturas anteriores de host bloqueado fueron reemplazadas. El verificador rechaza host bloqueado, contenido equivocado, errores de JavaScript, imágenes rotas, desbordamientos y controles de ajustes fuera de su panel antes de publicar. Datos y transporte ficticios; no se presenta como integración autenticada. Ver [instrucciones de comprobación visual](../scripts/qa/tienda-recorrido/README.md) e [informe de capturas](../resources/design-references/tienda-recorrido/verificacion.json).
- Estilo: Stylelint de los dos CSS modificados y revisión de diferencias. El verificador global `check:style` no pudo completarse por ausencia de `docs/style-system/baseline/jsx-hex-allowlist.tsv`; no se regeneró la lista de excepciones.
- Assets: compilación final publicada localmente con manifiesto y archivos verificados. El script conservó assets anteriores para las sesiones abiertas. No se realizó publicación remota.

### Cambios de conexión que faltaban

La respuesta actualiza Registro/Seguimiento/Continuidad desde una lectura común; un ajuste invalida el cierre sin borrar pagos. La captura inicial deja de ser editable al enviar disponibilidad. Anexos y retiros tienen atención propia para el auxiliar asignado, incluso con tarea respondida; no habilitan otra respuesta general. El anexo disponible requiere cierre y confirmación física independiente, con compromiso de inventario y protección contra doble aplicación. La entrega se bloquea mientras exista un ajuste pendiente o un anexo sin separar.

La sección de pago/entrega vuelve a estar accesible después de confirmar el apartado T-01. T-01 no ofrece abastecimiento de CEDIS. Caja PDV se resuelve contra el catálogo activo sin distinguir mayúsculas o espacios: el catálogo existente usa `CAJA PDV`, no fue necesario crear otro banco. El reporte original oportuno de transferencia MD se conserva al validar/remisionar después del corte; su intención no autoriza por sí sola la salida.

### Límites y regresión general

La ejecución exploratoria amplia de ControlPedidos (596 pruebas) detectó 21 errores, 12 fallos, dos omitidas y una riesgosa antes de las correcciones enfocadas. Dos de esos bloqueos antiguos de reenvío quedaron resueltos en la preparación de prueba y pasan en las 159 anteriores; **no se volvió a acreditar toda esa suite como verde**. La ejecución amplia inicial de interfaz también mostró dos fallos de elegibilidad de paquetería en `modalElegibilidadCedisPdv.test.jsx`, fuera de los nueve archivos verificados aquí. Conservar estos pendientes visibles; este cierre no los borra ni afirma que sean inocuos.

Pendiente de comprobación operativa: recorrido autenticado con usuarios reales de Ventas, Auxiliar PDV, CEDIS y Auditoría en el ambiente destinado a aceptación. Las pruebas HTTP/servicios, concurrencia y renderizado aislado aportan evidencia separada; ninguna se etiqueta como una sesión real de esos usuarios.

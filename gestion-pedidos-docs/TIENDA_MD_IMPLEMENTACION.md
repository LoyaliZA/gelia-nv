# T-02.1 — TIENDA MD

Implementación del 4 de noviembre de 2026, continuación de T-02. Por decisión explícita del usuario, TIENDA MD queda disponible para todos los departamentos, sin exigir el permiso especial de MD. Se conservan las facultades normales de vendedora, auxiliar de ventas, CEDIS y personal de sucursal; no se conceden permisos automáticamente.

## Comportamiento implementado

- Ajuste posterior autorizado: la captura ofrece únicamente consulta en Tienda o CEDIS. Mismo día se solicita al confirmar transferencia, después del cierre de consulta, con sucursal de recolección. Se retiró la tercera entrada inicial y su explicación extensa; se conserva compatibilidad con solicitudes MD previamente guardadas. Guardar un borrador no solicita mercancía.
- Los días y el corte se administran en Configuración operativa. Por defecto, lunes a viernes hasta las 13:00; sábado y domingo no permiten crear la solicitud MD. Las piezas ya disponibles en PDV conservan la ramificación pendiente de definición; no se convierten automáticamente en MD ni en apartado con otro pago.
- Después de la respuesta y del cierre de consulta, la vendedora confirma cobertura de transferencia y registra comprobantes mediante los controles existentes. MD no se restringe a Bellaroma ni a una lista de departamentos/usuarios.
- El servidor conserva la fecha y hora del primer reporte aceptado de transferencia, con corte a las 13:00 de México. A las 13:00:00 es oportuno; después, tardío. Sustituir el comprobante, agregar una exhibición o guardar de nuevo la misma modalidad no reinicia ese instante.
- Una solicitud MD tiene fecha propia. Reportar el pago en otra fecha no lo vuelve oportuno por entrar antes de las 13:00 del día siguiente. El fin de semana tampoco habilita MD. La fecha y hora declaradas por el usuario no sustituyen el instante aceptado por el servidor.
- La verificación del ingreso y la emisión de remisión pueden terminar después de las 13:00 del mismo día sin convertir en tardío el reporte original oportuno. Preparar y salir exigen también consulta cerrada, pago validado, remisión, destino, permisos y ausencia de bloqueos pendientes. No se añadió una espera por revisión administrativa posterior.
- Se reutiliza el recorrido documental T-02: bolsas con fotografías y observaciones, salida explícita, recepción verificada, almacén y ubicación de custodia identificados, y entrega al cliente con receptor y fotografía. No se vuelve a transcribir el inventario completo de piezas ni se etiqueta una fotografía como firma.
- Un reporte tardío, un día vencido o la falta de reporte dentro del corte muestran «Pendiente de acuerdo». Se bloquean la preparación y la salida MD, también en los servicios de empaque/salida anteriores. No se transforma ni reprograma automáticamente el pedido.
- Antes de salir, la vendedora puede registrar el acuerdo del cliente mediante «Acordar recolección posterior · T-02». Exige una explicación y confirmación expresa. Conserva comprobantes, ingreso, remisión y reporte MD original; registra responsable y fecha y continúa como T-02. CEDIS no acuerda por la vendedora.
- Si la mercancía ya salió, la recepción y custodia continúan para mantenerla identificada. Si el cliente no recoge ese día, la mercancía pagada se conserva y la entrega con fotografía sigue disponible posteriormente. No se libera como apartado impago, no se devuelve dinero y no se convierte automáticamente en T-02. MD no recibe por defecto el plazo de 20 días de T-02; ese seguimiento aparece al acordar expresamente la continuación ordinaria.

La hora de cierre de atención al público de cada sucursal no se inventa ni se sustituye por el vencimiento del apartado T-01. El personal confirma la entrega real; la interfaz distingue intención MD, pago reportado, preparación, traslado, custodia y entrega. City Center conserva la ramificación T-03 existente.

## Entradas del código

- `PreparacionTiendaConfig::facultadMismoDia`: disponibilidad sin filtro especial por departamento/usuario en esta etapa.
- `SolicitarRevisionRegistrarPedidosService` y `FormularioRegistrarTienda.jsx`: compatibilidad de solicitudes iniciales MD guardadas; las nuevas capturas usan consulta normal y después transferencia.
- `RegistrarCoberturaTiendaService`, `AplicarCorteMismoDiaTienda`, `CorteMismoDiaTienda` y `AtencionMismoDiaTienda`: modalidad, primer reporte, fecha solicitada, corte y continuación pendiente.
- `TransferenciaT02::documental`: alcance compartido de transferencia ordinaria y MD; `TransferenciaT02::aplica` mantiene la clasificación ordinaria para sus reglas específicas.
- `TransferenciaTiendaService` y `SeguimientoTransferenciaTienda.jsx`: operación compartida y acción explícita `continuar_posterior` en el endpoint existente.
- `FusionarSolicitudPreparacionPedido`: conserva metadata `tienda_md_solicitud` y `tienda_md_continuacion` al normalizar y guardar.
- Integración en CEDIS, Tienda, consulta del pedido y detalle de Resguardos. Los badges compartidos identifican MD sin mostrar códigos internos.

## Comprobaciones y pendientes

Comprobación dirigida, sin suite general: 34 pruebas de servidor aprobadas, 216 aserciones, y 27 pruebas de interfaz aprobadas. Cubren recorrido MD, conservación de mercancía pagada al día siguiente, corte exacto y pago tardío, fecha distinta a la solicitada, reporte original inmutable, continuación expresa por la vendedora, solicitud inicial a CEDIS sin permiso especial, bloqueo del caso PDV ambiguo, fin de semana y regresión de T-02/otros pagos. Las pruebas de servidor usan SQLite en memoria y caché de rutas aislada.

Se publica un build al cerrar el lote con Sail, conservando la corrección de permisos de publicación. No se agregaron migraciones ni dependencias. No se crean capturas nuevas ni se acredita revisión renderizada en escritorio/móvil o un recorrido autenticado con cuentas reales; siguen pendientes para la revisión del usuario. Las capturas conservadas de T-01 no representan MD.

No se hizo commit ni push. Incorporar los archivos nuevos de documentación al subir la entrega.

Ajuste posterior: [Configuración operativa y consulta por sucursal](CONFIGURACION_OPERATIVA_TIENDA.md) documenta los parámetros, permisos y comprobaciones de esta continuación.

## Claridad y recuperación — 5 de noviembre

La [revisión de disponibilidad y Mismo día](DISPONIBILIDAD_ACCIONES_RAPIDAS.md) actualiza presentación y bloqueo de piezas. El pendiente actual tiene prioridad; datos/documentos, primer reporte e historial quedan desplegables. Los botones usan borde e iconos; seguimiento y acuerdos se capturan al solicitarlos. Reabrir una consulta cerrada con faltantes no borra ni reinicia el reporte original de transferencia. Las nuevas consultas no pueden cerrarse con faltantes aceptados; se retiran o se sustituyen y se espera la respuesta correspondiente.

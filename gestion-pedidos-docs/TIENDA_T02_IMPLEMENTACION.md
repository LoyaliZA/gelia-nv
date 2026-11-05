# Tienda T-02 — transferencia y recolección posterior

Continuación posterior del mismo día: [T-02.1 — TIENDA MD](TIENDA_MD_IMPLEMENTACION.md) extiende el recorrido compartido. La exclusión de MD que describe el alcance original de esta entrega queda actualizada por esa implementación y por las decisiones vigentes.

Decisión e implementación del 4 de noviembre de 2026. Esta entrega continúa T-01 por solicitud del usuario. Las instrucciones anteriores de detener el trabajo en el servidor correspondían al cierre anterior; el usuario autorizó resolver el build y continuar T-02 con comprobaciones acotadas.

## Recorrido implementado

1. La vendedora consulta disponibilidad, transmite el resultado al cliente y cierra la consulta vigente. Se recomienda CEDIS como origen; consultar PDV conserva su procedencia. La selección de sucursal de recolección no cambia el almacén de origen.
2. Transferencia utiliza los comprobantes y la verificación de ingreso existentes. El auxiliar de ventas registra el número y adjunta la remisión Wizerp, incluyendo su fecha de emisión. La aprobación operativa existente permite preparar; no se agregó una espera por revisión administrativa posterior.
3. El responsable de origen registra las bolsas preparadas con una fotografía por bolsa y observaciones. No se vuelve a capturar el catálogo de piezas. El ticket de una entrega interna no es requisito nuevo de T-02, pues su comprobante exacto sigue pendiente de definición.
4. La salida o entrega interna es una acción explícita posterior. Se crea la recepción esperada del resguardo existente; esto no confirma recepción ni custodia. Si origen y destino son el mismo PDV, se presenta como entrega a recepción de la misma sucursal.
5. El personal autorizado del destino confirma que verificó todas las bolsas identificadas y las observaciones. Después confirma, por separado, el almacén PDV y la referencia de ubicación de custodia. No se cambia el almacén de origen del pedido.
6. La entrega al cliente exige receptor y fotografía. Reutiliza el servicio de entregas de Resguardos, con un método específico de fotografía para T-02: no se registra una fotografía como si fuera una firma. La integración existente actualiza Control de pedidos y evita duplicar entregas al reintentar.
7. La vendedora puede registrar notas de seguimiento. A los 20 días naturales desde la emisión de remisión se identifica «Pendiente de gestión» y se avisa a la vendedora una sola vez. No se cancela, devuelve dinero, bloquea la entrega ni libera inventario automáticamente por ese plazo.

El plazo de nuevos T-02 usa la fecha de emisión indicada al adjuntar la remisión. Sustituir el PDF conserva la fecha inicial. En registros sin esa metadata se conserva como referencia compatible la primera remisión registrada; esa fecha de carga no acredita por sí sola la emisión original.

## Alcance y accesos

- Aplica a la captura documental de Tienda con cobertura de transferencia ordinaria. Pago al recoger, crédito y Mismo día mantienen su proceso.
- Origen PDV: responsable de la instrucción vigente. Origen CEDIS: permisos y alcance de CEDIS. Destino: acceso a la sucursal y facultad operativa; la entrega conserva también el control de sucursal activa del servicio de Resguardos.
- Cada operación comprueba versión, etapa, consulta cerrada, ingreso validado, remisión y destino. Los bloqueos por ajustes, cancelación, retiro para envío y segundo traslado se conservan. Se registran responsable y fecha.
- City Center se identifica como ramificación T-03 y no habilita la salida ordinaria de T-02. T-03, T-02.1, T-05, T-09 y R-01 no se implementan como efecto de esta entrega.
- No se agregaron migraciones, catálogos de estados paralelos ni permisos concedidos automáticamente. No se inventan SKU, cantidades completas ni movimientos de inventario a partir de las excepciones de disponibilidad.

## Entradas principales

- `app/Support/ControlPedidos/TransferenciaT02.php`: condición del proceso.
- `app/Services/ControlPedidos/TransferenciaTiendaService.php`: coordinación de etapas sobre empaque, traslado y Resguardos existentes.
- `app/Services/ControlPedidos/SeguimientoRecoleccionT02Service.php`: gestión a los 20 días, llamada por la evaluación de vencimientos existente.
- `app/Http/Controllers/ControlPedidos/TransferenciaTiendaController.php` y rutas en `routes/web/modules/control-pedidos/core.php`: consulta y acciones autenticadas con acceso comprobado por pedido.
- `resources/js/Pages/ControlPedidos/Partials/SeguimientoTransferenciaTienda.jsx`: panel compartido por captura, consulta, CEDIS, Tienda y detalle de Resguardos. Reutiliza paneles, galerías, controles y tema GELIA.
- `GestionarRemisionPedidoBmaService.php` y `Auditar/Partials/ModalRevisarPedido.jsx`: fecha de emisión de remisión, sin renovar al sustituir.
- `RegistrarEntregaResguardoPdvService.php`: fotografía de T-02 diferenciada de la firma de los demás flujos.

## Corrección del build

Sail ejecuta npm con UID 1000. Había archivos de `public/build` creados por root que ese usuario no podía sobrescribir; el directorio sí era escribible. `scripts/retain-vite-assets.mjs` publica ahora cada archivo mediante copia temporal y reemplazo en su directorio, en lugar de sobrescribir y cambiar permisos de archivos ajenos con `cpSync`.

El manifiesto se reemplaza después de validar los assets, se conserva el anterior y no se eliminan los archivos que usan páginas abiertas. No se abrieron permisos generales ni se cambió la propiedad del repositorio. Se corrigió además la propiedad de archivos generados de pruebas en `storage/framework/testing`, que habían quedado de ejecuciones anteriores como root.

Usar `./vendor/bin/sail npm run build`; no compilar como root ni levantar Vite para servir este entorno. La compilación publicada se verificó desde la página de acceso: respuesta 200, referencia al asset del manifiesto y ausencia de `public/hot`.

## Evidencia y límites

- T-02: 9 pruebas de servidor aprobadas, 51 aserciones. Cubren recorrido completo desde CEDIS, procedencia PDV en la misma sucursal, pago pendiente, recepción anticipada, sucursal ajena, versión obsoleta, ramificación City Center, emisión conservada, idempotencia y gestión sin liberación a los 20 días.
- Regresión dirigida: 7 pruebas de remisiones y 13 de entregas de Resguardos aprobadas. La entrega ordinaria con firma sigue operativa.
- Interfaz y auxiliares: 24 pruebas aprobadas entre el panel T-02, consulta en solo lectura, estados previos y retención de assets.
- Servidor probado con SQLite en memoria y caché de rutas aislada; no se usaron pedidos reales para las comprobaciones. Se actualizó la caché de rutas de la aplicación para publicar los endpoints.
- Se conserva la evidencia visual anterior de T-01. **No se generaron capturas de T-02 ni se acredita revisión visual en escritorio/móvil o un recorrido autenticado con cuentas reales.** La siguiente revisión con el usuario debe comprobar esas superficies, los permisos de los usuarios operativos y un caso real de origen/destino. Las capturas T-01 no se presentan como evidencia T-02.

Los cambios están en el árbol de trabajo, incluidos archivos nuevos que deben incorporarse a Git. No se hizo commit ni push.

## Consulta de CEDIS — adecuación visual posterior

El usuario inició la revisión de T-02 y señaló que el modal de disponibilidad estaba incompleto. Se tomó Preparación en tienda como referencia, conservando el contrato de consulta existente.

- `PanelRespuestaDisponibilidad` presenta cliente, folio y origen, un espacio de captura y el pedido de referencia en paralelo. En móvil el documento se abre con Ver pedido; la captura y las acciones comparten desplazamiento.
- Se elige Sin incidencias o Con detalle/faltante, de forma excluyente. El conjunto correcto no exige transcribir productos. Las excepciones admiten búsqueda por SKU/nombre, escaneo con la cola compartida y captura manual cuando falta catálogo. La búsqueda conserva incluir_sin_inventario; cantidad afectada no representa inventario reservado.
- Sin existencias genera su motivo sin redactarlo; Con detalle exige descripción. Las filas se pueden quitar antes de enviar. Se serializa SKU cuando existe, sin introducir atributos incompatibles ni confirmar piezas correctas una por una.
- GaleriaEvidenciasPedido sustituye el input nativo: arrastrar/pegar, selección múltiple, miniaturas, eliminación, validación y visor modal. Se conservan los límites del endpoint (8 archivos, 10 MB por archivo) y la compatibilidad de imágenes/PDF. Al reabrir una respuesta se muestran sus evidencias generales vigentes.
- DocumentoSolicitudTienda se reutiliza con la URL autorizada del documento de CEDIS; las tareas de Tienda conservan su ruta específica. Se puede ampliar tanto el pedido como la evidencia. El estado del formulario se reinicia por pedido al abrir otro modal.
- La respuesta queda en Consulta respondida, a la espera de Ventas. No prepara bolsas, confirma pago, registra remisión, traslada ni crea custodia. Continúan las reglas de T-02 existentes después del cierre y documentación. No se modificó el formulario de pesaje de los flujos legados/nacional/municipal.

Comprobación acotada: 18 pruebas de interfaz aprobadas (tres de esta consulta y quince de regresión de Preparación Tienda), una prueba de servidor de consulta CEDIS sin traslado con 11 aserciones. Build publicado sin EACCES y diferencias sin errores de espacios. Cuatro capturas del build con datos ficticios: captura y visor en escritorio oscuro 1440×1000 y móvil claro 390×844, conservadas en `resources/design-references/cedis-disponibilidad`. Se comprobaron errores JS, desbordamiento y acceso a Enviar respuesta en móvil. Se inspeccionaron las capturas de ambos tamaños. No acredita sesión autenticada ni el recorrido completo entre CEDIS, Ventas y sucursal.

Reproducción: `./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-cedis-disponibilidad.mjs`. Sin commit ni push; conservar los cambios previos del árbol de trabajo.

## Corrección del cierre de consulta CEDIS — 5 de noviembre de 2026

La respuesta con incidencias conserva pendiente_pesaje hasta la aceptación explícita de Ventas. La regla general de puedeCerrarConsulta interpretaba ese estado como falta de respuesta y ocultaba Cerrar consulta, aunque revision_disponibilidad ya estuviera registrada.

PedidoBma ahora reconoce la consulta documental de Tienda en CEDIS por modalidad_entrega=tienda y area_revision=control_pedidos. Evalúa la respuesta vigente y los ajustes pendientes para permitir cerrar; conserva el bloqueo si la respuesta fue sustituida, hay anexos/retiros pendientes o una actualización de consulta. ListarPedidosBmaService también expone el motivo de bloqueo para CEDIS. El cierre continúa mediante el servicio existente, por la vendedora del pedido; no registra pago ni traslado. Los envíos que requieren pesaje mantienen su evaluación anterior.

Comprobación dirigida: tres pruebas de servidor aprobadas, 48 aserciones. La respuesta con faltante expone puede_cerrar_consulta=true, una respuesta sustituida o un anexo pendiente bloquean, y el cierre válido permite seleccionar transferencia sin crear pago ni tarea de traslado. Se incluyó regresión de consulta CEDIS sin incidencias y de bloqueo por consulta de Tienda no vigente. SQLite en memoria y cachés de configuración/rutas aisladas. No hay cambios de interfaz, build ni capturas nuevos en esta corrección. Recargar el pedido para recibir la capacidad actualizada. Sin commit ni push.

### Depuración visible y retiro de faltantes — 5 de noviembre de 2026

- Los códigos de fichas quedan como identificadores internos y documentación. Botones, avisos y nuevos movimientos usan nombres de operación; la bitácora traduce también referencias conservadas en comentarios anteriores.
- Ventas puede elegir un faltante desde la respuesta de CEDIS o Tienda, incluso si fue descrito sin SKU. Su retiro completo cierra la incidencia y queda registrado con responsable, motivo y fecha, sin liberar stock inexistente, modificar importes ni confirmar pagos. Un retiro mixto mantiene pendiente del auxiliar la parte físicamente apartada. La consulta debe cerrarse explícitamente después del ajuste; no se admiten retiros informales de pedidos pagados o remisionados.
- La selección usa la cantidad completa reportada del faltante; no divide una incidencia en retiros parciales. Las piezas realmente apartadas conservan su retiro parcial y liberación validada por el auxiliar. Los cambios documentales por PDF/imágenes conservan la identificación y atención del auxiliar.
- «Ver detalles» distingue el flujo actual de Tienda del legado: referencia y respuesta, sucursal, procedencia, modalidad de pago y remisión cuando exista. Oculta pesos, paquetería, domicilio y campos logísticos que no aplican. La vista de CEDIS también omite costos y campos de envío del circuito anterior para estas consultas.
- Comprobación dirigida: 4 pruebas PHP / 58 aserciones (incluye cierre CEDIS, faltante sin SKU, retiro mixto y retiro físico parcial); 9 pruebas de componentes; build publicado con Sail. Verificación renderizada con datos ficticios en escritorio oscuro y móvil claro, sin errores JavaScript ni desborde del modal. Capturas y reporte: `resources/design-references/tienda-detalle-retiro-2026-11-05/`; guion: `scripts/qa/tienda-recorrido/verificar-detalle-retiro.mjs`.
- Pendiente: recorrido autenticado con actores y documentos reales. Las capturas no acreditan operación real ni el traslado físico. Sin commit ni push.

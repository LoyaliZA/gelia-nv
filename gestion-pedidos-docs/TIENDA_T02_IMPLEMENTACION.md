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

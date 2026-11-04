# Continuidad para Cursor — Tienda T-01

Entrega del 4 de noviembre de 2026. Los cambios solicitados quedan implementados en el árbol de trabajo, pendientes de que el usuario los suba a Git. No se creó un commit ni se hizo push.

Por instrucción del usuario, se cierra el trabajo en este servidor inestable sin ejecutar más pruebas, compilaciones ni capturas. Este documento permite continuar en otro servidor sin repetir la implementación ni perder la evidencia.

## Lectura inicial

Leer las instrucciones locales de agentes, la [guía de diseño GELIA](../GUIA_DISENO_GELIA.md) y las [decisiones vigentes de Tienda](TIENDA_DECISIONES_VIGENTES.md). Las decisiones posteriores del usuario prevalecen sobre documentos históricos. Conservar tema, fuente, acento y primitivas de GELIA.

## Implementación entregada

- Los pedidos del nuevo flujo de Tienda usan los componentes actualizados al editar y consultar detalles. Se conserva la compatibilidad de pedidos legados y los formularios de municipal y nacional. No se afirma una auditoría exhaustiva de todos los módulos.
- Los badges distinguen automáticamente «Pendiente de respuesta», «Consulta respondida» y, al cerrar, «Separación aprobada» o «Pesaje respondido» según el proceso. «Apartado confirmado» sigue siendo una confirmación física separada. Se conservan colores y etiquetas legibles.
- Los importes de Tienda ocultan envío, reexpedición y seguro cuando no aplican. El saldo a favor y los checkboxes conservan el estilo GELIA.
- En T-01 documental con «Pago al recoger», GELIA muestra «Se paga en tienda» y no exige ni registra una exhibición de pago. El cobro y la remisión se realizan en PDV.
- Cerrar la consulta aprueba la separación del conjunto y notifica al auxiliar asignado. El auxiliar confirma el pedido separado sin volver a capturar piezas individuales ni cantidades ficticias.
- La confirmación de modalidad inicia la vigencia: lunes a viernes hasta las 17:00; sábado dentro del horario de 9:00 a 13:00, con vencimiento a las 13:00. Volver a guardar la misma modalidad no renueva el plazo. El contador no libera piezas automáticamente.
- El auxiliar registra la entrega con confirmación explícita del pago recibido en PDV, receptor, número de remisión y foto obligatoria; el ticket es opcional. Se conserva responsable y fecha. La aplicación de saldo a favor evita duplicarse al reintentar.
- El auxiliar puede liberar explícitamente un apartado impago con motivo. Se notifica a la vendedora, se libera la reserva de saldo a favor pendiente y se muestra «Apartado liberado». Los controles bloquean liberar como impago un pedido con pago, remisión o saldo ya aplicado. La finalización impide seguir operando el pedido.
- Se retiró el encabezado CSRF manual desactualizable de las solicitudes afectadas para usar la cookie vigente de Axios. Se conserva la protección CSRF y se informa el error 419 sin reintentar automáticamente operaciones financieras. Falta confirmar el incidente original en una sesión real.

## Archivos de entrada para continuar

Servidor:

- `app/Support/ControlPedidos/ApartadoT01.php`: alcance del nuevo flujo documental.
- `app/Services/ControlPedidos/OperacionApartadoT01Service.php`: entrega, liberación, trazabilidad y saldo a favor.
- `app/Services/ControlPedidos/CerrarConsultaPedidoBmaService.php`: cierre, transición y aviso de separación.
- `app/Services/ControlPedidos/GuardarApartadoRecogeHoyService.php` y `ConfirmarApartadoRecogeHoyService.php`: confirmación documental del conjunto.
- `app/Services/ControlPedidos/RegistrarCoberturaTiendaService.php` y `app/Support/ControlPedidos/PlazoApartadoTienda.php`: vigencia persistida.
- `app/Http/Controllers/ControlPedidos/PedidoBmaTiendaController.php` y `routes/web/modules/control-pedidos/tienda.php`: acciones protegidas de entrega/liberación; respuestas compatibles con modal y página.

Interfaz:

- `resources/js/Pages/ControlPedidos/Partials/flujoCapturaPedido.js`, `ModalFormPedido.jsx` y `ConsultaSolicitudTienda.jsx`: selección del flujo y detalles actualizados.
- `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js`: estados y colores compartidos.
- `resources/js/Pages/ControlPedidos/Partials/ImportesPagoTienda.jsx` y `VigenciaApartadoTienda.jsx`: importes, modalidad y contador.
- `resources/js/Pages/ControlPedidos/Tienda/Partials/PrepararApartadoRecoge.jsx` y `EntregaApartadoT01.jsx`: separación y entrega. La evidencia reutiliza las galerías GELIA.

## Capturas conservadas en el repositorio

Hay **12 capturas PNG**: importes, separación y entrega; cada vista en escritorio y móvil, con tema claro y oscuro. Se acompañan del [reporte de verificación](../resources/design-references/tienda-t01/verificacion.json).

- [Importes en móvil, tema claro](../resources/design-references/tienda-t01/t01-importes-movil-claro.png).
- [Separación en escritorio, tema oscuro](../resources/design-references/tienda-t01/t01-separacion-escritorio-oscuro.png).
- [Entrega en móvil, tema claro](../resources/design-references/tienda-t01/t01-entrega-movil-claro.png).

La carpeta completa es `resources/design-references/tienda-t01/`. Las capturas usan componentes reales con transporte simulado y reloj fijo; no acreditan un recorrido autenticado. No dependen de archivos temporales. La [documentación de reproducción](../scripts/qa/tienda-recorrido/README.md) describe los scripts; adaptar nombres de contenedores y servidor antes de utilizarlos en otro entorno.

## Verificación ya realizada

- Servidor: **84 pruebas aprobadas, 768 aserciones**, sobre los archivos `SolicitudRegistrarTiendaTest.php`, `ImportesPagoTiendaTest.php` y `RecogeTiendaFlujoTest.php`, con SQLite en memoria y caché de rutas aislada.
- Interfaz relevante: **37 pruebas aprobadas**. Las últimas modificaciones de entrega e importes se revisaron nuevamente en sus pruebas específicas.
- Se generaron las 12 capturas y se verificó la ausencia de desbordamiento horizontal en las vistas revisadas. Se comprobó el nuevo bloque de estilo del checkbox.
- La compilación y actualización local de caché de rutas ya habían concluido antes de la instrucción de detener verificaciones. No se publicaron cambios en otro servidor. No se agregaron migraciones.

Estas comprobaciones no equivalen a una suite completa aprobada. Las decisiones vigentes conservan las limitaciones y fallos anteriores ajenos al alcance, incluida la ausencia de un archivo base del control global de estilos; no regenerarlo para ocultar diferencias.

## Pendiente real en el nuevo servidor

Comprobar con sesiones de vendedora y auxiliar asignado el recorrido consulta → respuesta → cierre → separación → modalidad → entrega, y la alternativa de liberación de un apartado impago. Confirmar que el error 419 original no se reproduce con la sesión vigente. Revisar las capturas existentes antes de generar otras. Limitar cualquier comprobación adicional a lo que el usuario autorice y a cambios o fallos concretos; no repetir suites ni compilaciones por rutina.

## Antes de subir a Git

Incluir los archivos nuevos de servidor, interfaz y pruebas, los scripts de QA, este documento y la carpeta completa de capturas con su reporte. No omitir los archivos aún sin seguimiento. No agregar `.env`, archivos temporales, cachés ni compilados de ejecución como parte de esta entrega. Hay cambios locales en `.cursor/rules/` que deben conservarse y revisarse por separado; no se atribuyen a esta implementación. El usuario realiza el commit y el push.

## Texto de continuidad para Cursor

> Continúa desde `gestion-pedidos-docs/CONTINUIDAD_CURSOR_TIENDA_T01.md` y las decisiones vigentes de Tienda. La implementación solicitada está entregada en el árbol de trabajo; comprueba qué llegó en Git antes de editar. Conserva el flujo documental T-01, el pago externo en PDV, la separación del conjunto y los controles de entrega/liberación. No restaures captura individual ni comprobante obligatorio para Pago al recoger. Revisa primero las 12 capturas guardadas. El pendiente es el recorrido autenticado y confirmar el 419 en este nuevo servidor; no inicies pruebas, compilaciones o nuevas capturas de forma automática. Respeta las instrucciones locales y las nuevas indicaciones del usuario.

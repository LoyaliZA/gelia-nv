# CEDIS y empaque

- Propósito: preparar grupos de pedidos, registrar bultos y confirmar recolecciones sin anticipar recepción.
- Ubicación: `routes/web/modules/control-pedidos/cedis.php`; `app/Services/ControlPedidos/{ListarPedidosCedisService,MarcarEmpacadoPedidoBmaService,MarcarEnviadoPedidoBmaService}.php`.
- Frontend: `resources/js/Pages/ControlPedidos/Cedis/`; pruebas: `tests/Feature/ControlPedidos/{EmpaqueBultosCedisTest,BandejasOperativasTest}.php` y `tests/Unit/ControlPedidos/ControlPedidosCedisVisibilidadTest.php`.

## Estado actual
- [verificado] Responder pesaje nacional ordinario usa dos vistas: Pedido y revisión (documento/productos 40/60 en escritorio) y Cajas y evidencias, con captura montada, Siguiente/Atrás y Enviar respuesta sin cambiar validaciones. Cada envío nacional agrupa datos de caja y catálogo a la izquierda, y la galería existente a la derecha; se apilan en móvil. Con adjuntos usa Adjuntar más y miniaturas en fila horizontal sin alterar el desplegable de catálogo; cabecera y pie único permanecen fuera del scroll. `soloRevisiones` y otras modalidades conservan sus pasos. `resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx`; pruebas `ModalResponderPesaje.navegacion.test.jsx` junto al componente.
- [verificado] Nacional con consulta enviada permanece en CEDIS TODOS hasta que Ventas cierra (`consulta_cerrada_at`); PENDIENTES_PESAJE solo lista `pendiente_pesaje`. Destino de tarjeta: quien recibe y domicilio, no sucursal, salvo recolección en sucursal. `ListarPedidosCedisService.php`; `presentacionPedidoCedis.js`.
- [verificado] Empaque opera sobre raíz y complementos con bloqueo ordenado por ID: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:25`.
- [verificado] Bultos requieren evidencia cuando la variante lo exige: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:63`.
- [verificado] Recolección de varias cajas permite selección parcial; el pedido espera las restantes: `app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php:79` y `:128`.

## Reglas que no se rompen
- [verificado] Alcance CEDIS depende de departamentos del vendedor, salvo administrador/ver_todos: `app/Support/ControlPedidos/VisibilidadPedidoBma.php:115`.
- [verificado] Bitácora tiene facultad separada; ocultar también relaciones anidadas al serializar, sin borrar historial interno: `app/Support/ControlPedidos/BitacoraCedis.php:25`.
- [verificado] Bloquear empaque ante actualización, errores graves o traslado a CEDIS sin recepción: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:105` y `:125`.
- [verificado] Regla ordinaria exige pago validado/remisión; solo-resguardos exige anexo de envío aprobado y no remisión de mercancía: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:113`.
- [verificado] Enviado requiere empaque, etapa correcta y guía si ofrece rastreo: `app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php:38` y `:68`.
- [verificado] El hijo con `pedido_principal_id` no entra a la cola de pesaje. CEDIS responde las piezas del complemento en el detalle del padre, sin mezclar documentos: `app/Services/ControlPedidos/Nacional/ResponderPiezasComplementoNacional.php`.
- [verificado] Con recolección en sucursal no despacha por paquetería. Empaque, guía y salida esperan recepción CEDIS e `integrado` del vínculo físico: `app/Support/ControlPedidos/Nacional/BarreraRecepcionComplementoNacional.php`.

## Dependencias con otros módulos
- [Consultas](CONSULTAS_DISPONIBILIDAD.md), [Auditoría](AUDITORIA_PAGOS.md), [Guías](DELEGADO_GUIAS.md), Traspasos y PDV.
- [verificado] Fase posterior al empaque distingue guía de cliente, sin rastreo y traslado interno: `app/Support/ControlPedidos/MaquinaEstadosPedidoBma.php:204`.

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): grupo con miembro bloqueado, bulto sin evidencia, recolección parcial y usuario CEDIS sin Bitácora.

## Puntos blandos
- [verificado] `ResponderPesajeCedisDebugTest` es prueba HTTP con RefreshDatabase; «Debug» no implica archivo descartable: `tests/Feature/ControlPedidos/ResponderPesajeCedisDebugTest.php:17`.
- [verificado] `check_cedis_responder_pesaje_ui.php` inspecciona cadenas de archivos; no prueba interacción real: `tests/Unit/ControlPedidos/check_cedis_responder_pesaje_ui.php:15`.
- [inferido] Cambiar un estado del grupo desde una sola tarjeta puede afectar complementos; revisar atomicidad antes de ajustar botones: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:43`.

- Última verificación: 2026-11-24, sin commit, árbol de trabajo; nacional en TODOS hasta cierre de consulta.

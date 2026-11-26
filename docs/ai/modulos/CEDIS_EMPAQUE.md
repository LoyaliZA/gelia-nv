# CEDIS y empaque

- Propósito: preparar grupos de pedidos, registrar bultos y confirmar recolecciones sin anticipar recepción.
- Ubicación: `routes/web/modules/control-pedidos/cedis.php`; `app/Services/ControlPedidos/{ListarPedidosCedisService,MarcarEmpacadoPedidoBmaService,MarcarEnviadoPedidoBmaService}.php`; carátula municipal: `EmitirCaratulaMunicipalCedisService`.
- Frontend: `resources/js/Pages/ControlPedidos/Cedis/`; pruebas: `tests/Feature/ControlPedidos/{EmpaqueBultosCedisTest,BandejasOperativasTest,CaratulaMunicipalCedisTest}.php` y `tests/Unit/ControlPedidos/ControlPedidosCedisVisibilidadTest.php`.

## Estado actual
- [verificado] Pesaje nacional ordinario: vista 1 = patrón 2.0 (Todo OK / solo afectadas, Ver pedido); vista 2 = cajas/peso/fotos. Un POST a `responder-pesaje`; el servidor completa UUIDs vigentes no reportados como Bueno (`CompletarRevisionesPesajeNacional`). Piezas del catálogo global (`productos` activos), sin selector de almacén ni fila de `inventarios`. Un borrador de excepciones no sustituye cajas ya guardadas. Siguiente exige estado. `soloRevisiones` y municipal conservan tres pasos, almacén y el panel de disponibilidad. `ModalResponderPesaje.jsx`; `CapturaExcepcionesConsulta.jsx`.
- [verificado] Nacional con consulta enviada permanece en CEDIS TODOS hasta que Ventas cierra (`consulta_cerrada_at`); PENDIENTES_PESAJE solo lista `pendiente_pesaje`. Destino de tarjeta: quien recibe y domicilio, no sucursal, salvo recolección en sucursal. `ListarPedidosCedisService.php`; `presentacionPedidoCedis.js`.
- [verificado] Empaque opera sobre raíz y complementos con bloqueo ordenado por ID: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:25`.
- [verificado] Bultos requieren evidencia cuando la variante lo exige: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:63`.
- [verificado] En Nacional 2.0 sin seguro con guías de Delegado, **Marcar enviado** en el detalle registra todas las cajas activas pendientes. Los demás flujos mantienen la selección parcial: `app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php`.
- [verificado] Destino municipal muestra consignatario, teléfono y dos carátulas (con/sin identificación), vista previa e impresión; el PDF es a demanda y no exige sucursal destino: `SeccionCaratulaMunicipalCedis.jsx`, `control_pedidos.cedis.caratula_municipal`.

## Reglas que no se rompen
- [verificado] Alcance CEDIS depende de departamentos del vendedor, salvo administrador/ver_todos: `app/Support/ControlPedidos/VisibilidadPedidoBma.php:115`.
- [verificado] Bitácora tiene facultad separada; ocultar también relaciones anidadas al serializar, sin borrar historial interno: `app/Support/ControlPedidos/BitacoraCedis.php:25`.
- [verificado] Bloquear empaque ante actualización, errores graves o traslado a CEDIS sin recepción: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:105` y `:125`.
- [verificado] Regla ordinaria exige pago validado/remisión; solo-resguardos exige anexo de envío aprobado y no remisión de mercancía: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:113`.
- [verificado] Enviado requiere empaque, etapa correcta y guía si ofrece rastreo. En Nacional 2.0 sin seguro gestionado por Delegado, CEDIS usa el rastreo compartido y solo las guías PDF confirmadas de cada caja pendiente: `app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php`.
- [verificado] El hijo con `pedido_principal_id` no entra a la cola de pesaje. CEDIS responde las piezas del complemento en el detalle del padre, sin mezclar documentos: `app/Services/ControlPedidos/Nacional/ResponderPiezasComplementoNacional.php`.
- [verificado] Con recolección en sucursal no despacha por paquetería. Empaque, guía y salida esperan recepción CEDIS e `integrado` del vínculo físico: `app/Support/ControlPedidos/Nacional/BarreraRecepcionComplementoNacional.php`.

## Dependencias con otros módulos
- [Consultas](CONSULTAS_DISPONIBILIDAD.md), [Auditoría](AUDITORIA_PAGOS.md), [Guías](DELEGADO_GUIAS.md), Traspasos y PDV.
- [verificado] Fase posterior al empaque distingue guía de cliente, sin rastreo y traslado interno: `app/Support/ControlPedidos/MaquinaEstadosPedidoBma.php:204`.

- [verificado] El formulario de evidencia individual registra caja o bolsa por bulto. La custodia documental de Tienda sigue capturando solo bolsas. Los registros anteriores pueden quedar sin tipo.
- [verificado] El cumplimiento agrupado municipal conserva cajas, bolsas, total, fecha y responsable de empaque en el mismo paso.

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): grupo con miembro bloqueado, bulto sin evidencia, recolección parcial y usuario CEDIS sin Bitácora.

## Puntos blandos
- [verificado] `ResponderPesajeCedisDebugTest` es prueba HTTP con RefreshDatabase; «Debug» no implica archivo descartable: `tests/Feature/ControlPedidos/ResponderPesajeCedisDebugTest.php:17`.
- [verificado] `check_cedis_responder_pesaje_ui.php` inspecciona cadenas de archivos; no prueba interacción real: `tests/Unit/ControlPedidos/check_cedis_responder_pesaje_ui.php:15`.
- [inferido] Cambiar un estado del grupo desde una sola tarjeta puede afectar complementos; revisar atomicidad antes de ajustar botones: `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:43`.

- Última verificación: 2026-11-26, árbol de trabajo sobre 050eae0e. Confirmación de todas las cajas pendientes en Nacional sin seguro.

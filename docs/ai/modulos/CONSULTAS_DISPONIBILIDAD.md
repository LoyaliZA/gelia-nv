# Consultas de disponibilidad

- Propósito: obtener respuesta del conjunto y decisiones de Ventas sin declarar movimientos físicos.
- Ubicación: `app/Http/Controllers/ControlPedidos/DisponibilidadTiendaController.php`; `app/Services/ControlPedidos/{ResponderDisponibilidadInicialService,CerrarConsultaPedidoBmaService,ReabrirConsultaPedidoBmaService}.php`.
- Frontend: `resources/js/Pages/ControlPedidos/Partials/{PanelRespuestaDisponibilidad.jsx,useActualizacionesPedidos.js,vigenciaConsultaPedido.js}`.
- Pruebas: `tests/Feature/ControlPedidos/SolicitudRegistrarTiendaTest.php`; `Partials/{PanelRespuestaDisponibilidad.test.jsx,useActualizacionesPedidos.test.jsx}` junto al frontend.

## Estado actual
- [verificado] Consulta inicial y actualización comparten servicio, con contratos distintos de evidencia/ciclo: `app/Services/ControlPedidos/ResponderDisponibilidadInicialService.php`.
- [verificado] Se registran excepciones; la respuesta no aparta, traslada ni paga. `persistir_unidad` guarda una unidad sin cerrar la consulta ni tocar a las hermanas.
- [verificado] Cada captura o SKU revisable tiene `unidad_id` estable en `solicitud_preparacion_json`; `documentos_soporte` y `documentos_peticion` no son unidades. El índice del array no es identidad.
- [verificado] «Retiro revisado» es documental. No marca `aplicada`, no aparta, no pesa ni cambia etapa.
- [verificado] `AjustePiezas` consume adaptadores. Tienda conserva sus rutas. Municipal usa el mismo editor sin pedido relacionado ni pesaje: `PersistenciaAdjuntosConsulta`.
- [verificado] La captura vigente del conjunto vive al final del seguimiento de Ventas (`CapturaConjuntoPedido`), no duplicada en la tarjeta de respuesta. Nacional permanece como contrato, sin interfaz.

## Reglas que no se rompen
- [verificado] Rechazar actualización obsoleta y pedidos ya pagados/remisionados, empacados o salidos: `app/Services/ControlPedidos/ResponderDisponibilidadInicialService.php:54`.
- [verificado] Si ya hay compromiso físico, atender retiro físico antes de responder la actualización: `app/Services/ControlPedidos/ResponderDisponibilidadInicialService.php:101`.
- [verificado] Conservar deduplicación por solicitud y lote dentro del bloqueo del pedido: `app/Services/ControlPedidos/EjecutarSolicitudAjusteTienda.php:16`.
- [verificado] Cerrar consulta corresponde al propietario y requiere respuesta/ajustes resueltos: `app/Services/ControlPedidos/CerrarConsultaPedidoBmaService.php:83`.
- [verificado] Fusionar solo lectura operativa; conservar captura comercial y financiera local: `resources/js/Pages/ControlPedidos/Partials/vigenciaConsultaPedido.js:20`.
- [verificado] `version_consulta` deriva del historial; no sustituye revisión comercial/huella: `app/Support/ControlPedidos/LecturaFinancieraPedido.php:17` y `:52`.

## Dependencias con otros módulos
- [verificado] Evento mínimo tras commit y canal privado; el cliente relee con autorización: `app/Events/PedidoBmaActualizado.php:11`; `routes/channels.php:74`.
- [verificado] Hook compartido retiene lectura pendiente durante pausa/petición; CEDIS usa respaldo de 15 s con panel abierto: `resources/js/Pages/ControlPedidos/Partials/useActualizacionesPedidos.js:32`; `resources/js/Pages/ControlPedidos/Cedis/Index.jsx:114`.
- [verificado] Tras perder acceso por etapa, CEDIS detecta reingreso desde listado autorizado; no ampliar el GET para evitar 403: `gestion-pedidos-docs/M01_CORTE_ETAPAS_1_2.md:30`.
- Alcance municipal: [Municipal](ENVIO_MUNICIPAL.md). Inventario: [Preparación](PREPARACION_TIENDA.md).

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): dos sesiones, actualización con modal abierto y captura pendiente, ciclos sucesivos y pérdida/reingreso de acceso.

## Puntos blandos
- [verificado] UUID identifica; el ordinal decide orden incluso dentro del mismo segundo: `resources/js/Pages/ControlPedidos/Partials/vigenciaConsultaPedido.js:3`.
- [verificado] Las actualizaciones conservan capturas anteriores y exigen nueva foto del conjunto; no copiar la evidencia del ciclo previo: `gestion-pedidos-docs/DISPONIBILIDAD_ACCIONES_RAPIDAS.md:47` y `:62`.
- [verificado] «Registrar detalle» trata productos; «Reportar error» trata datos/proceso. Claves internas de incidencia se conservan: `gestion-pedidos-docs/DISPONIBILIDAD_ACCIONES_RAPIDAS.md:69`.
- [verificado] Corregir una unidad abierta archiva el resultado anterior en `unidades[].historial`; no usa comentarios de texto como `origen_unidad_id`.
- Archivos de consulta: PHP efectivo `sail` UID 1000. Si un directorio `storage/app/public/pedidos_bma/{id}` quedó `root:root`, `chown` solo esos ids en pruebas. Receta: `gestion-pedidos-docs/CONSULTAS_COMPARTIDAS_IMPLEMENTACION_Y_AUTOEVALUACION.md`.
- [por verificar] Retirar un faltante en CEDIS sigue solicitando actualización (`AccionesDisponibilidadTiendaService`); el cierre espera esa ronda. No se cambió en 3.1.

- Última verificación: 2026-11-18, `develop` @ `c63dd847` + cambios locales etapa 3.1.

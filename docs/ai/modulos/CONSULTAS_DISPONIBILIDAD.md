# Consultas de disponibilidad

- Propósito: obtener respuesta del conjunto y decisiones de Ventas sin declarar movimientos físicos.
- Ubicación: `app/Http/Controllers/ControlPedidos/DisponibilidadTiendaController.php`; `app/Services/ControlPedidos/{ResponderDisponibilidadInicialService,CerrarConsultaPedidoBmaService,ReabrirConsultaPedidoBmaService}.php`.
- Frontend: `resources/js/Pages/ControlPedidos/Partials/{PanelRespuestaDisponibilidad.jsx,useActualizacionesPedidos.js,vigenciaConsultaPedido.js}`; municipal PDV: `Tienda/Partials/ConsultasMunicipalesTienda.jsx`.
- Pruebas: `tests/Feature/ControlPedidos/SolicitudRegistrarTiendaTest.php`; `MunicipalOrdinarioM01Test` (`persistir_unidad`); `Partials/{PanelRespuestaDisponibilidad.test.jsx,cambiosDisponibilidadIdentidad.test.js}` junto al frontend.

## Estado actual
- [verificado] Consulta inicial y actualización comparten servicio, con contratos distintos de evidencia/ciclo: `app/Services/ControlPedidos/ResponderDisponibilidadInicialService.php`.
- [verificado] Se registran excepciones; la respuesta no aparta, traslada ni paga. `persistir_unidad` guarda una unidad sin cerrar la consulta ni tocar a las hermanas.
- [verificado] Los anexos nuevos de Preparación Tienda reciben `linea_id` y unidades persistentes distintas aunque compartan solicitud, SKU o documento. Se conservan las claves existentes; los alias se resuelven antes del guardado parcial/final y se rechazan si son ambiguos o ajenos. `UnidadesRevisionConsulta` y `cambiosDisponibilidad` reconcilian la captura sin reidentificar históricos. El índice no es identidad; `documentos_soporte` y `documentos_peticion` no son unidades.
- [verificado] El retiro se informa; no pide reconfirmación ni recaptura. Cerrar consulta se habilita, no se dispara solo; Ventas puede seguir anexando o retirando.
- [verificado] `AjustePiezas` consume adaptadores. Tienda conserva sus rutas. Municipal usa el editor sin pedido relacionado ni pesaje. Nacional reutiliza el editor y vincula el retiro a la revisión elegida, incluso sin SKU: `adaptadoresConsulta.js` y `RegistrarAjusteConsultaNacional`.
- [verificado] Ventas ve respuestas enviadas desplegadas y solicitudes contraídas. En Tienda y municipal a PDV (`consulta_municipal.area=tienda`), el avance de unidad no acredita el envío: «Pendiente de revisión» hasta `respondida_at` del anexo. `ConsultasMunicipalesTienda` no recarga en `persistir_unidad`; refresca el JSON. CEDIS municipal conserva la lectura inmediata. Captura vigente al final de Ventas.

## Reglas que no se rompen
- [verificado] Rechazar actualización obsoleta y pedidos ya pagados/remisionados, empacados o salidos: `app/Services/ControlPedidos/ResponderDisponibilidadInicialService.php:54`.
- [verificado] Si ya hay compromiso físico, atender retiro físico antes de responder la actualización: `app/Services/ControlPedidos/ResponderDisponibilidadInicialService.php:101`.
- [verificado] Conservar deduplicación por solicitud y lote dentro del bloqueo del pedido: `app/Services/ControlPedidos/EjecutarSolicitudAjusteTienda.php:16`.
- [verificado] Cerrar consulta corresponde al propietario y requiere respuesta/ajustes resueltos: `app/Services/ControlPedidos/CerrarConsultaPedidoBmaService.php:83`.
- [verificado] Fusionar solo lectura operativa; conservar captura comercial y financiera local: `resources/js/Pages/ControlPedidos/Partials/vigenciaConsultaPedido.js:20`.
- [verificado] En Nacional 2.0, CEDIS precarga las solicitudes vigentes y su evidencia. Cada UUID se vincula a su propia revisión y conserva cantidades y condición. El soporte general permite la consulta documental sin capturar líneas en Ventas.
- [verificado] Nacional con peso responde el patrón 2.0 (Todo OK / excepciones) dentro de Responder pesaje, no en el panel de disponibilidad. La consulta documental sin kilogramos sigue en `cedis.disponibilidad`. El servidor completa las piezas vigentes no reportadas como Bueno.
- [verificado] Un ajuste nacional previo al pago conserva la respuesta anterior y deja pendiente un nuevo pesaje. Al responder se invalida la cotización previa de las cajas ajustadas. El complemento tras pago o remisión mantiene su propio pedido, revisiones e idempotencia.
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
- [verificado] Corregir una unidad abierta de Tienda archiva `unidades[].historial`, desactiva sus documentos y borra las revisiones de ese avance; una unidad ya enviada no se sustituye. Esas revisiones no entran en la respuesta ni admiten acción de Ventas. Un detalle de Tienda exige foto. No usa comentarios como `origen_unidad_id`.
- Archivos de consulta: PHP efectivo `sail` UID 1000. Si un directorio `storage/app/public/pedidos_bma/{id}` quedó `root:root`, `chown` solo esos ids en pruebas. Receta: `gestion-pedidos-docs/CONSULTAS_COMPARTIDAS_IMPLEMENTACION_Y_AUTOEVALUACION.md`.
- [verificado] `ActualizacionConsulta::solicitar` no marca ronda si solo hay retiro; la reapertura sí (`forzar`). Anexos sí exigen respuesta y captura nueva.

- Última verificación: 2026-11-24, sin commit, árbol de trabajo; nacional con peso usa 2.0 en pesaje.

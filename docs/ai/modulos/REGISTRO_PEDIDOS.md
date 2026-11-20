# Registro de pedidos

- Propósito: conservar la captura comercial y dirigir cada modalidad al recorrido compatible.
- Ubicación: `routes/web/modules/control-pedidos/core.php`; `app/Http/Controllers/ControlPedidos/PedidoBmaController.php`; `app/Services/ControlPedidos/{CrearPedidoBmaService,ActualizarPedidoBmaService}.php`.
- Frontend: `resources/js/Pages/ControlPedidos/{Index.jsx,Partials/ModalFormPedido.jsx,Partials/FormularioRegistrarTienda.jsx,Partials/ModalFormPedidoLegado.jsx}`.
- Pruebas: `tests/Feature/ControlPedidos/{ConcurrenciaCapturaPedidoTest,SolicitudRegistrarTiendaTest,NacionalAltaConsultaTest}.php` y `Partials/ModalFormPedido.seleccion.test.jsx` junto al frontend.

## Estado actual
- [verificado] Tienda, Municipal marcado y Nacional 2.0 (`captura_nacional=compartida`) comparten captura. Nacional histórico y el borrador legado siguen en el formulario anterior; abrirlos no escribe la marca: `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx:13`.
- [verificado] Abrir/seleccionar modalidad sin captura significativa no debe crear borradores: `gestion-pedidos-docs/M01_CORTE_ETAPAS_1_2.md:13`.
- [verificado] La lectura del listado excluye borradores ajenos salvo alcance administrador: `app/Support/ControlPedidos/VisibilidadPedidoBma.php:171`.

## Reglas que no se rompen
- [verificado] Consultar como gerente/administrador no concede mutación comercial ajena; esta exige vendedor propietario: `app/Support/ControlPedidos/VisibilidadPedidoBma.php:182`.
- [verificado] El guardado comprueba revisión y huella de la fila; no basta comparar fechas: `app/Support/ControlPedidos/RevisionCapturaPedido.php:18`.
- [verificado] Clave de creación única y revisión persistida forman parte del contrato de concurrencia: `database/migrations/2026_10_15_000001_add_revision_registro_pedidos.php:9`.
- [verificado] Tras conversión confirmada, el autoguardado pinnea `modalidad_entrega` y conserva `conversion_modalidad`; un PATCH obsoleto recibe 409: `FusionarSolicitudPreparacionPedido.php:80`.
- [verificado] Cambiar modalidad abre `ModalFormPedido` con `intencion=conversion`; no hay wizard y el selector no persiste hasta M-02.
- Contratos de consulta/pago: [Consultas](CONSULTAS_DISPONIBILIDAD.md) y [Auditoría](AUDITORIA_PAGOS.md).

## Dependencias con otros módulos
- Modalidades: [Municipal](ENVIO_MUNICIPAL.md), [Preparación](PREPARACION_TIENDA.md), [Direcciones](DIRECCIONES.md).
- [verificado] El formulario actual declara que posee la lectura; evita un lector simultáneo desde la bandeja: `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx:31`.

## Cómo probar
- Seleccionar los casos de registro/concurrencia en [PRUEBAS](../PRUEBAS.md); verificar abrir vacío, recargar borrador y conflicto conservando captura.

## Puntos blandos
- [verificado] «Legado» sigue importado y ejecutado; no es un archivo huérfano: `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx:38`.
- [verificado] El identificador histórico `folio_remision` no autoriza editar el número de pedido desde Auditoría: `app/Services/ControlPedidos/GestionarRemisionPedidoBmaService.php:86`.
- [verificado] El alta nacional 2.0 envía pesaje comercial a CEDIS; anexo o retiro sin remisión sigue en el mismo pedido y pide otra respuesta. Con remisión, el ajuste se rechaza: `app/Services/ControlPedidos/Nacional/RegistrarAjusteConsultaNacional.php`.
- [verificado] Tras el cierre, la misma captura puede cambiar la paquetería, incluida PAQ. PENDIENTE. El importe lo calcula el servidor y un costo mayor que cero no pasa a cero: `app/Services/ControlPedidos/Nacional/CambiarPaqueteriaNacional.php`.

- Última verificación: 2026-11-20, working tree, sin commit.

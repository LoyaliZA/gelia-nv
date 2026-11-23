# Registro de pedidos

- Propósito: conservar la captura comercial y dirigir cada modalidad al recorrido compatible.
- Ubicación: `routes/web/modules/control-pedidos/core.php`; `app/Http/Controllers/ControlPedidos/PedidoBmaController.php`; `app/Services/ControlPedidos/{CrearPedidoBmaService,ActualizarPedidoBmaService}.php`.
- Frontend: `resources/js/Pages/ControlPedidos/{Index.jsx,Partials/ModalFormPedido.jsx,Partials/FormularioRegistrarTienda.jsx,Partials/ModalFormPedidoLegado.jsx}`.
- Pruebas: `tests/Feature/ControlPedidos/{ConcurrenciaCapturaPedidoTest,SolicitudRegistrarTiendaTest,NacionalAltaConsultaTest,NacionalOla3CierreTest}.php` y `Partials/ModalFormPedido.seleccion.test.jsx` junto al frontend.

## Estado actual
- [verificado] En Nacional, Mercancía aparece solo con respuesta CEDIS vigente; oculta registro, borrador y consulta/actualización pendiente. Reutiliza los ajustes nacionales y conserva sus bloqueos; piezas precapturadas y envío documental sin SKU se mantienen. `FormularioRegistrarNacional.jsx` y `SeguimientoSolicitudNacional.jsx`.
- [verificado] Nacional selecciona la salida entre CEDIS habilitados del catálogo; preselecciona el único elegible, guarda `almacen_id` y bloquea cambios tras solicitar pesaje. Conserva el id guardado si deja de estar disponible: `FormularioRegistrarNacional.jsx`; pruebas de selección e integración junto al componente.
- [verificado] Tienda, Municipal marcado y Nacional 2.0 (`captura_nacional=compartida`) comparten captura. Nacional histórico y el borrador legado siguen en el formulario anterior; abrirlos no escribe la marca: `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx:13`.
- [verificado] Anexo extraordinario (solo Nacional compartido con remisión o pago, antes o después de salir): acción «Anexo extraordinario» del menú de la fila y `POST /control-pedidos/{id}/anexo-extraordinario` crean un pedido hijo independiente (`pedido_principal_id` nulo, folio `-C{n}`, vínculo en `solicitud_preparacion_json.complemento_nacional`) sin pago prevalidado, con folio Wizerp capturado, piezas en `piezas_nacionales` y su mini consulta a CEDIS; sale en caja y guía aparte y no toca al principal. Ajuste ordinario con remisión o pago ya no crea complemento. Etiqueta «Anexo de …» vía `EncabezadoFolioPedido`: `app/Services/ControlPedidos/Nacional/RegistrarComplementoNacional.php`, `resources/js/Pages/ControlPedidos/Nacional/ModalAnexoExtraordinario.jsx`.
- [verificado] Abrir/seleccionar modalidad sin captura significativa no debe crear borradores: `gestion-pedidos-docs/M01_CORTE_ETAPAS_1_2.md:13`.
- [verificado] Nuevo pedido abre «Seleccionar modalidad» sin aviso de borrador; Tienda, municipal o nacional abre el formulario directamente con Modalidad preseleccionada. El borrador previo se conserva: `Index.jsx`, `Partials/ModalSeleccionarModalidad.jsx` y `Partials/FormularioRegistrarTienda.jsx`.
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
- [verificado] El borrador nacional guarda líneas opcionales por SKU o evidencia con UUID y cantidad. Conserva el PDF general por separado. `GuardarMercanciaNacional` guarda las líneas, sus adjuntos y la revisión de la captura en el mismo guardado del borrador.
- [verificado] Después del cierre y con cobertura de mercancía, Ventas puede solicitar recolección en sucursal antes de la salida. `CambiarNacionalATienda` conserva modalidad y cargos previos. La confirmación externa de cancelación de una guía es un hecho separado.
- [verificado] Tras el cierre, la captura nacional permite cambiar la paquetería y registrar cotización con dirección verificada y flete de cada caja. `CambiarPaqueteriaNacional` aplica los importes con los servicios de costos existentes.
- [verificado] El alta nacional ofrece PAQ. PENDIENTE y comerciales activas con logística, incluso si `requiere_peso` es falso. Ambos recorridos consultan peso en CEDIS: `FormularioRegistrarNacional` y `SolicitarPesajePedidoBmaService`.

- Última verificación: 2026-11-23, árbol de trabajo sobre `1c0f841f`; entrada Nuevo pedido y tres modalidades comprobadas con transporte simulado, sin commit.
- Última verificación: 2026-11-23, árbol de trabajo sobre `94668a09`; anexo extraordinario comprobado en navegador (creación, etiqueta, mini consulta de CEDIS, dirección heredada), sin commit.
- Última verificación: 2026-11-21, árbol de trabajo sobre `c3e44924`; selector nacional comprobado con transporte simulado, sin commit.
- Última verificación: 2026-11-23, merge de anexo extraordinario (`94668a09`, navegador) y selector de modalidad (`1c0f841f`, transporte simulado).

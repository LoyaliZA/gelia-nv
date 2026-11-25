# Preparación Tienda

- Propósito: atender la instrucción vigente y registrar hechos físicos del auxiliar.
- Ubicación: `routes/web/modules/control-pedidos/tienda.php`; `app/Http/Controllers/ControlPedidos/PedidoBmaTiendaController.php`; `app/Support/ControlPedidos/InstruccionEntrega.php`.
- Frontend: `resources/js/Pages/ControlPedidos/Tienda/`; pruebas: `tests/Feature/ControlPedidos/{PreparacionTiendaWorkspaceTest,InstruccionEntregaVigenteTest,RecorridoTiendaIntegralTest}.php`.
- Esquema: `database/migrations/2026_10_02_180000_apartado_fisico_cumplimiento_tarea.php:21` separa cumplimiento e instrucción.

## Estado actual
- [verificado] Consulta aprobada, confirmación de Ventas y separación física son hechos distintos: `app/Services/ControlPedidos/ConfirmarPedidoTiendaService.php:17`.
- [verificado] Con Pago al recoger guardado, Ventas confirma una sola vez; reintentar no avisa ni renueva vigencia: `app/Services/ControlPedidos/ConfirmarPedidoTiendaService.php:29`.
- [verificado] Pago al recoger se cobra/remisiona en PDV sin exhibición GELIA; su seguimiento posterior en GELIA es opcional y viene apagado: `gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md:230`.
- [verificado] Municipales de PDV por empacar se mezclan en Pendientes; el folio y Ver abren el detalle; empacados pasan a Atendidos sin CEDIS ni custodia: `FilasConsultaMunicipalTienda.php`.

## Reglas que no se rompen
- [verificado] Bloquear pedido y luego instrucción dentro de transacción; rechazar sustituida/liberada/cancelada o más de una abierta: `app/Support/ControlPedidos/InstruccionEntrega.php:50`.
- [verificado] Responder la revisión exige auxiliar asignado y estado permitido: `app/Services/ControlPedidos/ResponderDisponibilidadInicialService.php:63`.
- [verificado] Apartado documental completo no exige transcribir las piezas correctas: `gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md:41`.
- [verificado] Si se habilita seguimiento PDV, entregar exige su confirmación de pago, remisión y foto; el endpoint también comprueba el switch: `app/Services/ControlPedidos/OperacionApartadoT01Service.php:29`.
- [verificado] El contrato SKU compromete existencia disponible y evita doble compromiso por clave: `app/Services/ControlPedidos/CompromisoInventarioTienda.php:23`.
- [verificado] Un retiro parcial libera solo su cantidad comprometida; no inventar inventario: `app/Services/ControlPedidos/CompromisoInventarioTienda.php:84`.
- [verificado] MD no tiene filtro especial de departamento; siguen restricciones piloto normales: `app/Services/ControlPedidos/PreparacionTiendaConfig.php:134` y `:155`.

## Dependencias con otros módulos
- [Consultas](CONSULTAS_DISPONIBILIDAD.md), [Custodia](TRANSFERENCIA_CUSTODIA.md), PDV, inventario y Traspasos.
- [verificado] Cada acción física conserva su permiso propio; ver la bandeja no habilita todas las operaciones: `routes/web/modules/control-pedidos/tienda.php:12`.

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): instrucción sustituida, doble toma, anexo/retiro y entrega con pendientes; contraste documental/SKU.

## Puntos blandos
- [verificado] Los comentarios antiguos de MD por departamento contradicen la implementación vigente que devuelve true: `app/Services/ControlPedidos/PreparacionTiendaConfig.php:117` y `:138`.
- [verificado] Horarios pueden estar pausados: confirmar fuera de jornada conserva fecha previa sin generar vigencia: `app/Support/ControlPedidos/PlazoApartadoTienda.php:40`.
- [verificado] Documento, visor, escaneo y galería deben mantenerse; no restaurar «Decisión del cliente»: `gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md:13` y `:40`.

- Última verificación: 2026-11-24, sin commit, árbol de trabajo (filas municipales de empaque).

# Glosario — Gestión de pedidos

- **CEDIS**: área de consulta/preparación y expedición; distinta de la sucursal de recolección. `gestion-pedidos-docs/TIENDA_T02_IMPLEMENTACION.md:1`.
- **PDV**: Punto de venta; participa en cobro presencial, resguardos y entrega. `README.md:47`; `AGENTS.md:17`.
- **Wizerp**: sistema externo al que refieren pedido comercial, remisión y resultado de crédito. `gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md:19` y `:80`.
- **SAF**: saldo a favor del cliente aplicado o reservado al pedido. `gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md:55` y `:56`.
- **Exhibición**: un ingreso capturado dentro de los pagos del pedido. `app/Services/SaldosAFavor/RegistrarPagoPedidoBmaService.php:141`.
- **Apartado**: reserva/separación de mercancía; su contrato puede ser documental o por inventario. [Preparación](modulos/PREPARACION_TIENDA.md).
- **Custodia**: conservación identificada de mercancía en sucursal. [Transferencia](modulos/TRANSFERENCIA_CUSTODIA.md).
- **Consignatario**: persona del expediente del cliente elegida para la solicitud municipal. `app/Services/ControlPedidos/Municipal/RegistrarSolicitudMunicipalService.php:99`.
- **Terminal destino**: transporte receptor del envío municipal, seleccionado del catálogo de paqueterías. [Municipal](modulos/ENVIO_MUNICIPAL.md).
- **Diferido**: transporte cuyo costo aún es desconocido. [Municipal](modulos/ENVIO_MUNICIPAL.md).
- **POR COBRAR**: condición operativa del transporte en terminal. [Municipal](modulos/ENVIO_MUNICIPAL.md).
- **Mismo día / MD**: intención de atención asociada a transferencia, con primer reporte y acuerdo de continuación. [Transferencia](modulos/TRANSFERENCIA_CUSTODIA.md).
- **T-01 / T-02 / T-03 / M-01 / P-01**: identificadores documentales internos de recorridos; consultar sus nombres en [Estado](ESTADO.md), no usarlos como rótulos de usuario.
- **Anexo / retiro**: petición documentada para agregar/quitar piezas; distinta de su ejecución física. [Consultas](modulos/CONSULTAS_DISPONIBILIDAD.md).
- **Carátula**: documento de identificación de la preparación, con generación y colocación separadas. `routes/web/modules/control-pedidos/tienda.php:18`.
- **Complemento**: pedido relacionado que puede compartir operación de empaque con su principal. `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php:43`.

# Transferencia y custodia

- Propósito: conservar procedencia, destino y custodia pagada hasta entrega o traslado autorizado.
- Ubicación: `app/Services/ControlPedidos/{TransferenciaTiendaService,MovimientosTiendaService,ResolverSegundoTrasladoTiendaService,SeguimientoRecoleccionT02Service}.php`; rutas en `core.php` y `tienda.php` del área.
- Frontend: `resources/js/Pages/ControlPedidos/Partials/{SeguimientoTransferenciaTienda,ModalMovimientosTienda}.jsx`.
- Pruebas: `tests/Feature/ControlPedidos/TransferenciaTiendaTest.php`; `Partials/{SeguimientoTransferenciaTienda,ModalMovimientosTienda}.test.jsx` junto al frontend.

## Estado actual
- [verificado] En transferencia ordinaria con consulta en Preparación Tienda, confirmar bolsas registra en la misma transacción la separación de los anexos documentales revisados y resueltos. La salida, recepción, custodia y entrega conservan su registro por etapa: `app/Services/ControlPedidos/TransferenciaTiendaService.php`.
- [verificado] La configuración operativa tiene horarios apagados por defecto; los valores persistidos determinan ejecución: `app/Services/ControlPedidos/ConfiguracionOperativaTienda.php:14` y `:26`.
- [verificado] Con horarios activos, MD usa días/corte configurados en zona operativa; apagados no exige corte: `app/Support/ControlPedidos/CorteMismoDiaTienda.php:36`.

## Reglas que no se rompen
- [verificado] MD conserva el primer reporte aceptado por servidor, también al sustituir comprobantes: `app/Services/ControlPedidos/AplicarCorteMismoDiaTienda.php:20`.
- [verificado] Emisión Wizerp inicia plazo de recolección y guarda días aplicables; sustituir PDF no reinicia fecha: `app/Services/ControlPedidos/GestionarRemisionPedidoBmaService.php:30`.
- [verificado] Vencimiento de recolección solo marca pendiente de gestión; conserva mercancía/pago: `app/Services/ControlPedidos/SeguimientoRecoleccionT02Service.php:31`.
- [verificado] Segundo traslado exige facultad específica de gerencia y recorrido; autorizar conserva ubicación física hasta salida: `app/Services/ControlPedidos/ResolverSegundoTrasladoTiendaService.php:22` y `:54`.
- [verificado] Salida requiere custodia PDV activa en destino y actor de origen; recepción corresponde al destino: `app/Services/ControlPedidos/MovimientosTiendaService.php:65`.
- [verificado] Movimientos comprueban versión bajo bloqueo: `app/Services/ControlPedidos/MovimientosTiendaService.php:77`.
- [verificado] La custodia nacional de sucursal recorre `pendiente_recepcion → recibido → en_custodia → entregado` en las bandejas PDV. Crear el resguardo no marca recepción. Origen nacional no abre bultos. La entrega exige foto. `cambio-tienda-nacional` solo solicita recolección; recepción, custodia y entrega no pasan por `control_pedidos.crear`: `app/Services/ControlPedidos/Nacional/EventosCustodiaSucursalNacional.php` y `PedidoBmaController::cambioTiendaNacional`.
- [verificado] El vínculo físico recorre `vinculado → en_traslado → recibido_cedis → integrado`. Recepción CEDIS no libera empaque; liberar integración es un hecho aparte: `app/Services/ControlPedidos/Nacional/CustodiaComplementoNacional.php`.

## Dependencias con otros módulos
- PDV Resguardos, sucursales/almacenes operables, [Auditoría](AUDITORIA_PAGOS.md), [Preparación](PREPARACION_TIENDA.md).
- [verificado] Continuar MD como recolección posterior exige acuerdo de Ventas; no reprogramar automáticamente: `gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md:31`.

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): origen/destino distintos, reenvío obsoleto, gerencia también vendedora, sucursal sin custodia y primer reporte inmutable.

## Puntos blandos
- [verificado] Cambio a Municipal/Nacional registra `pendiente_conexion`; no convierte modalidad ni cobra envío: `app/Services/ControlPedidos/MovimientosTiendaService.php:102`.
- [verificado] City Center no debe bloquearse por nombre; necesita custodia PDV como cualquier destino: `gestion-pedidos-docs/TIENDA_T03_IMPLEMENTACION.md:7`.
- [verificado] `InstruccionEntrega::assertSegundoTraslado` conserva una regla distinta y no tiene llamadas encontradas en app/; no conectarla como atajo: `app/Support/ControlPedidos/InstruccionEntrega.php:124`.
- [inferido] Usar ese helper reintroduciría veto a gerencia que vendió; por confirmar con prueba de actor dual antes de reutilizarlo: `app/Support/ControlPedidos/InstruccionEntrega.php:130`.

- Última verificación: 2026-11-26, árbol de trabajo sobre `64425d8f`, preparación de bolsas con anexos documentales resueltos.

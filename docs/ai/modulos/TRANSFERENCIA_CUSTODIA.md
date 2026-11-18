# Transferencia y custodia

- Propósito: conservar procedencia, destino y custodia pagada hasta entrega o traslado autorizado.
- Ubicación: `app/Services/ControlPedidos/{TransferenciaTiendaService,MovimientosTiendaService,ResolverSegundoTrasladoTiendaService,SeguimientoRecoleccionT02Service}.php`; rutas en `core.php` y `tienda.php` del área.
- Frontend: `resources/js/Pages/ControlPedidos/Partials/{SeguimientoTransferenciaTienda,ModalMovimientosTienda}.jsx`.
- Pruebas: `tests/Feature/ControlPedidos/TransferenciaTiendaTest.php`; `Partials/{SeguimientoTransferenciaTienda,ModalMovimientosTienda}.test.jsx` junto al frontend.

## Estado actual
- [verificado] Bolsas, salida, recepción, ubicación de custodia y entrega se registran separadas: `app/Services/ControlPedidos/TransferenciaTiendaService.php:169`.
- [verificado] La configuración operativa tiene horarios apagados por defecto; los valores persistidos determinan ejecución: `app/Services/ControlPedidos/ConfiguracionOperativaTienda.php:14` y `:26`.
- [verificado] Con horarios activos, MD usa días/corte configurados en zona operativa; apagados no exige corte: `app/Support/ControlPedidos/CorteMismoDiaTienda.php:36`.

## Reglas que no se rompen
- [verificado] MD conserva el primer reporte aceptado por servidor, también al sustituir comprobantes: `app/Services/ControlPedidos/AplicarCorteMismoDiaTienda.php:20`.
- [verificado] Emisión Wizerp inicia plazo de recolección y guarda días aplicables; sustituir PDF no reinicia fecha: `app/Services/ControlPedidos/GestionarRemisionPedidoBmaService.php:30`.
- [verificado] Vencimiento de recolección solo marca pendiente de gestión; conserva mercancía/pago: `app/Services/ControlPedidos/SeguimientoRecoleccionT02Service.php:31`.
- [verificado] Segundo traslado exige facultad específica de gerencia y recorrido; autorizar conserva ubicación física hasta salida: `app/Services/ControlPedidos/ResolverSegundoTrasladoTiendaService.php:22` y `:54`.
- [verificado] Salida requiere custodia PDV activa en destino y actor de origen; recepción corresponde al destino: `app/Services/ControlPedidos/MovimientosTiendaService.php:65`.
- [verificado] Movimientos comprueban versión bajo bloqueo: `app/Services/ControlPedidos/MovimientosTiendaService.php:77`.

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

- Última verificación: 2026-11-18, `develop` @ `c63dd84722c4bf6a318d233177d560aaa2b44f3d`; lectura estática.

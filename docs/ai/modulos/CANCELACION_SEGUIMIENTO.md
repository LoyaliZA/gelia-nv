# Cancelación y seguimiento

- Propósito: separar baja lógica, cancelación comercial, liberación física y resolución financiera.
- Ubicación: `app/Services/ControlPedidos/{RouterCancelacionPedidoBmaService,FinalizarCancelacionOperativaService,EliminarRegistroPedidoBmaService,EvaluarVencimientoApartadoFisicoService}.php`; `routes/console.php`.
- Frontend: `resources/js/Pages/ControlPedidos/{Plazos/Index.jsx,Partials/ModalCancelarPedido.jsx,Partials/ModalEliminarRegistroPedido.jsx}`.
- Pruebas: `tests/Unit/ControlPedidos/{ControlPedidosFase7Test,ControlPedidosEvaluarRetrasosTest}.php`; `tests/Feature/ControlPedidos/EliminarRegistroPedidoBmaTest.php`.

## Estado actual
- [verificado] Router elige cancelación inmediata u operativa según flag, actor habilitado y tareas: `app/Services/ControlPedidos/RouterCancelacionPedidoBmaService.php:21`.
- [verificado] Cancelación operativa solicitada no libera aún pagos/SAF; espera liberación por área: `app/Services/ControlPedidos/RouterCancelacionPedidoBmaService.php:86`.
- [verificado] Plazos tienen permiso de configuración propio: `routes/web/modules/control-pedidos/core.php:40`.

## Reglas que no se rompen
- [verificado] Finalización espera tareas resueltas y matriz financiera; excepción administrativa exige permiso: `app/Services/ControlPedidos/FinalizarCancelacionOperativaService.php:44`.
- [verificado] Evaluador físico omite custodia documental y mercancía pagada antes de aplicar vencimiento ordinario: `app/Services/ControlPedidos/EvaluarVencimientoApartadoFisicoService.php:45`.
- [verificado] Apartado documental impago vencido genera aviso/revisión física, sin liberación automática: `app/Services/ControlPedidos/EvaluarVencimientoApartadoFisicoService.php:51`.
- Custodia pagada y plazo desde remisión: [Transferencia](TRANSFERENCIA_CUSTODIA.md).

## Dependencias con otros módulos
- SAF, PDV, [Preparación](PREPARACION_TIENDA.md) y configuración operativa.
- [verificado] Scheduler ejecuta vencimientos y conciliación de traslados cada hora: `routes/console.php:55`.
- [verificado] Seguimiento de recolección entra por el evaluador físico: `app/Services/ControlPedidos/EvaluarVencimientoApartadoFisicoService.php:78`.

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): cancelación con físico pendiente, resolución financiera, horarios pausados y mercancía pagada vencida.

## Puntos blandos
- [verificado] «Eliminar registro» toma snapshot y hace soft delete, pero elimina archivos públicos; no equivale a cancelar: `app/Services/ControlPedidos/EliminarRegistroPedidoBmaService.php:24` y `:39`.
- [inferido] Restaurar fila no garantiza recuperar binarios eliminados; por confirmar recuperabilidad antes de prometer restauración completa: `app/Services/ControlPedidos/EliminarRegistroPedidoBmaService.php:40`.
- [verificado] Recordatorio de las 11:00 sigue registrado cuando se añade otra hora configurada: `routes/console.php:54` y `:78`.
- [inferido] Puede haber dos invocaciones diarias; comprobar deduplicación antes de cambiar horario: `routes/console.php:77`.

- Última verificación: 2026-11-18, `develop` @ `c63dd84722c4bf6a318d233177d560aaa2b44f3d`; lectura estática.

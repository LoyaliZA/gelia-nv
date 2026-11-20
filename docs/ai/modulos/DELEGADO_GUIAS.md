# Delegado y guías

- Propósito: registrar rastreo y soporte de guía, individualmente o por importación.
- Ubicación: `routes/web/modules/control-pedidos/delegado.php`; `app/Services/ControlPedidos/{AsignarGuiaPedidoBmaService,ActualizarGuiaPedidoBmaService,ImportarGuiasPedidoService,GestionarGuiaPdfPedidoBmaService}.php`.
- Frontend: `resources/js/Pages/ControlPedidos/Delegado/`; pruebas: `tests/Feature/ControlPedidos/BusquedaRemisionDelegadoTest.php` y `tests/Unit/ControlPedidos/{ControlPedidosFase22Test,ControlPedidosActualizarGuiasTest}.php`.

## Estado actual
- [verificado] Delegado tiene bandeja por permiso/fase, sin filtro de departamento: `app/Support/ControlPedidos/VisibilidadPedidoBma.php:20`.
- [verificado] Asignar guía a pedido aún sin empaque guarda rastreo sin avanzar fase: `app/Services/ControlPedidos/AsignarGuiaPedidoBmaService.php:95`.
- [verificado] Importación aplica el servicio individual; errores de una fila se cuentan como omitidos: `app/Services/ControlPedidos/ImportarGuiasPedidoService.php:60`.

## Reglas que no se rompen
- [verificado] Resguardo y PAQ. PENDIENTE no admiten guía hasta resolver sus condiciones: `app/Services/ControlPedidos/AsignarGuiaPedidoBmaService.php:31`.
- [verificado] En captura nacional, una caja sin peso no recibe guía y el crédito bloqueado no guía ni sale: `app/Support/ControlPedidos/Nacional/BarreraCreditoNacional.php`.
- [verificado] Impedir guía posterior a entrega al transporte: `app/Support/ControlPedidos/InstruccionEntrega.php:116`.
- [verificado] Solo cuando ya está empacado, asignar guía avanza a pendiente de envío: `app/Services/ControlPedidos/AsignarGuiaPedidoBmaService.php:47`.
- [verificado] Corregir rastreo pasa por la cola de errores; no limpiar todos los campos reportados: `app/Services/ControlPedidos/AsignarGuiaPedidoBmaService.php:54`.

## Dependencias con otros módulos
- [CEDIS](CEDIS_EMPAQUE.md) confirma recolección; [Direcciones](DIRECCIONES.md) invalida guía al cambiar destino.
- [verificado] Número de rastreo y PDF tienen endpoints separados: `routes/web/modules/control-pedidos/delegado.php:14`.

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): asignación antes/después de empacar, importación mixta y documentos de guía.

## Puntos blandos
- [verificado] El importador resuelve por ID_Pedido, no por folio Wizerp/remisión: `app/Services/ControlPedidos/ImportarGuiasPedidoService.php:80`.
- [inferido] Un archivo importado puede quedar aplicado parcialmente; revisar contadores/errores antes de reintentar el lote: `app/Services/ControlPedidos/ImportarGuiasPedidoService.php:27` y `:63`.
- [verificado] No confundir las fases de guía propia del cliente con la cola normal de Delegado: `app/Support/ControlPedidos/MaquinaEstadosPedidoBma.php:213`.

- Última verificación: 2026-11-20, working tree, sin commit.

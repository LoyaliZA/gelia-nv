# Direcciones de envío

- Propósito: seleccionar una dirección del cliente y conservar el destino histórico del pedido.
- Ubicación: `routes/web/modules/control-pedidos/direcciones.php`; `app/Http/Controllers/ControlPedidos/DireccionesAuxiliarController.php`; `app/Services/ControlPedidos/Direcciones/`.
- Frontend: `resources/js/Pages/ControlPedidos/Direcciones/` y `Partials/ModalCambiarDireccion.jsx`.
- Pruebas: `tests/Unit/ControlPedidos/Direcciones/SnapshotDireccionPedidoTest.php`; `tests/Feature/Clientes/Direcciones/`.
- Esquema: `database/migrations/2026_07_13_170000_create_pedido_bma_direcciones_snapshot.php`.

## Estado actual
- [verificado] Auxiliar usa permisos `clientes.direcciones.*`, sin necesitar acceso al módulo Clientes completo: `routes/web/modules/control-pedidos/direcciones.php:7`.
- [verificado] Snapshot conserva versión propia y proyecta el domicilio del pedido: `app/Services/ControlPedidos/Direcciones/CrearSnapshotDireccionPedido.php:35` y `:89`.
- [verificado] Se admite origen normalizado y legado; snapshot manual vigente puede conservarse: `app/Services/ControlPedidos/Direcciones/CrearSnapshotDireccionPedido.php:22` y `:73`.
- [verificado] Continuación nacional elige la dirección principal verificada, muestra nombre de quien recibe y domicilio, y permite alta adicional (N-07/N-09) sin mutar el catálogo al cotizar: `ContinuacionEnvioNacional.jsx`.
- [verificado] Un enlace público sin `accion_permitida` infiere primera dirección si el cliente no tiene activas, o adicional si ya las tiene: `ResolverAccionEnlaceDireccion.php`. El botón «Generar enlace» no envía acción; el servicio la rellena.

## Reglas que no se rompen
- [verificado] Cambio tras remisión o guía requiere permisos adicionales; enviado/entregado bloquea cambio: `app/Services/ControlPedidos/Direcciones/CambiarDireccionPedido.php:32`.
- [verificado] Cambiar dirección crea snapshot e invalida guía existente: `app/Services/ControlPedidos/Direcciones/CambiarDireccionPedido.php:51`.
- [verificado] Invalidación conserva el archivo, marca `activo=false` la guía del pedido y de las cajas no recolectadas, limpia su rastreo y deja la cancelación de paquetería como otro registro: `app/Services/ControlPedidos/Direcciones/InvalidarGuiaPorCambioDireccion.php`.

## Dependencias con otros módulos
- Clientes, solicitudes públicas y [Delegado](DELEGADO_GUIAS.md).
- Contratos extensos: [documentación de direcciones](../../../docs/control-pedidos/pase-3-impacto-direcciones.md).

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): snapshot, permiso tras remisión/guía, pedido enviado y solicitud pública.

## Puntos blandos
- [verificado] Marcar un PDF como `[INVALIDADA]` no elimina su fila ni su archivo: `app/Services/ControlPedidos/Direcciones/InvalidarGuiaPorCambioDireccion.php:30`.
- [verificado] La salida no acepta un PDF de guía si ninguno quedó activo: `app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php`.
- [verificado] La documentación describe flag para normalizadas; valor efectivo del despliegue queda por confirmar sin leer configuración operativa: `docs/control-pedidos/pase-3-cierre.md:8`.

- Última verificación: 2026-11-27, working tree, sin commit.

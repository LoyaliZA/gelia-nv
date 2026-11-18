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

## Reglas que no se rompen
- [verificado] Cambio tras remisión o guía requiere permisos adicionales; enviado/entregado bloquea cambio: `app/Services/ControlPedidos/Direcciones/CambiarDireccionPedido.php:32`.
- [verificado] Cambiar dirección crea snapshot e invalida guía existente: `app/Services/ControlPedidos/Direcciones/CambiarDireccionPedido.php:51`.
- [verificado] Invalidación conserva archivo histórico, limpia rastreo y solicita nueva guía: `app/Services/ControlPedidos/Direcciones/InvalidarGuiaPorCambioDireccion.php:28`.

## Dependencias con otros módulos
- Clientes, solicitudes públicas y [Delegado](DELEGADO_GUIAS.md).
- Contratos extensos: [documentación de direcciones](../../../docs/control-pedidos/pase-3-impacto-direcciones.md).

## Cómo probar
- [PRUEBAS](../PRUEBAS.md): snapshot, permiso tras remisión/guía, pedido enviado y solicitud pública.

## Puntos blandos
- [verificado] Marcar un PDF como `[INVALIDADA]` no elimina su fila ni su archivo: `app/Services/ControlPedidos/Direcciones/InvalidarGuiaPorCambioDireccion.php:30`.
- [inferido] Lectores que busquen cualquier PDF de guía pueden encontrar el histórico invalidado; por confirmar consumidores antes de reutilizar esa búsqueda: `app/Services/ControlPedidos/Direcciones/CambiarDireccionPedido.php:57`.
- [verificado] La documentación describe flag para normalizadas; valor efectivo del despliegue queda por confirmar sin leer configuración operativa: `docs/control-pedidos/pase-3-cierre.md:8`.

- Última verificación: 2026-11-18, `develop` @ `c63dd84722c4bf6a318d233177d560aaa2b44f3d`; lectura estática.

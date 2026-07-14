# Pase 3 — Informe de cierre

Fecha: 2026-07-13

## Entregado

- `pedido_bma_direcciones` + `pedidos_bma.cliente_direccion_id`
- Feature flag `CONTROL_PEDIDOS_DIRECCIONES_NORMALIZADAS` (`config/control_pedidos.php`)
- Snapshot en `EnviarPedidoBmaService` con proyección a `domicilio_entrega` / `codigo_postal`
- Selector en `ModalFormPedido` cuando flag ON; textarea legado cuando OFF
- `CambiarDireccionPedido` + invalidación de guía + ruta `control_pedidos.cambiar_direccion`
- Resguardo: aprobar no envía a CEDIS; liberar con pago+remisión sí; empacar bloqueado en resguardo
- Comandos `pedidos:diagnosticar-direcciones`, `pedidos:crear-snapshots-legado`, `pedidos:conciliar-direcciones`
- Permisos `control_pedidos.direccion.*`
- Análisis: `docs/control-pedidos/pase-3-impacto-direcciones.md`

## Rollback

`CONTROL_PEDIDOS_DIRECCIONES_NORMALIZADAS=false` — restaura UI/flujo legado sin borrar snapshots.

## Pruebas

`SnapshotDireccionPedidoTest` + `ControlPedidosFase2Test`: OK.

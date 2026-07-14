# Pase 3 — Impacto de direcciones en Pedidos BMA

Fecha: 2026-07-13

## Flujo actual confirmado

```text
BORRADOR → PENDIENTE_AUXILIAR → EN_CEDIS → PENDIENTE_DE_GUIA|PENDIENTE_DE_ENVIO → ENVIADO
```

Servicios clave: `EnviarPedidoBmaService`, `AprobarPedidoBmaService`, `LiberarResguardoPedidoBmaService`, `MarcarEmpacadoPedidoBmaService`.

## Cambios

1. Tabla `pedido_bma_direcciones` + FK `pedidos_bma.cliente_direccion_id`
2. Snapshot al enviar a auxiliar
3. Feature flag `CONTROL_PEDIDOS_DIRECCIONES_NORMALIZADAS`
4. Selector en `ModalFormPedido` cuando flag ON
5. Resguardo: aprobar no manda a CEDIS; liberar con pago+remisión sí lo hace; empacar bloquea resguardo
6. Permisos `control_pedidos.direccion.*`

## Rollout

- A: flag OFF, lectura/diagnóstico
- B: flag ON piloto (selector opcional vía config)
- C: exigir dirección en logística
- D: snapshot fuente oficial; texto legado proyección

## Rollback

Apagar `CONTROL_PEDIDOS_DIRECCIONES_NORMALIZADAS=false`. No borrar snapshots ni migraciones.

# MIG-03 · Payload de stock (contrato interno → MIG-04)

MIG-04 **no** debe enviar stock plano sin pasar por este contrato. Clase: `App\Services\Tiendanube\TiendanubeStockPayload`. MIG-03 valida y serializa; **no** llama a la API.

Entrada: [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md).

## Estructura

```json
{
  "store_id": 12345,
  "product_id": 100,
  "variant_id": 900,
  "location_id": "01GQ2ZHK064BQRHGDB7CCV0Y6N",
  "stock": 7,
  "stock_management": true,
  "mode": "set_level"
}
```

| Campo | Tipo | Notas |
|-------|------|-------|
| `store_id` | int | Tienda de `tiendanube_configuracion` |
| `product_id` | int | Producto remoto |
| `variant_id` | int | Variante remota, debe pertenecer al producto |
| `location_id` | string opaco | ID remoto exacto; obligatorio en `set_level` |
| `stock` | int o `null` | `null` = ilimitado explícito. Nunca omitir el campo en `set_level` |
| `stock_management` | bool | En `set_stock_management` es la intención explícita |
| `mode` | string | `set_level` \| `set_stock_management` |

## Validaciones previas a llamada remota

| Regla | Error local |
|-------|-------------|
| `location_id` existe en `tiendanube_ubicaciones` para `store_id` y `activa=true` | `ubicacion_invalida` |
| `variant_id` pertenece a `product_id` | `variante_invalida` |
| `locations_probe=ok` (capacidad de Location) | `inventario_no_disponible` si probe ≠ `ok` |
| Ubicación con `synced_at` y, si hay niveles previos, lectura de niveles de esa variante (`synced_at` no nulo) cuando `multi_inventario_activo` | `espejo_desactualizado` |
| Ubicación ajena (otra tienda o inactiva) | `ubicacion_invalida` (rechazar **antes** de HTTP) |
| `mode` desconocido | `modo_invalido` |

## Mapeo a API 2025-03

Documentado para MIG-04. No implementar estas llamadas en MIG-03.

| Intención interna | Endpoint / campo remoto | Pendiente ensayo |
|-------------------|-------------------------|------------------|
| `set_level` | `PUT /products/{id}/variants/{id}` body `{ inventory_levels: [{ location_id, stock }] }` | merge vs replace: la guía indica un nivel por request en etapas tempranas; no enviar `variant.stock` junto al nivel |
| Ajuste incremental | `POST /products/{id}/variants/stock` `{ action, value, location_id, id }` | `replace` vs `variation` |
| `set_stock_management` | `PUT` variante `{ stock_management }` **sin** `stock` plano | confirmar que no toca primera ubicación |

Compatibilidad deprecada (no usar): enviar solo `variant.stock` actualiza la primera ubicación.

**Usado por:** MIG-04 [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md), MIG-05 (jobs), MIG-06.

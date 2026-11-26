# MIG-04 · Payloads de escritura

Fecha: 11 de septiembre de 2026. Entradas: [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md), [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md), [MIG-03-payload-stock.md](./MIG-03-payload-stock.md), [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md).

Implementación: `TiendanubeProductoWriteService`, `TiendanubeStockPayload::toVariantApiPayload()`. Ensayo live contra tienda: **no ejecutado**. Contratos cubiertos con `Http::fake`.

**Usado por:** MIG-05, MIG-06, TN-07F (precios).

## Por operación

| Operación | Endpoint | Campos permitidos | Campos prohibidos | Idempotencia |
|-----------|----------|-------------------|-------------------|--------------|
| Crear producto | POST `/products` | `name` (LocalizedString), `description`, `brand`, `tags`, `video_url`, `seo_title`, `seo_description`, `free_shipping`, `requires_shipping`, `categories` (IDs, omitir si vacío), `variants[]` (`sku`, `price`, `promotional_price`, `cost`, `values`), `images[]` (`src`). Visibilidad: ver tabla abajo. Stock inicial: **no** en este POST si Location está disponible; PUT de nivel después. | `published` y `visibility` juntos. `variant.stock` si `locations_probe=ok` o `multi_inventario_activo`. Mapear stock total a la primera ubicación. | no (reconciliar; ver [MIG-04-reconciliacion-operaciones.md](./MIG-04-reconciliacion-operaciones.md)) |
| Actualizar producto | PUT `/products/{id}` | Parcial: mismos campos de producto. `categories: []` solo con `replace_categories`. | `published`+`visibility`. Precios/stock de variante. | sí |
| Actualizar variante (precios) | PUT `.../variants/{id}` | `sku`, `price`, `promotional_price`, `cost` según intención. Validar que la variante pertenece al producto. | `stock` plano si Location/multi-inv. `inventory_levels`. `stock_management` (va en PUT aparte). | sí |
| Stock por ubicación | PUT `.../variants/{id}` | Solo `TiendanubeStockPayload` → `toVariantApiPayload()`: `set_level` = `{ inventory_levels: [{ location_id, stock }] }` (`stock` siempre presente; `null` = ilimitado). `set_stock_management` = `{ stock_management }` sin `stock` ni niveles. Un nivel por request. | `variant.stock` junto a `inventory_levels`. Omitir `stock` en `set_level`. Usar total como primer nivel. `POST /products/{id}/variants/stock` (incremental; **no usado**). | sí (PUT) |
| Crear imagen | POST `.../images` | `src` **o** `attachment`+`filename`; `position` opcional | ambos `src` y `attachment` | no (reconciliar) |
| Borrar imagen | DELETE `.../images/{id}` | — | borrar antes de que el POST de reemplazo esté OK o reconciliado | sí |

### Visibilidad

| Versión configurada | Payload |
|---------------------|---------|
| `v1` | solo `published` bool |
| `2025-03` | `published: true` → `visibility: "visible"`; `published: false` → `visibility: "hidden"`; **nunca** ambos |

## Intenciones de precio/promoción/costo

| Intención | Payload | Verificado ensayo |
|-----------|---------|-------------------|
| Conservar | omitir clave | tests: PUT precio no incluye otras claves ni stock |
| Establecer | valor string (`"150.00"`) | test crear/actualizar precio |
| Eliminar promoción | `promotional_price: null` (JSON null, no `""` ni guion CSV) | test Feature |
| Eliminar costo | clave presente vacía/`null` → **omitir** (no se envía `cost: null`; la API de catálogo no replica el CSV) | documentado; no ensayo live |

`price` vacío/`null` en update: no se envía (no se usa para “borrar” precio).

## Inventario: merge vs replace

| Ensayo | Resultado |
|--------|-----------|
| PUT variante con un solo `inventory_levels[]` (ubicación A) | Test local: el body **solo** incluye A. No se reenvían otras ubicaciones. |
| Omitir otras ubicaciones conserva B | **Pendiente live.** No se asume merge ni replace. Código envía un nivel por request (guía multi-inventory). Si un ensayo live demuestra replace, bloquear la operación y no “rellenar” otras ubicaciones a ciegas. |
| Precio y stock | PUT de precios **sin** `stock` ni `inventory_levels`. PUT de nivel **sin** precios. |

## Creación simple con existencias

Si `locations_probe=ok` o multi-inventario: exigir `location_id` explícito; POST producto sin `variant.stock`; después PUT nivel. Si Location no está disponible: `variant.stock` plano legado (solo esa ruta).

## Pendientes no validados en tienda

- Semántica real merge/replace de `inventory_levels`.
- Quitar `cost` remoto con `null`.
- `POST .../variants/stock` (`replace` vs `variation`): no implementar en MIG-04.

# MIG-04 · Reconciliación de operaciones de escritura

Fecha: 11 de septiembre de 2026. Entrada: política de reintentos en [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md) (Idempotencia de transporte). Payloads: [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md).

El cliente **no** reintenta POST `/products`, `/products/*/images` ni `/webhooks` en timeout/conexión/5xx (solo 429). Esta fase reconcilia en el servicio de escritura **antes** de permitir un nuevo POST.

**Usado por:** MIG-05 (jobs), MIG-06.

## POST inciertos (timeout / respuesta ambigua)

| Operación | Acción tras timeout | Reconciliación | Prohibido |
|-----------|---------------------|----------------|-----------|
| POST `/products` | No repetir POST. Buscar por SKU: 1) espejo local `tiendanube_producto_variantes.sku`; 2) `GET /products?sku=` (`findProductsBySku`). Si hay coincidencia, `GET /products/{id}` + upsert. | Si no hay SKU o no aparece: error “creación incierta… no repetir POST”. | POST ciego / retry automático |
| POST `.../images` | GET producto; imágenes cuyo `id` no estaba en el conjunto previo. Match: `src` pedido, si no `position`, si no única candidata nueva. | Si no hay match: error; **no** DELETE de fotos anteriores. | duplicar imagen; borrar y reintentar |

`esPostIncierto`: `statusCode` 0 / `connection_failed`, o 502/503/504.

## Imágenes: cargar-antes-de-borrar

| Paso | Regla |
|------|-------|
| Reemplazar todas | POST (o reconciliar) **antes** de DELETE. IDs previos se capturan al inicio. DELETE solo de esos IDs, excluyendo el id nuevo. |
| Fallo de carga | Fotos anteriores intactas (remoto y espejo local). |
| Clave de solicitud | `sha256(product_id + src\|attachment_sha256 + position + reemplazar)`. Sirve para identificar el mismo reintento en logs/jobs MIG-05; la reconciliación operativa compara IDs/src/posición del GET, no persiste la clave en MIG-04. |
| URLs finales | POST puede devolver `/tmp/`; se resuelve con GET producto o heurística CDN (`-1024-1024`). |

## Fallos parciales (producto / variante)

PUT producto y PUT variante (precios vs inventario) son independientes. Si uno falla: `GET /products/{id}` + upsert del estado real, **luego** se relanza el error. No se informa éxito total. Vaciar categorías (`categories: []` + `replace_categories`) sigue la misma regla.

## Importación masiva / SKU

| Control | Conservado |
|---------|------------|
| Prevalidación | Nombre (`SKU.ext` / `SKU_n.ext`), tamaño &lt; 10 MB, MIME/extensión |
| Sin “primera coincidencia” ciega | Si `count(sku) > 1` → ítem `sku_ambiguo`, sin `producto_id`, sin HTTP |
| SKU inexistente | `sku_no_encontrado` (igual que antes) |
| Reemplazar primera del lote | `reemplazar` en `agregarImagen` ahora carga antes de borrar; un ítem `ok` previo del mismo producto no vuelve a reemplazar |

`resolverSku` del controlador sigue usando `orderBy('id')->first()` para la UI de una coincidencia; la importación masiva **no** usa esa vía cuando hay duplicados.

## Pendientes

- Ensayo live de timeout real (conexión a la tienda): no ejecutado.
- Persistencia de clave de solicitud / tabla de operaciones pendientes: MIG-05.

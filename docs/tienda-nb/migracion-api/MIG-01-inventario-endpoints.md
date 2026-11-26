# MIG-01 · Inventario HTTP Tiendanube

Fecha: 11 de septiembre de 2026. Alcance: checkout actual del módulo TiendaNube. Sin secretos.

Todas las llamadas a `api.tiendanube.com` del módulo pasan por `TiendanubeApiClient`. No hay `Http::` directo a la API en comandos, jobs ni scripts. `AuditarImagenesTiendanubeService` hace GET al CDN de cada `src` de imagen (fuera de este inventario).

Base URL efectiva: `{host}/{versión}/{store_id}` + ruta relativa. Default de versión en código: `v1` hasta MIG-06.

**Usado por:** MIG-02 (lectura catálogo), MIG-04 (escrituras), MIG-05 (jobs/webhooks), MIG-06 (evidencia). Matriz de compatibilidad: [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md).

## Endpoints del cliente

| Consumidor | Verbo | Ruta | Entrada | Respuesta | Permisos (doc) | Paginación | Efecto | Idempotente |
|---|---|---|---|---|---|---|---|---|
| `TiendanubeController::probarConexion` → `probeReadOnly` | GET | `/store` | — | objeto Store | `read_store` (o scope de tienda) | no | Actualiza `store_name` / `store_url` locales | sí |
| `TiendanubeCatalogoSyncService` | GET | `/categories` | `page`, `per_page` | lista Category | catálogo / `read_products` | `page`/`per_page` + `Link` | Upsert + prune categorías locales | sí |
| `ProcessTiendanubeWebhook` | GET | `/categories/{id}` | id evento | Category | idem | no | Upsert categoría | sí |
| `TiendanubeCatalogoSyncService` | GET | `/products` | `page`, `per_page` | lista Product (+ variants/images embebidos) | `read_products` | `page`/`per_page` + `Link` | Upsert + prune productos locales | sí |
| `ProcessTiendanubeWebhook`, `TiendanubeProductoWriteService` | GET | `/products/{id}` | id | Product | `read_products` | no | Upsert espejo | sí |
| `TiendanubeWebhookService` | GET | `/webhooks` | query opcional | lista Webhook | `write_webhooks` (lectura de suscripciones) | query; sin loop local | Listado UI / `asegurarRecomendados` | sí |
| `TiendanubeWebhookService` | POST | `/webhooks` | `{event, url}` | Webhook | `write_webhooks` | no | Crea suscripción remota | no (POST incierto) |
| `TiendanubeWebhookService` | PUT | `/webhooks/{id}` | `{event, url}` | Webhook | `write_webhooks` | no | Reemplaza suscripción | sí (PUT) |
| `TiendanubeWebhookService` | DELETE | `/webhooks/{id}` | id | 2xx vacío | `write_webhooks` | no | Elimina suscripción | sí |
| `TiendanubeProductoWriteService` | POST | `/products` | payload producto + variant/images | Product | `write_products` | no | Crea producto remoto + espejo | no (POST incierto) |
| `TiendanubeProductoWriteService` | PUT | `/products/{id}` | payload parcial | Product | `write_products` | no | Actualiza producto | sí |
| `TiendanubeProductoWriteService` | PUT | `/products/{productId}/variants/{variantId}` | sku/price/stock/cost | Variant | `write_products` | no | Actualiza variante | sí |
| `TiendanubeProductoWriteService` (y `TiendanubeImageImportService` indirecto) | POST | `/products/{id}/images` | `src` o `attachment`+`filename` | Product Image | `write_products` | no | Crea imagen remota | no (POST incierto) |
| `TiendanubeProductoWriteService` | DELETE | `/products/{productId}/images/{imageId}` | ids | 2xx | `write_products` | no | Borra imagen remota | sí |
| `probeReadOnly` (diagnóstico MIG-01) | GET | `/locations` | — | lista Location o error | `read_locations` | no | Solo diagnóstico; no persiste | sí |

## Métodos del cliente sin consumidor

| Método | Ruta | Nota |
|---|---|---|
| `getWebhook` | GET `/webhooks/{id}` | Sin callers |
| `paginateAll` | — | Helper legado; el sync usa `paginatePath` |

## Pruebas y fakes

Los tests Feature del módulo (`tests/Feature/Tiendanube/*`) usan `Http::fake` contra `api.tiendanube.com/{versión}/{store_id}/...`. No hay llamadas reales a la tienda en CI.

## Jobs / comandos

| Pieza | HTTP TN |
|---|---|
| `SyncTiendanubeCatalogoJob` | vía `TiendanubeCatalogoSyncService` |
| `ProcessTiendanubeWebhook` | GET producto/categoría |
| `ProcessTiendanubeImageImportJob` | vía write → POST imágenes |
| `tiendanube:auditar-imagenes` | no (CDN) |

## Fuera de alcance

Recursos API de pedidos, clientes, metafields, etc. No hay llamadas en este módulo.

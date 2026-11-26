# MIG-02 · Mapeo de lectura del catálogo

Fecha: 11 de septiembre de 2026. Checkout de referencia: `94730a221ad6efc175c67ff3317157078d450a74`. Destino: API REST 2025-03. Entrada: [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md), [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md). Inventario HTTP y transporte no se duplican aquí.

Consumidores auditados: `TiendanubeCatalogoSyncService`, `ProcessTiendanubeWebhook` (upsert), `TiendanubeController` (index, detalle JSON, búsqueda, reportes CSV), `TiendanubeImageImportService` (SKU), `AuditarImagenesTiendanubeService` (dimensiones locales, no sync). UI: `TablaProductos`, `ModalDetalleProducto`, `ModalEditarProducto`, `ModalCrearProducto`.

## Campos utilizados por el módulo

| Recurso | Campo API | Uso local (vista/sync/reporte) | Normalización | Notas 2025-03 |
|---------|-----------|-------------------------------|---------------|---------------|
| Category | `id` | PK local `tiendanube_categorias.id` | `(int)`; no regenerar | Identidad remota |
| Category | `name` | JSON; `nombreVisible()` en listados/modal | Guardar objeto LocalizedString crudo | i18n objeto |
| Category | `handle` | JSON espejo | crudo | |
| Category | `description` | JSON espejo | crudo | |
| Category | `parent` / `parent_id` | `parent_id` | `parent` si existe, si no `parent_id`; null permitido | Ambos aceptados |
| Category | `seo_title` | string 70; no buscado en index | `localizedToString` + `truncateSeo` 70 | LocalizedString o string |
| Category | `seo_description` | string 320 | `localizedToString` + truncate 320 | |
| Product | `id` | PK; búsqueda exacta en index | `(int)` | |
| Product | `name` | JSON; `nombreVisible()` (es → es_MX → first) | crudo | No aplanar |
| Product | `description` | JSON detalle | crudo | |
| Product | `handle` | JSON espejo | crudo | |
| Product | `brand` | string; búsqueda LIKE | `localizedToString` | |
| Product | `published` | bool UI Publicado/Oculto; filtro reportes | ver `resolvePublished` | Preferir `visibility` si viene |
| Product | `visibility` | no se persiste | `hidden` → published false; `visible`/`unlisted` → true | Sin columna nueva en MIG-02 |
| Product | `free_shipping` | espejo; default false si ausente | bool | |
| Product | `requires_shipping` | espejo | bool; **default true si la clave falta** | Distinto de “ausente = no tocar” |
| Product | `video_url` | string 2048 | localized + truncate 2048 | null vs omitido: clave ausente usa `?? null` en upsert de producto (campo escalar, no hijo) |
| Product | `seo_title` | string 70; búsqueda LIKE | localized + 70 | |
| Product | `seo_description` | string 320 | localized + 320 | |
| Product | `tags` | text; búsqueda LIKE | `localizedToString` | |
| Product | `attributes` | JSON detalle / multivariante | crudo | |
| Product | `canonical_url` | string 2048 | localized + 2048 | |
| Product | `images` | hijos `tiendanube_producto_imagenes` | snapshot: ver reglas | Clave ausente ≠ `[]` |
| Product | `variants` | hijos variantes; SKU principal | snapshot: ver reglas | Stock legado; niveles = MIG-03 |
| Product | `categories` | pivot `tiendanube_producto_categoria` | IDs o `{id}`; skip si categoría no existe local | Clave ausente no toca pivot |
| Product | — | `synced_at` | `now()` en cada upsert | Local |
| Product | `gelia_producto_id` | enlace interno | **no escrito por sync** | Preservar |
| Image | `id` | PK | `(int)` | |
| Image | `src` | miniatura, import, auditoría CDN | localized + truncate 2048 | |
| Image | `position` | orden HasMany | int, default 1 | |
| Image | `alt` | espejo | localized + truncate 512 | |
| Image | `width`/`height` | alertas (no sync) | no mapear desde GET products | Auditoría posterior |
| Variant | `id` | PK | `(int)` | |
| Variant | `sku` | búsqueda, import imágenes, reportes | `localizedToString` | |
| Variant | `price` | detalle / edición | `nullableDecimal` → decimal 12,2 | string API → float; no redondear extra |
| Variant | `promotional_price` | detalle | `nullableDecimal` | |
| Variant | `cost` | detalle | `nullableDecimal` | |
| Variant | `stock` | espejo legado | `""`/null/no numérico → null (ilimitado); **nunca 0 por ausencia** | Multi-inventory = MIG-03 |
| Variant | `stock_management` | espejo | bool, default false si ausente | |
| Variant | `values` | JSON opciones | crudo LocalizedString[] | |
| Variant | `barcode` | espejo | `localizedToString` | |
| Variant | `weight` | decimal 10,3 | `nullableDecimal` | |

`localizedToString`: null → null; scalar trim (vacío → null); mapa prefiere `es`, `es_MX`, `es_AR`, `pt`, `en`; luego primer valor no vacío (incluye anidado).

## Reglas de normalización

### Ausente vs null vs vacío

| Situación | Comportamiento |
|-----------|----------------|
| Clave `images` / `variants` / `categories` **ausente** | No modificar hijos ni pivot existentes (respuesta parcial / listado incompleto) |
| Clave presente con array `[]` | Reemplazo intencional: borrar hijos locales no listados |
| Clave presente con `null` u otro no-array | No modificar hijos (no es snapshot) |
| LocalizedString `null` en campo aplanado | Columna string = null |
| LocalizedString `""` o solo espacios | null |
| JSON `name`/`description`/`handle`/`attributes`/`values` con `null` | Guardar null |
| `stock` `""` o null | null (ilimitado). No convertir a 0. Diferencias de stock en ensayo → MIG-03 |
| `price`/`cost`/etc. `""` o no numérico | null |
| `published` ausente y `visibility` ausente | false |
| `requires_shipping` ausente | true (contrato histórico del módulo) |
| `free_shipping` ausente | false |

### `visibility` vs `published`

Lectura (sync/webhook): si `visibility` es string reconocida, manda sobre `published`. `hidden` → no publicado; `visible` y `unlisted` → publicado (la UI local sigue siendo binaria Publicado/Oculto). Si `visibility` falta o es desconocida, usar `published` bool. Escritura conjunta de ambos campos: no aplica en MIG-02 (ver matriz MIG-01 / MIG-04).

### Snapshot completo

Operaciones de sustitución de imágenes, variantes o categorías de un producto requieren la clave presente y un array. GET `/products/{id}` (webhook) se considera snapshot. GET `/products` de listado debe traer embeds; si la API omite un embed, no depurar esa relación.

Depuración de **colecciones** (productos/categorías huérfanos) solo si: (1) todas las páginas de esa colección se descargaron, (2) `config('tiendanube.sync_prune_enabled')` es true, (3) la lista de IDs vistos no está vacía. Un 404 de ruta (`TiendanubeApiRouteException`) no prueba ausencia de un producto. Un 404 JSON de recurso (`TiendanubeApiNotFoundException`) en webhook sí puede borrar ese id (sin cambio en MIG-02).

Lista de IDs vistos vacía tras paginación “completa” = anomalía o tienda vacía: **no** borrar el espejo local (evitar wipe). Usar wipe explícito si hace falta vaciar.

### Paginación

Sync usa `paginatePath` (header `Link` validado; fallback `page++`). Cuerpo JSON objeto (no lista) aborta la colección como incompleta. Fallo de página intermedia: upsert de lo ya visto, **sin** prune de esa colección, estado de log `parcial`.

## Columnas locales nuevas (migraciones aditivas)

| Tabla | Columna | Motivo |
|-------|---------|--------|

Ninguna en MIG-02. `visibility` se deriva a `published`. Inventario por ubicación no se modela aquí.

**Usado por:** MIG-03 (stock legado), MIG-04 (payloads de lectura post-escritura), MIG-06 (evidencia).

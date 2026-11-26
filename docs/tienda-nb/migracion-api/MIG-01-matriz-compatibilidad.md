# MIG-01 · Matriz de compatibilidad API 2025-03

Fuentes: [intro](https://tiendanube.github.io/api-documentation/intro), [resources](https://tiendanube.github.io/api-documentation/resources). Ensayo live contra tienda real: **no ejecutado** en esta entrega. Default de producción permanece en `v1` hasta MIG-06.

**Usado por:** MIG-02 (campos y normalización), MIG-03 (Location/stock), MIG-04 (payloads), MIG-06 (consolidación). Inventario HTTP: [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md).

## Transporte (aplica a todos)

| Tema | Contrato v1 usado hoy | Contrato 2025-03 (doc) | Delta comprobada (doc) | Pendiente de ensayo | Impacto local |
|---|---|---|---|---|---|
| URL | `https://api.tiendanube.com/v1/{store_id}/...` | `https://api.tiendanube.com/2025-03/{store_id}/...` | Versión en el path | URL real de la cuenta de staging | Config `api_host` + `api_version`; override `TIENDANUBE_API_BASE` |
| Auth | Header legado `Authentication: bearer` | `Authorization: Bearer {token}` | Doc 2025-03 muestra Authorization | Si la cuenta aún exige el header legado | Cliente envía `Authorization`; no se rotan credenciales |
| User-Agent | `Gelianv ({appId})` | `AppName (email o URL de contacto)` | 400 si falta UA | Contacto aceptado por TN | `TIENDANUBE_USER_AGENT` + `TIENDANUBE_USER_AGENT_CONTACT` |
| JSON | `Content-Type: application/json` | `application/json; charset=utf-8` | 415 sin Content-Type | charset | Laravel Http JSON |
| Rate limit | Reintento 429 genérico | Leaky bucket; headers `x-rate-limit-*` | Doc | Valores reales de la tienda | Reintento acotado (3) + sleep desde `x-rate-limit-reset` |
| Paginación | Heurística `count >= per_page` | `Link` + `x-total-count`; default 30 en products | Doc products: default 30 | Presencia de `Link` en v1 vs 2025-03 | `paginatePath` usa Link; fallback `page++` |
| Errores | `RuntimeException` | 400/402/415/422/429/5xx | 402 pago; 422 campos | Cuerpos reales | Excepciones tipadas |
| Host BR | no usado | `api.nuvemshop.com.br` | — | — | `api_host` configurable |

### Precedencia de URL

1. Si `TIENDANUBE_API_BASE` tiene valor → se usa como prefijo (sin `{store_id}`); se valida que no duplique versión ni use versión fuera de `{v1, 2025-03}`.
2. Si no → `{TIENDANUBE_API_HOST}/{TIENDANUBE_API_VERSION}/{store_id}`.
3. Default: host `https://api.tiendanube.com`, versión `v1`.

## Store

| Endpoint | v1 actual | 2025-03 | Delta doc | Pendiente ensayo | Código |
|---|---|---|---|---|---|
| GET `/store` | name localizado, domains | mismo recurso | campos extra posibles | forma exacta de `name` / dominio | `getStore`; probe; persiste name/url |

## Category

| Endpoint | v1 actual | 2025-03 | Delta doc | Pendiente ensayo | Código |
|---|---|---|---|---|---|
| GET `/categories` | lista + LocalizedString | i18n objeto siempre | — | `Link` | sync |
| GET `/categories/{id}` | objeto | idem | — | 404 JSON | webhook |

## Product

| Endpoint | v1 actual | 2025-03 | Delta doc | Pendiente ensayo | Código |
|---|---|---|---|---|---|
| GET `/products` | lista; `per_page` 50 | default 30; `visibility`; no mezclar `published`+`visibility` en filtros | `visibility`; default page size | multi-inventory en listado | sync; `per_page` config |
| GET `/products/{id}` | objeto con variants/images | + `visibility` | leer `visibility` si hace falta distinguir unlisted/hidden | payload real | webhook + write |
| POST `/products` | `published` bool | no enviar `published` y `visibility` juntos; `published:false` → hidden | 422 si ambos | create 2025-03 | WriteService solo envía `published` (compatible) |
| PUT `/products/{id}` | parcial | misma regla visibility | — | — | WriteService |

MIG-03 puede migrar a `visibility`. No se implementa en MIG-01.

## Product Variant

| Endpoint | v1 actual | 2025-03 | Delta doc | Pendiente ensayo | Código |
|---|---|---|---|---|---|
| PUT `.../variants/{id}` | `stock` plano, price, sku, cost | inventario por ubicación si multi-inventory activo | stock puede dejar de ser la fuente de verdad | ¿la tienda tiene multi-inventory? | WriteService; **no cambiar stock en MIG-01** |

Ver [inventario por ubicación](https://tiendanube.github.io/api-documentation/guides/multi-inventory/products).

## Product Image

| Endpoint | v1 actual | 2025-03 | Delta doc | Pendiente ensayo | Código |
|---|---|---|---|---|---|
| POST `.../images` | `src` o `attachment`+`filename` | mismo patrón | — | timeout/duplicados | POST **sin** reintento de conexión |
| DELETE `.../images/{id}` | 2xx | 2xx | — | — | write |

## Webhook

| Endpoint | v1 actual | 2025-03 | Delta doc | Pendiente ensayo | Código |
|---|---|---|---|---|---|
| CRUD `/webhooks` | event + url HTTPS | igual; Location events nuevos | eventos `location/*` existen | no registrar location en MIG-01 | WebhookService |
| Eventos locales | product/category/app | siguen válidos | — | — | `config/tiendanube.php` |

## Location (sin implementación comercial)

| Endpoint | Uso MIG-01 | Scopes | Pendiente | Código |
|---|---|---|---|---|
| GET `/locations` | probe diagnóstico | `read_locations` | 403 si la app no tiene el scope | `getLocations`; check `forbidden` no bloquea conexión de catálogo |
| GET `.../inventory-levels` | no | `read_locations` | MIG-02/03 | no |
| POST/PUT/PATCH/DELETE locations | no (TN-07) | `write_locations` | — | no |

Si faltan permisos de ubicaciones: solicitar `read_locations` (y `write_locations` solo cuando MIG posterior lo necesite). No rotar token ni cambiar scopes en BD por esta tarea.

## Idempotencia de transporte (cliente)

| Verbo / ruta | Reintento automático |
|---|---|
| GET/HEAD | 429, 502, 503, 504, error de conexión (máx. 3) |
| PUT/DELETE | 429, 502, 503, 504 (máx. 3); no conexión duplicada de POST |
| POST `/products`, `/products/*/images`, `/webhooks` | solo 429; **no** timeout/conexión/5xx |

## Pendientes críticos no validados en tienda

- Header Authorization aceptado por la cuenta real (vs Authentication legado).
- Presencia y formato de `Link` en listados v1 y 2025-03.
- Multi-inventory / stock por location en la tienda de producción.
- Scope `read_locations` en el token actual.
- Fecha de apagado de v1: no acreditada.

# MIG-03 · Modelo de ubicaciones

Fecha: 11 de septiembre de 2026. Checkout: implementar sobre MIG-01/02. Destino: API REST 2025-03.

Entradas: [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md) (Location), [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md) (stock legado).

**Usado por:** MIG-04 [MIG-03-payload-stock.md](./MIG-03-payload-stock.md), MIG-06.

## Capacidad de la tienda (ensayo)

| Check | Resultado | Fecha | Notas |
|-------|-----------|-------|-------|
| `read_locations` | pendiente | — | Se persiste en `tiendanube_configuracion.locations_probe` al usar «Probar conexión» (`ok` / `forbidden` / `unavailable` / `error`). No hay ensayo live en esta entrega. |
| Multi-inventory activo | pendiente | — | Se marca `multi_inventario_activo=true` de forma aditiva cuando el sync ve `inventory_levels` o más de una ubicación activa. No se asume por cambiar `api_version`. |

Si `locations_probe` no es `ok`, la escritura de stock permanece bloqueada (no se finge compatibilidad plana).

## Esquema local

Migración aditiva: `database/migrations/2026_09_11_120000_add_tiendanube_inventario_ubicaciones.php`. No modifica PKs de productos/variantes.

| Tabla | Clave | Campos | Unicidad |
|-------|-------|--------|----------|
| `tiendanube_ubicaciones` | `id` string opaco (PK remota) | `store_id`, `name` JSON, `is_default`, `priority`, `tags` JSON, `activa`, `remote_updated_at`, `synced_at` | PK = ID remoto exacto. Índice `(store_id, activa)` |
| `tiendanube_variante_niveles` | `id` autoincrement | `variante_id` FK cascade, `ubicacion_id` FK restrict, `stock` nullable, `synced_at` | `unique(variante_id, ubicacion_id)` |
| `tiendanube_configuracion` | (existente) | `locations_probe` string nullable, `multi_inventario_activo` bool nullable | — |
| `tiendanube_producto_variantes.stock` | (existente) | espejo legado `variant.stock` | no se usa como fuente de verdad si hay niveles |

`location_id` / `ubicaciones.id` es texto; se conserva el ULID/string remoto sin castearlo a entero.

No hay FK ni enlace a almacenes Gelia.

### Retiro / inactivación

- Snapshot completo de `GET /locations` (lista descargada sin error): ubicaciones locales no vistas → `activa=false`. No se borran filas ni niveles.
- Snapshot incompleto o 401/403/404/error: no inactivar.
- Niveles: solo se recortan cuando la variante trae la clave `inventory_levels` como array (regla snapshot MIG-02). Clave ausente → no tocar niveles. Ausencia de un nivel en snapshot completo sí borra esa fila de nivel (la ubicación puede seguir inactiva con historial de la tabla de ubicaciones).

No sumar niveles de snapshots de fechas distintas: `stock_total` se calcula solo sobre el conjunto local vigente de esa variante.

## Semántica de stock

| Concepto | Definición | Origen |
|----------|------------|--------|
| Nivel | Stock de una variante en una `location_id`. `null` = ilimitado explícito en ese nivel | `variant.inventory_levels[]` |
| `stock_total` | Suma de niveles vigentes **solo si** hay al menos un nivel. Si algún nivel es `null` (ilimitado), el total no es un entero (`ilimitado=true`) | derivado local |
| Nivel ausente | ≠ cero. Sin filas de nivel no se inventa 0 ni se reparte el legado | clave `inventory_levels` ausente o ubicación sin fila |
| Stock legado (`variant.stock`) | Referencia de transición. Con multi-inventory la API puede devolver el total remoto; no se escribe de vuelta como stock plano | MIG-02 mapeo; columna `stock` |
| `stock_management` | Control de stock de la variante. Cambio requiere intención explícita (UI). Escritura remota: MIG-04 | espejo variante |

## Lecturas/escrituras de `.stock` en código

| Archivo | Operación | Campo | Tratamiento MIG-03 |
|---------|-----------|-------|-------------------|
| `TiendanubeCatalogoSyncService::syncVariantes` | lectura API → columna | `variant.stock` legado | Sigue `normalizeStock`; no convierte ausencia a 0. Niveles aparte |
| `TiendanubeCatalogoSyncService::syncNivelesInventario` | lectura API → niveles | `inventory_levels[].stock` | Snapshot; skip `location_id` desconocido |
| `TiendanubeProductoVariante::stockResumen` | lectura local | total / legado | Total desde niveles si existen; legado solo como referencia |
| `TiendanubeProductoWriteService::crear/actualizar` | escritura remota | `variant.stock` plano | **Bloqueada** si `multi_inventario_activo` o `locations_probe=ok`. No enviar total a la primera ubicación |
| `TiendanubeStockPayload` | contrato interno | `stock` + `location_id` | Validación local; sin HTTP. MIG-04 enviará `inventory_levels` |
| `ModalDetalleProducto.jsx` | UI lectura | `stock_resumen` | Total + desglose; `∞` explícito; no `stock ?? 0` |
| `ModalEditarProducto.jsx` / `ModalCrearProducto.jsx` | UI edición | stock por ubicación | Controles visibles, deshabilitados; no envían `stock` al API |
| `TiendanubeCatalogoSyncTest` / fixtures | tests | legado `""` y niveles A=3 B=7 | Ausencia ≠ 0; IDs opacos |

**Usado por:** MIG-04 [MIG-03-payload-stock.md](./MIG-03-payload-stock.md), MIG-06.

# MIG-02 · Comparación de sincronización

Ensayo con fixtures (Http fake, API `2025-03`). **No se ejecutó sync contra la tienda real** en este checkout: `APP_ENV=production` en el host del workspace; MIG-02 prohíbe escrituras/sync de prueba sobre catálogo real. Repetir esta plantilla en staging con probe previo y `TIENDANUBE_SYNC_PRUNE_ENABLED=false`.

Entrada: fixtures en [MIG-02-fixtures-catalogo.md](./MIG-02-fixtures-catalogo.md).

## Plantilla por corrida

| Fecha | API version | Store ID (últimos 4) | Sync log ID | Resultado |
|-------|-------------|----------------------|-------------|-----------|
| 2026-09-11 | 2025-03 | 4291 (fixture `8004291`, no tienda real) | log de test in-memory | ok |
| _pendiente staging live_ | 2025-03 | | | |

## Conteos

Corrida de test `test_sync_fixtures_multi_i18n_nulls_y_arbol` (fixtures cat-arbol + prod-multi + prod-i18n + prod-nulls). Antes = vacío en sqlite `:memory:`.

| Métrica | Antes | Después | Delta | OK |
|---------|-------|---------|-------|-----|
| Productos locales | 0 | 3 | +3 | sí |
| Categorías locales | 0 | 2 | +2 | sí |
| Variantes | 0 | 5 (3+1+1) | +5 | sí |
| Imágenes | 0 | 3 (solo prod-multi; i18n omite clave) | +3 | sí |

`prod-simple` cubierto en `test_upsert_categoria_producto_imagen_desde_payload_api` (stock `""` → null). Prune: solo con `TIENDANUBE_SYNC_PRUNE_ENABLED=true` (`test_sync_elimina_productos_y_categorias_huerfanos_si_prune_habilitado`). Default config `false`.

## Muestra de IDs (máx. 10)

| Tipo | IDs verificados | Notas |
|------|-----------------|-------|
| Categoría | 10, 20 | parent_id de 20 = 10 |
| Producto | 100, 200, 300, 400 | 100 simple; 400 `visibility: hidden` → no publicado |
| Variante | 900, 901–903, 910, 920 | precios decimal; 900 stock ilimitado |
| Imagen | 500, 501–503 | 777 conservada si falta clave `images` |

## Alcance parcial / fallos

| Colección | Página fallida | Datos conservados | Acción |
|-----------|----------------|-------------------|--------|
| products | página 2 HTTP 503 | producto 100 upsert; huérfanos 999/888 intactos; `eliminados_*=0` | estado `parcial`; prune no corre (`TiendanubeCatalogoSyncProtegidoTest`) |
| products+categories | lista vacía + prune on | espejo local intacto (IDs vistos vacíos) | no wipe |

Stock por ubicación no comparado (MIG-03). No se convirtieron stocks a cero.

Procedimiento staging live (cuando exista entorno de ensayo): probe `store`+`categories`; conteos SQL antes; sync con prune off; rellenar fila 2; muestra de ≤10 IDs reales **anonimizados** (últimos 4 del store).

**Usado por:** MIG-06 [MIG-06-evidencia-integracion.md](./MIG-06-evidencia-integracion.md).

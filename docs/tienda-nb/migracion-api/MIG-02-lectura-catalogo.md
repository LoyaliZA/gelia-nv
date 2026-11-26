# MIG-02 · Lectura y sincronización del catálogo

Fecha: 11 de septiembre de 2026. Proyecto: gelia-nv / módulo TiendaNube.
Destino: API REST 2025-03. Referencia de código previa: cliente y configuración leídos en `main` durante esta conversación; Cursor debe fijar el SHA actual antes de editar. No hay prueba contra la tienda ni implementación realizada en este documento.

## Alcance de esta tarea

Ejecutar solo MIG-02, en una rama/revisión acotada. Leer instrucciones del repositorio. No incorporar automáticamente pasos posteriores ni reconstruir todo el módulo. Mantener las mejoras TN-01 a TN-06. Toda migración de datos es aditiva y preserva identidad/historial.

## Objetivo y dependencia

Requiere MIG-01. Adaptar lectura de productos, variantes, categorías e imágenes al contrato 2025-03 preservando IDs y relaciones existentes. Esta entrega prepara sincronización; inventario detallado por ubicación se completa en MIG-03.

## Artefactos

### Entradas (MIG-01)

| Archivo | Uso en MIG-02 |
|---------|----------------|
| [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) | Endpoints GET `/categories`, `/products`, `/products/{id}`, paginación `Link`, consumidores de sync y webhook |
| [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md) | Deltas Store, Category, Product, Variant, Image; campo `visibility`; default `per_page`; pendientes de ensayo |

Consultar estas entradas antes de enumerar campos o adaptar paginación. No repetir inventario HTTP ni matriz de transporte aquí.

### Entregables de esta fase

| Archivo | Contenido |
|---------|-----------|
| [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md) | Campos usados por vistas/búsquedas/reportes/sync; normalización respuesta externa → esquema local; ausente vs null/vacío |
| [MIG-02-fixtures-catalogo.md](./MIG-02-fixtures-catalogo.md) | Fixtures: producto simple, multivariante, i18n, categorías, imágenes, valores nulos; sin datos reales de tienda |
| [MIG-02-comparacion-sync.md](./MIG-02-comparacion-sync.md) | Plantilla y evidencia de comparación before/after (conteos, IDs, relaciones) en ensayo |

MIG-03 y MIG-04 consultan [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md) para stock legado vs niveles y payloads de lectura.

## Pasos

1. Enumerar cada campo utilizado por vistas, búsquedas, reportes y escrituras; registrar en [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md). Crear fixtures en [MIG-02-fixtures-catalogo.md](./MIG-02-fixtures-catalogo.md): producto simple/multivariante, idiomas, categorías, imágenes y valores nulos. Cruzar con secciones Category/Product/Variant/Image de [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md). No afirmar que todas las respuestas son completas si el endpoint admite selección de campos.
2. Documentar normalización explícita entre respuesta externa y esquema local en [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md). Distinguir ausente de null/vacío. No borrar imágenes/variantes/categorías porque un campo no llegó en una respuesta parcial. Operaciones de sustitución requieren snapshot completo identificado.
3. Mantener IDs remotos y enlaces internos; no recrear catálogo ni limpiar tablas para migrar. Revisar tamaños y tipos de columnas, precisión decimal y URLs. Añadir migraciones aditivas solo cuando haya un dato necesario que no pueda representarse; documentar columnas nuevas en [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md).
4. Adaptar paginación y marcadores de recursos vistos al cliente MIG-01 según [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) (`paginatePath`, header `Link`). Marcar completa la descarga únicamente tras todas las páginas válidas. Si falla una colección, conservar datos anteriores y registrar alcance parcial en [MIG-02-comparacion-sync.md](./MIG-02-comparacion-sync.md).
5. Conservar depuración protegida. Deshabilitarla durante primera comparación de versiones y exigir validación de conectividad/contrato antes de interpretar ausencia. Confirmar que la ruta consultada representa el recurso correcto (ver `TiendanubeApiRouteException` vs `TiendanubeApiNotFoundException` en matriz MIG-01); no eliminar por un 404 general de la API.
6. Sincronizar primero en copia/entorno de ensayo y comparar conteos, IDs, nombres, variantes, precios, promociones, costos y relaciones; registrar resultados en [MIG-02-comparacion-sync.md](./MIG-02-comparacion-sync.md). Diferencias de stock se revisan con MIG-03, no se convierten en ceros.
7. Adaptar detalle, búsqueda, alertas y reportes para nueva normalización documentada en [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md); preservar el contrato de UI cuando sea correcto. Evitar una duplicación de catálogo “v1” y “2025-03” permanente.

## Archivos iniciales

`TiendanubeCatalogoSyncService`, `TiendanubeCatalogoPruneService`, `TiendanubeSyncRecursoVistoService`, modelos Tiendanube y `SyncTiendanubeCatalogoJob`. Verificar consumidor `TiendanubeController` y tests `TiendanubeCatalogoSyncTest` / `TiendanubeCatalogoSyncProtegidoTest`.

## Aceptación

- [x] [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md) cubre todos los campos usados y reglas de normalización.
- [x] IDs y enlaces internos preservados, sin wipe de migración.
- [x] Respuesta incompleta no elimina relaciones.
- [x] Moneda/importes y nulls conservados sin pérdida de precisión introducida.
- [x] Fallo de página intermedia no ejecuta depuración.
- [x] [MIG-02-comparacion-sync.md](./MIG-02-comparacion-sync.md) documenta comparación before/after con fixtures de [MIG-02-fixtures-catalogo.md](./MIG-02-fixtures-catalogo.md). (Live staging pendiente: este host es producción.)
- [x] Búsquedas/reportes existentes siguen funcionando.

## Entrega y reversión

Entregar [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md), [MIG-02-fixtures-catalogo.md](./MIG-02-fixtures-catalogo.md) y [MIG-02-comparacion-sync.md](./MIG-02-comparacion-sync.md) a MIG-03/04/05. Reversión conserva nuevas columnas y snapshots; detener sync problemático en lugar de purgar catálogo. No declarar toda la migración terminada por poder listar productos.

## Fuentes y verificación

- [Recursos oficiales 2025-03](https://tiendanube.github.io/api-documentation/resources).
- [Introducción y transporte](https://tiendanube.github.io/api-documentation/intro).
- [Ubicaciones](https://tiendanube.github.io/api-documentation/resources/location).
- [Variantes](https://tiendanube.github.io/api-documentation/resources/product-variant).
- [Inventario por ubicación](https://tiendanube.github.io/api-documentation/guides/multi-inventory/products).

Consultar la referencia específica de cada endpoint y la capacidad real de la tienda al implementar. No asumir que disponibilidad documental equivale a permiso/capacidad de la cuenta. No está acreditada aquí una fecha de apagado de v1.

## Instrucción para Cursor

> Implementa exclusivamente MIG-02, comprobando sus dependencias en el checkout actual y usando documentación oficial 2025-03. **Lee primero** [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) y [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md). Crea o actualiza los entregables listados en **Artefactos**; no dupliques inventario HTTP ni matriz de transporte. Antes de modificar código, concreta contratos y archivos afectados. Conserva las mejoras existentes; no añadas funcionalidades comerciales de TN-07 ni otros recursos API ajenos al catálogo. Reporta evidencia por criterio de aceptación, pruebas realmente ejecutadas, migraciones, compatibilidad de jobs y pendientes. No cierres como completo un punto crítico no validado. Una tarea de implementación no autoriza por sí sola despliegue o escrituras de prueba sobre productos reales.

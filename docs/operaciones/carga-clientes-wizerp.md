# Carga masiva de clientes (Wizerp)

## Cuándo ejecutar

- Exportar el CSV desde Wizerp e importarlo en **Admin → Sistema de Clientes → Carga Masiva**.
- **Recomendado:** completar la importación **antes de las 09:00** (hora del servidor).

## Conflicto con pagos vencidos (09:00)

El comando `pagos:rechazar-vencidos` (programado a las 09:00 en `routes/console.php`) resta montos de clientes por solicitudes de pago vencidas.

Durante una importación activa:

- Se activa la bandera de caché `import_clientes_en_curso`.
- Si el scheduler corre a las 09:00 mientras la importación sigue en curso, **el rechazo de pagos se omite** ese día para evitar condiciones de carrera sobre `monto_venta_actual`.

## Reglas de `codigo_lista`

| Situación en el CSV | Efecto |
|---------------------|--------|
| **Sin columna** `codigo_lista` | Solo se actualiza `monto_venta_actual`. La lista no cambia (el monto solo puede ascender la lista, nunca degradarla). |
| Columna presente y celda **vacía** | Cliente **inactivo** (`es_inactivo = true`). Lista técnica = Público General. Sigue visible en búsquedas y solicitudes. |
| Columna con valor `pg` | Activo en Público General (`es_inactivo = false`). |
| Columna con `1`, `2`, `3`, etc. | Activo con la lista del mapa Wizerp correspondiente. |

## Buenas prácticas

1. No cerrar la pestaña del navegador hasta que termine la sincronización.
2. No subir el mismo archivo dos veces en paralelo.
3. Revisar el reporte de ascensos y el mensaje de clientes marcados inactivos al finalizar.
4. Consultar `storage/logs/laravel.log` para métricas (`Importacion clientes Wizerp finalizada`).

## Cabeceras CSV soportadas

`numero_cliente` (requerido), `nombre`, `codigo_lista`, `monto_venta_actual`, `vendedor_id`, `es_heredado`.

Alias aceptados: `numero_cli` → `numero_cliente`, `monto_venta_actua` → `monto_venta_actual`.

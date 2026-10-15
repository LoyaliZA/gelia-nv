# Checkpoint A1 — Validación y corrección de vencimientos PDV

Fecha: 14 de octubre de 2026.

## Resultado

Problema confirmado mediante pruebas de regresión. Las consultas de reportes omitían
`custodia_confirmada_at` al cargar modelos para clasificar o filtrar por antigüedad.
El evaluador operativo requiere esa fecha: sin el atributo cargado, no clasifica el
resguardo como próximo a vencer ni vencido, aunque la fecha exista en la base de datos.

La corrección de producción consiste exclusivamente en agregar esa columna a dos
selecciones de `AplicarFiltrosReporteResguardoPdvQuery`:

- `contarClasificaciones`: corrige los conteos R-05 y R-06.
- `restringirPorAntiguedad`: corrige la selección de resguardos mediante los filtros
  `proximo_a_vencer` y `vencido`.

Los consumidores del mismo cálculo, incluidas las exportaciones de métricas,
reciben la clasificación corregida. No se cambió el formato de exportación.

## Comportamiento esperado y casos afectados

Se conserva íntegro `CalcularAntiguedadOperativaResguardoPdvService`:

- La custodia se evalúa únicamente en estado `en_custodia`, con confirmación de
  custodia y sin entrega completada ni devolución confirmada.
- El ancla del plazo es `custodia_confirmada_at`, no `recepcion_fisica_at`.
- Los días, ventana de aviso y zona horaria proceden de la configuración vigente.
- Hasta el fin del día límite inclusive puede corresponder la clasificación
  `proximo_a_vencer`; `vencido` requiere que el corte sea posterior al límite.
- Sin confirmación de custodia no se inventa una fecha ni se sustituye por recepción.

Las pruebas del reporte usan la configuración existente de 15 días hábiles,
lunes a viernes, aviso de 3 días y zona `America/Mexico_City`.
Con corte al 7 de septiembre de 2026 a las 12:00:

| Caso sintético | Confirmación de custodia | Resultado esperado |
| --- | --- | --- |
| Próximo a vencer | 20 de agosto | Próximo a vencer; límite 10 de septiembre |
| Vencido | 3 de agosto | Vencido; límite 24 de agosto |
| Vigente | 7 de septiembre | Ninguna de las dos clasificaciones |
| Sin confirmación | Nula | Ninguna de las dos clasificaciones |
| Entregado o devuelto | 3 de agosto | Excluido de ambas clasificaciones |
| Otra sucursal | 3 de agosto | Excluido al consultar la sucursal seleccionada |

La recepción física de estos casos se fija antes de la confirmación, el 1 de julio,
para detectar también una sustitución incorrecta del ancla. Los filtros comprueban
los identificadores seleccionados, además de los valores de las métricas.

Otro caso prueba el límite del 24 de agosto a las `23:59:59.999999` y el inicio del
25 de agosto: debe pasar de próximo a vencer a vencido, sin cambiar la regla.

## Evidencia automatizada

1. **Línea base:** 12 pruebas, 27 aserciones y 2 fallos preexistentes. Dos pruebas
   antiguas del evaluador creaban resguardos sin confirmación de custodia y esperaban
   clasificación usando solo recepción. Se actualizaron sus datos al contrato vigente.
2. **Reproducción antes de corregir producción:** los 4 casos nuevos fallaron
   (6 aserciones). R-05/R-06 devolvían `0/0` en lugar de `1/1`; ambos filtros
   devolvían listas vacías; la clasificación al límite también fallaba.
   En esa misma ejecución, el evaluador con modelos completos sí reconoció ambos casos.
3. **Después de las dos selecciones corregidas:** **16 pruebas, 40 aserciones,
   todas satisfactorias**, en 18.554 segundos. Se ejecutaron exclusivamente:
   - `tests/Unit/PuntoVenta/CalcularAntiguedadOperativaResguardoPdvTest.php`
   - `tests/Unit/PuntoVenta/Reportes/CalcularMetricasReporteResguardoPdvTest.php`

El PHP del equipo (8.2.32) no satisface el requisito del proyecto/PHPUnit. Las pruebas
se ejecutaron con PHP 8.4.25 y PHPUnit 12.5.25 en un contenedor temporal de la imagen
local `sail-8.4/app:latest`, sin red, con el repositorio montado en solo lectura,
almacenamiento temporal y SQLite `:memory:`. No se usaron servicios ni datos productivos.
Se evitó la configuración cacheada del entorno y se desactivó la escritura de resultados
de PHPUnit. No se modificaron esquemas persistentes; las migraciones del entorno de
pruebas operaron exclusivamente sobre la base efímera en memoria.

Comando de verificación final, ejecutado desde el equipo:

```bash
docker run --rm --network none --entrypoint /bin/sh \
  --mount type=bind,source=/home/ti-admin/gelianv,target=/var/www/html,readonly \
  --tmpfs /var/www/html/storage:mode=1777 --tmpfs /tmp:mode=1777 \
  -w /var/www/html \
  -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e APP_CONFIG_CACHE=/tmp/gelia-a1-config.php \
  -e APP_EVENTS_CACHE=/tmp/gelia-a1-events.php \
  -e APP_ROUTES_CACHE=/tmp/gelia-a1-routes.php \
  sail-8.4/app:latest -c 'mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
exec php vendor/bin/phpunit --do-not-cache-result tests/Unit/PuntoVenta/CalcularAntiguedadOperativaResguardoPdvTest.php tests/Unit/PuntoVenta/Reportes/CalcularMetricasReporteResguardoPdvTest.php'
```

Para reproducir únicamente los 4 casos nuevos, usar la clase del reporte y el filtro
`--filter 'test_(clasificaciones_cargan|filtros_de_custodia|clasificacion_de_custodia)'`.

## Archivos modificados

| Archivo | Cambio |
| --- | --- |
| `app/Services/PuntoVenta/Reportes/Resguardos/AplicarFiltrosReporteResguardoPdvQuery.php` | Agregar `custodia_confirmada_at` en dos selecciones; dos líneas de producción |
| `tests/Unit/PuntoVenta/Reportes/CalcularMetricasReporteResguardoPdvTest.php` | Regresión de conteos, ambos filtros, exclusiones y frontera temporal |
| `tests/Unit/PuntoVenta/CalcularAntiguedadOperativaResguardoPdvTest.php` | Actualizar datos de dos pruebas antiguas a la confirmación de custodia |
| `CHECKPOINT_A1_VENCIMIENTOS_PDV.md` | Este informe |

## Límites y riesgos pendientes

- No se midió la cantidad de registros productivos afectados. Los registros que
  realmente carezcan de confirmación seguirán sin clasificarse; no se realizó relleno
  de datos ni se propuso cambiar esa regla.
- El checkpoint valida la carga del ancla y su clasificación con el estado consultado;
  no resuelve la reconstrucción histórica de estados ni otros hallazgos de la auditoría.
- La ejecución usa SQLite en memoria; no constituye una prueba sobre MySQL productivo.
- No se cambiaron otras fórmulas, componentes, reglas operativas ni migraciones.
  No se realizaron commits ni push.

Checkpoint A1 finalizado. Los demás hallazgos quedan pendientes de aprobación.

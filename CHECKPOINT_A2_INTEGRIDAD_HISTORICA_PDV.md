# GELIA — Checkpoint A2: integridad histórica de reportes PDV

Fecha: 14 de octubre de 2026.

## 1. Resumen ejecutivo

Se confirmaron fugas de información posterior al corte y cambios de resultados al
consultar nuevamente un periodo después de nuevas operaciones.

Se corrigieron límites temporales locales en los reportes de turnos y operación,
sin tocar servicios operativos ni esquemas. Por decisión explícita del usuario,
los promedios conservan la duración completa de los casos atribuidos al periodo;
solo los tiempos acumulados se recortan a su intersección con el periodo y el corte.

La integridad histórica global **no queda validada**. Los resguardos todavía mezclan
estado actual con corte histórico. Se reprodujeron entrega, devolución y entrega
parcial posteriores al corte; sus pruebas están marcadas como incompletas, con
diagnóstico explícito, para no presentar esos casos como resueltos.

No se implementaron snapshots, un reconstructor de estados, reglas nuevas,
migraciones, métricas nuevas, gráficas, respaldo diario ni exportación de evidencias.
Los cambios de A1 se conservaron y sus pruebas relacionadas siguen pasando.

## 2. Archivos y servicios inspeccionados

Rutas relativas a la raíz del proyecto:

| Área | Archivos principales |
| --- | --- |
| Entrada y exportaciones | `app/Http/Controllers/PuntoVenta/Reportes/MetricasReportePdvController.php`, `app/Http/Requests/PuntoVenta/Reportes/ConsultarMetricasReportePdvRequest.php`, `app/Services/PuntoVenta/Reportes/ObtenerPayloadMetricasReportePdvService.php`, `SolicitarExportacionReportePdvService.php` y controlador de exportaciones |
| Turnos y operación | `app/Services/PuntoVenta/Reportes/TurnosOperacion/{NormalizarFiltrosReporteTurnoOperacionPdvService,AplicarFiltrosReporteTurnoOperacionPdvQuery,CalcularMetricasReporteTurnoOperacionPdvService}.php` |
| Resguardos | `app/Services/PuntoVenta/Reportes/Resguardos/{NormalizarFiltrosReporteResguardoPdvService,AplicarFiltrosReporteResguardoPdvQuery,CalcularMetricasReporteResguardoPdvService}.php` |
| Contratos de indicadores | `app/Support/PuntoVenta/Reportes/{DefinicionesMetricaReportePdv,MetricaTurnoOperacionPdvIds,MetricaResguardoPdvIds}.php` |
| Modelos | `app/Models/PuntoVenta/{TurnoPdv,TurnoPdvAtencion,TurnoPdvEvento,JornadaPdv,IntervaloOperativoPdv,OperacionPdvEvento,SucursalDiaOperacionPdv,OperacionGestionAuditoriaPdv,ResguardoPdv,ResguardoPdvBulto,ResguardoPdvEntrega,ResguardoPdvEvento,ResguardoPdvIncidencia}.php` |
| Productores temporales necesarios | Servicios de asignación, transferencia, inicio/cierre de atención y reatención; `OperacionPdvConfig`, cierre/ampliación de horario; `RegistrarConfirmacionCustodiaPdvService`, `RegistrarEntregaResguardoPdvService`, `ConfirmarDevolucionResguardoPdvService`, `CorregirResguardoPdvService` y evaluador de antigüedad |
| Pruebas y aislamiento | Las cuatro clases ejecutadas en §5, `tests/TestCase.php`, `phpunit.xml` y la imagen local de Sail usada en A1 |

## 3. Problemas confirmados y correcciones

### Turnos y operación: correcciones locales aplicadas

- **Eventos futuros:** `cumpleAtribucion` verificaba el rango y los filtros operativos,
  pero no el corte. Ahora exige también `momento <= corte_reporte_at`.
  Se mantiene `desde <= momento < hasta`; el corte incluye su instante exacto.
- **Primera asignación futura:** una atención creada después del corte cambiaba la
  muestra T-05/T-15 de un turno antiguo. Se ignora su asignación futura y se evalúa
  la baja según su fecha al corte, conservando el criterio previo de exclusión.
- **Espera inicial:** un inicio real o cierre posterior sustituía la espera conocida
  al corte. T-06 utiliza el corte cuando esa espera todavía estaba abierta.
- **Atenciones y jornadas abiertas:** un cierre posterior incorporaba duraciones
  completas a T-07/T-16 y O-02. Ahora se mantienen como abiertas al corte y fuera
  de la muestra cerrada. No se inventan duraciones completas para casos abiertos.
- **Bajas y no presentación:** T-09/T-10 podían aplicar resultados futuros a cohortes
  antiguas. Ahora requieren que la baja o el cierre correspondiente ya ocurriera.
- **Acumulados O-03/O-04/O-05:** se aplica
  `max(0, min(fin o corte, corte, hasta) - max(inicio, desde))`.
  Se incluye el tramo que se solapa con el periodo aunque comenzara antes; se excluyen
  intervalos futuros o sin intersección positiva. El indicador de abierto se evalúa
  respecto del corte, no respecto de si hoy existe una fecha de finalización.

Los promedios de atención/ciclo/jornada siguen usando sus duraciones completas y
la atribución existente: por ejemplo, atención/ciclo por finalización y jornada
por apertura. No se transformaron en promedios de horas trabajadas dentro del rango.

La prueba CM09 esperaba ocupación de 11.11 % usando una jornada que terminaba una
hora después del corte. Se actualizó para exigir valor nulo y `en_curso=true`:
el denominador vigente usa jornadas cerradas y esa jornada aún no lo estaba.
No se cambió la fórmula de ocupación ni se incorporaron jornadas abiertas al denominador.

### Resguardos: diagnóstico confirmado, reconstrucción detenida

- R-01/R-02 usan `where estado=...` sobre el estado actual, sin reconstrucción ni
  exclusión general de altas/entradas posteriores al corte.
- R-04/R-05/R-06 calculan plazos al corte, pero sobre estado y configuración actuales.
  A1 carga correctamente el ancla; no resuelve que una entrega posterior haga
  desaparecer la custodia histórica ni congela los plazos usados en el pasado.
- R-03 exige incidencia actualmente abierta: una autorización o cierre posterior
  puede ocultar una incidencia que seguía abierta al corte.
- R-07/R-08/R-09 y los conteos R-10/R-11/R-12 aplican rangos, pero no una barrera
  uniforme contra fechas posteriores al corte cuando `hasta > corte`.
- R-08 usa estado/final actual y una ventana adicional de dos años para abiertos;
  una salida futura cambia tanto muestra cerrada como conteo en curso.
- Una entrega parcial puede conservar `en_custodia` en la cabecera mientras cambia
  bultos y eventos. Contar solo estados de cabecera no reconstruye sus saldos históricos.
- La devolución inspeccionada registra los bultos restantes en custodia. Tras una
  entrega parcial puede devolver ese remanente; no se presume un flujo adicional de
  devoluciones parciales arbitrarias que el código inspeccionado no acredita.

No se reemplazó esta reconstrucción por inferencias silenciosas sobre el estado actual.
El comportamiento existente de resguardos queda identificado expresamente como
no apto para certificar una fotografía histórica completa.

## 4. Confiabilidad histórica por indicador

“Confiable” se refiere a estabilidad temporal bajo registros completos, sin edición
retroactiva y con alcance fijo; no certifica otras fórmulas, permisos o filtros de
la auditoría original. “Condicionada” exige condiciones adicionales explícitas.

| Indicadores | Estado tras A2 | Fundamento o limitación |
| --- | --- | --- |
| T-01 altas | Confiable temporalmente | Marca `alta_at`, rango y barrera de corte; depende de conservar sucursal y atribución |
| T-02 asignaciones, T-04 bajas, T-12 transferencias | Confiables temporalmente | Eventos con `ocurrido_at` dentro de rango y corte; se cuentan eventos, no necesariamente turnos únicos |
| T-03 finalizados y T-08 ciclo | Condicionados | Fin acotado al corte, pero aún dependen del estado/cierre actual; no hay replay de estados |
| T-05/T-15 espera en cola | Condicionados | Primera asignación conocida al corte y baja temporal; cohortes por alta, dependientes de conservar secuencias y marcas |
| T-06 espera inicial | Condicionada | Extremos futuros limitados al corte; conserva la muestra existente que incluye esperas abiertas, no solo casos cerrados |
| T-07/T-16 duración de atención | Condicionados | Casos cerrados al corte conservan duración completa; abiertos quedan separados. Conteo de abiertos y filtros finos requieren revisar atribución de cohortes |
| T-09/T-10 abandono/no presentación | Condicionados | Resultado debe existir al corte; fórmulas/cohortes originales conservadas |
| T-11 reatención | Condicionada | Tiene secuencias y eventos, pero utiliza ventana actual de reatención; no se certifica reconstrucción de ventanas anteriores |
| T-13 prórrogas | Condicionada | Marcas/eventos acotados por corte; preserva el conteo existente por atención con prórroga |
| T-14 prioridades | Condicionada | Alta acotada; prioridad de la fila actual, sin versión histórica acreditada |
| O-01 jornadas y O-02 duración | Condicionados | Atribución por apertura; abierto/cerrado al corte corregido, promedio completo de jornadas cerradas |
| O-03/O-04/O-05 acumulados | Confiables para rango/corte con intervalos completos | Intersección temporal corregida. Fecha operativa y franja conservan filtro por inicio; no se redistribuye un intervalo entre franjas/días |
| O-06 ocupación y O-07 disponibilidad | Condicionados; no validados globalmente | Heredan límites corregidos, pero siguen mezclando acumulados con denominador de jornadas cerradas. No se corrigió la fórmula ni la instrumentación de atención |
| O-08 días sin altas | Fotografía actual | `acepta_altas` es mutable; el reporte no reconstruye cómo estaba cada día en un corte anterior |
| O-09 cierres por horario | Confiable temporalmente | Evento con fecha, acotado por rango y corte |
| O-10 ampliaciones y O-11 altas fuera de cierre | No reconstruibles con certeza mediante la consulta actual | Día mutable, ampliaciones/reaperturas y configuración actual; no se acreditó historial completo de cada modificación |
| R-01/R-02 existencias por estado | Fotografía actual | No reconstruyen estado de recepción/custodia al corte |
| R-03 incidencias abiertas | No confiable históricamente hoy | Estado actual; `reportado_at`/`autorizado_at` ayudan, pero falta resolver cierres y su cobertura |
| R-04/R-05/R-06 antigüedad | Condicionados para presente; no históricos certificados | Ancla A1 correcta, estado y plazos actuales |
| R-07 recepción | Condicionada | Marcas temporales disponibles; rango SQL sin barrera uniforme de corte y filtros sobre estado actual |
| R-08 custodia y R-09 entrega | No confiables históricamente hoy | Final/estado actuales y cohortes existentes; necesitan reconstrucción coherente |
| R-10 incidencia | No confiable históricamente hoy | Activos pueden incluir eventos futuros y población filtrada por estado actual |
| R-11 recepciones/R-12 devoluciones | Condicionados | Eventos reconstruibles si el rango no rebasa el corte y su alcance es estable; hoy no hay barrera de corte uniforme |

## 5. Casos automatizados y resultados

Datos exclusivamente sintéticos, corte habitual 7 de septiembre de 2026 a las 12:00
en México. Se vuelve a consultar el mismo filtro después de registrar finales o
eventos a las 14:00/17:00. No se modifican datos operativos reales.

| Corrección/caso | Antes | Después |
| --- | --- | --- |
| Pausa, disponibilidad y atención, final futuro (3 casos) | 7,200 segundos pasan a 25,200; abierto pasa a cerrado | Conservan 7,200 y abierto al corte |
| Los mismos 3 tipos con `hasta=11:00`, corte 12:00 | Incluyen 7,200 en lugar de 3,600 | Conservan 3,600 antes/después del cierre posterior |
| Intervalo 08:00–11:00, periodo desde 10:00 | Se omite: 0 segundos | Se incluye una hora de solapamiento |
| Intervalo futuro 13:00–14:00 | Contribuía al acumulado con rango hasta fin de día | No altera el acumulado histórico |
| Jornada 08:00, cierre posterior 17:00 | Cambia a muestra cerrada y agrega promedio de 32,400 segundos | Sigue en curso al corte, sin incorporarse al promedio cerrado |
| Atención iniciada 10:00, cierre posterior 14:00 | Agrega promedio de 14,400 segundos y cambia el conteo abierto | Sigue en curso; cierre de turno y ciclo futuros tampoco se cuentan |
| Altas/asignaciones 11:00, 12:00 y 12:00:01 | Cuenta tres aun con corte 12:00 | Cuenta dos; si `hasta=12:00`, cuenta una |
| Corte 18:00 UTC frente a fechas de México | No había barrera de corte | Prueba el mismo instante que 12:00 México |
| Primera asignación posterior al corte | Modifica muestra T-05 de 0 a 1 | Conserva muestra de 0 |
| Inicio real posterior al corte | Espera pasa de 10,800 a 18,000 segundos | Conserva 10,800 y en curso |
| Baja/no presentación posteriores | Abandono pasa de 0 % a 100 % en el ejemplo | Tasas históricas sin cambio |
| Duraciones completas atribuidas al periodo | Contrato ya existente | Conservado: atención 7,200 y ciclo/jornada 10,800 segundos en sus cohortes respectivas |
| Custodia → entrega futura | R-02/R-05 cambian de 1 a 0; R-08 incorpora un cierre futuro | Problema reproducido; prueba incompleta, sin reconstrucción implementada |
| Custodia → devolución futura | Misma deriva de estado y muestra | Problema reproducido; prueba incompleta |
| Entrega parcial futura de 1 de 2 bultos | Cabecera sigue en custodia, activos R-10 pasan de 0 a 1 | Problema reproducido; prueba incompleta |

Ejecuciones:

1. Nueva clase A2 inicial, antes de corregir producción: **15 casos, 40 aserciones,
   9 fallos y 4 incompletos**. Dos controles pasaron; uno de ellos se fortaleció para
   separar solapamiento y evento futuro, evitando que sus errores se compensaran.
2. Reproducción focalizada adicional, todavía antes de corregir producción:
   **4 casos, 9 aserciones, 4 fallos** (solapamiento, asignación, espera y tasas).
3. Ejecución final relacionada: **44 casos, 129 aserciones, 0 errores/fallos y
   3 incompletos**; **41 casos satisfactorios**. Los tres incompletos corresponden
   a los diagnósticos pendientes de resguardos; no equivalen a regresiones aprobadas.
   Duración: 27.178 segundos; memoria: 101 MB.

### Entorno y comandos

PHP 8.4.25 / PHPUnit 12.5.25, imagen local `sail-8.4/app:latest`, sin instalar
dependencias. Contenedor temporal sin red, repositorio de solo lectura, almacenamiento
y cachés temporales, entorno `testing`, SQLite `:memory:`. No se inicia el conjunto de
servicios de Compose ni se usan volúmenes/bases productivas. Las migraciones de pruebas
solo crean estructuras efímeras en memoria; no hay migraciones nuevas ni cambios de
esquemas persistentes. No se escribe la caché de resultados de PHPUnit.

Comando final:

```bash
docker run --rm --network none --entrypoint /bin/sh \
  --mount type=bind,source=/home/ti-admin/gelianv,target=/var/www/html,readonly \
  --tmpfs /var/www/html/storage:mode=1777 --tmpfs /tmp:mode=1777 \
  -w /var/www/html \
  -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e APP_CONFIG_CACHE=/tmp/gelia-a2-config.php \
  -e APP_EVENTS_CACHE=/tmp/gelia-a2-events.php \
  -e APP_ROUTES_CACHE=/tmp/gelia-a2-routes.php \
  sail-8.4/app:latest -c 'mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
exec php vendor/bin/phpunit --do-not-cache-result --display-incomplete tests/Unit/PuntoVenta/Reportes/IntegridadHistoricaReportePdvTest.php tests/Unit/PuntoVenta/Reportes/CalcularMetricasReporteTurnoOperacionPdvTest.php tests/Unit/PuntoVenta/Reportes/CalcularMetricasReporteResguardoPdvTest.php tests/Unit/PuntoVenta/CalcularAntiguedadOperativaResguardoPdvTest.php'
```

En la primera reproducción se usó el mismo aislamiento con este comando interno:

```bash
php vendor/bin/phpunit --do-not-cache-result --display-incomplete tests/Unit/PuntoVenta/Reportes/IntegridadHistoricaReportePdvTest.php
```

En la segunda reproducción focalizada:

```bash
php vendor/bin/phpunit --do-not-cache-result --filter 'test_a2_(acumulado_incluye|primera_asignacion|inicio_de_atencion|baja_y_no)' tests/Unit/PuntoVenta/Reportes/IntegridadHistoricaReportePdvTest.php
```

PHPUnit finaliza con código 0 si solo existen incompletos en esta configuración;
eso **no certifica A2**. Para exigir que esos pendientes bloqueen una validación
automática se puede añadir `--fail-on-incomplete`; no se modificó la configuración global.

## 6. Archivos modificados en A2

| Archivo | Justificación |
| --- | --- |
| `app/Services/PuntoVenta/Reportes/TurnosOperacion/AplicarFiltrosReporteTurnoOperacionPdvQuery.php` | Barrera de corte para atribución temporal, conservando límites del rango |
| `app/Services/PuntoVenta/Reportes/TurnosOperacion/CalcularMetricasReporteTurnoOperacionPdvService.php` | Guardas de finales/resultados futuros y recorte de acumulados; sin cambiar fórmulas de tasas ni promedios completos |
| `tests/Unit/PuntoVenta/Reportes/CalcularMetricasReporteTurnoOperacionPdvTest.php` | CM09 deja de exigir el uso de un cierre de jornada futuro |
| `tests/Unit/PuntoVenta/Reportes/IntegridadHistoricaReportePdvTest.php` | Regresiones temporales y tres diagnósticos explícitamente incompletos |
| `CHECKPOINT_A2_INTEGRIDAD_HISTORICA_PDV.md` | Este informe |

Los archivos de consulta/pruebas de resguardos y el informe A1 ya estaban modificados
al iniciar A2. No se alteraron ni revirtieron; no deben confundirse con cambios de A2.

## 7. Riesgos pendientes y límites de los datos

- Los eventos de resguardo tienen estado anterior/nuevo, fecha, bulto y snapshot;
  entregas tienen fecha y relación con bultos; recepción/custodia/salida tienen marcas
  por bulto. Son fuentes útiles para reconstrucción, pero no se verificó cobertura
  productiva, importaciones legadas, secuencias faltantes ni todas las modificaciones.
- La custodia de cabecera se confirma al completar bultos. No puede sustituirse
  sin más por la primera recepción ni por una transición parcial.
- El evaluador de antigüedad utiliza plazos vigentes. Cambios de configuración pueden
  alterar resultados históricos aun con el estado reconstruido. Algunos eventos guardan
  contexto, pero no se acreditó una versión completa de reglas para cualquier corte.
- `SucursalDiaOperacionPdv` sobrescribe estado, ampliación y cierres al reabrir/ampliar.
  Existen eventos y auditoría de gestión, sin un contrato verificado de replay completo.
- Los filtros de fecha operativa/franja de intervalos siguen atribuyéndose por inicio.
  Dividir intervalos entre días/franjas o repartirlos en futuras gráficas exige acordar
  esa regla y probar cruces de medianoche. El recorte implementado cubre el rango absoluto.
- O-06/O-07 y la generación de intervalos `en_atencion` conservan sus limitaciones
  previas; A2 no convierte ocupación/disponibilidad en indicadores globalmente certificados.
- No se garantiza estabilidad ante cambios retroactivos de marcas, sucursal, prioridad,
  permisos o datos importados. También debe distinguirse “ocurrido al corte” de
  “registrado y conocido al corte”; este checkpoint usa tiempos operativos, no snapshots
  bitemporales. Los registros sintéticos pueden crearse hoy con fechas operativas pasadas.
- Las consultas SQL de resguardos y sus rangos con distintos offsets requieren una
  validación específica de normalización horaria; no se extrapola la prueba UTC de
  atribución en memoria de turnos a todas esas consultas.
- Sin datos productivos no se cuantificó impacto real; SQLite no certifica ejecución
  ni rendimiento de MySQL. No se hicieron pruebas generales ajenas al alcance.

## 8. Recomendaciones y decisiones necesarias

1. Autorizar un trabajo separado de reconstrucción histórica de resguardos: definir
   fuentes, orden de eventos, estados de recepción/custodia, entregas parciales y
   devoluciones del remanente. Si faltan eventos o anclas, devolver condición de
   información insuficiente, sin usar silenciosamente el estado actual.
2. Evaluar primero si los eventos existentes cubren el contrato; solo después proponer,
   con aprobación, snapshots o persistencia adicional. No se implementó ese historial aquí.
3. Definir el contrato histórico de plazos/horarios y de datos legados. Una copia de
   filtros/corte no congela filas ni configuración; un informe reproducible debe
   conservar sus fuentes o una instantánea autorizada.
4. Mantener separadas fotografías presentes, movimientos por fecha y duraciones de
   cohortes. Las comparaciones deben conservar alcance, zona, atribución y cobertura,
   usando solo indicadores temporalmente certificados.
5. Conservar la decisión del usuario: promedios de duraciones completas y acumulados
   recortados. Antes de comparar ocupación, resolver su denominador y la cobertura de
   intervalos de atención como trabajo independiente sujeto a autorización.

## 9. Estado final

**Diagnóstico A2 concluido; correcciones temporales locales verificadas. Integridad
histórica global pendiente de decisiones adicionales.**

Se detiene la implementación antes de desarrollar el reconstructor de estados o
persistencia histórica. No se realizaron commits ni push. No se continúa con otros
hallazgos, checkpoints ni rediseño visual sin aprobación.

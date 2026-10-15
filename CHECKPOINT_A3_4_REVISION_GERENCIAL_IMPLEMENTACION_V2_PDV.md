# GELIA — Checkpoint A3.4: revisión gerencial y puerta de implementación V2

Fecha: 2026-10-14.

## 1. Resumen ejecutivo

**Estado: PARCIAL. Revisión gerencial resuelta; implementación V2 bloqueada por la puerta de seguridad.**

Se corrigió la causa común de los siete errores gerenciales: el fixture concedía gestionar, pero no consultar el equipo. Las 17 pruebas gerenciales pasan, incluida una nueva que demuestra que ambos permisos siguen separados. No se cambiaron permisos ni respuestas de producción.

Antes de añadir eventos V2 se reprodujeron dos defectos transaccionales legados con servicios reales y procesos MySQL independientes:

1. Una pausa y una reserva pueden confirmarse simultáneamente para la misma persona.
2. Dos sucursales pueden reservar simultáneamente a la misma persona para turnos distintos.

En ambos casos las operaciones terminan correctamente para sus llamadores; el conflicto persiste en las filas confirmadas. No es solo contención, un deadlock recuperable ni una expectativa incorrecta del test.

La sección 3 de la solicitud exige avanzar cuando «No exista evidencia de corrupción, doble asignación o pérdida de operaciones» y detener la integración afectada ante un defecto estructural de asignación. Esa condición no se cumple. **No se instrumentaron reservas, inicios, transferencias ni reatenciones; tampoco se amplió el catálogo para anunciar productores inexistentes.**

Regresión focalizada final: **72 pruebas / 253 aserciones, aprobada**. Puerta MySQL: **3 pruebas / 24 aserciones, 2 fallos de invariantes y 1 control aprobado**. Los dos fallos se conservaron como pruebas de regresión, sin marcarlos como satisfactorios.

## 2. Diagnóstico de los siete errores gerenciales

Archivo: `tests/Feature/PuntoVenta/GestionEquipoOperativoPdvTest.php`.

Reproducción inicial: **16 pruebas / 36 aserciones / 7 errores**, 19.798 s. El `firstWhere` no encuentra al integrante en `json('equipo')` y se intenta leer un índice de `null`.

La respuesta no es un error de persistencia ni un cambio del formato público. `ConsultaOperacionGeneralPdvService::ejecutar` distingue explícitamente:

- `PERMISO_TURNOS_VER`: permite consultar la operación general.
- `PERMISO_OPERACION_EQUIPO_VER`: permite incluir el equipo.
- `PERMISO_OPERACION_EQUIPO_GESTIONAR`: autoriza las acciones gerenciales; no concede implícitamente lectura en la consulta general.

Sin `EQUIPO_VER`, el servicio asigna intencionalmente `equipo = []`. El fixture `crearGerente` no incluía ese permiso, aunque siete escenarios esperaban consultar integrantes. Las pruebas de `OperacionUiPdvTest` y `GestionVendedoresPdvTest` también distinguen ese permiso, corroborando que la separación es intencional.

| Escenario afectado | Causa | Después del ajuste común |
|---|---|---|
| Colocar/quitar pausa | Consulta de equipo con fixture sin permiso de lectura | Aprobado |
| Cerrar jornada | Misma causa | Aprobado |
| Activar con marcadores obsoletos | Misma causa | Aprobado |
| Marcar cierre pendiente | Misma causa | Aprobado |
| Completar cierre tras atención | Misma causa | Aprobado |
| Reactivar jornada cerrada | Misma causa | Aprobado |
| Cancelar cierre pendiente | Misma causa | Aprobado |

No fue necesario modificar el endpoint, la consulta de integrantes, los servicios operativos ni los cambios previos del árbol de trabajo.

## 3. Corrección y pruebas antes/después

Se añadió `PERMISO_OPERACION_EQUIPO_VER` **solo al usuario sintético preparado por el fixture gerencial**. Es una precondición explícita de los casos que consultan el equipo, no una concesión nueva para usuarios o roles reales.

Se agregó `test_gestionar_sin_equipo_ver_no_expone_equipo_en_consulta_general`:

1. Retira el permiso de lectura del gerente de prueba.
2. Verifica que activar al vendedor sigue permitido.
3. Verifica HTTP 200 y `equipo = []` en la consulta general.
4. Comprueba la jornada realmente creada.

Resultado después: **17 pruebas / 52 aserciones, aprobadas**, 20.503 s. Los siete escenarios anteriores se ejecutaron completos; no se relajaron sus expectativas de estado ni se convirtieron fallos en skips. La regresión final vuelve a incluir los 17 casos.

## 4. Contención existente y defectos estructurales

### Contención ya clasificada en A3.3.1

Las esperas de rango de jornadas y el deadlock desactivado documentados en A3.3.1 siguen siendo limitaciones legadas. Por sí solos no prueban corrupción; allí se verificaron rollback, unicidad y reintento. No se repitió esa auditoría ni se cambiaron índices, aislamiento o política global de reintentos.

### Nuevas comprobaciones necesarias para la puerta V2

Se usó el `MatchmakerTurnosPdvService` real, su selector de cola y la consulta real de empleados elegibles. No se sustituyeron disponibilidad ni asignación por dobles de prueba. La pausa se ejecutó mediante `IniciarPausaPdvService::iniciarParaUsuario`, el escritor operativo real.

**Carrera asignación/pausa:**

1. El asignador selecciona el turno y valida disponibilidad/ausencia de atención abierta.
2. Una barrera lo retiene justo antes del INSERT de atención, dentro de su transacción.
3. La pausa bloquea jornada e intervalo, valida que no existe atención y queda retenida antes de crear su intervalo.
4. Se libera la pausa y se espera su confirmación.
5. Se libera la asignación, que continúa usando la decisión previa.
6. Ambas operaciones confirman: turno `ASIGNADO`, **una pausa abierta y una atención/reserva abierta para la misma persona**.

**Carrera entre dos sucursales:**

1. La persona tiene acceso y jornadas abiertas válidas en ambas sucursales; la regla actual de atención abierta es global por usuario.
2. Dos matchmakers bloquean turnos distintos en sucursales distintas.
3. Ambos alcanzan la barrera anterior al INSERT después de comprobar disponibilidad y ausencia de otra atención abierta.
4. Se confirma el primero y luego se permite continuar al segundo, cuya transacción ya había validado la ausencia.
5. Ambos confirman: **dos reservas abiertas para el mismo usuario**. La restricción única de atención es `(turno_id, numero_secuencia)`; no arbitra empleados compartidos entre turnos.

No se simularon las carreras ejecutando comandos completos uno después de otro: las dos transacciones ya habían iniciado y validado el estado antes de permitir el primer commit. La barrera hace reproducible el intercalado. El caso de pausa se comprobó en una sucursal; el segundo demuestra la exclusión global faltante entre sucursales.

**Control positivo:** dos matchmakers sobre el mismo turno sí se serializan mediante el bloqueo del selector; se observó espera en `performance_schema.data_lock_waits`. El primero asignó una vez y el segundo devolvió cero; quedó una sola reserva.

Estos defectos preceden a V2: toda la prueba se ejecutó con medición **desactivada** y la tabla histórica terminó con cero filas. No se detectaron modificando datos productivos.

## 5. Archivos y servicios V2 revisados

Se inspeccionaron exclusivamente los escritores necesarios y sus dependencias:

- `MatchmakerTurnosPdvService`, `SeleccionarTurnoColaPdvService`, `AsignarTurnoPdvService`.
- `IniciarAtencionTurnoPdvService`, `AplicarInicioAtencionTurnoPdvService`, `AplicarPlazosAtencionNuevaTurnoPdvService`.
- `TransferirTurnoPdvService`, `AsignarReatencionTurnoPdvService`.
- Consulta de disponibilidad, pausa, apertura V1 y registrador V0 para establecer órdenes de bloqueo.

**Ninguno de esos servicios productivos fue modificado.** Los resultados de la puerta impidieron integrar V2 de manera segura dentro del alcance aprobado.

## 6. Eventos históricos V2 implementados

**Ninguno.** El catálogo conserva únicamente `jornada.abierta` y `medicion.correccion`, con su versión anterior.

| Evento solicitado | Estado |
|---|---|
| `turno.reservado` | No implementado: falta coordinación demostrada de exclusión del empleado |
| `atencion.iniciada` | No implementado: fase B no habilitada tras fallar la puerta |
| `turno.transferido_origen` | No implementado |
| `turno.transferido_destino` | No implementado |
| `turno.reatencion_reservada` | No implementado |

No se crearon eventos artificiales, tipos de intervalo nuevos ni hechos retrospectivos.

## 7. Cobertura de reserva e inicio

La cobertura sigue siendo V1: apertura únicamente, incompleta y sin métricas V2. Configuración inicial desactivada y cohorte vacía, sin cambios de despliegue.

Se confirmó por lectura de código que `inicio_at` de la atención representa la reserva y que el inicio efectivo se escribe en `atencion_inicio_at`, mediante `AplicarInicioAtencionTurnoPdvService`. El camino manual lo llama desde `IniciarAtencionTurnoPdvService`; el automático lo llama desde `AplicarPlazosAtencionNuevaTurnoPdvService` cuando lo dispone la configuración.

Esto identifica los productores, **no valida nueva instrumentación manual o automática**. Las pruebas operativas anteriores se conservaron; no se atribuye cobertura histórica de V2 a su existencia ni a un inicio automático se le atribuye presencia física del cliente.

## 8. Transferencias y reatenciones

Ambos flujos comparten la necesidad de comprobar y reservar al destino dentro de una coordinación de empleado compatible con los demás escritores. La transferencia afecta origen y destino; la reatención crea otra secuencia del mismo turno.

No se añadió el mutex V0 después de sus bloqueos actuales de turno/atención, ni se introdujo un orden arbitrario entre dos empleados. Tampoco se modificaron ventanas, selección de destinos, enfriamiento o permisos.

La integración histórica de ambos queda **bloqueada junto a la reserva**. No se ejecutaron nuevas pruebas V2 de transferencia automática, causalidad ni idempotencia histórica porque no existe esa implementación. Esto no afirma que se haya demostrado una corrupción específica de transferencia o un defecto de inicio automático.

## 9. Protocolo transaccional y orden de bloqueos

| Flujo actual | Orden/puntos relevantes observados |
|---|---|
| Apertura instrumentada V1 | Mutex exclusivo de `users` → jornada/intervalo → evento V0 |
| Matchmaker/asignación | Selector bloquea turno → servicio vuelve a bloquear turno → consultas normales de persona/ausencia de atención → INSERT de atención → UPDATE de turno/evento → plazos/inicio eventual |
| Inicio manual | Turno → atención → persistencia de inicio efectivo |
| Transferencia | Turno → atención de origen → consultas de destino/ausencia de atención → cierre de origen y nueva atención destino |
| Reatención | Turno → comprobaciones de ventana/destino/disponibilidad → nueva atención |
| Pausa | Jornada → intervalo abierto → consulta normal de atención → cierre/creación de intervalos |
| Registrador V0 | Mutex exclusivo estable de `users` → lectura actual compartida de secuencia → INSERT histórico |

Las comprobaciones de disponibilidad no comparten hoy un mutex que abarque la decisión y la escritura con la pausa y con otras sucursales. Las FK toman sus propios bloqueos, pero las pruebas demuestran que no sustituyen esa exclusión operativa.

Añadir el mutex de V0 **después** de modificar la operación no revalida la decisión previa ni establece por sí solo un protocolo seguro. Además introduciría dependencias turno/atención → usuario sobre un sistema que ya tiene rutas usuario → jornada e intervalos. No se afirma haber ensayado todas las inversiones posibles; es una razón para no implantar una disciplina parcial.

### Protocolo propuesto, todavía no implementado

Como continuación del coordinador propuesto en A3.1:

1. Obtener candidatos sin considerarlos aún reservados.
2. Adquirir exclusión común de los empleados afectados, ordenados por ID; acordar un orden completo compatible para turno, jornada, atención e intervalo.
3. Revalidar cola/FIFO, elegibilidad, pausa, atención abierta y versiones bajo esa exclusión antes de escribir.
4. Confirmar operación y evento en la misma conexión/transacción; calcular secuencia bajo el mutex del agregado.
5. Hacer que pausa y los demás escritores que compiten por esa exclusión respeten el mismo protocolo, aunque sus eventos históricos se instrumenten en otra fase.

El orden exacto debe probarse con las rutas de apertura y notificación/job que pueden reentrar al matchmaker. En particular, `EjecutarMatchmakerTurnosPdvJob` tiene unicidad por sucursal, no exclusión global del empleado entre sucursales. No se usa esa unicidad como garantía de este último caso.

Es una propuesta pendiente de autorización y prueba, no un cambio aprobado o aplicado. **Reintentar solo deadlocks no resuelve los dos casos demostrados: ambos comandos confirmaron sin excepción.**

## 10. Pruebas SQLite y MySQL

| Ejecución real | Resultado |
|---|---|
| Gerencia antes | 16 pruebas, 36 aserciones, 7 errores, 19.798 s |
| Gerencia después | 17 pruebas, 52 aserciones, aprobadas, 20.503 s |
| Regresión SQLite final | **72 pruebas, 253 aserciones, aprobadas, 40.602 s** |
| Puerta MySQL, esquema de ensayo ya completo | **3 pruebas, 24 aserciones, 2 fallos y 1 control aprobado, 2.751 s** |
| Reproducción final MySQL | **Mismos 3 casos y 24 aserciones, mismos 2 fallos, 2.076 s** |

SQLite final: `GestionEquipoOperativoPdvTest`, `ContratoOperativoMedicionPdvTest`, `CicloAtencionTurnosPdvTest`, `JornadaDisponibilidadOperacionPdvTest`, `MatchmakerTurnosPdvTest`. Cubren la preparación corregida, permisos, prioridad/FIFO, disponibilidad, pausas, enfriamiento y ciclo operativo existente. No equivalen a una validación V2 exitosa.

MySQL **8.4.9 / InnoDB / REPEATABLE READ**, PHP **8.4.25**, PHPUnit **12.5.25**. Dos procesos independientes por escenario, PID y `CONNECTION_ID` distintos. Aplicación nueva de pruebas sin cargar `.env`, conexiones fijas al host/base efímeros y sin cache de configuración del proyecto. La variable explícita habilita el arnés; sin ella se marca skip, sin sustitución silenciosa por SQLite.

Se aplicaron **12 migraciones necesarias** sobre la base nueva, con fixtures mínimos de tablas padre y campos auxiliares. Las tablas PDV y sus restricciones proceden de las migraciones reales seleccionadas. No se repitieron las 423 migraciones ni se afirma una nueva certificación del esquema completo. Los primeros ensayos del arnés detectaron columnas auxiliares faltantes (`prioridad_cola` y `sucursales.es_demo`); se completó exclusivamente el esquema temporal de pruebas antes de obtener los resultados de seguridad anteriores. Esos errores de preparación no se cuentan como defectos productivos.

### Comandos reproducibles

```bash
docker network create --internal gelia-a34-20261014
docker run -d --name gelia-a34-mysql-20261014 \
  --network gelia-a34-20261014 --network-alias mysql-a34 \
  --tmpfs /var/lib/mysql:rw \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_ROOT_HOST=% \
  -e MYSQL_DATABASE=gelia_medicion_a34_test mysql:8.4
docker exec gelia-a34-mysql-20261014 mysqladmin ping --silent

set -o pipefail
docker run --rm --network gelia-a34-20261014 --entrypoint /bin/sh \
  --mount type=bind,source=/home/ti-admin/gelianv,target=/var/www/html,readonly \
  --tmpfs /var/www/html/storage:mode=1777 --tmpfs /tmp:mode=1777 -w /var/www/html \
  -e PDV_MEDICION_A34_MYSQL_TEST=1 -e APP_ENV=testing \
  -e APP_CONFIG_CACHE=/tmp/a34-config.php \
  -e APP_EVENTS_CACHE=/tmp/a34-events.php -e APP_ROUTES_CACHE=/tmp/a34-routes.php \
  sail-8.4/app:latest -c 'mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
exec php vendor/bin/phpunit --do-not-cache-result tests/Feature/PuntoVenta/SeguridadReservaV2MySqlTest.php' \
  | tee /tmp/gelia-a34-seguridad-mysql-final.log
```

El comando MySQL termina con **código 1**, por los dos defectos pendientes; no debe interpretarse como una ejecución aprobada. Las aserciones finales conservan la regla esperada: no permitir pausa/reserva incompatible ni dos reservas globales del empleado.

```bash
set -o pipefail
docker run --rm --network none --entrypoint /bin/sh \
  --mount type=bind,source=/home/ti-admin/gelianv,target=/var/www/html,readonly \
  --tmpfs /var/www/html/storage:mode=1777 --tmpfs /tmp:mode=1777 -w /var/www/html \
  -e APP_CONFIG_CACHE=/tmp/a34-config.php \
  -e APP_EVENTS_CACHE=/tmp/a34-events.php -e APP_ROUTES_CACHE=/tmp/a34-routes.php \
  sail-8.4/app:latest -c 'mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
exec php vendor/bin/phpunit --do-not-cache-result tests/Feature/PuntoVenta/GestionEquipoOperativoPdvTest.php tests/Feature/PuntoVenta/ContratoOperativoMedicionPdvTest.php tests/Feature/PuntoVenta/CicloAtencionTurnosPdvTest.php tests/Feature/PuntoVenta/JornadaDisponibilidadOperacionPdvTest.php tests/Feature/PuntoVenta/MatchmakerTurnosPdvTest.php' \
  | tee /tmp/gelia-a34-regresion-sqlite.log
```

La reproducción gerencial antes/después utilizó el mismo entorno SQLite con solo `GestionEquipoOperativoPdvTest.php` como argumento. También se comprobó sintaxis de los cuatro archivos PHP involucrados y `git diff --check`, sin errores.

## 11. Evidencia de concurrencia y rollback

En la reproducción final:

- Pausa: PID 9, conexión 30, confirma intervalo 12. Asignación: PID 8, conexión 29, confirma una reserva. Resultado: `ASIGNADO`, `pausas=1`, `reservas=1`.
- Entre sucursales: PID 10/conexión 32 y PID 11/conexión 33 confirman una asignación cada uno para el usuario sintético 8. Resultado: `reservas_abiertas=2`.
- Mismo turno: PID 12/conexión 35 asigna una vez; PID 13/conexión 36 devuelve cero. Una reserva abierta.

Son datos sintéticos obtenidos de escrituras reales de los servicios en el servidor temporal. No se insertaron atenciones mediante factories para fabricar la prueba. Las barreras retienen comandos antes del INSERT, después de sus validaciones reales. El fixture habilita el módulo operativo y concede a la persona los permisos de acceso, atención y pausa; la medición histórica permanece desactivada. Se simulan únicamente difusión de eventos y colas de alertas, para aislar efectos secundarios; no se sustituye el motor de selección/asignación/pausa.

No hubo timeout ni deadlock en las reproducciones finales de estos escenarios: **ambas operaciones incompatibles confirman**. Por ello no existe rollback que pueda invocarse como solución a esas carreras. No se ensayó rollback histórico V2 ni idempotencia de eventos V2: esos eventos no existen. La tabla de medición tiene cero filas durante estos ensayos.

## 12. Compatibilidad con reglas operativas

No se cambió código productivo en A3.4. Se conservaron:

- Reglas, FIFO, prioridad, selección, ventanas, permisos y estados.
- Intervalos existentes y distinción entre reserva e inicio efectivo.
- A1, A2, corrección A3.2 y las correcciones V0/V1 anteriores.
- Los diez diagnósticos pendientes de A3, sin modificarlos ni convertirlos en resultados aprobados.
- Catálogo, configuración y cobertura histórica anteriores; instrumentación desactivada por defecto.

La comparación SHA-256 contra la captura inicial confirma que el único archivo preexistente alterado es la clase de prueba gerencial. No se modificó resguardos, interfaces, reportes ni datos persistentes.

## 13. Limitaciones y casos no cubiertos

- Los nuevos fallos demuestran carreras posibles con un intercalado concreto; no miden su frecuencia productiva ni justifican afirmar que toda asignación falla.
- El caso de doble reserva se probó entre sucursales, no entre dos turnos de una cola ya serializada por el selector.
- No se implementó ni validó un coordinador alternativo; no se promete ausencia de deadlocks.
- No se ejecutaron pruebas históricas V2 de inicio manual/automático, transferencia, reatención, causalidad, conflictos de clave o fallo de INSERT histórico. La puerta impidió iniciar esa implementación.
- No se ejecutaron suites globales, pruebas de carga, caídas del servidor, bases compartidas, producción ni reconstrucción histórica.
- Las tablas padre del arnés son acotadas; la compatibilidad global de migraciones sigue siendo la validación previa A3.3.1, no un resultado nuevo.

## 14. Archivos creados y modificados

Modificado:

- `tests/Feature/PuntoVenta/GestionEquipoOperativoPdvTest.php`: permiso de lectura explícito en el fixture, prueba negativa de separación de permisos y formato/importación de la misma clase.

Creados:

- `tests/Feature/PuntoVenta/SeguridadReservaV2MySqlTest.php`: tres invariantes concurrentes, dos de ellas actualmente fallidas.
- `tests/Support/PuntoVenta/MySqlMedicionA34.php`: bootstrap restringido al MySQL efímero A3.4.
- `tests/Support/PuntoVenta/seguridad_reserva_worker.php`: procesos de ensayo con servicios reales y barreras.
- Este informe.

Sin nuevas migraciones productivas, cambios de catálogo, registrador, modelo, servicios de turnos, pausa o apertura.

## 15. Decisiones que requieren autorización

Se necesita acordar y autorizar un **protocolo común de exclusión y revalidación del empleado**, con pruebas antes de extenderlo. Afectaría la coordinación de asignación, pausa, apertura, inicio, reatención y transferencia; supera añadir registro histórico al final de los comandos.

Alternativas concretas para revisar:

1. **Recomendada:** checkpoint de coordinación transaccional acotado que preserve reglas y FIFO, pero unifique la exclusión de empleados y revalide antes de escribir. Probar primero las dos carreras conservadas, compatibilidad con V1 y destinos de transferencia ordenados.
2. Evaluar un coordinador o mecanismo de exclusión distinto si no puede respetarse el orden con `users`; documentar contrato y demostrarlo en MySQL antes de adoptarlo.

No se aplicó una optimización global ni una nueva regla comercial. Limitar cohortes, modificar aislamiento/índices globales o reintentar indiscriminadamente no se usó para ocultar el problema. Tampoco se solicitó habilitar producción.

## 16. Estado final y respuestas

**PARCIAL: fase A gerencial completada; fase B/V2 bloqueada por defecto estructural demostrado.**

- **¿Se resolvieron los siete errores gerenciales?** Sí, en su preparación de pruebas; no se alteró el contrato de permisos.
- **¿La contención existente impide V2?** Las esperas legadas solas no; los dos estados incompatibles confirmados sí impiden superar la puerta de seguridad.
- **¿Se registran reservas e inicios efectivos V2?** No. No se añadieron productores históricos.
- **¿Funcionan los inicios automáticos?** No se cambió su funcionamiento existente; no se validó ni implementó su nueva evidencia histórica V2.
- **¿Transferencia y reatención quedaron cubiertas?** No; integración retenida por la misma puerta.
- **¿Los eventos V2 son atómicos e idempotentes?** No puede afirmarse: no están implementados. Las garantías V0/V1 previas no certifican escritores nuevos.
- **¿Podemos pasar a V3?** No. Primero debe resolverse y probarse la coordinación, y luego completar V2 con autorización.

El entorno fue interno, sin puertos publicados y con datos exclusivamente en tmpfs. Se retiraron únicamente los recursos creados para A3.4:

```bash
docker rm -f gelia-a34-mysql-20261014
docker network rm gelia-a34-20261014
```

No se realizaron commits, push, despliegues, activación de cohortes reales ni operaciones sobre información persistente.

## 17. Siguiente etapa recomendada

Revisar los dos fallos conservados y autorizar una intervención previa de coordinación transaccional, sin instrumentar nuevos tipos ni cambiar reglas comerciales. Criterio mínimo de salida: ambos tests MySQL deben pasar por preservación real de las invariantes, manteniendo el control de un único turno y la regresión operativa.

Después de demostrar ese protocolo, retomar V2 con reserva e inicio manual/automático y sus caminos equivalentes. Este checkpoint se detiene aquí; no continúa con V3, pausas históricas, enfriamiento, cierres, métricas ni rediseño.

# GELIA — Checkpoint A3.3.1: validación técnica de V0/V1

Fecha: 2026-10-14. Referencia: `CHECKPOINT_A3_3_IMPLEMENTACION_V0_V1_PDV.md`.

## 1. Resumen ejecutivo

**Estado: aprobado con restricciones para revisión y desarrollo local; no autoriza activación ni despliegue.**

Se comprobaron aperturas completas en procesos paralelos, migraciones del esquema completo en MySQL nuevo, recuperación de DDL parcialmente fallido y funcionamiento con permisos limitados. Se corrigió un defecto local del registrador: la lectura de secuencia exigía UPDATE sobre la bitácora. Ahora emplea bloqueo compartido, conservando lectura actual y serialización exclusiva por usuario.

Resultados finales:

- **MySQL: 25 pruebas / 239 aserciones, aprobadas.** Incluyen 3 pruebas técnicas nuevas, 7 escenarios concurrentes nuevos y las 15 pruebas V0/V1 anteriores.
- **SQLite focalizado: 71 pruebas / 281 aserciones, aprobadas.** Incluye las 66 anteriores y 5 casos HTTP gerenciales relacionados con apertura y autorización.
- **Regresión ampliada: 82 pruebas / 306 aserciones, 7 errores.** Los mismos errores aparecen con el registrador anterior; no se presentan como corregidos ni como pruebas exitosas.
- Sintaxis de los cinco archivos PHP involucrados y `git diff --check`: sin errores.

Persisten esperas de rango del flujo legado entre usuarios distintos y errores preexistentes en pruebas de consulta de equipo. No hubo cambios de reglas operativas, permisos reales, resguardos, React ni métricas. Instrumentación desactivada por defecto. Sin commits, push, despliegues ni datos productivos.

## 2. Pruebas de concurrencia real

Entorno: **MySQL 8.4.9, InnoDB, REPEATABLE READ**, PHP 8.4.25 / PHPUnit 12.5.25. Cada trabajador arranca una aplicación y conexión independiente mediante `proc_open`. Se registran PID, `CONNECTION_ID`, resultado, identificadores y duración.

Las barreras son archivos temporales compartidos entre los procesos del contenedor. Retienen una consulta o escritura real del servicio mediante observadores SQL exclusivos de pruebas; no se añadieron hooks al código productivo. En las carreras bloqueantes se verifica además `performance_schema.data_lock_waits` antes de liberar al primer proceso.

| Escenario | Evidencia y resultado |
|---|---|
| Mismo usuario/sucursal, medición activa | Segundo proceso espera el mutex del usuario; tras commit devuelve la misma jornada e intervalo con `reintento=true`. Solo una jornada, un intervalo y un evento, secuencia 1 y clave SHA-256 esperada. |
| Mismo usuario, sucursales distintas y snapshot anterior | Primer proceso conserva su evento sin commit; el segundo abre una transacción y lee antes de esperar. Al continuar, registra secuencia 2; quedan dos jornadas e intervalos, dos claves únicas y ámbitos correctos. Comprueba lectura actual tras la corrección. |
| Timeout inducido | Con `innodb_lock_wait_timeout=1`, el competidor recibe **1205**. Antes de liberar al ganador no existen filas confirmadas para ese usuario. Ganador confirma; tercer proceso reintenta y devuelve lo existente. Estado final único, sin filas parciales. |
| Fallo posterior al INSERT histórico | Primer proceso inserta evento y luego lanza una excepción de ensayo mientras el segundo espera. Se revierte su transacción; el competidor crea una apertura válida con secuencia 1. Una sola terna final. |
| Usuarios distintos, medición activa | Se observa espera del segundo INSERT de jornada en el índice `pdv_jornadas_user_sucursal_activa_unique`, `LOCK_MODE=X`, `LOCK_DATA=supremum pseudo-record`. Tras liberar, ambas operaciones confirman ámbitos separados y secuencia 1 por usuario. |
| Control de usuarios distintos, desactivada | La misma espera aparece en el mismo índice y registro de rango. Ambas operaciones finalizan; cero eventos históricos. No es un bloqueo atribuible exclusivamente a V1. |
| Mismo usuario/sucursal, desactivada | Ambos procesos leen ausencia de jornada y quedan retenidos antes de continuar, garantizando carrera real. En la ejecución final uno recibe **1213 (deadlock)** y otro confirma. Un tercer proceso reintenta con éxito. Solo una jornada y un intervalo; cero eventos históricos. |

Ejemplo final real: procesos PID 10/11, conexiones 19/20, devolvieron jornada 2 e intervalo 2; el primero creó y el segundo reintentó. En el ensayo desactivado, PID 23/conexión 38 recibió 1213, PID 24/conexión 39 confirmó jornada 12, y PID 25/conexión 40 la recuperó sin duplicarla. Son identificadores sintéticos de este entorno, no identificadores productivos.

### Interpretación y límites

El timeout y el deadlock son fallos transaccionales, no rechazos funcionales de una regla comercial. Se aceptan en los ensayos únicamente después de comprobar rollback, estado final y reintento. En una exploración anterior, el flujo desactivado también devolvió `ValidationException: No fue posible abrir la jornada por concurrencia`; se distingue del error SQL y se conserva el comportamiento vigente.

Una expectativa exploratoria de independencia absoluta entre usuarios falló. Se contrastó con el flujo desactivado y se sustituyó por una prueba que identifica el bloqueo y verifica integridad; **no se eliminó la limitación ni se afirma que los usuarios nunca se bloquean entre sí**. Revisar los rangos adquiridos por las limpiezas/consultas de jornadas legadas requiere un alcance posterior. No se cambiaron aislamiento, índices, reglas de apertura ni políticas de reintento.

Las pausas de aproximadamente 3 segundos de esos ensayos son retenciones artificiales para observar el bloqueo, no un benchmark de latencia normal. No se garantiza ausencia absoluta de deadlocks ni se certifican carga, todos los escritores, todos los órdenes de bloqueo o recuperación frente a caída del servidor.

## 3. Migraciones con esquema completo

Se revisó previamente la secuencia y sus dependencias: **423 archivos**. Se identificaron operaciones históricas destructivas en `up()` —drop de columnas/tablas, truncado de catálogos, consolidaciones y borrado de permisos—; solo se ejecutaron dentro de una base nueva y efímera, nunca contra datos persistentes.

La migración `2026_08_29_120001_backfill_pedido_bma_cierres_pago.php` puede llamar a un comando de backfill. Se inspeccionó su condición y comando: en la base vacía no hay pedidos pendientes y la llamada no se ejecuta. No se realizó reconstrucción de registros. También se revisaron usos de configuración, cache y auxiliares invocados; no se identificó una dependencia que obligara a usar servicios externos o secretos. Cache fue `array`, broadcasts `null`; red Docker interna y sin puertos publicados.

Resultado real: `migrate --force` sobre **gelia_medicion_a331_test**, base nueva, terminó con **423 migraciones registradas y 325 tablas**, incluida `pdv_medicion_eventos`.

Comprobaciones automatizadas:

- Identificadores y referencias BIGINT UNSIGNED compatibles con las tablas reales.
- JSON nativo para estados/contexto; DATETIME con precisión 6 para ambas fechas históricas.
- Índices únicos de UUID, idempotencia y secuencia por agregado.
- Tres CHECK, once FK con RESTRICT y dos triggers de inmutabilidad.
- Orden de creación compatible; sin colisión de nombres en la secuencia completa.
- Ensayo adicional de la migración aislada sobre padres copiados del esquema real mediante `CREATE TABLE … LIKE`: sus ocho definiciones `SHOW CREATE TABLE` permanecieron idénticas antes/después. El nuevo DDL no alteró esas tablas.

### Fallo parcial inducido y recuperación

Se creó una segunda base **gelia_medicion_a331_parcial**, también efímera, con las estructuras de padres necesarias. Un usuario DDL sin privilegios suficientes provocó un fallo después de crear la tabla y los CHECK, pero antes de los triggers:

1. Con binlog activo y `log_bin_trust_function_creators=0`, se observó **1419**.
2. La tabla permanece creada, vacía y sin triggers. MySQL no revierte esos DDL automáticamente.
3. Repetir `up()` sin reconciliar produce **1050, tabla existente**. No simula éxito ni elimina datos.
4. Solo tras verificar cero filas se ensayó `down()` sobre esa tabla temporal vacía. Con trust habilitado y todavía sin TRIGGER, se reprodujo **1142**.
5. Se retiró de nuevo exclusivamente la tabla de ensayo vacía; al conceder TRIGGER al usuario DDL y cumplir el requisito de binlog, `up()` terminó con los dos triggers y once FK.
6. Se restauró el valor global de trust original, **0**, dentro del servidor efímero.

Se ensayó **fallo parcial por privilegios**, no una caída real del servidor. No se modificó la migración para saltar silenciosamente tablas incompletas. Una instalación parcial exige inspección y reconciliación explícita antes de activar escritores; la receta de retirar una tabla vacía no es autorización para borrar una bitácora con evidencia.

## 4. Integridad y permisos

Usuario de aplicación creado exclusivamente en MySQL temporal:

| Recurso | Permiso comprobado |
|---|---|
| `pdv_medicion_eventos` | SELECT, INSERT; sin UPDATE, DELETE ni DDL |
| `pdv_jornadas`, `pdv_intervalos_operativos` | SELECT, INSERT, UPDATE para el flujo operativo vigente |
| `users` | SELECT y UPDATE limitado a la columna `id`, requerido en este motor para la lectura con bloqueo exclusivo; el servicio no actualiza el identificador |
| `sucursales`, `configuraciones_sistema` | SELECT |

Con ese perfil, la apertura y su registro funcionan. UPDATE/DELETE/TRUNCATE sobre evidencia se rechazan con **1142**. Para comprobar también los triggers, se concedieron temporalmente UPDATE/DELETE de la bitácora: ambos cambios se rechazaron con **SQLSTATE 45000**. Esas concesiones se revocaron; `SHOW GRANTS` confirmó SELECT/INSERT como estado final de la bitácora.

El archivado lógico del actor conserva la evidencia. El borrado físico de ese actor, que no era el empleado de la jornada, se rechaza con **1451** por la nueva referencia histórica. `down()` con evidencia se rechaza. Los rollbacks operativos revierten filas no confirmadas sin ejecutar un borrado funcional de historia confirmada.

Usuario DDL separado, sin SUPER: SELECT, CREATE, ALTER, INDEX, REFERENCES y TRIGGER sobre la base temporal de ensayo. Con binlog activo se comprobó que necesita además una configuración compatible de `log_bin_trust_function_creators` o intervención de un administrador autorizado. **No se concede SUPER al usuario de aplicación ni se cambia ningún privilegio real.**

La protección es suficiente para el perfil de aplicación ensayado. No impide que un administrador con privilegios para cambiar el esquema retire triggers o destruya datos. La separación de roles y el control de DDL son requisitos de despliegue.

## 5. Defectos identificados y corrección

### V0: lectura de evidencia incompatible con permisos limitados — corregido

Antes, `RegistrarTransicionMedicionPdvService` obtenía la última secuencia con `FOR UPDATE` sobre la bitácora. MySQL 8.4.9 rechazó la apertura con **1142, SELECT with locking clause command denied**, aun con SELECT/INSERT históricos y permisos operativos suficientes. El mismo requisito existía al recuperar una colisión de idempotencia.

Corrección mínima: ambas lecturas usan `sharedLock()` —`LOCK IN SHARE MODE` en MySQL—. El mutex exclusivo sobre la fila estable de `users` permanece intacto. La evidencia no puede actualizarse, por lo que una lectura actual compartida basta para secuencia y recuperación; se mantienen versión, referencias, claves, atomicidad y contrato.

Prueba antes: `test_privilegios_minimos_inmutabilidad_y_retencion` falla en la consulta de secuencia. Después: **1 prueba / 11 aserciones**, aprobada. La regresión final incluye esa prueba, el snapshot anterior con secuencia 1/2, carreras y las pruebas V0/V1 anteriores.

### Hallazgos que no se corrigieron dentro de este alcance

- Espera de rango entre usuarios: reproducida también con instrumentación desactivada. No se cambiaron los escritores legados ni el aislamiento para esconderla.
- DDL parcial por privilegios: procedimiento de prevención/recuperación documentado; sin alteración del esquema aprobado.
- Siete errores de pruebas gerenciales anteriores: reproducidos antes de la corrección; no se modificaron permisos ni funcionalidades para hacerlos pasar.

## 6. Pruebas antes y después, comandos y omisiones

| Ejecución | Resultado real |
|---|---|
| Primera integración completa de migraciones | 1 prueba, 7 aserciones, aprobada; después se ampliaron verificaciones de estructura y permisos |
| Exploración inicial concurrente | 5 pruebas, 78 aserciones, 2 fallos de expectativas: independencia entre usuarios y rechazo funcional legado no contemplado por el arnés |
| Permisos, antes de corregir V0 | Error 1142 al leer secuencia histórica con `FOR UPDATE` |
| Permisos, después | 1 prueba, 11 aserciones, aprobada |
| MySQL final, servidor recreado desde cero | **25 pruebas, 239 aserciones, 59.148 s, aprobado** |
| SQLite ampliado, código corregido | **82 pruebas, 306 aserciones, 7 errores, 54.994 s** |
| Solo gerencia, registrador anterior | **16 pruebas, 36 aserciones, mismos 7 errores, 21.299 s** |
| SQLite focalizado final | **71 pruebas, 281 aserciones, 39.100 s, aprobado** |

Los siete errores de `GestionEquipoOperativoPdvTest` son `Trying to access array offset on null` al buscar un integrante en `json('equipo')`. Casos y líneas verificadas: colocar/quitar pausa (123), cerrar jornada (154), marcadores obsoletos (187), cierre pendiente (288), completar cierre (332), reactivar jornada (354) y cancelar cierre pendiente (394). La causa completa de esa consulta queda fuera del checkpoint. Sus pruebas permanecen sin cambios.

Para contrastar el código anterior se reconstruyó únicamente el registrador previo y se verificó su SHA-256 contra la captura inicial; se montó como archivo de solo lectura **solo en el contenedor de comparación**, sin revertir el repositorio. La prueba gerencial anterior reprodujo exactamente los siete errores.

### Preparación real del entorno

```bash
docker network create --internal gelia-a331-20261014
docker run -d --name gelia-a331-mysql-20261014 \
  --network gelia-a331-20261014 --network-alias mysql-a331 --network-alias mysql-a33 \
  --tmpfs /var/lib/mysql:rw \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_ROOT_HOST=% \
  -e MYSQL_DATABASE=gelia_medicion_a331_test mysql:8.4
docker exec gelia-a331-mysql-20261014 mysqladmin ping --silent
docker exec gelia-a331-mysql-20261014 mysql -uroot \
  -e 'CREATE DATABASE gelia_medicion_a33_test;'
```

Se recreó únicamente ese contenedor temporal antes de la ejecución final para repetir las 423 migraciones desde una base nueva. La segunda base `gelia_medicion_a33_test` permite ejecutar las 15 pruebas anteriores sin mezclarlas con el esquema completo. La base parcial y los usuarios de prueba se crean dentro de los tests nuevos.

### Comando final MySQL

```bash
set -o pipefail
docker run --rm --network gelia-a331-20261014 --entrypoint /bin/sh \
  --mount type=bind,source=/home/ti-admin/gelianv,target=/var/www/html,readonly \
  --tmpfs /var/www/html/storage:mode=1777 --tmpfs /tmp:mode=1777 -w /var/www/html \
  -e PDV_MEDICION_A331_MYSQL_TEST=1 -e PDV_MEDICION_MYSQL_TEST=1 -e APP_ENV=testing \
  -e APP_CONFIG_CACHE=/tmp/a331-config.php \
  -e APP_EVENTS_CACHE=/tmp/a331-events.php -e APP_ROUTES_CACHE=/tmp/a331-routes.php \
  sail-8.4/app:latest -c 'mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
exec php vendor/bin/phpunit --do-not-cache-result tests/Feature/PuntoVenta/ValidacionTecnicaMedicionMySqlTest.php tests/Feature/PuntoVenta/ConcurrenciaAperturaMedicionMySqlTest.php tests/Feature/PuntoVenta/MedicionPdvMySqlTest.php' \
  | tee /tmp/gelia-a331-final-mysql.log
```

El soporte nuevo evita cargar `.env` y fija host/base/usuarios sintéticos; exige la variable de activación de prueba. Sin ella, las clases MySQL se omiten explícitamente, sin sustituirlas por SQLite. Los contenedores no usaron volúmenes persistentes ni puertos publicados.

### SQLite y comparación

Se usó el mismo contenedor PHP con `--network none`, sin variables `PDV_MEDICION_*`, repositorio de solo lectura y tmpfs. `phpunit.xml` fuerza SQLite `:memory:`. Comando interno final:

```bash
php vendor/bin/phpunit --do-not-cache-result \
  --filter 'AperturaMedicionPdvTest|ContratoOperativoMedicionPdvTest|CicloAtencionTurnosPdvTest|JornadaDisponibilidadOperacionPdvTest|PausaOperacionPdvTest|GestionEquipoOperativoPdvTest::test_(gerente_activa|vendedor_sin_permiso|gerente_no_puede|doble_activacion)' \
  tests/Feature/PuntoVenta/AperturaMedicionPdvTest.php \
  tests/Feature/PuntoVenta/ContratoOperativoMedicionPdvTest.php \
  tests/Feature/PuntoVenta/CicloAtencionTurnosPdvTest.php \
  tests/Feature/PuntoVenta/JornadaDisponibilidadOperacionPdvTest.php \
  tests/Feature/PuntoVenta/PausaOperacionPdvTest.php \
  tests/Feature/PuntoVenta/GestionEquipoOperativoPdvTest.php
```

La ejecución ampliada utilizó esos seis archivos sin `--filter`. La comparación anterior utilizó solo `GestionEquipoOperativoPdvTest.php`, añadiendo al contenedor el montaje:

```bash
--mount type=bind,source=/tmp/gelia-a331-registrador-anterior.php,target=/var/www/html/app/Services/PuntoVenta/Operacion/RegistrarTransicionMedicionPdvService.php,readonly
```

Registros temporales: `/tmp/gelia-a331-final-mysql.log`, `/tmp/gelia-a331-final-sqlite.log` (ampliada con errores), `/tmp/gelia-a331-gerencia-anterior.log` y `/tmp/gelia-a331-final-focalizada.log`. No contienen datos productivos.

Omisiones explícitas: no suite global, pruebas de carga, caída durante commit, matrices de todos los escritores, despliegue con credenciales reales, interfaces móviles/broadcasts completos ni los diez diagnósticos pendientes de A3. Ninguna omisión se presenta como aprobada.

## 7. Archivos modificados

Único archivo productivo alterado respecto del inicio de A3.3.1:

- `app/Services/PuntoVenta/Operacion/RegistrarTransicionMedicionPdvService.php`: dos lecturas compartidas y comentario explicativo; sin cambio de contrato.

Archivos nuevos:

- `tests/Feature/PuntoVenta/ConcurrenciaAperturaMedicionMySqlTest.php`.
- `tests/Feature/PuntoVenta/ValidacionTecnicaMedicionMySqlTest.php`.
- `tests/Support/PuntoVenta/MySqlMedicionA331.php`.
- `tests/Support/PuntoVenta/apertura_medicion_worker.php`.
- Este informe.

Se compararon hashes de los **26 archivos previos** modificados/no versionados presentes al comenzar: solamente cambió el registrador. A1, A2, A3.2, sus pruebas/documentos y los diez diagnósticos A3 conservan su contenido inicial. La migración productiva y el servicio de apertura no se modificaron en este checkpoint.

## 8. Riesgos pendientes

1. **Concurrencia legada:** las esperas de rango pueden afectar usuarios distintos. Se observó además un deadlock con medición desactivada. Requiere un análisis acotado posterior si se busca reducir contención o definir reintentos automáticos; no se alteró esa política.
2. **Regresión gerencial:** siete casos de consulta de equipo siguen fallando antes/después. No se certifica esa clase completa. Deben revisarse por separado antes de una aceptación funcional integral.
3. **DDL parcial:** falta de privilegios puede dejar una tabla sin protección completa. No basta con que exista; hay que comprobar CHECK, FK y triggers antes de habilitar la cohorte.
4. **Administración privilegiada y retención:** no se cambió la política de conservación, ni se autorizaron purgas. La protección depende también de separar privilegios de aplicación y DDL.
5. **Cobertura:** V1 solo registra aperturas nuevas. No hay certificación histórica completa, métricas V2, backfills ni cobertura de otros escritores.

## 9. Requisitos de un despliegue futuro

- Autorización explícita de despliegue y, por separado, de activación de cohorte. Mantener `desactivada` y lista vacía mientras tanto; ambos defaults se comprobaron en la aplicación nueva.
- MySQL compatible con lo ensayado, revisión previa del rol DDL, binlog y permisos TRIGGER. No copiar cambios de variables globales del ensayo a producción sin evaluación del administrador.
- Aplicar la migración antes de activar escritores y verificar estructura completa. Ante fallo parcial, detener activación y reconciliar sin eliminar evidencia.
- Rol de aplicación con SELECT/INSERT sobre bitácora, permisos operativos y capacidad de bloqueo de usuario ensayados; sin DROP/ALTER/TRIGGER para la aplicación. El perfil comprobado no equivale a certificar todos los módulos del sistema.
- Reversión funcional mediante desactivación, conservando historia. No ejecutar `down()` sobre una bitácora con evidencia.
- Revisar/aceptar expresamente la contención legada y los errores gerenciales pendientes antes de dar aceptación operativa integral.

Al terminar se retiraron únicamente los recursos temporales de este checkpoint:

```bash
docker rm -f gelia-a331-mysql-20261014
docker network rm gelia-a331-20261014
```

## 10. Estado final

**APROBADO CON RESTRICCIONES.**

- **¿V0/V1 son técnicamente consistentes?** Sí, en los escenarios verificados, con la corrección local y las limitaciones enumeradas.
- **¿Hay problemas de concurrencia que corregir?** Se conserva una contención de rango legada y la posibilidad de errores transaccionales recuperables; no se observó pérdida ni duplicación de hechos en los casos ejecutados. No se declara ausencia de deadlocks.
- **¿La migración es compatible con el proyecto?** Sí: secuencia completa de 423 migraciones aprobada sobre MySQL nuevo. La instalación depende de privilegios y comprobación del estado final.
- **¿La evidencia está protegida?** Sí frente al usuario de aplicación ensayado y DML ordinario; el administrador DDL y la política de retención siguen siendo controles necesarios.
- **¿Se puede comenzar V2?** Puede considerarse desarrollo aislado después de revisar/aceptar estas restricciones y autorizarlo expresamente. Este resultado no aprueba activación operativa ni declara resuelta toda la regresión gerencial.

## 11. Recomendación para V2

Revisar este informe y decidir primero cómo abordar la contención y los siete errores gerenciales. Si se aceptan como pendientes de alcance separado, V2 puede comenzar únicamente con nueva autorización y manteniendo la instrumentación productiva desactivada. Incorporar barreras, lectura actual y permisos limitados en las pruebas de sus futuros escritores.

No se inició V2, ninguna métrica ni una nueva fase. Se detiene el trabajo al concluir este checkpoint.

# GELIA — Checkpoint A3.3: implementación local V0 + V1

Fecha: 2026-10-14. Alcance: infraestructura histórica y apertura de jornadas.
Referencias: `CHECKPOINT_A3_1_CONTRATO_MEDICION_PDV.md` y `CHECKPOINT_A3_2_PREPARACION_INSTRUMENTACION_PDV.md`.

## 1. Resumen ejecutivo

V0 y V1 implementados localmente y listos para revisión técnica. La migración se comprobó en MySQL 8.4 efímero antes de integrar V1. La instrumentación permanece **desactivada**, sin sucursales habilitadas por defecto. No se desplegó ni se migró ninguna base persistente; no se realizaron commits, push ni backfills.

Resultados finales: **15 pruebas / 70 aserciones en MySQL**, y **66 pruebas / 270 aserciones en SQLite** con regresión focalizada. Sin errores ni pruebas incompletas en estas ejecuciones. Las pruebas diagnósticas pendientes de A3 se conservan y no forman parte de estos resultados.

## 2. Arquitectura efectivamente implementada

`AbrirJornadaPdvService → configuración de cohorte → jornada + intervalo → RegistrarTransicionMedicionPdvService → pdv_medicion_eventos`, todo dentro de la transacción operativa existente. El registro es síncrono, anterior al `JornadaAbierta::dispatch` vigente; no depende de un listener posterior al commit.

El catálogo versionado delimita lo admitido. El modelo y los triggers impiden actualizaciones y eliminaciones ordinarias de evidencia. La tabla admite referencias futuras; el registrador actual escribe exclusivamente agregados de persona y los dos tipos descritos en §5.

## 3. Migración y estructura final de la tabla

Migración aditiva: `database/migrations/2026_10_14_180000_create_pdv_medicion_eventos_table.php`. Objetivo comprobado contra `compose.yaml`: `mysql:8.4`, InnoDB. Los identificadores referenciados son BIGINT UNSIGNED, compatibles con `foreignId`.

| Grupo | Columnas finales |
|---|---|
| Identidad y contrato | `id`, `evento_uuid`, `esquema_version`, `contrato_version`, `tipo` |
| Agregado | `agregado_tipo`, `agregado_id`, `secuencia` |
| Ámbito y actor | `user_id`, `actor_id`, `actor_tipo`, `sucursal_id`, `origen` |
| Referencias operativas | `jornada_id`, `turno_id`, `atencion_id`, `intervalo_id`, `motivo_pausa_id`, `configuracion_id` |
| Temporalidad | `efectivo_at`, `registrado_at`, `zona_horaria`, `fecha_operativa` |
| Trazabilidad | `sesion_uuid`, `correlacion_uuid`, `causado_por_id`, `corrige_evento_id`, `idempotency_key`, `comando_hash` |
| Contexto mínimo | JSON `estado_anterior`, `estado_nuevo`, `contexto` |

FK reales a `users`, `sucursales`, `pdv_jornadas`, `pdv_turnos`, `pdv_turno_atenciones`, `pdv_intervalos_operativos`, `pdv_motivos_pausa`, `configuraciones_sistema` y autorreferencias, todas con RESTRICT. No se alteraron tablas anteriores.

Unicidad por UUID, clave de idempotencia y `(agregado_tipo, agregado_id, secuencia)`. Índices de usuario/fecha efectiva/id, sucursal/fecha efectiva/id, jornada/fecha efectiva/id, fecha registrada/id y correlación; Laravel añade los necesarios para FK.

Tres CHECK: versiones/secuencia positivas, actor humano/sistema coherente y correspondencia del agregado con sus identificadores. MySQL verificó los CHECK, dos triggers de inmutabilidad, índices, FK y precisión temporal 6. SQLite utiliza triggers equivalentes para las comprobaciones de inserción.

Las fechas históricas se guardan en UTC, DATETIME(6). `registrado_at` procede de `UTC_TIMESTAMP(6)` del servidor, independiente de la fecha efectiva y del reloj simulado de Laravel. En SQLite el valor por defecto tiene precisión de segundos. Los extremos operativos existentes siguen con su precisión original de segundos: no se inventan microsegundos. La fecha de registro representa inserción, **no orden de commit**.

### Retención administrativa

Se inspeccionaron `User`, su archivo administrativo, `CatalogoController::destroySucursal` y las FK operativas anteriores. Archivar usuarios usa soft delete; no elimina su identidad referenciada. El borrado físico de sucursales con jornadas ya estaba restringido. La prueba MySQL demuestra que archivar al actor conserva el evento y que borrarlo físicamente resulta bloqueado por la nueva FK. No se identificó una operación administrativa legítima vigente que obligara a cambiar una regla para V1.

Las futuras purgas físicas de actores y referencias instrumentadas requerirán una política explícita. Las columnas futuras de motivos/configuración permanecen nulas en V1. No se pretende resolver ahora la retención de esas futuras etapas. `down()` rechaza eliminar una bitácora con filas; no borra evidencia para permitir una reversión.

## 4. Modelo y registrador

`MedicionEventoPdv` interpreta fechas como UTC, convierte JSON, desactiva timestamps Eloquent y rechaza update/delete. Los triggers protegen también las escrituras directas ordinarias.

`RegistrarTransicionMedicionPdvService` exige una transacción abierta. Valida versión, tipo, origen, actor real, pertenencia de jornada/intervalo al empleado y sucursal, y referencias opcionales de turno/atención. La apertura exige que fecha efectiva, apertura persistida e inicio del intervalo coincidan. No acepta contexto libre de apertura.

Para sistema/job se admite `actor_id = null` con `actor_tipo = sistema`; el empleado afectado sigue siendo real. Personal exige actor igual al empleado; gerencial exige actor diferente. No contiene permisos propios ni es un endpoint: la autorización continúa en los servicios operativos actuales.

## 5. Catálogo disponible

Versión 1 en `CatalogoEventosMedicionPdv`:

- `jornada.abierta`: única transición emitida automáticamente por V1.
- `medicion.correccion`: anotación adicional con motivo y referencia a un evento del mismo usuario, sucursal y jornada; disponible y probada en el registrador. No tiene interfaz ni escritor operativo automático. No recalcula ni sustituye tiempos.

Orígenes admitidos: `personal`, `gerencial`, `sistema`, `job`. Los eventos de otras fases todavía se rechazan.

## 6. Idempotencia y concurrencia

Se bloquea una fila estable de `users` con `SELECT … FOR UPDATE` antes del primer evento, incluso cuando todavía no existe historial. Bajo ese bloqueo se obtiene la última secuencia mediante lectura actual bloqueante y se suma uno. No se usa un MAX sin serialización. La secuencia es global por persona, incluso entre sucursales; no ordena agregados distintos ni equivale al orden de commits.

La clave se almacena como SHA-256; otra huella SHA-256 contiene el comando normalizado y ordenado recursivamente. Repetir exactamente el comando devuelve el evento existente. Misma clave con otro contenido produce un error explícito de validación. Hay unicidad SQL y recuperación de colisión por clave con lectura actual tras un INSERT competidor; otra colisión SQL se propaga. Sesión y correlación forman parte del contenido: el reintento directo del registrador debe conservarlas.

V1 utiliza la clave determinista `pdv:medicion:apertura:{jornada_id}`. Las llamadas repetidas del servicio retornan la jornada existente antes de generar UUID o registrar otra apertura. Las lecturas bloqueantes de jornada/intervalo se habilitan solo en cohortes instrumentadas para evitar snapshots anteriores al bloqueo en REPEATABLE READ.

**Evidencia concurrente concreta:** dos conexiones con transacciones solapadas; una registra y conserva el bloqueo del usuario, la otra intenta adquirirlo y recibe timeout 1205. Esto demuestra exclusión del agregado. No demuestra dos comandos completos de apertura en paralelo, ni rendimiento, ausencia de deadlocks o carreras con todos los escritores legados. Los reintentos de apertura y las secuencias se probaron de forma sucesiva.

## 7. Activación y estado inicial

Se reutilizó la configuración Laravel existente; no había necesidad de añadir un servicio externo de flags.

- `PDV_MEDICION_ESTADO`: `desactivada` por defecto; admite `activa` y `suspendida`.
- `PDV_MEDICION_SUCURSALES`: lista explícita de identificadores separados por comas, vacía por defecto. Solo `activa` más pertenencia a la lista habilitan registro.
- Un estado desconocido se rechaza; desactivar/suspender no borra evidencia.
- Sin cobertura, la apertura no llama al registrador ni añade bloqueos de medición.

No se modificaron variables de despliegue ni se activó producción. La activación futura exige autorización posterior y aplicar la migración previamente. Los eventos y el proveedor de configuración declaran `transiciones: [jornada.abierta]`, `completa: false`, `metricas_v2: false`. No existe certificación de cobertura integral ni un reporte V2.

## 8. Integración real con apertura

Solo `abrirParaUsuario` de `AbrirJornadaPdvService` incorpora escritura histórica. Conserva las validaciones y autorizaciones previas de `ejecutar` y del flujo gerencial. Después de crear jornada e intervalo, registra identificadores reales, actor/origen, fecha efectiva persistida, zona y fecha operativa, sesión UUID nueva y correlación UUID. La sesión representa esa apertura operativa; no copia el identificador de autenticación web.

Si devuelve una jornada existente, no registra una nueva apertura. Activar posteriormente tampoco crea un evento para una jornada previa. No se instrumentó `reactivarParaUsuario`.

Los llamadores reales de `abrirParaUsuario` encontrados son apertura personal y `GestionarEquipoOperativoPdvService::activar`. No existe una apertura individual automática sin actor en ese flujo: no se cambió su firma para fabricar un caso. Sistema/job funciona y está probado en V0, pero no hay productor automático V1. La apertura horaria de sucursal es otra operación y queda fuera del alcance.

## 9. Pruebas V0 y resultados

Puerta previa a V1: **11 pruebas / 50 aserciones**, MySQL 8.4 aislado; aprobada. Después de esa puerta se integró V1 y se ampliaron controles de transacción obligatoria, retención y coherencia del origen.

Ejecución final de `MedicionPdvMySqlTest`: **15 pruebas / 70 aserciones**, 3.619 s, aprobada. Incluye 13 casos V0 y 2 V1; no se suman estas cifras a la puerta previa como casos nuevos.

Valida estructura del motor, actor humano y sistema, relojes independientes, idempotencia/conflicto, referencias, secuencia, corrección adicional, modelo y SQL inmutables, CHECK/FK, rollback, bloqueo entre conexiones, retención, rechazo de registro fuera de transacción y protección de `down()`.

### Entorno y comandos reales

Imágenes locales `mysql:8.4` y `sail-8.4/app:latest`, PHP 8.4.25, PHPUnit 12.5.25. Red Docker interna sin puertos publicados; MySQL con datos exclusivamente en tmpfs. Ninguna conexión a bases existentes. El arnés crea padres mínimos de FK y aplica las migraciones operativas reales necesarias más la nueva migración. **No equivale a ejecutar todas las migraciones históricas en MySQL.**

```bash
docker network create --internal gelia-a33-20261014
docker run -d --name gelia-a33-mysql-20261014 \
  --network gelia-a33-20261014 --network-alias mysql-a33 \
  --tmpfs /var/lib/mysql:rw \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_ROOT_HOST=% \
  -e MYSQL_DATABASE=gelia_medicion_a33_test mysql:8.4
docker exec gelia-a33-mysql-20261014 mysqladmin ping --silent

docker run --rm --network gelia-a33-20261014 --entrypoint /bin/sh \
  --mount type=bind,source=/home/ti-admin/gelianv,target=/var/www/html,readonly \
  --tmpfs /var/www/html/storage:mode=1777 --tmpfs /tmp:mode=1777 \
  -w /var/www/html -e PDV_MEDICION_MYSQL_TEST=1 -e APP_ENV=testing \
  -e APP_CONFIG_CACHE=/tmp/gelia-a33-config.php \
  -e APP_EVENTS_CACHE=/tmp/gelia-a33-events.php \
  -e APP_ROUTES_CACHE=/tmp/gelia-a33-routes.php \
  sail-8.4/app:latest -c 'mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
exec php vendor/bin/phpunit --do-not-cache-result tests/Feature/PuntoVenta/MedicionPdvMySqlTest.php'
```

La prueba sobrescribe la conexión de pruebas hacia `mysql-a33/gelia_medicion_a33_test`; sin la variable explícita marca skip y nunca sustituye MySQL por SQLite silenciosamente.

## 10. Pruebas V1 y resultados

`AperturaMedicionPdvTest`: **11 casos**, incluidos los tres estados sin cobertura. Comprueba apertura personal con permisos, activación por el servicio gerencial real, referencias, fechas, UUID, secuencia, reintentos, jornada preexistente, inactividad/suspensión/cohorte, fallo real de INSERT, fallo de autorización, aislamiento y cobertura parcial. Dos casos MySQL adicionales ejercitan el servicio de apertura real y su rollback después de insertar el evento.

Comando final de regresión focalizada, **66 pruebas / 270 aserciones**, 37.137 s:

```bash
docker run --rm --network none --entrypoint /bin/sh \
  --mount type=bind,source=/home/ti-admin/gelianv,target=/var/www/html,readonly \
  --tmpfs /var/www/html/storage:mode=1777 --tmpfs /tmp:mode=1777 \
  -w /var/www/html \
  -e APP_CONFIG_CACHE=/tmp/gelia-a33-config.php \
  -e APP_EVENTS_CACHE=/tmp/gelia-a33-events.php \
  -e APP_ROUTES_CACHE=/tmp/gelia-a33-routes.php \
  sail-8.4/app:latest -c 'mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
exec php vendor/bin/phpunit --do-not-cache-result tests/Feature/PuntoVenta/AperturaMedicionPdvTest.php tests/Feature/PuntoVenta/ContratoOperativoMedicionPdvTest.php tests/Feature/PuntoVenta/CicloAtencionTurnosPdvTest.php tests/Feature/PuntoVenta/JornadaDisponibilidadOperacionPdvTest.php tests/Feature/PuntoVenta/PausaOperacionPdvTest.php'
```

SQLite `:memory:` según `phpunit.xml`, con migraciones del proyecto en memoria. También se ejecutó `php -l` en la imagen PHP sobre los diez archivos PHP de A3.3 enumerados en §12: todos sin errores. `git diff --check` sin errores. No se ejecutó una suite global.

## 11. Evidencia de atomicidad y rollback

| Caso probado | Resultado |
|---|---|
| Apertura correcta | Jornada, intervalo y evento visibles juntos en la transacción; reintento devuelve el mismo resultado |
| Falta de permiso | Sin jornada, evento ni dispatch |
| Trigger SQLite rechaza el INSERT del evento | Sin jornada, intervalo, evento ni dispatch |
| Registrador MySQL inserta y después lanza excepción controlada | Se revierten jornada, intervalo y evento; sin dispatch |
| Fallo operativo posterior en transacción V0 | Se revierte evento y modificación de versión de jornada |
| Reintento idéntico del registrador | Un solo evento |
| Misma clave, otra correlación | Validación explícita; no duplica |

Las pruebas usan transacciones de ensayo y verifican visibilidad/rollback; no simulan recuperación frente a caída del servidor durante commit. Los eventos/notificaciones anteriores conservan su ubicación y comportamiento; no se rediseñó su entrega.

## 12. Archivos creados y modificados por A3.3

Creados:

- `database/migrations/2026_10_14_180000_create_pdv_medicion_eventos_table.php`.
- `app/Models/PuntoVenta/MedicionEventoPdv.php`.
- `app/Support/PuntoVenta/Medicion/CatalogoEventosMedicionPdv.php`.
- `app/Services/PuntoVenta/Operacion/RegistrarTransicionMedicionPdvService.php`.
- `app/Services/PuntoVenta/Operacion/InstrumentacionMedicionPdvConfig.php`.
- `tests/Feature/PuntoVenta/MedicionPdvMySqlTest.php`.
- `tests/Feature/PuntoVenta/AperturaMedicionPdvTest.php`.
- Este informe.

Modificados:

- `config/punto_venta.php`: activación independiente de medición.
- `app/Services/PuntoVenta/Operacion/AbrirJornadaPdvService.php`: registro transaccional condicionado.
- `app/Services/PuntoVenta/Operacion/ResuelveConcurrenciaJornadaPdv.php`: opción de lectura bloqueante; por defecto conserva la lectura anterior. Solo la apertura instrumentada pasa `true`.

El árbol de trabajo ya contenía los cambios y documentos A1/A2/A3/A3.1/A3.2; no deben confundirse con entregables nuevos de A3.3.

## 13. Compatibilidad con funcionalidades existentes

Regresión aprobada del contrato A3.2, ciclo de atención, disponibilidad, cierre y pausas, con la instrumentación desactivada. La prueba nueva comprueba también apertura gerencial con instrumentación activa y dispatch personal sin duplicación.

No se cambiaron permisos, estados, FIFO, reglas de asignación, enfriamiento, endpoints, trabajos, listeners, móviles, React ni resguardos. No se afirma haber ejecutado pruebas completas de interfaces móviles, horarios, broadcasts o integración externa. La corrección gerencial de enfriamiento y las correcciones temporales anteriores permanecen vigentes.

## 14. Riesgos y limitaciones

- Pendiente: ensayo de dos aperturas completas en procesos paralelos con barreras, colisiones de claves entre agregados, deadlocks, carga y convivencia con escritores legados. El bloqueo de usuario está probado; no es certificación de concurrencia integral.
- La nueva ruta activa añade bloqueos y escritura síncrona. Un fallo de medición impide confirmar la apertura, como exige el contrato. La cohorte debe mantenerse deshabilitada hasta autorizar el piloto.
- Triggers requieren permisos DDL adecuados al migrar. No se verificaron privilegios del usuario de despliegue ni compatibilidad con otras versiones de MySQL. MySQL DDL no es transaccional: una migración fallida exige inspección antes de reintentar.
- La inmutabilidad bloquea UPDATE/DELETE ordinarios, no a un administrador capaz de retirar triggers, hacer TRUNCATE o cambiar el esquema. Endurecer privilegios y recuperación es una tarea de despliegue pendiente.
- RESTRICT conserva referencias y bloquea purgas físicas. No se implantó una política global de conservación ni anonimización. La reversión con evidencia consiste primero en desactivar; no permite borrar la bitácora mediante `down()`.
- No hay historial de activaciones/suspensiones persistido ni certificador de ventanas; las declaraciones de cobertura son deliberadamente parciales.
- Las fechas operativas legadas conservan su interpretación vigente; no se reformatearon sus tablas ni reconstruyeron husos históricos. Aún no hay una proyección temporal que consuma esta evidencia.

## 15. Elementos todavía no implementados

Sin instrumentación de reactivación, cierre, reserva, inicio, transferencia, reatención, pausa, cooldown, cambios de sucursal o configuración. Sin reconstrucción histórica, proyección de intervalos, certificación de cobertura, métricas O-04/O-05/O-06/O-07 V2, dashboard, exportaciones nuevas ni productores automáticos de apertura individual.

Las pruebas diagnósticas A3 se mantienen sin convertir sus pendientes en éxitos ni eliminarlos; no se ejecutaron de nuevo por no implementar esas métricas. Se verificó que su archivo conserva el hash previo `0350db6e71f3e2f0ba5675a750c9e36d4a3216429c8726a07f8c136fb1a76e6c`; también se conserva el hash previo del test A3.2 `9d6d829a5019c944c3139199cc2e33f33ebe8987635a7902aa768cef8b9766e7`.

## 16. Estado final del checkpoint

**V0 y V1 implementados y probados localmente; preparados para revisión técnica, sin aprobación de producción.** Solo se emite automáticamente `jornada.abierta` al crear realmente una jornada en una cohorte habilitada. Estado inicial desactivado. Los resultados exitosos se distinguen de las validaciones de concurrencia/compatibilidad pendientes anteriores.

El entorno MySQL fue temporal, sin volumen persistente, y se retiró al finalizar con:

```bash
docker rm -f gelia-a33-mysql-20261014
docker network rm gelia-a33-20261014
```

No se ejecutó `migrate` contra bases existentes ni se usó Docker Compose para levantar servicios del proyecto. No se realizaron commits, push, despliegues ni cambios productivos.

## 17. Siguiente etapa recomendada

Revisar este cambio y cerrar el ensayo de concurrencia de comandos completos, privilegios y retención antes de autorizar un piloto. Una nueva etapa de instrumentación requerirá aprobación expresa; V2, métricas y rediseño quedan detenidos.

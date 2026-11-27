# Multienvío nacional: prueba de concurrencia real en MySQL (2026-11-27)

Estado: DONE. No apareció ningún bug en los servicios. Lo único que hubo que corregir fue el arnés de la prueba (ver «RED»). No se hizo commit.

## Archivos
- Nuevo: `tests/Feature/ControlPedidos/MultienvioNacionalConcurrenciaMysqlTest.php`, un TestCase de PHPUnit simple que se omite salvo que `MULTIENVIO_MYSQL=1`.
- Nuevo: `tests/Support/ControlPedidos/multienvio_nacional_concurrencia.php`, el script que corre los roles `setup|sostener|competir|bloqueo|afirmar|limpiar` para los casos `confirmar|activar`.
- Editado: `docs/ai/PRUEBAS.md`, con una línea nueva y la fila de Multienvío actualizada.
- Editado: `docs/ai/modulos/REGISTRO_PEDIDOS.md`, en la nota de pendientes: la concurrencia y el rollback en MySQL constan como ejecutados.
- Servicios: sin cambios.

## Cómo resuelve la conexión
`phpunit.xml` fuerza `DB_CONNECTION=sqlite` y `DB_DATABASE=:memory:`, y los subprocesos heredan esos valores. El script lee el `.env` de la aplicación con `Dotenv::parse` y hace `putenv` de `DB_*` antes de arrancar la app. Igual que el arnés existente, después vuelve a fijar `database.connections.mysql.*` en la configuración. Se niega a correr si el host no es `mysql`. Las credenciales nunca se imprimen ni se escriben. También fija `broadcasting=null`, `queue=sync` y `cache=array`, y redirige los discos `public` y `local` a `/tmp/multienvio-mysql-<id>`, de modo que las copias del soporte no tocan el almacenamiento real.

## Detección de la espera de bloqueo (difiere del arnés existente)
El usuario `gelianv` no tiene permiso sobre `performance_schema` (error 1142 en `data_lock_waits` y `threads`). Por eso el sondeo `bloqueo` consulta la sesión del proceso `competir` en `information_schema.PROCESSLIST`, buscándola por su `CONNECTION_ID()`. La prueba exige:
- COMMAND `Query` o `Execute`;
- un `... for update` sobre el pedido sintético;
- TIME de al menos 1 s.

Además, `afirmar` exige que la segunda llamada haya tardado al menos 1 s y que haya terminado después de escribirse el archivo de liberación.

## Escenarios
- **(a) confirmar.** `setup` crea los datos sintéticos: el usuario, una paquetería y un tipo de caja propios y un pedido nacional de origen con 3 cajas, un PDF de soporte, una evidencia de caja y una dirección manual. Con eso llama a `RegistrarDecision activar` y a `GuardarBorrador`, con 3 envíos que suman 1500. `sostener` toma `FOR UPDATE` sobre el pedido de origen, espera la liberación y entonces corre `ConfirmarMultienvio` dentro de la transacción. `competir` corre `ConfirmarMultienvio` con el mismo grupo y la misma revisión. Asserts:
  - 1 solo grupo, en estado `activo`, y ambas llamadas devuelven ese mismo id;
  - exactamente 3 miembros con orden [1,2,3], el origen como envío 1, y exactamente 3 pedidos del vendedor sintético (ningún duplicado);
  - la mercancía suma 1500 y los folios no se repiten;
  - las 3 cajas quedan una por envío y en el orden esperado;
  - cada envío tiene 1 caja activa, 1 historial `multienvio_confirmado` y 1 referencia vigente.
- **(b) activar.** El mismo patrón con `RegistrarDecisionMultienvio 'activar'` en las dos llamadas. Asserts: 1 grupo (`configurando`), las dos llamadas devuelven el mismo id, no hay excepción (tampoco una violación de unicidad) y no se crea ningún pedido.
- **(c)** Se cumple en los dos escenarios: `competir` quedó bloqueado en `select * from pedidos_bma where id = <sintético> ... for update` (estado `statistics`) hasta la liberación, con una espera medida de unos 1,62 a 1,77 s.

## Comandos y resultados
1. `migrate --path=…100000 --path=…100100 --force --no-interaction --pretend`: solo `create table multienvios_nacionales` (con FK, unique e índice) y `alter table pedidos_bma` (con `multienvio_nacional_id`, la FK, `multienvio_orden`, `pedidos_bma_multienvio_orden_unique` y `pedidos_bma_multienvio_nacional_idx`). No tocó nada más.
2. `migrate` con esos mismos dos `--path`: DONE las dos, en el batch 20. Las 5 migraciones pendientes del compañero (2026_11_25_200000, 205000, 210000, 2026_11_26_120000, 130000) siguieron en Pending.
3. **(d)** `migrate:rollback` con esos dos `--path`: primero 100100 y después 100000, DONE las dos. Comprobación: `tabla=no col=no orden=no idx=0`. Re-aplicadas: DONE las dos. Esto confirma en MySQL 8.4.13 que el orden de `down()` es el correcto (primero la FK y luego los índices).
4. Prueba, corrida 1: **RED del arnés**, 2 fallos. El sondeo exigía COMMAND=`Query`, pero PDO usa sentencias preparadas y la sesión en espera mostraba `"command":"Execute","time":2,"state":"statistics"`. La espera real sí estaba ocurriendo. Se aceptó `Execute`. La limpieza de esta corrida terminó bien (conteos 61/9).
5. Corridas 2 y 3: las aserciones pasaron (28), pero quedaron 2 «warnings» porque un `@file_get_contents` de un archivo que aún no existía aparecía en el mensaje de un assert. Se reemplazó por una lectura que primero comprueba que el archivo existe.
6. Corridas 4 y 5, consecutivas: **GREEN**, `2 passed (28 assertions)` en las dos, unos 6,7 s.
   - Ejemplo: `[confirmar] afirmar={"ok":true,"grupo_id":7,"segundos_espera":1.738}`.
   - Ejemplo: `[activar] afirmar={"ok":true,"grupo_id":8,"segundos_espera":1.737}`.
7. Sin la variable de entorno: `2 skipped` («Defina MULTIENVIO_MYSQL=1 …»).
8. Filtro `MultienvioNacional|MobileEspejoRutasTest` en SQLite: `113 passed, 2 skipped (796 assertions)`. Los 2 omitidos son la prueba nueva.
9. Rollback final de las dos migraciones: DONE. `migrate:status --pending` muestra las 7 pendientes, como al inicio (las 5 del compañero y las 2 de Multienvío). `max(batch)` volvió a 19.

## Evidencia de limpieza
- Cada corrida imprime el JSON de `limpiar`. En todas: `"quedan":[]`, `"antes":{"pedidos":61,"users":9}` y `"despues":{"pedidos":61,"users":9}`.
- Ejemplo del caso confirmar: los pedidos [74,75,76], el usuario 17 y el grupo 7, con `borrados` igual a {documentos 4, cajas 3, historial 3, referencias 3, direcciones 3, multienvios 1, pedidos 3, users 1, paqueterías 1, tipos de caja 1}.
- La limpieza borra por clave primaria, en orden seguro para las FK. Antes de borrar pedidos o el usuario recorre todas las tablas con FK hacia `pedidos_bma` y `users` (según `information_schema`) y no borra nada si queda alguna referencia.

Comprobación final, independiente, con tinker:

| Comprobación | Resultado |
|---|---|
| `pedidos_bma` | 61 filas, id máximo 61 |
| `users` | 9 filas, id máximo 10 (igual que al inicio) |
| Grupos `multienvios_nacionales` | 0 (antes del rollback final) |
| Pedidos con `multienvio_nacional_id` | 0 |
| Filas con el prefijo `MULTIENVIO-TEST-` | 0 en pedidos, usuarios, paqueterías y tipos de caja |
| Cajas, documentos, historial, referencias y direcciones con `pedido_bma_id > 61` | 0 en cada tabla |
| `storage/app/public/pedidos_bma/multienvio` | no existe |

Los dos directorios temporales de la corrida fallida, en el `/tmp` del contenedor, se borraron a mano. Solo contenían archivos del arnés. La prueba borra su directorio solo cuando termina bien.

## Observaciones
- Los contadores AUTO_INCREMENT de `pedidos_bma` y `users` (y de las demás tablas tocadas) avanzaron por las filas sintéticas ya borradas. Es inevitable sin una base aparte.
- Como cualquier operación normal de la app, mientras corre el servicio (unos ms dentro de `sostener`) los INSERT toman bloqueos S de FK sobre filas existentes de catálogo: el estatus `pesaje_respondido`, que solo se lee. La paquetería y el tipo de caja son propios. Durante los segundos de espera solo se bloquea el pedido sintético.
- `GenerarFolioPedidoBmaService` asignó a los envíos creados folios `PBMA-2026-xxxxx` consecutivos a los reales. Se borraron, así que esos números quedan libres otra vez.
- En el contenedor siguen corriendo `gelianv-queue-1` y `gelianv-scheduler-1` sobre la misma base. Las filas sintéticas existen unos 3 s por caso.
- Aviso del entorno, ajeno a la prueba: `.phpunit.result.cache` da permission denied en `/var/www/html`.
- No se tocó gelianv_db, gelianv_app ni ningún otro contenedor, no se ejecutó la suite completa contra MySQL y no se aplicaron migraciones ajenas.

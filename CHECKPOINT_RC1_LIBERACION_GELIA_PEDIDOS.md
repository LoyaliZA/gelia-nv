# CHECKPOINT RC1 — Liberación de Control de Pedidos

Fecha: 14 de octubre de 2026. Base de este árbol: `861cb19` (`main`), más cambios sin commit. No se hizo commit, push, reset, checkout ni migración sobre `gelia_nv`.

## Veredicto

**NO-GO.**

Q1, Q7 y Q2 conviven y pasan sus pruebas sobre este árbol (`main` y la base de Sail). No pueden liberarse mañana sobre el único entorno que los scripts de despliegue realmente operan. Ese entorno sirve otro checkout, con otro esquema, y no contiene estas correcciones.

No hay paquete parcial separable: Q7 necesita `numero_remision`, Q2 necesita `prioridad_md` y el código de destino usa `sucursal_destino_id`. Esas columnas no están en la base que atiende el stack de despliegue. Copiar solo los archivos de RC1 ahí dejaría código y esquema incompatibles.

## 1. Qué se auditó

Árbol de trabajo: `/home/ti-admin/gelianv`, rama `main`, commit `861cb19`. Cambios de Q1, Q7 y Q2 sin commit. `compose.yaml` también está modificado y queda fuera del corte.

El stack que ejecutan `deploy.sh`, `update.sh` y `update-safe.sh` no monta este árbol.

| Pieza | Este árbol (Sail) | Stack de los scripts |
| --- | --- | --- |
| Código montado | `/home/ti-admin/gelianv` | `/home/ti-admin/gelia-nv-test` |
| Contenedor PHP | `gelianv-laravel.test-1` | `gelianv_app` |
| Git | `main` @ `861cb19` | `develop` @ `1e9d3a63`, con cambios locales ajenos (plazos, Tiendanube) |
| `APP_ENV` / URL | desarrollo, `http://100.75.11.59:9080` | `production`, `https://gelianvtest.neobash.site` |
| MySQL | 8.4.9, base `laravel` | 8.0.44, base `gelia_nv` |
| Migraciones en disco / registradas | 424 archivos; la de Q2 ya está registrada en `laravel` | 276 archivos; 272 filas en `gelia_nv.migrations` |
| Pedidos | 36 en `laravel` | 6 en `gelia_nv` |
| `solicitud_preparacion_json` | presente, JSON, nullable; las 36 filas siguen en NULL | ausente |
| `sucursal_destino_id`, `prioridad_md`, `numero_remision` | presentes | ausentes en `pedidos_bma` |

`gelianv_app` tiene OPcache con `validate_timestamps=0`. Aunque alguien copiara archivos al checkout servido, PHP-FPM no los tomaría hasta un recarga. Hoy el archivo de Q2 no está en ese checkout.

El usuario de `gelia_nv` (`ti_admin_aromas`) tiene `ALL PRIVILEGES` sobre ese esquema, así que un `ALTER` no fallaría por permiso. El riesgo no es el privilegio: es aplicar el conjunto de migraciones de `main` sobre una base que no está en esa línea.

`storage` de `gelianv_app` es el volumen `gelia-nv-test_storage_data`, con dueño `gelia:1337` y modo que permite escritura del grupo. No se modificó.

## 2. Estado de cada paquete

Los tres siguen implementados. RC1 no reescribió sus reglas. El único cambio de comportamiento añadido en esta sesión es el rechazo explícito cuando la intención no puede persistirse.

### Q1 — carátulas

Sigue en `ConfirmarCaratulaColocadaService`: la closure captura `$caratulaId` y exige que el PDF exista en el disco antes de colocar. Confirmación de la vigente y rechazo de la obsoleta quedaron cubiertos por `ConfirmarCaratulaIdentidadTest` (14 pruebas), en verde dentro del lote de esta sesión.

### Q7 — remisión y guías

Sigue en `BusquedaDelegadoPedidoBma` y en la opción «Número de remisión» de `pedidosBmaStyles.js`. `BusquedaRemisionDelegadoTest` (9 pruebas) llegó a sus aserciones y pasó. El `Permission denied` de Q2 era el directorio de discos de prueba, no la búsqueda.

### Q2 — captura

La intención vive en `pedidos_bma.solicitud_preparacion_json`. Guardar un borrador no crea tarea, carátula, referencia oficial, traspaso ni resguardo PDV. Un histórico con JSON null no inventa municipio.

Corrección de RC1, en `PersistirIntencionPreparacionPedido`: si el request trae `solicitud_preparacion` y la columna no existe, el servicio lanza `RuntimeException` dentro de la transacción de alta o actualización. El autoguardado responde 422, sin `folio`. El guardado manual responde con flash de error, no de éxito. La transacción revierte también el resto de ese request. Quien opera ve «Servidor · error de validación» y el mensaje del servidor; la rama ya existente del modal no pinta «Servidor · {folio}» en un 422.

Si la captura de preparación va vacía, la clave no viaja y el borrador puede guardarse sin esa columna. En cuanto hay modalidad, folio o entrega, el guardado no se confirma hasta que el esquema existe.

## 3. Archivos del corte

Incluidos, respecto de `861cb19`:

| Archivo | Paquete |
| --- | --- |
| `app/Services/ControlPedidos/ConfirmarCaratulaColocadaService.php` | Q1 |
| `tests/Feature/ControlPedidos/ConfirmarCaratulaIdentidadTest.php` | Q1 |
| `app/Support/ControlPedidos/BusquedaDelegadoPedidoBma.php` | Q7 |
| `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js` | Q7, solo opciones y textos del buscador de Delegado |
| `tests/Feature/ControlPedidos/BusquedaRemisionDelegadoTest.php` | Q7 |
| `resources/js/Pages/ControlPedidos/Delegado/remisionBusqueda.test.jsx` | Q7 |
| `database/migrations/2026_10_14_231500_add_solicitud_preparacion_json_to_pedidos_bma.php` | Q2 |
| `app/Services/ControlPedidos/FusionarSolicitudPreparacionPedido.php` | Q2 |
| `app/Services/ControlPedidos/PersistirIntencionPreparacionPedido.php` | Q2, con el rechazo si falta la columna |
| `app/Models/ControlPedidos/PedidoBma.php` | Q2 |
| `app/Http/Requests/ControlPedidos/PedidoBmaRequestBase.php` | Q2 |
| `app/Services/ControlPedidos/ActualizarPedidoBmaService.php` | Q2 |
| `app/Services/ControlPedidos/CrearPedidoBmaService.php` | Q2 |
| `app/Services/ControlPedidos/ListarPedidosBmaService.php` | Q2 |
| `app/Http/Controllers/ControlPedidos/PedidoBmaController.php` | Q2, dos campos en la respuesta de autoguardado |
| `resources/js/Pages/ControlPedidos/Partials/capturaPreparacionPedido.js` | Q2 |
| `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx` | Q2, sin cambio de layout en RC1 |
| `resources/js/Pages/ControlPedidos/Index.jsx` | Q2, el aviso de borrador local respeta el usuario |
| `resources/js/Pages/ControlPedidos/Partials/modalPersistenciaContrato.test.jsx` | Q2 |
| `tests/Feature/ControlPedidos/BorradorPreparacionContratoTest.php` | Q2, más ausencia de columna, traspaso y resguardo |

Fuera del corte:

- `compose.yaml`: opción local `--log-bin-trust-function-creators=1` para triggers. Q1, Q7 y Q2 no la necesitan.
- Checkpoints P0, P1, A* y PDV. No son código de ejecución. No se reescribieron.
- El árbol sucio de `/home/ti-admin/gelia-nv-test` (plazos y Tiendanube).
- Reportes PDV. No se tocaron.

## 4. Pruebas

Aislamiento: PHPUnit del contenedor Sail, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `APP_ENV=testing`. PHP del contenedor: 8.4.25. El código nuevo no usa sintaxis posterior a 8.2. Vitest 4.1.11 en el host.

El fallo `Permission denied` al abrir `storage/framework/testing/disks/public/pedidos_bma` venía de ese directorio en modo `700`, creado por otro uid. Se amplió el permiso del árbol de discos de prueba dentro del contenedor Sail (`chmod -R a+rwx` sobre `storage/framework/testing/disks`). No se cambió Q7 ni ninguna regla de negocio. Si otro proceso vuelve a crear ese directorio como `700`, la suite puede volver a morir antes de las aserciones.

Lote final, en verde:

```sh
./vendor/bin/sail artisan test \
  tests/Feature/ControlPedidos/BorradorPreparacionContratoTest.php \
  tests/Feature/ControlPedidos/ConfirmarCaratulaIdentidadTest.php \
  tests/Feature/ControlPedidos/BusquedaRemisionDelegadoTest.php \
  tests/Unit/ControlPedidos/ValidarAlmacenSalidaPedidoTest.php \
  tests/Unit/ControlPedidos/DestinoSucursalPedidoBmaTest.php
```

40 pruebas, 363 aserciones.

```sh
npx vitest run \
  resources/js/Pages/ControlPedidos/Partials/modalPersistenciaContrato.test.jsx \
  resources/js/Pages/ControlPedidos/Delegado/remisionBusqueda.test.jsx \
  resources/js/Pages/ControlPedidos/Partials/destinoSucursalPedido.test.js
```

3 archivos, 15 pruebas, en verde. El modal montado es `ModalFormPedido`.

Correspondencia con los doce recorridos pedidos:

| Recorrido | Dónde quedó |
| --- | --- |
| 1–3. Alta, municipal completo y recuperación al reabrir | `test_autoguardar_conserva_intencion_municipal_sin_crear_tarea` y el caso de reapertura del modal en Vitest |
| 4. Borrador histórico sin inventar datos | `test_historico_sin_intencion_no_inventa_datos_municipales` |
| 5. Parcial sin borrar montos, saldos, almacén ni sucursal | `test_guardado_parcial_no_borra_montos_direccion_ni_intencion` |
| 6–7. Carátula vigente y obsoleta | `test_confirma_caratula_vigente_y_sincroniza_transicion_historial_y_cumplimiento`, `test_rechaza_una_generada_anterior_a_la_vigente` |
| 8–9. Captura, búsqueda y guía del pedido elegido | `test_captura_auxiliar_conserva_folios_y_referencia_remision`, `test_numero_remision_encuentra_a_y_conserva_el_criterio_en_respuesta`, `test_asignar_y_actualizar_por_id_no_modifica_b_ni_identidades` |
| 10. Autorizaciones | `test_endpoint_sin_permiso_retorna_403_sin_transicion`, `test_coincidir_no_elude_permiso_fase_pago_documento_o_guia_cliente`, `test_cambio_de_destino_se_audita_y_autoriza` |
| 11. Almacén solo receptor no sirve de salida; la sucursal sigue pudiendo ser destino | `ValidarAlmacenSalidaPedidoTest` y `DestinoSucursalPedidoBmaTest`. No se modificó esa regla (Q4) |
| 12. El borrador no crea tarea, traspaso ni resguardo | conteos en cero en el autoguardado municipal |

No apareció un fallo de integración distinto del permiso de disco, y ese quedó explicado. No se silenció ninguna aserción.

## 5. Migración Q2

Archivo: `database/migrations/2026_10_14_231500_add_solicitud_preparacion_json_to_pedidos_bma.php`.

`up()` añade `solicitud_preparacion_json` JSON nullable solo si no existe. No borra ni renombra. `down()` elimina esa columna y, con ella, las intenciones guardadas después del despliegue. No toca tareas, documentos ni referencias.

### MySQL 8.0.44, estructura de `gelia_nv`

Se creó la base desechable `rc1_q2_schema_check` en `gelianv_db`. `CREATE TABLE ... LIKE gelia_nv.pedidos_bma` más copia de las 6 filas. `gelia_nv` no se alteró.

- Antes: 74 columnas, sin la columna nueva, 6 filas.
- `ALTER` aditivo. El checksum de identidad, fecha, vendedor, estatus, almacén, costos, saldo y folio de remisión quedó igual (`MATCH`).
- Las 6 copias quedaron con JSON NULL.
- Una escritura de prueba en la copia devolvió `ENVIO_MUNICIPIO`, municipio `San Pedro`, tipo JSON `OBJECT` y `destinatario_es_cliente` falso. Las otras 5 siguieron en NULL.
- Un segundo `ADD COLUMN` falló con `ERROR 1060 Duplicate column name`. El PHP evita ese fallo con `hasColumn`.
- `DROP COLUMN` en la copia quitó solo la columna; las 6 filas siguieron. Eso demuestra que revertir la migración borra la intención y conserva el resto de la fila.
- Se eliminó `rc1_q2_schema_check`. `gelia_nv` sigue con 6 filas y sin la columna.

Esa prueba acredita el `ALTER` sobre la forma actual de `pedidos_bma` en `gelia_nv`. No acredita ejecutar el resto de migraciones de `main` ni arrancar el código de este árbol contra esa base.

### Modelo Laravel, MySQL 8.4.9 de Sail

Otra base desechable, con la forma de `laravel.pedidos_bma` y la columna retirada. El usuario `sail` no ve una base nueva hasta un `GRANT` explícito: sin ese permiso la lectura falla con `1044`. En `gelia_nv` el usuario de aplicación ya tiene privilegio de esquema; el `1044` es del ensayo, no de ese usuario.

Con el permiso de ensayo:

- Sin columna, guardar la intención lanza la excepción nueva. Folio y costo del renglón sintético no cambian.
- `up()` de la migración real crea la columna.
- Una segunda escritura, leída por el cast `array` del modelo, devolvió modalidad, municipio y `false`. El costo siguió en 40.

La primera tentativa de esa escritura disparó `PedidoBmaActualizado` y quiso insertar en `jobs` de la base desechable, que no tiene esa tabla. Es el observer que ya existe al actualizar un pedido, más el hecho de haber apuntado la conexión por defecto al esquema aislado. En la repetición se desconectó el dispatcher solo para leer el JSON. No es un defecto de Q2. La base `laravel` quedó con sus 36 pedidos.

La base de desarrollo `laravel` ya tiene registrada esta migración y la columna, con las 36 intenciones en NULL. Eso ocurrió antes de RC1. No se volvió a migrar. No es la base `gelia_nv`.

## 6. Frontend

No se cambió el diseño del modal. Vitest monta el modal real y comprueba modalidad, prioridad, folio, municipio, destinatario, teléfono, dirección, transporte, cobro y almacén al reabrir; el ida y vuelta de modalidad; y que un borrador local de otra persona no pisa lo que vino del servidor.

«Local · guardado» sigue siendo solo el navegador. «Servidor · {folio}» solo después de un HTTP correcto. Un 409 sigue siendo conflicto. Un 422, incluido el de columna ausente, no marca el servidor como guardado.

No se recorrió el modal con una sesión iniciada en el navegador. La verificación de controles es la del componente montado y la de la API de autoguardado.

## 7. Concurrencia que sigue abierta

Q3 no se implementó. Siguen sin cubrirse: revisión monotónica, un solo escritor, idempotencia durable, resolución del 409, una sola cola para guardado manual y automático, y una barrera antes de solicitar preparación. Dos guardados simultáneos pueden pisarse. RC1 no lo presenta como resuelto.

Tampoco se tocó Q4. La elegibilidad de almacenes queda como está.

## 8. Por qué no se despliega

`update.sh` y `update-safe.sh` hacen `git pull` en el directorio donde se lanzan y, después, `php artisan migrate --force` dentro de `gelianv_app`. Ese contenedor no ve este árbol. Lanzarlos desde aquí no instala Q1, Q7 ni Q2, y un pull en el checkout servido mezclaría `develop` con trabajo local ajeno.

Aunque se copiaran a mano los archivos de la sección 3 a `gelia-nv-test`:

- Q1 referencia un servicio de carátula que ese checkout no tiene en la misma forma (`ConfirmarCaratulaColocadaService.php` no está).
- Q7 lee `numero_remision`, columna ausente.
- Q2 lee y escribe `prioridad_md` y `sucursal_destino_id`, ausentes, y el modelo de este árbol declara esas columnas.
- `migrate --force` con las 424 migraciones de este árbol no es un corte pequeño ni está ensayado sobre `gelia_nv`.

Eso cumple la condición de no declarar GO: código y esquema incompatibles durante el despliegue, y la migración de Q2 no está aplicada ni debe aplicarse sola sobre una aplicación que no es este `main`.

OPcache en el stack servido no recarga solo. `rollback.sh` hace `git checkout` del commit anterior en el directorio donde se ejecuta y no revierte la base. No se usó.

## 9. Procedimiento, solo cuando el destino ya sea este `main`

No ejecutarlo contra `gelianvtest` ni contra `gelia_nv` con el checkout actual. Hace falta un destino cuyo código ya sea `861cb19` y cuyo `pedidos_bma` ya tenga `sucursal_destino_id`, `prioridad_md` y `numero_remision`. Ese destino no está identificado entre los contenedores en ejecución. Sail (`laravel`, 36 pedidos) se le parece en esquema, pero es la base de desarrollo y no se usó como producción.

Orden, el día que ese destino exista y haya autorización explícita:

1. Confirmar con `information_schema` que esas tres columnas existen y que `solicitud_preparacion_json` no existe. Si falta cualquiera de las tres, detenerse.
2. Respaldo con el mecanismo ya usado por los scripts: `backup_db_antes_de_migrar` (`php artisan db:backup` en el contenedor de aplicación). Si el respaldo falla, no migrar. Comprobar que el archivo aparece en `storage/app/backups/` y que se puede listar. No hace falta restaurarlo en el ensayo si el `ALTER` de la sección 5 ya se reprodujo; sí hace falta saber en qué servidor está el dump antes de tocar nada.
3. Aplicar solo esta migración, con el código anterior todavía en memoria de PHP-FPM:

   `php artisan migrate --force --path=database/migrations/2026_10_14_231500_add_solicitud_preparacion_json_to_pedidos_bma.php`

   `up()` es una sentencia. Si falla, la columna no queda a medias. No reintentar un `ADD` manual: el error 1060 significa que ya está.
4. Publicar el código de la sección 3, compilar con `npm run build` en el contenedor de aplicación y solo entonces recargar PHP-FPM (`USR2`), `queue:restart` y permisos de `storage` y `bootstrap/cache` como ya hace `update-safe.sh`.
5. No usar `rollback.sh` ni `migrate:rollback`. Para volver atrás, restaurar el código anterior y dejar la columna. Las intenciones ya escritas se conservan y el código viejo las ignora. Borrar la columna solo es aceptable antes de que exista alguna intención, o con un respaldo restaurable y aceptación de perder esos JSON.

### Comprobaciones de humo, sin escribir pedidos reales

Antes de abrir el modal a quien opera:

- `GET /login` responde 200 con el `Host` del entorno.
- Un pedido existente, abierto en solo lectura, sigue mostrando almacén, sucursal, montos y documentos.
- No aparece «Servidor · {folio}» si se fuerza un guardado de intención con la columna ausente (debe ser 422). Con la columna presente, un borrador de prueba creado para el ensayo —no un pedido ya operativo— guarda municipio y lo recupera al cerrar y abrir.
- Buscar por número de remisión no cambia guías.
- Confirmar una carátula de prueba vigente coloca; una obsoleta no escribe.
- Un almacén con `permite_salida_pedidos` en falso no queda seleccionable como salida nueva.

### Detener el despliegue si

- El destino no está en `861cb19` o le faltan `sucursal_destino_id`, `prioridad_md` o `numero_remision`.
- El respaldo no se crea.
- `migrate` devuelve algo distinto de éxito, o `information_schema` no muestra la columna JSON nullable al terminar.
- Un autoguardado con intención responde 200 mientras la columna no existe.
- El build de Vite falla o la recarga de PHP-FPM no toma el código nuevo (OPcache con `validate_timestamps=0` lo exige).
- El health check de `/login` no da 200.
- Cualquier lectura de humo inserta o actualiza un pedido que no sea el borrador creado para la prueba.
- Hay que borrar la columna para deshacer, y ya existen intenciones.

## 10. Pendiente Q3–Q10

Sigue el orden de P1.2. RC1 no lo cambia.

- Q3. Concurrencia del guardado y barrera antes de solicitar. Sigue abierto. Ver sección 7.
- Q4. Recuperar la selección de almacén sin el cambio silencioso CEDIS→PDV. No se modificó la elegibilidad.
- Q5. Soporte documental de Aromas sin exigir peso en la captura que no lo requiere.
- Q6. Una sucesora conserva lo compatible y no copia hechos ya ejecutados.
- Q8a, Q8b, Q8c. Integración con PDV, custodia y devolución. Fuera de este corte, igual que los reportes PDV.
- Q9. Una sola autoridad de transporte y cobro. La paquetería del pedido y la municipal siguen siendo dos controles.
- Q10. Extracción mecánica, solo después de los contratos anteriores.

Q1, Q7 y Q2 quedan verificados en este árbol y bloqueados para el stack que hoy despliegan los scripts.

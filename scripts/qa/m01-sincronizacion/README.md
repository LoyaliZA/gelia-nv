# Consultas: etapas 1 y 2 — reproducción aislada

Usar Sail como `sail`, MySQL **codex_m01_audit_20261114** y Selenium libre. No reconstruir la base, ejecutar migraciones ni utilizar estos fixtures en operación. Los PHP validan la base exacta antes de escribir o leer. Los guiones no borran pedidos ni documentos anteriores.

Se reutiliza `../m01-continuidad/navegadorComun.mjs`: aplicación Laravel real, build publicado y archivos PNG ficticios legibles. No se simulan endpoints ni se ejecutan comandos de negocio mediante Axios desde los guiones: las mutaciones provienen de los controles de React. Los interceptores y observadores solo registran peticiones y cambios visibles.

## Entorno

Conservar la base exclusiva existente y sus migraciones. Si falta, detener la reproducción y preparar un entorno aislado con el administrador; no cambiar a la base operativa.

```bash
qa_args=(exec -T --user sail
  -e APP_CONFIG_CACHE=/tmp/gelia-m01-qa-config.php
  -e APP_ROUTES_CACHE=/tmp/gelia-m01-qa-routes.php
  -e DB_CONNECTION=mysql -e DB_DATABASE=codex_m01_audit_20261114
  -e DB_USERNAME=root -e APP_ENV=testing -e CACHE_STORE=array
  -e QUEUE_CONNECTION=sync -e BROADCAST_CONNECTION=null -e MAIL_MAILER=array)

./vendor/bin/sail "${qa_args[@]}" laravel.test sh -c \
  'php scripts/qa/m01-sincronizacion/fixtures.php > /tmp/m01-sinc-fixtures.json'
```

El fixture crea Ventas y CEDIS como usuarios distintos y un pedido municipal pendiente, con almacén, terminal, consignatario y Wizerp sintéticos. No asigna roles o permisos a identidades operativas. El password del código pertenece exclusivamente a estas identidades de prueba.

En una terminal propia, iniciar únicamente Laravel QA:

```bash
./vendor/bin/sail "${qa_args[@]}" \
  -e APP_URL=http://laravel.test:9193 \
  -e APP_ALLOWED_HOSTS=laravel.test,gelianv-laravel.test-1 \
  -e SESSION_DRIVER=file -e SESSION_COOKIE=gelia_m01_sinc \
  -e SESSION_SECURE_COOKIE=false \
  laravel.test php artisan serve --host=0.0.0.0 --port=9193 --no-reload
```

Los dos nombres de host llegan al mismo Laravel y permiten cookies de sesión independientes en dos ventanas de Chromium. Ambos documentos permanecen visibles; los guiones comprueban que las respuestas llegaron **antes** de enfocar la otra ventana. Selenium dispone de una sola plaza: se usa una sesión WebDriver con dos ventanas y dos sesiones Laravel. Los guiones rechazan una plaza ocupada y eliminan solo su propia sesión al terminar.

No iniciar Vite. Publicar con `./vendor/bin/sail npm run build` después de las pruebas del lote; conservar `public/hot` ausente.

## Recorridos

```bash
./vendor/bin/sail exec --user sail \
  -e GELIA_QA_FIXTURE=/tmp/m01-sinc-fixtures.json \
  -e GELIA_QA_OUTPUT=/tmp/gelia-m01-sincronizacion \
  laravel.test node scripts/qa/m01-sincronizacion/navegador.mjs

./vendor/bin/sail "${qa_args[@]}" laravel.test \
  php scripts/qa/m01-sincronizacion/regresion-fixtures.php

./vendor/bin/sail exec --user sail \
  -e GELIA_QA_FIXTURE=/tmp/m01-sinc-fixtures.json \
  -e GELIA_QA_OUTPUT=/tmp/gelia-m01-presentacion \
  laravel.test node scripts/qa/m01-sincronizacion/presentacion.mjs

./vendor/bin/sail exec --user sail \
  -e GELIA_QA_FIXTURE=/tmp/m01-sinc-fixtures.json \
  -e GELIA_QA_OUTPUT=/tmp/gelia-m01-alta-vacia \
  laravel.test node scripts/qa/m01-sincronizacion/alta-vacia.mjs
```

- `navegador.mjs`: alta municipal vacía, respuesta con faltante, retiro, segunda respuesta, cierre, reapertura y tercera respuesta con ambos modales abiertos. Incluye espera con archivo local pendiente en CEDIS.
- `presentacion.mjs`: tarjetas de las tres modalidades, Wizerp autorizado, detalle/ruta municipal, formulario nacional con peso real, recarga municipal, respuesta de Tienda y anexo documental con segunda respuesta. Incluye recepción municipal con el contrato canónico final. No registra pesaje, pagos, remisión, separación ni traslado.
- `alta-vacia.mjs`: selección aislada de las tres modalidades, incluida la transición a Nacional sin ID; espera el intervalo de autoguardado legado y exige cero POST de pedidos.

Cada prueba de mutación necesita casos nuevos. Para reintentos, `regresion-fixtures.php --renovar-tienda --renovar-municipal` crea nuevos casos, conserva los anteriores en `regresion_historial` y registra la última Tienda comprobada con anexo respondido en `tienda_verificada`. No reinicia ni elimina pedidos. No sumar estos fixtures intencionales como borradores involuntarios.

Para comprobar solo cabeceras tras un cambio CSS, renovar ambos casos y ejecutar `presentacion.mjs` con `-e GELIA_QA_SOLO_TARJETAS=1` y otro `GELIA_QA_OUTPUT`. Es una lectura sin mutaciones que comprueba anchos 1440/1024/390, claro/oscuro, desbordamiento y palabras partidas mediante rangos DOM. `GELIA_QA_SOLO_TIENDA=1` limita el diagnóstico al recorrido Tienda; no acredita las otras modalidades.

La lectura final no modifica filas:

```bash
./vendor/bin/sail "${qa_args[@]}" laravel.test \
  php scripts/qa/m01-sincronizacion/verificar-persistencia.php
```

Comprueba historia y Wizerp originales, evidencias anteriores, retiro de faltante, anexo respondido, flete diferido y ausencia de pagos/remisión/empaque/tareas municipales. El número de pedidos debe coincidir exactamente con los casos explícitos y sus renovaciones.

## Alcance de la evidencia y cierre

`BROADCAST_CONNECTION=null` aísla los avisos del entorno operativo y los guiones desconectan Echo. La recepción automática acredita el respaldo real de 15 segundos, el GET autorizado y la aplicación del estado React. No acredita entrega de Reverb ni notificaciones entre cuentas operativas. No se falsea visibilidad ni se disparan eventos de actualización desde los guiones.

Los JSON preservan consola, aserciones y medidas. Una ejecución fallida no es evidencia aprobada; no copiar sus `fallo*.png` como resultado final. Usar los resultados que indiquen `aprobado` y distinguir el alcance de cada ejecución.

Al terminar, cerrar únicamente el Laravel 9193 y sus workers, después de identificar sus PID/argumentos con Sail; no reutilizar PID antiguos ni detener contenedores compartidos. Comprobar Selenium libre y `public/hot` ausente. En esta entrega se conservaron las filas y los binarios sintéticos identificados de la base de auditoría para revisión, sin limpiezas automáticas de registros.

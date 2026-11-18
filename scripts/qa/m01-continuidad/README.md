# Registro municipal y consulta CEDIS: reproducción aislada

Usar Sail y **solo** `codex_m01_audit_20261114`. Los PHP comprueban el nombre exacto de la base antes de operar. No usar estas herramientas como seed de operación. No reconstruir la base mientras Laravel QA o Selenium estén trabajando.

## Backend y frontend

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test --filter='MunicipalOrdinarioM01Test|ElegibilidadPesajePedidoTest|ControlPedidosEnvioDiferidoFase1Test'
./vendor/bin/sail exec --user sail -e NODE_OPTIONS=--no-experimental-webstorage laravel.test npm test -- resources/js/Pages/ControlPedidos/Municipal/continuidadRegistro.test.jsx resources/js/Pages/ControlPedidos/Municipal/registroMunicipal.test.jsx resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.seleccion.test.jsx resources/js/Pages/ControlPedidos/Partials/FormularioRegistrarTienda.confirmacion.test.jsx resources/js/Pages/ControlPedidos/Partials/flujoCapturaPedido.test.js
./vendor/bin/sail npm run build
```

## Preparación y navegador

La preparación MySQL y el Laravel temporal siguen [el entorno de auditoría](../m01-auditoria/README.md). Sustituir el fixture por `scripts/qa/m01-continuidad/fixtures.php`. Variables de fixture, verificación y limpieza:

```bash
./vendor/bin/sail exec -T --user sail \
  -e APP_CONFIG_CACHE=/tmp/gelia-m01-qa-config.php \
  -e APP_ROUTES_CACHE=/tmp/gelia-m01-qa-routes.php \
  -e DB_CONNECTION=mysql -e DB_DATABASE=codex_m01_audit_20261114 \
  -e DB_USERNAME=root -e APP_ENV=testing -e CACHE_STORE=array \
  -e QUEUE_CONNECTION=sync -e BROADCAST_CONNECTION=null \
  laravel.test sh -c 'php scripts/qa/m01-continuidad/fixtures.php > /tmp/m01-fixtures.json'
```

El servidor temporal usa esas variables y además `MAIL_MAILER=array`, `APP_URL=http://laravel.test:9193`, `APP_ALLOWED_HOSTS=laravel.test`, `SESSION_DRIVER=file`, `SESSION_COOKIE=gelia_m01_continuidad`, `SESSION_SECURE_COOKIE=false`; ejecutar mediante Sail **como `--user sail`** `php artisan serve --host=0.0.0.0 --port=9193 --no-reload`. No activar Vite ni modificar el servidor compartido.

Con ese proceso activo y el build publicado:

```bash
./vendor/bin/sail exec --user sail laravel.test node scripts/qa/m01-continuidad/navegador.mjs
./vendor/bin/sail exec --user sail laravel.test node scripts/qa/m01-continuidad/reapertura.mjs
./vendor/bin/sail exec --user sail laravel.test node scripts/qa/m01-continuidad/validacion.mjs
```

Cada guion rechaza Selenium ocupado, usa su propia sesión y la elimina al terminar. `navegadorComun.mjs` genera un documento ficticio legible mediante canvas y lo carga usando el endpoint de archivos de Selenium. No utiliza Axios para ejecutar acciones de negocio; el interceptor solo registra las respuestas de los botones reales. El folio de la prueba incorpora el usuario sintético para evitar elegir pedidos de intentos anteriores. El usuario ficticio tiene facultades de Ventas y CEDIS; esta evidencia no representa aceptación con cuentas operativas ni entrega de notificaciones entre personas.

`reapertura.mjs` puede continuar una revisión ya solicitada si un intento previo se interrumpió. El JSON lo indica explícitamente. `validacion.mjs` espera un 422, comprueba el mensaje visible y deja únicamente un borrador incompleto en la base exclusiva.

Resultados y capturas: `/tmp/gelia-m01-continuidad` dentro del contenedor. Copiar solo los resultados/capturas finales; un `fallo.png` viejo no acredita el éxito. La lectura `verificar-persistencia.php`, con las variables aisladas, valida cliente, departamento, folio, documento, expediente, almacén, cierre y transporte; no modifica filas.

Después de capturar la evidencia, ejecutar `limpiar-archivos.php` con esas mismas variables. Elimina únicamente binarios llamados `m01-qa.png`, relacionados con identidades sintéticas y de hash igual al documento generado. Conserva registros de auditoría. Para repetir el visor o las descargas se necesitan fixtures nuevos. Cerrar únicamente el Laravel temporal 9193 y sus workers, sin detener contenedores.

Los procesos de navegador y los PHP que escriban archivos privados deben ejecutarse como `sail`, igual que el servidor de uso. Un servidor QA como root no acredita permisos de escritura de la aplicación. No cambiar permisos 700/600 para compensar un propietario incorrecto.

`envio-automatico.mjs` comprueba específicamente el envío directo sin pulsar Guardar solicitud: usa el pedido municipal del fixture, captura folio/documento/destino/consignatario/condiciones, acciona Enviar consulta y reabre para verificar persistencia. No necesita ejecutar primero `navegador.mjs`. Puede aislar sus archivos con `-e GELIA_QA_FIXTURE=/tmp/envio-auto-fixtures.json -e GELIA_QA_OUTPUT=/tmp/gelia-envio-automatico`. Los POST registrados por el interceptor deben mostrar autoguardado, solicitud y consulta, en ese orden. No ejecuta operaciones de negocio con Axios directo.

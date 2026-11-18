# Reproducir la auditoría M-01

Estos guiones contienen datos ficticios y se limitan a la base **codex_m01_audit_20261114**. No ejecutar contra bases de uso. La prueba municipal valida el destino antes de usar RefreshDatabase; PHPUnit reconstruye la base exclusiva. Crear otra vez los fixtures después de cada ejecución MySQL.

## Integración

Con Sail activo y la base exclusiva creada por el administrador:

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test --filter=MunicipalOrdinarioM01Test
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-m01-mysql-config.php -e APP_ROUTES_CACHE=/tmp/gelia-m01-mysql-routes.php laravel.test php vendor/bin/phpunit --configuration=scripts/qa/m01-auditoria/phpunit-mysql.xml --filter=MunicipalOrdinarioM01Test
```

El archivo MySQL usa el usuario root **del contenedor de pruebas**, con la contraseña que ya proporciona el entorno; no contiene contraseñas operativas. No pasar esta configuración a otros tests sin revisar sus restricciones.

## Concurrencia

Después de las migraciones del comando anterior:

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-m01-qa-config.php -e APP_ROUTES_CACHE=/tmp/gelia-m01-qa-routes.php -e DB_CONNECTION=mysql -e DB_DATABASE=codex_m01_audit_20261114 -e DB_USERNAME=root -e APP_ENV=testing -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e BROADCAST_CONNECTION=null laravel.test php scripts/qa/m01-auditoria/concurrencia.php
```

Usa cuatro procesos con conexiones independientes, documentos en `/tmp/m01-auditoria-documentos` y datos ficticios. Ejecutar una vez por reconstrucción de la base; no reutilizar sus nombres únicos sin limpiar exclusivamente esa base.

## Navegador real

1. Finalizar las pruebas MySQL antes de crear fixtures. No reconstruir la base mientras corre el navegador.
2. Ejecutar `fixtures.php` con las mismas variables del guion de concurrencia y guardar su JSON en `/tmp/m01-fixtures.json` **dentro del contenedor Laravel**. Si se redirige la salida a `/tmp` en el host, copiarla mediante `sail exec -T laravel.test sh -c 'cat > /tmp/m01-fixtures.json' < /tmp/m01-fixtures.json`.
3. Iniciar un Laravel propio en el puerto **9193**, con las mismas variables anteriores y además `APP_URL=http://laravel.test:9193`, `APP_ALLOWED_HOSTS=laravel.test`, `SESSION_DRIVER=file`, `SESSION_COOKIE=gelia_m01_auditoria`, `SESSION_SECURE_COOKIE=false` y `MAIL_MAILER=array`. Usar `php artisan serve --host=0.0.0.0 --port=9193 --no-reload` mediante Sail. No tocar el servidor de uso ni iniciar Vite.
4. Con el build publicado, ejecutar `./vendor/bin/sail exec laravel.test node scripts/qa/m01-auditoria/navegador.mjs`. El guion rechaza Selenium ocupado y elimina únicamente su sesión al terminar. El password constante corresponde solo al usuario sintético creado por el fixture.
5. Recuperar `/tmp/gelia-m01-navegador` del contenedor. Sus capturas son de la aplicación real y datos ficticios. Cerrar solo el proceso Laravel 9193 y sus workers, sin detener contenedores.
6. El navegador usa los discos locales configurados para ejercer las descargas reales; sus nombres de archivo son aleatorios. `limpiar-archivos.php`, con las mismas variables de la base exclusiva, elimina únicamente los binarios identificados por fixtures. La rama de limpieza del primer recorrido usa además hash idéntico del canvas ficticio y la carpeta específica de aquel recorrido. No borra carpetas ni archivos por fecha. No utilizar ese guion como limpieza general.

Después de la auditoría se conservaron la base exclusiva y las evidencias, y se retiraron los cuatro binarios ficticios del navegador. Sus registros aislados sirven como evidencia, pero para repetir descargas hay que reconstruir los fixtures. No se guardaron credenciales ni documentos reales en el repositorio.

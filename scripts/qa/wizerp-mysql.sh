#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
container=gelia-wizerp-mysql-test
if docker container inspect "$container" >/dev/null 2>&1; then
    echo 'Ya existe el contenedor de pruebas; no se reutilizará ni eliminará automáticamente.' >&2
    exit 1
fi
container_id=''
trap 'if [[ -n "$container_id" ]]; then docker rm -f "$container_id" >/dev/null 2>&1 || true; fi' EXIT
container_id=$(docker run -d --name "$container" --label gelia.purpose=wizerp-tests --network gelianv_sail \
    --memory=768m --cpus=1 -e MYSQL_ROOT_PASSWORD=gelia-isolated-test -e MYSQL_DATABASE=gelia_wizerp_test \
    mysql:8.4 --innodb-buffer-pool-size=64M --max-connections=25 --log-bin-trust-function-creators=1)
ready=false
for attempt in {1..60}; do
    if docker exec -e MYSQL_PWD=gelia-isolated-test "$container" mysql -uroot -Nse 'SELECT 1' gelia_wizerp_test >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 1
done
if [[ "$ready" != true ]]; then
    echo 'MySQL de pruebas no está disponible.' >&2
    exit 1
fi
./vendor/bin/sail exec -T laravel.test php -r '
$xml = simplexml_load_file("phpunit.xml");
$xml["bootstrap"] = "/var/www/html/vendor/autoload.php";
$values = ["DB_CONNECTION" => "mysql", "DB_HOST" => "gelia-wizerp-mysql-test", "DB_PORT" => "3306", "DB_DATABASE" => "gelia_wizerp_test", "DB_USERNAME" => "root", "DB_PASSWORD" => "gelia-isolated-test", "DB_URL" => "", "APP_CONFIG_CACHE" => "/tmp/gelia-mysql-test-config.php", "APP_ROUTES_CACHE" => "/tmp/gelia-mysql-test-routes.php"];
foreach ($values as $name => $value) {
    $nodes = $xml->xpath("/phpunit/php/env[@name=\"".$name."\"]");
    $node = $nodes[0] ?? $xml->php->addChild("env");
    $node["name"] = $name; $node["value"] = $value; $node["force"] = "true";
}
foreach ($xml->testsuites->testsuite as $suite) { foreach ($suite->directory as $dir) { $dir[0] = "/var/www/html/".(string) $dir; } }
$xml->asXML("/tmp/gelia-wizerp-phpunit.xml");
'
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-mysql-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-mysql-test-routes.php \
    laravel.test php vendor/bin/phpunit --configuration /tmp/gelia-wizerp-phpunit.xml --do-not-cache-result tests/Integration/Clientes

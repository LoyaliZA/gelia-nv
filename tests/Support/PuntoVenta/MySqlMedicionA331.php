<?php

namespace Tests\Support\PuntoVenta;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

final class MySqlMedicionA331
{
    public static function application(string $database = 'gelia_medicion_a331_test', string $usuario = 'root')
    {
        if (getenv('PDV_MEDICION_A331_MYSQL_TEST') !== '1'
            || ! in_array($database, ['gelia_medicion_a331_test', 'gelia_medicion_a331_parcial'], true)) {
            throw new \LogicException('Solo se admite el entorno efímero explícito de A3.3.1.');
        }
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        // No leer .env ni el cache de configuración del entorno de trabajo.
        $app->loadEnvironmentFrom('.env.a331-inexistente');
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($database, $usuario) {
            $app['config']->set([
                'app.env' => 'testing', 'cache.default' => 'array', 'queue.default' => 'sync',
                'broadcasting.default' => 'null', 'session.driver' => 'array',
                'database.default' => 'mysql',
                'database.connections.mysql' => [
                    'driver' => 'mysql', 'host' => 'mysql-a331', 'port' => 3306,
                    'database' => $database, 'username' => $usuario, 'password' => '',
                    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '', 'strict' => true, 'timezone' => '+00:00',
                ],
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}

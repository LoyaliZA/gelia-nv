<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function createApplication()
    {
        $app = parent::createApplication();

        // phpunit.xml fuerza sqlite :memory:, pero bootstrap/cache/config.php
        // puede dejar database.default en mysql y disparar migrate:fresh sobre datos reales.
        $dbConnection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: null;
        $dbDatabase = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: ':memory:';

        if ($dbConnection === 'sqlite') {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite.database', $dbDatabase);
            $app['config']->set('app.env', 'testing');
            $app['config']->set('broadcasting.default', 'null');
            $app['config']->set('queue.default', 'sync');
            $app['db']->purge();
        }

        // Las pruebas nunca escriben en storage/app real: un archivo de prueba podría colisionar con el de un
        // pedido real del mismo número, o dejar carpetas con otro propietario.
        $raiz = sys_get_temp_dir().'/gelia-pruebas-'.getmypid();
        foreach ((array) $app['config']->get('filesystems.disks', []) as $nombre => $disco) {
            $actual = $disco['root'] ?? null;
            if (($disco['driver'] ?? null) === 'local' && is_string($actual) && str_starts_with($actual, storage_path('app'))) {
                $app['config']->set("filesystems.disks.{$nombre}.root", $raiz.'/'.$nombre);
            }
        }
        static $limpieza = false;
        if (! $limpieza) {
            $limpieza = true;
            register_shutdown_function(static function () use ($raiz) {
                if (is_dir($raiz)) {
                    (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($raiz);
                }
            });
        }

        $app['config']->set('webauthn.enabled', true);

        return $app;
    }
}

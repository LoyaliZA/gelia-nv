<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
            $app['db']->purge();
        }

        return $app;
    }
}

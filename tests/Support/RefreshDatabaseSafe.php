<?php

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait RefreshDatabaseSafe
{
    use RefreshDatabase;

    protected function migrateDatabases()
    {
        // sqlite :memory: — migrate (no migrate:fresh). El runner phpunit mockea artisan()
        // y RefreshDatabase no llega a crear tablas si se usa PendingCommand.
        $this->app[Kernel::class]->call('migrate', ['--force' => true]);
    }
}

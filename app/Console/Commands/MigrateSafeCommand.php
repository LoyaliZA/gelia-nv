<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateSafeCommand extends Command
{
    protected $signature = 'migrate:safe {--seed : Ejecutar seeders después de migrar}';

    protected $description = 'Respalda la BD y luego ejecuta migrate --force (recomendado en desarrollo).';

    public function handle(): int
    {
        $this->info('Creando respaldo antes de migrar...');

        if ($this->call('db:backup') !== self::SUCCESS) {
            $this->error('Migración abortada: no se pudo crear el respaldo.');

            return self::FAILURE;
        }

        $migrateOptions = ['--force' => true];
        if ($this->option('seed')) {
            $migrateOptions['--seed'] = true;
        }

        return $this->call('migrate', $migrateOptions);
    }
}

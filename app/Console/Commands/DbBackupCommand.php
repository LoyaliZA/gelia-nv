<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DbBackupCommand extends Command
{
    protected $signature = 'db:backup {--path= : Ruta del archivo .sql de salida}';

    protected $description = 'Genera un respaldo SQL de la base de datos actual (desarrollo).';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? '') !== 'mysql') {
            $this->error('db:backup solo soporta conexiones MySQL.');

            return self::FAILURE;
        }

        $database = $config['database'] ?? '';
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? '3306');
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if ($database === '') {
            $this->error('No hay base de datos configurada.');

            return self::FAILURE;
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $path = $this->option('path')
            ?: $directory . DIRECTORY_SEPARATOR . 'backup_' . $database . '_' . now()->format('Y-m-d_His') . '.sql';

        $process = new Process([
            'mysqldump',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--single-transaction',
            '--routines',
            '--triggers',
            $database,
        ], null, [
            'MYSQL_PWD' => $password,
        ]);

        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('mysqldump falló: ' . trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        File::put($path, $process->getOutput());

        $bytes = File::size($path);
        $this->info("Respaldo creado: {$path} (" . number_format($bytes / 1024, 1) . ' KB)');

        // #region agent log
        $logPath = base_path('.cursor/debug-bb9f41.log');
        if (is_dir(dirname($logPath))) {
            file_put_contents($logPath, json_encode([
                'sessionId' => 'bb9f41',
                'runId' => 'post-fix',
                'hypothesisId' => 'FIX',
                'location' => 'DbBackupCommand::handle',
                'message' => 'backup_created',
                'data' => ['path' => $path, 'bytes' => $bytes, 'database' => $database],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL, FILE_APPEND);
        }
        // #endregion

        return self::SUCCESS;
    }
}

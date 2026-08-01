<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Respaldo MySQL a storage/app/backups.
 *
 * Ops: restringir permisos del dir; retener off-host; BD solo en red Docker.
 * Si BACKUP_ENCRYPTION_KEY está definida, el .sql.gz se cifra (openssl AES-256-CBC)
 * y no se deja el dump en claro. Sin ALLOW_DESTRUCTIVE_DB.
 */
class DbBackupCommand extends Command
{
    protected $signature = 'db:backup {--path= : Ruta base del archivo de salida (sin exigir extensión)}';

    protected $description = 'Genera un respaldo SQL comprimido (y cifrado si hay BACKUP_ENCRYPTION_KEY).';

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

        $base = $this->option('path')
            ?: $directory.DIRECTORY_SEPARATOR.'backup_'.$database.'_'.now()->format('Y-m-d_His');
        $base = preg_replace('/\.(sql|gz|enc)$/i', '', $base) ?? $base;
        $sqlPath = $base.'.sql';

        $process = new Process([
            'mysqldump',
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--single-transaction',
            '--routines',
            '--triggers',
            $database,
        ], null, [
            'MYSQL_PWD' => $password,
        ]);

        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('mysqldump falló: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        File::put($sqlPath, $process->getOutput());

        try {
            $final = $this->comprimirYOpcionalmenteCifrar($sqlPath);
        } catch (\Throwable $e) {
            @File::delete($sqlPath);
            $this->error('Post-proceso de respaldo falló: '.$e->getMessage());

            return self::FAILURE;
        }

        $bytes = File::size($final);
        $this->info('Respaldo creado: '.$final.' ('.number_format($bytes / 1024, 1).' KB)');

        return self::SUCCESS;
    }

    /**
     * Comprime .sql → .sql.gz; si hay llave, cifra a .sql.gz.enc y borra el gz en claro.
     */
    public function comprimirYOpcionalmenteCifrar(string $sqlPath): string
    {
        if (! is_file($sqlPath)) {
            throw new \RuntimeException("No existe {$sqlPath}");
        }

        $gzPath = $sqlPath.'.gz';
        $gz = gzopen($gzPath, 'wb9');
        if ($gz === false) {
            throw new \RuntimeException('No se pudo crear gzip.');
        }

        $in = fopen($sqlPath, 'rb');
        if ($in === false) {
            gzclose($gz);
            throw new \RuntimeException('No se pudo leer el SQL.');
        }

        while (! feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            gzwrite($gz, $chunk);
        }
        fclose($in);
        gzclose($gz);
        File::delete($sqlPath);

        $key = (string) env('BACKUP_ENCRYPTION_KEY', '');
        if ($key === '') {
            return $gzPath;
        }

        $encPath = $gzPath.'.enc';
        $process = new Process([
            'openssl',
            'enc',
            '-aes-256-cbc',
            '-pbkdf2',
            '-salt',
            '-in',
            $gzPath,
            '-out',
            $encPath,
            '-pass',
            'env:BACKUP_ENCRYPTION_KEY',
        ], null, [
            'BACKUP_ENCRYPTION_KEY' => $key,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            File::delete($encPath);
            throw new \RuntimeException('openssl enc falló: '.trim($process->getErrorOutput()));
        }

        File::delete($gzPath);

        return $encPath;
    }
}

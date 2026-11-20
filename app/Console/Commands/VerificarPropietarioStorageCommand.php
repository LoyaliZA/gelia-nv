<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Detecta archivos de storage/app creados con otro propietario (por ejemplo root) que el servidor web no puede escribir. */
class VerificarPropietarioStorageCommand extends Command
{
    protected $signature = 'storage:verificar {--limite=20 : Máximo de rutas a listar}';

    protected $description = 'Verifica que storage/app pertenezca al usuario que ejecuta la aplicación';

    public function handle(): int
    {
        if (! function_exists('posix_geteuid')) {
            $this->warn('POSIX no está disponible; no se puede comprobar el propietario.');

            return self::SUCCESS;
        }

        $uid = posix_geteuid();
        $base = storage_path('app');
        $ajenas = [];
        $total = 0;

        if (is_dir($base)) {
            $iterador = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ($iterador as $ruta => $info) {
                if (@fileowner($ruta) !== $uid) {
                    $total++;
                    if (count($ajenas) < (int) $this->option('limite')) {
                        $ajenas[] = substr($ruta, strlen($base) + 1);
                    }
                }
            }
            // Directorios que ni siquiera se pudieron abrir cuentan como ajenos.
            foreach (glob($base.'/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
                if (! is_readable($dir) || ! is_executable($dir)) {
                    $total++;
                    $ajenas[] = substr($dir, strlen($base) + 1).' (sin acceso)';
                }
            }
        }

        if ($total === 0) {
            $this->info('storage/app pertenece al usuario actual (uid '.$uid.').');

            return self::SUCCESS;
        }

        $this->error("{$total} elemento(s) de storage/app no pertenecen al usuario actual (uid {$uid}); el servidor no podrá escribir en ellos.");
        foreach ($ajenas as $ruta) {
            $this->line('  '.$ruta);
        }
        $this->line('Reparar: ./vendor/bin/sail exec -u root laravel.test chown -R sail:sail storage/app');

        return self::FAILURE;
    }
}

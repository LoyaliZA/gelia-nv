<?php

namespace App\Console\Commands;

use Database\Seeders\EntornoPruebaSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Carga el volcado local en una base ya migrada.
 * Exige --reemplazar-datos y rechaza production. No usa migrate:fresh.
 */
class DbImportarEntornoPruebaCommand extends Command
{
    protected $signature = 'db:importar-entorno-prueba {--reemplazar-datos : Vacía las tablas del volcado y las vuelve a cargar}';

    protected $description = 'Importa el volcado del entorno de prueba y siembra permisos y la cuenta de revisión.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('db:importar-entorno-prueba no se ejecuta en production.');

            return self::FAILURE;
        }

        if (! $this->option('reemplazar-datos')) {
            $this->error('Hace falta --reemplazar-datos. Este comando vacía las tablas incluidas en el volcado.');

            return self::FAILURE;
        }

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        if (($config['driver'] ?? '') !== 'mysql') {
            $this->error('db:importar-entorno-prueba solo soporta conexiones MySQL.');

            return self::FAILURE;
        }

        $contrasenaAgente = config('entorno_prueba.agente_password');
        if (! is_string($contrasenaAgente) || trim($contrasenaAgente) === '') {
            $this->error('Defina AGENTE_REVISION_PASSWORD antes de importar.');

            return self::FAILURE;
        }

        $ruta = DbVolcadoEntornoPruebaCommand::rutaVolcado();
        if (! is_file($ruta)) {
            $this->error('No está el volcado en '.$ruta.'. Ejecute db:volcado-entorno-prueba.');

            return self::FAILURE;
        }

        try {
            $tablas = self::tablasEnVolcado($ruta);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $excluidas = array_flip(DbVolcadoEntornoPruebaCommand::TABLAS_EXCLUIDAS);
        $aVaciar = [];
        foreach ($tablas as $tabla) {
            if ($tabla === 'migrations' || isset($excluidas[$tabla])) {
                continue;
            }
            if (! preg_match('/^[A-Za-z0-9_]+$/', $tabla)) {
                continue;
            }
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            $aVaciar[] = $tabla;
        }

        if ($aVaciar === []) {
            $this->error('El volcado no declara tablas para cargar.');

            return self::FAILURE;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($aVaciar as $tabla) {
                DB::table($tabla)->delete();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $database = (string) ($config['database'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        $comando = sprintf(
            'gunzip -c %s | mysql --host=%s --port=%s --user=%s --default-character-set=utf8mb4 --max_allowed_packet=512M %s',
            escapeshellarg($ruta),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
        );

        $proceso = Process::fromShellCommandline($comando, null, [
            'MYSQL_PWD' => $password,
        ]);
        $proceso->setTimeout(1200);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            $this->error('La carga del volcado falló: '.trim($proceso->getErrorOutput()));

            return self::FAILURE;
        }

        $this->call('db:seed', [
            '--class' => EntornoPruebaSeeder::class,
            '--no-interaction' => true,
        ]);

        $this->info('Entorno de prueba importado.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    public static function tablasEnVolcado(string $ruta): array
    {
        $gz = gzopen($ruta, 'rb');
        if ($gz === false) {
            throw new RuntimeException('No se pudo leer el volcado.');
        }

        $tablas = [];
        $buffer = '';
        while (! gzeof($gz)) {
            $bloque = gzread($gz, 1024 * 1024);
            if ($bloque === false || $bloque === '') {
                break;
            }

            $buffer .= $bloque;
            if (preg_match_all('/^-- Dumping data for table `([A-Za-z0-9_]+)`/m', $buffer, $coincidencias)) {
                foreach ($coincidencias[1] as $tabla) {
                    $tablas[$tabla] = true;
                }
            }

            if (strlen($buffer) > 200) {
                $buffer = substr($buffer, -200);
            }
        }

        gzclose($gz);

        return array_keys($tablas);
    }
}

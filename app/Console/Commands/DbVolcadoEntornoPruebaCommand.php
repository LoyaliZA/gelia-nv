<?php

namespace App\Console\Commands;

use Database\Seeders\PermisosActualesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;

/**
 * Volcado de datos para entornos de prueba.
 *
 * Solo SQL. No copia archivos de storage. Las rutas de imagen quedan en las filas
 * y en el entorno de prueba no tienen archivo. Sin ALLOW_DESTRUCTIVE_DB.
 */
class DbVolcadoEntornoPruebaCommand extends Command
{
    protected $signature = 'db:volcado-entorno-prueba';

    protected $description = 'Genera el volcado SQL local del entorno de prueba, sin archivos de imagen ni tablas efímeras.';

    public const ARCHIVO = 'gelia-entorno-prueba.sql.gz';

    /** @var list<string> */
    public const TABLAS_EXCLUIDAS = [
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'sessions',
        'password_reset_tokens',
        'personal_access_tokens',
        'migrations',
    ];

    public static function rutaVolcado(): string
    {
        return storage_path('app/entorno-prueba/'.self::ARCHIVO);
    }

    public static function claveEsSecreto(string $clave): bool
    {
        return (bool) preg_match('/token|password|secret|api_key/i', $clave);
    }

    public function handle(): int
    {
        $config = $this->configuracionMysql();
        if ($config === null) {
            return self::FAILURE;
        }

        try {
            $this->exportarCatalogoPermisos();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $directorio = storage_path('app/entorno-prueba');
        File::ensureDirectoryExists($directorio);
        $sqlPath = $directorio.DIRECTORY_SEPARATOR.'gelia-entorno-prueba.sql';
        $gzPath = self::rutaVolcado();

        $argumentos = [
            'mysqldump',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--no-create-info',
            '--skip-triggers',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--max_allowed_packet=512M',
        ];

        foreach (array_merge(self::TABLAS_EXCLUIDAS, ['configuraciones_sistema']) as $tabla) {
            $argumentos[] = '--ignore-table='.$config['database'].'.'.$tabla;
        }

        $argumentos[] = $config['database'];

        $proceso = new Process($argumentos, null, [
            'MYSQL_PWD' => $config['password'],
        ]);
        $proceso->setTimeout(1200);
        $proceso->disableOutput();

        $sql = fopen($sqlPath, 'wb');
        if ($sql === false) {
            $this->error('No se pudo crear el archivo de volcado.');

            return self::FAILURE;
        }

        $stderr = '';
        $escrituraFallo = false;
        $proceso->run(function (string $tipo, string $datos) use (&$stderr, &$escrituraFallo, $sql): void {
            if ($tipo === Process::OUT) {
                if (fwrite($sql, $datos) === false) {
                    $escrituraFallo = true;
                }
            } else {
                $stderr .= $datos;
            }
        });
        fclose($sql);

        if ($escrituraFallo || ! $proceso->isSuccessful()) {
            File::delete($sqlPath);
            $this->error('mysqldump falló: '.trim($stderr));

            return self::FAILURE;
        }

        try {
            $this->anexarConfiguraciones($sqlPath);
            $this->comprimir($sqlPath, $gzPath);
        } catch (RuntimeException $e) {
            File::delete($sqlPath);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $bytes = File::size($gzPath);
        $this->info('Volcado creado: '.$gzPath.' ('.number_format($bytes / 1024, 1).' KB)');
        $this->info('Catálogo de permisos: '.PermisosActualesSeeder::rutaCatalogo());

        return self::SUCCESS;
    }

    /**
     * @return array{host: string, port: string, database: string, username: string, password: string}|null
     */
    private function configuracionMysql(): ?array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? '') !== 'mysql') {
            $this->error('db:volcado-entorno-prueba solo soporta conexiones MySQL.');

            return null;
        }

        $database = (string) ($config['database'] ?? '');
        if ($database === '') {
            $this->error('No hay base de datos configurada.');

            return null;
        }

        return [
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? '3306'),
            'database' => $database,
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
        ];
    }

    private function exportarCatalogoPermisos(): void
    {
        if (! Schema::hasTable('permissions')) {
            throw new RuntimeException('No existe la tabla de permisos.');
        }

        $nombres = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn ($nombre): string => (string) $nombre)
            ->filter(static fn (string $nombre): bool => $nombre !== '')
            ->values()
            ->all();

        $ruta = PermisosActualesSeeder::rutaCatalogo();
        File::ensureDirectoryExists(dirname($ruta));

        $lineas = [
            '<?php',
            '',
            '/**',
            ' * Catálogo de permisos capturado desde la base por db:volcado-entorno-prueba.',
            ' * El seeder crea los que falten y no elimina permisos incorporados después.',
            ' */',
            '',
            'return [',
        ];

        foreach ($nombres as $nombre) {
            $escapado = str_replace(['\\', "'"], ['\\\\', "\\'"], $nombre);
            $lineas[] = "    '{$escapado}',";
        }

        $lineas[] = '];';
        $lineas[] = '';

        if (File::put($ruta, implode("\n", $lineas)) === false) {
            throw new RuntimeException('No se pudo escribir el catálogo de permisos.');
        }
    }

    private function anexarConfiguraciones(string $sqlPath): void
    {
        if (! Schema::hasTable('configuraciones_sistema')) {
            return;
        }

        $pdo = DB::connection()->getPdo();
        $filas = DB::table('configuraciones_sistema')->orderBy('id')->get();
        $sql = fopen($sqlPath, 'ab');
        if ($sql === false) {
            throw new RuntimeException('No se pudo anexar la configuración del sistema.');
        }

        fwrite($sql, "\nSET NAMES utf8mb4;\n-- Dumping data for table `configuraciones_sistema`\n");

        foreach ($filas as $fila) {
            $valor = self::claveEsSecreto((string) $fila->clave) ? '' : $fila->valor;
            $valores = [
                (string) (int) $fila->id,
                $this->citar($pdo, (string) $fila->clave),
                $valor === null ? 'NULL' : $this->citar($pdo, (string) $valor),
                $this->citar($pdo, (string) ($fila->tipo ?? 'string')),
                $fila->descripcion === null ? 'NULL' : $this->citar($pdo, (string) $fila->descripcion),
                $fila->grupo === null ? 'NULL' : $this->citar($pdo, (string) $fila->grupo),
                $fila->created_at === null ? 'NULL' : $this->citar($pdo, (string) $fila->created_at),
                $fila->updated_at === null ? 'NULL' : $this->citar($pdo, (string) $fila->updated_at),
            ];

            $linea = 'INSERT INTO `configuraciones_sistema` (`id`, `clave`, `valor`, `tipo`, `descripcion`, `grupo`, `created_at`, `updated_at`) VALUES ('
                .implode(', ', $valores)
                .");\n";

            if (fwrite($sql, $linea) === false) {
                fclose($sql);
                throw new RuntimeException('No se pudo escribir la configuración del sistema.');
            }
        }

        fclose($sql);
    }

    private function citar(\PDO $pdo, string $valor): string
    {
        $citado = $pdo->quote($valor);
        if ($citado === false) {
            throw new RuntimeException('No se pudo citar un valor del volcado.');
        }

        return $citado;
    }

    private function comprimir(string $sqlPath, string $gzPath): void
    {
        $gz = gzopen($gzPath, 'wb9');
        if ($gz === false) {
            throw new RuntimeException('No se pudo crear el volcado comprimido.');
        }

        $entrada = fopen($sqlPath, 'rb');
        if ($entrada === false) {
            gzclose($gz);
            throw new RuntimeException('No se pudo leer el SQL del volcado.');
        }

        while (! feof($entrada)) {
            $bloque = fread($entrada, 1024 * 1024);
            if ($bloque === false) {
                break;
            }
            gzwrite($gz, $bloque);
        }

        fclose($entrada);
        gzclose($gz);
        File::delete($sqlPath);
    }
}

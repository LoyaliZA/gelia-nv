<?php

namespace Tests\Feature\ControlPedidos;

use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Dos procesos y un bloqueo real de InnoDB sobre la base MySQL de desarrollo del .env (no SQLite, donde
 * lockForUpdate no bloquea). Requisito previo: aplicar solo las migraciones 2026_11_27_100000 y
 * 2026_11_27_100100 con --path. Crea filas sintéticas MULTIENVIO-TEST-<aleatorio> y siempre las borra.
 */
class MultienvioNacionalConcurrenciaMysqlTest extends TestCase
{
    private const SCRIPT = '/Support/ControlPedidos/multienvio_nacional_concurrencia.php';

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MULTIENVIO_MYSQL') !== '1') {
            $this->markTestSkipped('Defina MULTIENVIO_MYSQL=1 (MySQL de desarrollo con las migraciones de Multienvío aplicadas).');
        }
    }

    public function test_dos_confirmaciones_simultaneas_dejan_un_grupo_activo_con_tres_envios(): void
    {
        $this->carrera('confirmar');
    }

    public function test_dos_activaciones_simultaneas_crean_un_solo_grupo(): void
    {
        $this->carrera('activar');
    }

    private function carrera(string $caso): void
    {
        $dir = sys_get_temp_dir().'/multienvio-mysql-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $sostener = null;
        $competir = null;
        $error = null;

        try {
            $this->correr(['setup', $caso, $dir]);

            $sostener = $this->abrir(['sostener', $caso, $dir]);
            $limite = time() + 30;
            while (! is_file($dir.'/held') && time() < $limite) {
                usleep(100000);
            }
            $this->assertFileExists($dir.'/held', $this->leer($dir.'/sostener-err.txt'));

            $competir = $this->abrir(['competir', $caso, $dir]);
            usleep(1600000);
            $this->assertFalse(is_file($dir.'/competir.json'), 'La segunda llamada no esperó: '.$this->leer($dir.'/competir.json').$this->leer($dir.'/competir-err.txt'));
            $bloqueo = json_decode($this->salida(['bloqueo', $caso, $dir]), true, 512, JSON_THROW_ON_ERROR);
            file_put_contents($dir.'/release', (string) microtime(true));

            $codigoSostener = proc_close($sostener);
            $sostener = null;
            $codigoCompetir = proc_close($competir);
            $competir = null;
            $this->assertSame(0, $codigoSostener, $this->leer($dir.'/sostener-err.txt'));
            $this->assertSame(0, $codigoCompetir, $this->leer($dir.'/competir-err.txt'));
            $this->assertTrue($bloqueo['esperando'] ?? false, 'La segunda llamada no estaba esperando el bloqueo de InnoDB: '.json_encode($bloqueo));

            $this->correr(['afirmar', $caso, $dir]);
            fwrite(STDERR, "\n[{$caso}] bloqueo=".json_encode($bloqueo).' afirmar='.$this->leer($dir.'/afirmar-out.txt')."\n");
        } catch (Throwable $e) {
            $error = $e;
        } finally {
            if ($sostener !== null || $competir !== null) {
                @file_put_contents($dir.'/release', (string) microtime(true));
                $sostener !== null && proc_close($sostener);
                $competir !== null && proc_close($competir);
            }
            $codigoLimpieza = proc_close($this->abrir(['limpiar', $caso, $dir]));
            $limpieza = $this->leer($dir.'/limpiar-out.txt');
            fwrite(STDERR, "[{$caso}] limpiar=".$limpieza."\n");
            if ($codigoLimpieza !== 0) {
                fwrite(STDERR, "\n*** LIMPIEZA INCOMPLETA EN LA BASE DE DESARROLLO ({$dir}) ***\n".$this->leer($dir.'/limpiar-err.txt')."\n");
            }
        }

        if ($error !== null) {
            throw $error;
        }
        $this->assertSame(0, $codigoLimpieza, 'LIMPIEZA INCOMPLETA: '.$this->leer($dir.'/limpiar-err.txt'));
        $resumen = json_decode($limpieza, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([], $resumen['quedan']);
        $this->assertSame($resumen['antes'], $resumen['despues'], 'Los conteos de pedidos/usuarios no volvieron al estado inicial.');
        $this->borrarDirectorio($dir);
    }

    /**
     * @param  list<string>  $args
     * @return resource
     */
    private function abrir(array $args)
    {
        [$rol, , $dir] = $args;
        $proceso = proc_open(
            array_merge([PHP_BINARY, dirname(__DIR__, 2).self::SCRIPT], $args),
            [
                1 => ['file', $dir.'/'.$rol.'-out.txt', 'w'],
                2 => ['file', $dir.'/'.$rol.'-err.txt', 'w'],
            ],
            $pipes
        );
        $this->assertIsResource($proceso);

        return $proceso;
    }

    /** @param  list<string>  $args */
    private function correr(array $args): void
    {
        $codigo = proc_close($this->abrir($args));
        if ($codigo !== 0) {
            $this->fail($args[0].': '.($this->leer($args[2].'/'.$args[0].'-err.txt') ?: 'el proceso falló.'));
        }
    }

    /** @param  list<string>  $args */
    private function salida(array $args): string
    {
        $this->correr($args);

        return $this->leer($args[2].'/'.$args[0].'-out.txt');
    }

    private function leer(string $ruta): string
    {
        return is_file($ruta) ? (string) file_get_contents($ruta) : '';
    }

    private function borrarDirectorio(string $dir): void
    {
        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterador as $archivo) {
            $archivo->isDir() ? @rmdir($archivo->getPathname()) : @unlink($archivo->getPathname());
        }
        @rmdir($dir);
    }
}

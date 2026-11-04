<?php

namespace Tests\Feature\ControlPedidos;

use PHPUnit\Framework\TestCase;

/** Dos procesos y un bloqueo real de InnoDB. No usa la base de trabajo. */
class InstruccionRegistrarConcurrenciaMysqlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('SOLICITUD_TIENDA_MYSQL') !== '1') {
            $this->markTestSkipped('Defina SOLICITUD_TIENDA_MYSQL=1 para la base aislada gelia_solicitud_211_test.');
        }
    }

    public function test_dos_solicitudes_concurrentes_dejan_una_tarea(): void
    {
        $this->carrera('duplicar');
    }

    public function test_despacho_de_instruccion_sustituida_no_toca_la_nueva(): void
    {
        $this->carrera('sustituir');
    }

    private function carrera(string $caso): void
    {
        $dir = sys_get_temp_dir().'/solicitud-211-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $this->assertSame(0, $this->correr(['setup', $caso, $dir]), 'No se pudo preparar la base aislada.');

        $sostener = $this->abrir(['sostener', $caso, $dir]);
        $limite = time() + 30;
        while (! is_file($dir.'/held') && time() < $limite) {
            usleep(100000);
        }
        $this->assertFileExists($dir.'/held', (string) @file_get_contents($dir.'/sostener-err.txt'));

        $competir = $this->abrir(['competir', $caso, $dir]);
        usleep(1200000);
        $this->assertFalse(is_file($dir.'/competir.json'), (string) @file_get_contents($dir.'/competir-err.txt'));
        $bloqueos = trim($this->salida(['bloqueo', $caso, $dir]));
        file_put_contents($dir.'/release', '1');

        $codigoSostener = proc_close($sostener);
        $codigoCompetir = proc_close($competir);
        $this->assertSame(0, $codigoSostener, (string) @file_get_contents($dir.'/sostener-err.txt'));
        $this->assertSame(0, $codigoCompetir, (string) @file_get_contents($dir.'/competir-err.txt'));

        $this->assertSame(0, $this->correr(['afirmar', $caso, $dir]));
        if ($bloqueos !== '-1') {
            $this->assertGreaterThan(0, (int) $bloqueos, 'No hubo una espera de bloqueo en InnoDB.');
        }
    }

    /**
     * @param  list<string>  $args
     * @return resource
     */
    private function abrir(array $args)
    {
        $dir = $args[2];
        $rol = $args[0];
        $proceso = proc_open(
            array_merge([PHP_BINARY, dirname(__DIR__, 2).'/Support/ControlPedidos/instruccion_registrar_concurrencia.php'], $args),
            [
                1 => ['file', $dir.'/'.$rol.'-out.txt', 'w'],
                2 => ['file', $dir.'/'.$rol.'-err.txt', 'w'],
            ],
            $pipes
        );
        $this->assertIsResource($proceso);

        return $proceso;
    }

    /**
     * @param  list<string>  $args
     */
    private function correr(array $args): int
    {
        $proceso = $this->abrir($args);
        $codigo = proc_close($proceso);
        if ($codigo !== 0) {
            $this->fail((string) @file_get_contents($args[2].'/'.$args[0].'-err.txt') ?: 'El proceso aislado falló.');
        }

        return $codigo;
    }

    /**
     * @param  list<string>  $args
     */
    private function salida(array $args): string
    {
        $proceso = $this->abrir($args);
        $codigo = proc_close($proceso);
        $this->assertSame(0, $codigo, (string) @file_get_contents($args[2].'/'.$args[0].'-err.txt'));

        return (string) @file_get_contents($args[2].'/'.$args[0].'-out.txt');
    }
}

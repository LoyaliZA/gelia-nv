<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\PuntoVenta\MedicionEventoPdv;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\PuntoVenta\MySqlMedicionA331;
use Tests\TestCase;

class ConcurrenciaAperturaMedicionMySqlTest extends TestCase
{
    private string $barrera;

    private array $procesos = [];

    private int $usuario;

    private int $sucursal;

    public function createApplication()
    {
        return getenv('PDV_MEDICION_A331_MYSQL_TEST') === '1'
            ? MySqlMedicionA331::application() : parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PDV_MEDICION_A331_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Requiere MySQL efímero A3.3.1 con todas las migraciones.');
        }
        $this->assertSame('gelia_medicion_a331_test', DB::connection()->getDatabaseName());
        $this->barrera = sys_get_temp_dir().'/a331-'.bin2hex(random_bytes(8));
        mkdir($this->barrera);
        $this->usuario = User::factory()->create()->id;
        $this->sucursal = Sucursal::factory()->create()->id;
    }

    protected function tearDown(): void
    {
        if (isset($this->barrera)) {
            touch($this->barrera.'/carrera.liberar');
        }
        foreach ($this->procesos as $nombre => $p) {
            touch($this->barrera.'/'.$nombre.'.liberar');
            touch($this->barrera.'/'.$nombre.'.iniciar');
            $estado = proc_get_status($p);
            if ($estado['running']) {
                proc_terminate($p);
            }
            proc_close($p);
        }
        if (isset($this->barrera)) {
            foreach (glob($this->barrera.'/*') as $archivo) {
                unlink($archivo);
            }
            rmdir($this->barrera);
        }
        parent::tearDown();
    }

    public function test_dos_aperturas_solapadas_mismo_agregado_confirman_un_solo_hecho(): void
    {
        $this->lanzar('a', $this->usuario, 'retener_usuario');
        $this->iniciar('a');
        $this->esperar('a.retenido');
        $this->lanzar('b', $this->usuario);
        $this->iniciar('b');
        $this->esperarBloqueo();
        touch($this->barrera.'/a.liberar');
        $a = $this->resultado('a');
        $b = $this->resultado('b');
        $this->assertTrue($a['ok'], json_encode($a));
        $this->assertTrue($b['ok'], json_encode($b));
        $this->assertNotSame($a['pid'], $b['pid']);
        $this->assertNotSame($a['conexion'], $b['conexion']);
        $this->assertFalse($a['reintento']);
        $this->assertTrue($b['reintento']);
        $this->assertSame($a['jornada'], $b['jornada']);
        $this->assertSame($a['intervalo'], $b['intervalo']);
        $this->verificarEstado($this->usuario, 1);
    }

    public function test_secuencia_actual_entre_sucursales_aun_con_snapshot_anterior(): void
    {
        $otra = Sucursal::factory()->create()->id;
        $this->lanzar('a', $this->usuario, 'retener_evento');
        $this->iniciar('a');
        $this->esperar('a.retenido');
        $this->lanzar('b', $this->usuario, 'snapshot', 'activa', $otra);
        $this->iniciar('b');
        $this->esperarBloqueo();
        touch($this->barrera.'/a.liberar');
        $this->assertTrue($this->resultado('a')['ok']);
        $b = $this->resultado('b');
        $this->assertTrue($b['ok'], json_encode($b));
        $this->assertFalse($b['reintento']);
        $eventos = MedicionEventoPdv::query()->where('user_id', $this->usuario)->orderBy('secuencia')->get();
        $this->assertSame([1, 2], $eventos->pluck('secuencia')->all());
        $this->assertSame([$this->sucursal, $otra], $eventos->pluck('sucursal_id')->all());
        $this->assertCount(2, $eventos->pluck('idempotency_key')->unique());
        $this->assertSame(2, DB::table('pdv_jornadas')->where('user_id', $this->usuario)->count());
        $this->assertSame(2, DB::table('pdv_intervalos_operativos')->where('user_id', $this->usuario)->count());
    }

    public function test_timeout_no_deja_datos_parciales_y_admite_reintento(): void
    {
        $this->lanzar('a', $this->usuario, 'retener_usuario');
        $this->iniciar('a');
        $this->esperar('a.retenido');
        $this->lanzar('b', $this->usuario, 'timeout');
        $this->iniciar('b');
        $this->esperarBloqueo();
        $b = $this->resultado('b');
        $this->assertFalse($b['ok']);
        $this->assertSame(1205, $b['codigo_mysql']);
        $this->verificarEstado($this->usuario, 0);
        touch($this->barrera.'/a.liberar');
        $this->assertTrue($this->resultado('a')['ok']);
        $this->lanzar('c', $this->usuario);
        $this->iniciar('c');
        $c = $this->resultado('c');
        $this->assertTrue($c['ok']);
        $this->assertTrue($c['reintento']);
        $this->verificarEstado($this->usuario, 1);
    }

    public function test_fallo_tras_evento_revierte_y_apertura_competidora_completa(): void
    {
        $this->lanzar('a', $this->usuario, 'fallo_evento');
        $this->iniciar('a');
        $this->esperar('a.retenido');
        $this->lanzar('b', $this->usuario);
        $this->iniciar('b');
        $this->esperarBloqueo();
        touch($this->barrera.'/a.liberar');
        $this->assertFalse($this->resultado('a')['ok']);
        $b = $this->resultado('b');
        $this->assertTrue($b['ok'], json_encode($b));
        $this->assertFalse($b['reintento']);
        $this->verificarEstado($this->usuario, 1);
    }

    public function test_usuarios_distintos_identifican_espera_de_rango_y_conservan_integridad(): void
    {
        $otro = User::factory()->create()->id;
        $this->lanzar('a', $this->usuario, 'retener_evento');
        $this->iniciar('a');
        $this->esperar('a.retenido');
        $this->lanzar('b', $otro);
        $this->iniciar('b');
        $independiente = $this->esperar('b.resultado', 3, false);
        $esperas = DB::select('SELECT l.OBJECT_NAME, l.INDEX_NAME, l.LOCK_TYPE, l.LOCK_MODE, l.LOCK_DATA FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.BLOCKING_ENGINE_LOCK_ID');
        $sql = is_file($this->barrera.'/b.sql') ? file_get_contents($this->barrera.'/b.sql') : '';
        touch($this->barrera.'/a.liberar');
        $a = $this->resultado('a');
        $b = $this->resultado('b');
        $this->assertTrue($a['ok'], json_encode($a));
        $this->assertTrue($b['ok'], json_encode($b));
        $this->verificarEstado($this->usuario, 1);
        $this->verificarEstado($otro, 1);
        fwrite(STDOUT, "\nA331 activa independiente=".json_encode($independiente).' bloqueos='.json_encode($esperas).' SQL: '.$sql."\n");
        if (! $independiente) {
            $this->assertNotEmpty($esperas, 'La espera debe quedar atribuida a un bloqueo real.');
            $this->assertSame('pdv_jornadas', $esperas[0]->OBJECT_NAME);
            $this->assertSame('pdv_jornadas_user_sucursal_activa_unique', $esperas[0]->INDEX_NAME);
        }
    }

    public function test_control_desactivada_espera_entre_usuarios(): void
    {
        $otro = User::factory()->create()->id;
        $this->lanzar('a', $this->usuario, 'retener_intervalo', 'desactivada');
        $this->iniciar('a');
        $this->esperar('a.retenido');
        $this->lanzar('b', $otro, 'normal', 'desactivada');
        $this->iniciar('b');
        $independiente = $this->esperar('b.resultado', 3, false);
        $esperas = DB::select('SELECT l.OBJECT_NAME, l.INDEX_NAME, l.LOCK_MODE, l.LOCK_DATA FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.BLOCKING_ENGINE_LOCK_ID');
        fwrite(STDOUT, "\nA331 control desactivada independiente=".json_encode($independiente).' bloqueos='.json_encode($esperas)."\n");
        touch($this->barrera.'/a.liberar');
        $this->assertTrue($this->resultado('a')['ok']);
        $this->assertTrue($this->resultado('b')['ok']);
        $this->verificarEstado($this->usuario, 0, 1);
        $this->verificarEstado($otro, 0, 1);
    }

    public function test_desactivada_carrera_conserva_unicidad_y_no_escribe_medicion(): void
    {
        // Ambas aperturas ya leyeron la ausencia de jornada antes de continuar.
        $this->lanzar('a', $this->usuario, 'carrera_sin_medicion', 'desactivada');
        $this->lanzar('b', $this->usuario, 'carrera_sin_medicion', 'desactivada');
        $this->iniciar('a');
        $this->iniciar('b');
        $this->esperar('a.retenido');
        $this->esperar('b.retenido');
        touch($this->barrera.'/carrera.liberar');
        $a = $this->resultado('a');
        $b = $this->resultado('b');
        $this->assertTrue($a['ok'] || $b['ok'], 'Al menos una apertura debe confirmar.');
        foreach ([$a, $b] as $resultado) {
            if (! $resultado['ok']) {
                $this->assertTrue(in_array($resultado['codigo_mysql'], [1205, 1213], true)
                    || $resultado['clase'] === ValidationException::class);
            }
        }
        $this->lanzar('c', $this->usuario, 'normal', 'desactivada');
        $this->iniciar('c');
        $c = $this->resultado('c');
        $this->assertTrue($c['ok']);
        $this->assertTrue($c['reintento']);
        $this->verificarEstado($this->usuario, 0, 1);
    }

    private function lanzar(string $nombre, int $usuario, string $modo = 'normal', string $estado = 'activa', ?int $sucursal = null): void
    {
        $comando = [PHP_BINARY, base_path('tests/Support/PuntoVenta/apertura_medicion_worker.php'),
            $this->barrera, $nombre, (string) $usuario, (string) ($sucursal ?? $this->sucursal), $modo, $estado];
        $this->procesos[$nombre] = proc_open($comando, [0 => ['file', '/dev/null', 'r'],
            1 => ['file', $this->barrera.'/'.$nombre.'.out', 'w'], 2 => ['file', $this->barrera.'/'.$nombre.'.err', 'w']], $pipes, base_path());
        $this->assertIsResource($this->procesos[$nombre]);
        $this->esperar($nombre.'.listo');
    }

    private function iniciar(string $nombre): void
    {
        touch($this->barrera.'/'.$nombre.'.iniciar');
        $this->esperar($nombre.'.ejecutando');
    }

    private function esperar(string $marca, float $segundos = 12, bool $exigir = true): bool
    {
        $limite = microtime(true) + $segundos;
        do {
            clearstatcache();
            if (is_file($this->barrera.'/'.$marca)) {
                return true;
            }
            usleep(10000);
        } while (microtime(true) < $limite);
        if ($exigir) {
            $errores = [];
            foreach (glob($this->barrera.'/*.err') as $archivo) {
                $errores[] = file_get_contents($archivo);
            }
            $this->fail('Barrera vencida '.$marca.' '.implode(' ', $errores));
        }

        return false;
    }

    private function resultado(string $nombre): array
    {
        $this->esperar($nombre.'.resultado');
        $resultado = json_decode(file_get_contents($this->barrera.'/'.$nombre.'.resultado'), true, flags: JSON_THROW_ON_ERROR);
        fwrite(STDOUT, "\nA331 ".$nombre.' '.json_encode($resultado)."\n");

        return $resultado;
    }

    private function esperarBloqueo(): void
    {
        $limite = microtime(true) + 3;
        do {
            if (DB::select('SELECT * FROM performance_schema.data_lock_waits') !== []) {
                $this->assertTrue(true);

                return;
            }
            usleep(10000);
        } while (microtime(true) < $limite);
        $this->fail('No se observó solapamiento bloqueante en MySQL.');
    }

    private function verificarEstado(int $usuario, int $eventos, ?int $jornadas = null): void
    {
        $jornadas ??= $eventos;
        $this->assertSame($jornadas, DB::table('pdv_jornadas')->where('user_id', $usuario)->count());
        $this->assertSame($jornadas, DB::table('pdv_intervalos_operativos')->where('user_id', $usuario)->count());
        $this->assertSame($eventos, MedicionEventoPdv::query()->where('user_id', $usuario)->count());
        if ($eventos === 1) {
            $evento = MedicionEventoPdv::query()->where('user_id', $usuario)->sole();
            $this->assertSame(1, $evento->secuencia);
            $this->assertSame(hash('sha256', 'pdv:medicion:apertura:'.$evento->jornada_id), $evento->idempotency_key);
            $this->assertSame($this->sucursal, $evento->sucursal_id);
            $this->assertSame($evento->jornada_id, DB::table('pdv_intervalos_operativos')->where('id', $evento->intervalo_id)->value('jornada_id'));
        }
    }
}

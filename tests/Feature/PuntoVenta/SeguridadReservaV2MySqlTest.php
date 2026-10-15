<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\PuntoVenta\MySqlMedicionA34;
use Tests\TestCase;

/** Puerta previa a V2: las invariantes deben cumplirse antes de instrumentar. */
class SeguridadReservaV2MySqlTest extends TestCase
{
    private string $barrera;

    private array $procesos = [];

    private User $usuario;

    private Sucursal $sucursal;

    public function createApplication()
    {
        return getenv('PDV_MEDICION_A34_MYSQL_TEST') === '1'
            ? MySqlMedicionA34::application() : parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PDV_MEDICION_A34_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Requiere MySQL efímero A3.4; no sustituir por SQLite.');
        }
        $this->assertSame('gelia_medicion_a34_test', DB::connection()->getDatabaseName());
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'America/Mexico_City'));
        if (! Schema::hasTable('pdv_medicion_eventos')) {
            $this->crearEsquemaAcotado();
        }
        Role::findOrCreate('Super Admin', 'web');
        DB::table('configuraciones_sistema')->updateOrInsert(['clave' => PuntoVentaModulo::CLAVE_FLAG], ['valor' => '1']);
        foreach ([PuntoVentaModulo::PERMISO_ACCEDER, PuntoVentaModulo::PERMISO_TURNOS_ATENDER, PuntoVentaModulo::PERMISO_OPERACION_PAUSA] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([PuntoVentaModulo::PERMISO_ACCEDER, PuntoVentaModulo::PERMISO_TURNOS_ATENDER, PuntoVentaModulo::PERMISO_OPERACION_PAUSA]);
        $this->sucursal = $this->prepararSucursal();
        $this->barrera = sys_get_temp_dir().'/a34-'.bin2hex(random_bytes(8));
        mkdir($this->barrera);
    }

    protected function tearDown(): void
    {
        foreach ($this->procesos as $nombre => $p) {
            touch($this->barrera.'/'.$nombre.'.liberar');
            touch($this->barrera.'/'.$nombre.'.iniciar');
            if (proc_get_status($p)['running']) {
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
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_asignacion_y_pausa_no_deben_confirmar_simultaneamente_para_la_misma_persona(): void
    {
        $turno = TurnoPdv::factory()->create(['sucursal_id' => $this->sucursal->id]);
        $this->lanzar('asignar', 'asignacion', $this->sucursal->id);
        $this->esperar('asignar.validado');
        $this->lanzar('pausar', 'pausa', $this->sucursal->id);
        $this->esperar('pausar.validado');
        touch($this->barrera.'/pausar.liberar');
        $pausa = $this->resultado('pausar');
        touch($this->barrera.'/asignar.liberar');
        $asignacion = $this->resultado('asignar');
        $this->assertTrue($pausa['ok'], json_encode($pausa));
        $this->assertTrue($asignacion['ok'], json_encode($asignacion));
        $this->assertNotSame($pausa['conexion'], $asignacion['conexion']);
        $pausas = DB::table('pdv_intervalos_operativos')->where('user_id', $this->usuario->id)->whereNull('fin_at')->where('tipo', TipoIntervaloOperativoPdv::EnPausa->value)->count();
        $reservas = DB::table('pdv_turno_atenciones')->where('user_id', $this->usuario->id)->whereNull('fin_at')->count();
        fwrite(STDOUT, "\nA34 estado final turno=".$turno->fresh()->estado.' pausas='.$pausas.' reservas='.$reservas."\n");
        $this->assertSame(0, DB::table('pdv_medicion_eventos')->count());
        $this->assertFalse($pausas > 0 && $reservas > 0, 'Puerta V2: se confirmó pausa y reserva incompatibles.');
    }

    public function test_dos_sucursales_no_deben_reservar_simultaneamente_a_la_misma_persona(): void
    {
        $otra = $this->prepararSucursal();
        TurnoPdv::factory()->create(['sucursal_id' => $this->sucursal->id]);
        TurnoPdv::factory()->create(['sucursal_id' => $otra->id]);
        $this->lanzar('a', 'asignacion', $this->sucursal->id);
        $this->esperar('a.validado');
        $this->lanzar('b', 'asignacion', $otra->id);
        $this->esperar('b.validado');
        touch($this->barrera.'/a.liberar');
        $a = $this->resultado('a');
        touch($this->barrera.'/b.liberar');
        $b = $this->resultado('b');
        $this->assertTrue($a['ok'], json_encode($a));
        $this->assertTrue($b['ok'], json_encode($b));
        $this->assertNotSame($a['conexion'], $b['conexion']);
        $reservas = DB::table('pdv_turno_atenciones')->where('user_id', $this->usuario->id)->whereNull('fin_at')->count();
        fwrite(STDOUT, "\nA34 reservas abiertas usuario=".$this->usuario->id.' cantidad='.$reservas."\n");
        $this->assertSame(0, DB::table('pdv_medicion_eventos')->count());
        $this->assertSame(1, $reservas, 'Puerta V2: dos turnos distintos reservan a la misma persona.');
    }

    public function test_control_doble_matchmaker_mismo_turno_conserva_una_sola_reserva(): void
    {
        TurnoPdv::factory()->create(['sucursal_id' => $this->sucursal->id]);
        $this->lanzar('a', 'asignacion', $this->sucursal->id);
        $this->esperar('a.validado');
        $this->lanzar('b', 'asignacion', $this->sucursal->id);
        $limite = microtime(true) + 3;
        do {
            $bloqueos = DB::select('SELECT * FROM performance_schema.data_lock_waits');
            if ($bloqueos !== []) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $limite);
        $this->assertNotEmpty($bloqueos, 'Debe observarse espera real del segundo selector.');
        touch($this->barrera.'/a.liberar');
        $this->assertTrue($this->resultado('a')['ok']);
        $b = $this->resultado('b');
        $this->assertTrue($b['ok'], json_encode($b));
        $this->assertSame(0, $b['asignaciones']);
        $this->assertSame(1, DB::table('pdv_turno_atenciones')->where('user_id', $this->usuario->id)->whereNull('fin_at')->count());
    }

    private function prepararSucursal(): Sucursal
    {
        $sucursal = Sucursal::factory()->create();
        $this->usuario->concederAccesoSucursal($sucursal, esPrincipal: false);
        DB::table('pdv_sucursal_dias')->insert(['sucursal_id' => $sucursal->id, 'fecha_operativa' => '2026-10-14', 'acepta_altas' => true, 'version' => 1]);
        $id = DB::table('pdv_jornadas')->insertGetId(['user_id' => $this->usuario->id, 'sucursal_id' => $sucursal->id,
            'estado' => EstadoJornadaPdv::Abierta->value, 'jornada_activa_marcador' => '1', 'apertura_at' => '2026-10-14 09:00:00', 'version' => 1]);
        DB::table('pdv_intervalos_operativos')->insert(['user_id' => $this->usuario->id, 'sucursal_id' => $sucursal->id,
            'jornada_id' => $id, 'tipo' => TipoIntervaloOperativoPdv::Disponible->value, 'inicio_at' => '2026-10-14 09:00:00', 'intervalo_abierto_marcador' => '1', 'version' => 1]);

        return $sucursal;
    }

    private function lanzar(string $nombre, string $modo, int $sucursal): void
    {
        $this->procesos[$nombre] = proc_open([PHP_BINARY, base_path('tests/Support/PuntoVenta/seguridad_reserva_worker.php'),
            $this->barrera, $nombre, $modo, (string) $sucursal, (string) $this->usuario->id],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->barrera.'/'.$nombre.'.out', 'w'], 2 => ['file', $this->barrera.'/'.$nombre.'.err', 'w']], $pipes, base_path());
        $this->assertIsResource($this->procesos[$nombre]);
        $this->esperar($nombre.'.listo');
        touch($this->barrera.'/'.$nombre.'.iniciar');
        $this->esperar($nombre.'.ejecutando');
    }

    private function esperar(string $marca): void
    {
        $limite = microtime(true) + 10;
        do {
            clearstatcache();
            if (is_file($this->barrera.'/'.$marca)) {
                return;
            }
            $nombre = explode('.', $marca)[0];
            if (! str_ends_with($marca, '.resultado') && is_file($this->barrera.'/'.$nombre.'.resultado')) {
                $this->fail('El trabajador terminó antes de '.$marca.': '.file_get_contents($this->barrera.'/'.$nombre.'.resultado'));
            }
            usleep(10000);
        } while (microtime(true) < $limite);
        $evidencia = [];
        foreach (glob($this->barrera.'/*') as $archivo) {
            $evidencia[basename($archivo)] = file_get_contents($archivo);
        }
        $this->fail('Barrera vencida '.$marca.' '.json_encode($evidencia));
    }

    private function resultado(string $nombre): array
    {
        $this->esperar($nombre.'.resultado');
        $resultado = json_decode(file_get_contents($this->barrera.'/'.$nombre.'.resultado'), true, flags: JSON_THROW_ON_ERROR);
        fwrite(STDOUT, "\nA34 ".$nombre.' '.json_encode($resultado)."\n");

        return $resultado;
    }

    private function crearEsquemaAcotado(): void
    {
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::table('users', fn (Blueprint $t) => $t->softDeletes());
        Schema::create('sucursales', function (Blueprint $t) {
            $t->id();
            $t->string('codigo');
            $t->string('nombre');
            $t->boolean('activo')->default(true);
            $t->boolean('es_demo')->default(false);
            $t->timestamps();
        });
        Schema::create('clientes', function (Blueprint $t) {
            $t->id();
            $t->boolean('es_demo')->default(false);
        });
        Schema::create('catalogo_listas_descuento', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->boolean('activo')->default(true);
        });
        Schema::create('configuraciones_sistema', function (Blueprint $t) {
            $t->id();
            $t->string('clave')->unique();
            $t->text('valor')->nullable();
            $t->string('tipo')->nullable();
            $t->text('descripcion')->nullable();
            $t->string('grupo')->nullable();
            $t->timestamps();
        });
        foreach (['2026_05_02_194129_create_permission_tables.php', '2026_09_01_150000_create_sucursal_user_table.php',
            '2026_09_04_120000_create_pdv_turnos_tables.php', '2026_09_04_180000_turnos_ciclo_atencion_4f.php',
            '2026_09_04_200000_create_pdv_operacion_tables.php', '2026_09_07_230000_create_pdv_motivos_pausa_table.php',
            '2026_09_07_230100_add_pausa_metadata_to_pdv_intervalos_operativos.php', '2026_09_21_170000_add_fecha_operativa_unique_to_pdv_turnos.php',
            '2026_09_29_140000_add_disponible_desde_to_pdv_jornadas.php', '2026_10_03_120000_add_prioridad_cola_y_tono_lista_turnos.php', '2026_10_14_180000_create_pdv_medicion_eventos_table.php'] as $archivo) {
            (require database_path('migrations/'.$archivo))->up();
        }
        Schema::table('pdv_turnos', fn (Blueprint $t) => $t->boolean('es_demo')->default(false));
    }
}

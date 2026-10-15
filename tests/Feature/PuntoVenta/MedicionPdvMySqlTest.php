<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\JornadaAbierta;
use App\Models\PuntoVenta\MedicionEventoPdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\RegistrarTransicionMedicionPdvService;
use App\Support\PuntoVenta\Medicion\CatalogoEventosMedicionPdv as Catalogo;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Solo se conecta al contenedor de pruebas interno y a una base dedicada. */
class MedicionPdvMySqlTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (getenv('PDV_MEDICION_MYSQL_TEST') === '1') {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.mysql', [
                'driver' => 'mysql', 'host' => 'mysql-a33', 'port' => 3306,
                'database' => 'gelia_medicion_a33_test', 'username' => 'root', 'password' => '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
                'strict' => true, 'timezone' => '+00:00',
            ]);
            $app['db']->purge();
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PDV_MEDICION_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Requiere el MySQL efímero aislado de A3.3; no sustituir por SQLite.');
        }
        $this->assertSame('gelia_medicion_a33_test', DB::connection()->getDatabaseName());
        if (! Schema::hasTable('pdv_medicion_eventos')) {
            $this->crearBaseMinima();
            (require database_path('migrations/2026_10_14_180000_create_pdv_medicion_eventos_table.php'))->up();
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (getenv('PDV_MEDICION_MYSQL_TEST') === '1') {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_v0_mysql_estructura_y_restricciones_reales(): void
    {
        $this->assertStringStartsWith('8.4.', DB::selectOne('SELECT VERSION() AS v')->v);
        $indices = collect(DB::select('SHOW INDEX FROM pdv_medicion_eventos'))->pluck('Key_name')->unique();
        $this->assertTrue($indices->contains('pdv_med_secuencia_uq'));
        $this->assertTrue($indices->contains('pdv_med_idem_uq'));
        $this->assertCount(2, DB::select("SHOW TRIGGERS LIKE 'pdv_medicion_eventos'"));
        $checks = DB::select("SELECT * FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pdv_medicion_eventos' AND CONSTRAINT_TYPE = 'CHECK'");
        $this->assertCount(3, $checks);
        $columna = DB::selectOne("SELECT DATETIME_PRECISION AS p FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pdv_medicion_eventos' AND COLUMN_NAME = 'registrado_at'");
        $this->assertSame(6, (int) $columna->p);
    }

    public function test_v0_usuario_y_reloj_independiente_de_registro(): void
    {
        Carbon::setTestNow('2000-01-01 00:00:00');
        $evento = $this->registrar($this->datos());
        $this->assertSame(200, $evento->actor_id);
        $this->assertSame(100, $evento->user_id);
        $this->assertSame(1, $evento->secuencia);
        $this->assertSame('2026-09-07T16:00:00+00:00', $evento->efectivo_at->toIso8601String());
        $this->assertNotSame(2000, $evento->registrado_at->year);
        $this->assertFalse($evento->contexto['cobertura']['completa']);
    }

    public function test_v0_actor_sistema_sin_usuario_ficticio(): void
    {
        $datos = $this->datos();
        $datos['actor_id'] = null;
        $datos['origen'] = 'job';
        $evento = $this->registrar($datos);
        $this->assertNull($evento->actor_id);
        $this->assertSame('sistema', $evento->actor_tipo);
        $this->assertSame(100, $evento->user_id);
    }

    public function test_v0_idempotencia_y_conflicto_explicito(): void
    {
        $datos = $this->datos();
        $uno = $this->registrar($datos);
        $dos = $this->registrar($datos);
        $this->assertSame($uno->id, $dos->id);
        $this->assertSame(1, MedicionEventoPdv::query()->count());
        $datos['correlacion_uuid'] = (string) Str::uuid();
        $this->expectException(ValidationException::class);
        $this->registrar($datos);
    }

    public function test_v0_rechaza_tipo_no_admitido_y_referencias_incoherentes(): void
    {
        foreach ([['tipo' => 'actividad.inventada'], ['sucursal_id' => 2], ['contrato_version' => 2], ['actor_id' => null], ['origen' => 'personal']] as $cambio) {
            try {
                $this->registrar(array_merge($this->datos(), $cambio));
                $this->fail('Debió rechazar contenido no válido.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, MedicionEventoPdv::query()->count());
    }

    public function test_v0_correccion_append_only_y_secuencia(): void
    {
        $original = $this->registrar($this->datos());
        $datos = $this->datos();
        $datos['tipo'] = Catalogo::CORRECCION;
        $datos['corrige_evento_id'] = $original->id;
        $datos['contexto'] = ['motivo' => 'Anotación verificable de prueba'];
        $correccion = $this->registrar($datos);
        $this->assertSame(2, $correccion->secuencia);
        $this->assertSame($original->id, $correccion->corrige_evento_id);
        $this->assertSame(Catalogo::APERTURA, $original->fresh()->tipo);
    }

    public function test_v0_modelo_y_sql_directo_no_modifican_evidencia(): void
    {
        $evento = $this->registrar($this->datos());
        foreach (['update', 'delete'] as $accion) {
            try {
                $accion === 'update' ? $evento->update(['origen' => 'personal']) : $evento->delete();
                $this->fail('El modelo debe ser inmutable.');
            } catch (\LogicException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
            try {
                $query = DB::table('pdv_medicion_eventos')->where('id', $evento->id);
                $accion === 'update' ? $query->update(['origen' => 'personal']) : $query->delete();
                $this->fail('El trigger debe proteger SQL directo.');
            } catch (QueryException $e) {
                $this->assertSame('45000', $e->errorInfo[0]);
            }
        }
        $this->assertSame('gerencial', $evento->fresh()->origen);
    }

    public function test_v0_restricciones_check_y_fk_en_motor(): void
    {
        $base = $this->registrar($this->datos())->getAttributes();
        unset($base['id']);
        foreach ([['secuencia' => 0], ['actor_id' => 999999], ['actor_tipo' => 'sistema']] as $cambio) {
            try {
                DB::table('pdv_medicion_eventos')->insert(array_merge($base, [
                    'evento_uuid' => (string) Str::uuid(), 'idempotency_key' => hash('sha256', (string) Str::uuid()), 'secuencia' => 2,
                ], $cambio));
                $this->fail('La restricción SQL debe impedirlo.');
            } catch (QueryException $e) {
                $this->assertContains((int) $e->errorInfo[1], [3819, 1452]);
            }
        }
    }

    public function test_v0_rollback_del_evento_y_operacion(): void
    {
        try {
            DB::transaction(function () {
                DB::table('pdv_jornadas')->where('id', 1)->update(['version' => 2]);
                $this->registrar($this->datos());
                throw new \RuntimeException('Fallo operativo posterior');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo operativo posterior', $e->getMessage());
        }
        $this->assertSame(0, MedicionEventoPdv::query()->count());
        $this->assertSame(1, (int) DB::table('pdv_jornadas')->where('id', 1)->value('version'));
    }

    public function test_v0_bloqueo_del_agregado_entre_conexiones_solapadas(): void
    {
        $this->registrar($this->datos());
        config(['database.connections.medicion_concurrente' => config('database.connections.mysql')]);
        $otra = DB::connection('medicion_concurrente');
        $otra->statement('SET SESSION innodb_lock_wait_timeout=1');
        $otra->beginTransaction();
        try {
            $otra->table('users')->where('id', 100)->lockForUpdate()->first();
            $this->fail('La segunda conexión no debe adquirir el agregado bloqueado.');
        } catch (QueryException $e) {
            $this->assertSame(1205, (int) $e->errorInfo[1]);
        } finally {
            $otra->rollBack();
            DB::purge('medicion_concurrente');
        }
    }

    public function test_v0_down_no_borra_evidencia_existente(): void
    {
        $this->registrar($this->datos());
        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2026_10_14_180000_create_pdv_medicion_eventos_table.php'))->down();
    }

    public function test_v0_exige_transaccion_operativa(): void
    {
        DB::rollBack();
        $this->expectException(\LogicException::class);
        $this->registrar($this->datos());
    }

    public function test_v0_retencion_permite_archivar_actor_pero_impide_borrarlo(): void
    {
        $evento = $this->registrar($this->datos());
        DB::table('users')->where('id', 200)->update(['deleted_at' => now()]);
        $this->assertSame(200, $evento->fresh()->actor_id);
        try {
            DB::table('users')->where('id', 200)->delete();
            $this->fail('La referencia histórica debe conservarse.');
        } catch (QueryException $e) {
            $this->assertSame(1451, (int) $e->errorInfo[1]);
        }
    }

    public function test_v1_apertura_real_y_reintento_en_mysql(): void
    {
        config(['punto_venta.medicion.estado' => 'activa', 'punto_venta.medicion.sucursales' => [1]]);
        Event::fake([JornadaAbierta::class]);
        $servicio = app(AbrirJornadaPdvService::class);
        $fecha = Carbon::parse('2026-09-07 11:00:00', 'America/Mexico_City');
        $uno = $servicio->abrirParaUsuario(200, 1, $fecha, 100);
        $dos = $servicio->abrirParaUsuario(200, 1, $fecha, 100);
        $evento = MedicionEventoPdv::query()->sole();
        $this->assertSame($uno['jornada']->id, $dos['jornada']->id);
        $this->assertTrue($dos['reintento']);
        $this->assertSame($uno['intervalo']->id, $evento->intervalo_id);
        $this->assertSame(200, $evento->user_id);
        $this->assertSame(100, $evento->actor_id);
        $this->assertTrue($evento->efectivo_at->equalTo($fecha));
        $this->assertFalse($evento->contexto['cobertura']['metricas_v2']);
    }

    public function test_v1_rollback_real_tras_insertar_evento_en_mysql(): void
    {
        config(['punto_venta.medicion.estado' => 'activa', 'punto_venta.medicion.sucursales' => [1]]);
        Event::fake([JornadaAbierta::class]);
        $this->app->instance(RegistrarTransicionMedicionPdvService::class, new class extends RegistrarTransicionMedicionPdvService
        {
            public function ejecutar(array $datos): MedicionEventoPdv
            {
                parent::ejecutar($datos);
                throw new \RuntimeException('Fallo posterior al INSERT de medición');
            }
        });
        try {
            app(AbrirJornadaPdvService::class)
                ->abrirParaUsuario(200, 1, Carbon::parse('2026-09-07 11:00:00'), 100);
            $this->fail('Debió revertirse toda la apertura.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo posterior al INSERT de medición', $e->getMessage());
        }
        $this->assertSame(0, MedicionEventoPdv::query()->count());
        $this->assertSame(0, DB::table('pdv_jornadas')->where('user_id', 200)->count());
        $this->assertSame(0, DB::table('pdv_intervalos_operativos')->where('user_id', 200)->count());
        Event::assertNotDispatched(JornadaAbierta::class);
    }

    private function registrar(array $datos): MedicionEventoPdv
    {
        return app(RegistrarTransicionMedicionPdvService::class)->ejecutar($datos);
    }

    private function datos(): array
    {
        return [
            'tipo' => Catalogo::APERTURA, 'user_id' => 100, 'actor_id' => 200,
            'sucursal_id' => 1, 'jornada_id' => 1, 'intervalo_id' => 1,
            'efectivo_at' => '2026-09-07T10:00:00-06:00', 'origen' => 'gerencial',
            'sesion_uuid' => (string) Str::uuid(), 'correlacion_uuid' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(), 'contexto' => [],
            'zona_horaria' => 'America/Mexico_City', 'fecha_operativa' => '2026-09-07',
        ];
    }

    private function crearBaseMinima(): void
    {
        // Padres mínimos de las FK, sin tocar otras tablas ni usar una base existente.
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->softDeletes();
        });
        Schema::create('sucursales', function (Blueprint $t) {
            $t->id();
        });
        Schema::create('pdv_turnos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sucursal_id');
        });
        Schema::create('pdv_turno_atenciones', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('turno_id');
            $t->unsignedBigInteger('user_id');
        });
        Schema::create('pdv_motivos_pausa', function (Blueprint $t) {
            $t->id();
        });
        Schema::create('configuraciones_sistema', function (Blueprint $t) {
            $t->id();
            $t->string('clave')->nullable();
            $t->text('valor')->nullable();
        });
        (require database_path('migrations/2026_09_04_200000_create_pdv_operacion_tables.php'))->up();
        (require database_path('migrations/2026_09_29_140000_add_disponible_desde_to_pdv_jornadas.php'))->up();
        DB::table('users')->insert([['id' => 100], ['id' => 200]]);
        DB::table('sucursales')->insert([['id' => 1], ['id' => 2]]);
        DB::table('pdv_jornadas')->insert([
            'id' => 1, 'user_id' => 100, 'sucursal_id' => 1, 'estado' => 'ABIERTA',
            'apertura_at' => '2026-09-07 10:00:00', 'version' => 1,
        ]);
        DB::table('pdv_intervalos_operativos')->insert([
            'id' => 1, 'jornada_id' => 1, 'user_id' => 100, 'sucursal_id' => 1,
            'tipo' => 'disponible', 'inicio_at' => '2026-09-07 10:00:00', 'version' => 1,
        ]);
    }
}

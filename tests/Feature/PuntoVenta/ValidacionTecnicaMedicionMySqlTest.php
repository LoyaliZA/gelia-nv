<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\JornadaAbierta;
use App\Models\PuntoVenta\MedicionEventoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\InstrumentacionMedicionPdvConfig;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\Support\PuntoVenta\MySqlMedicionA331;
use Tests\TestCase;

class ValidacionTecnicaMedicionMySqlTest extends TestCase
{
    public function createApplication()
    {
        if (getenv('PDV_MEDICION_A331_MYSQL_TEST') !== '1') {
            return parent::createApplication();
        }

        return MySqlMedicionA331::application();
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PDV_MEDICION_A331_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Requiere MySQL 8.4 efímero A3.3.1.');
        }
        $this->assertSame('gelia_medicion_a331_test', DB::connection()->getDatabaseName());
    }

    public function test_secuencia_completa_de_migraciones_en_mysql_nuevo(): void
    {
        $this->assertSame('desactivada', config('punto_venta.medicion.estado'));
        $this->assertSame([], config('punto_venta.medicion.sucursales'));
        $this->assertFalse(app(InstrumentacionMedicionPdvConfig::class)->activaParaSucursal(1));
        $this->assertFalse(Schema::hasTable('migrations'), 'Usar una base nueva, no una existente.');
        $salida = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $salida, Artisan::output());
        $this->assertTrue(Schema::hasTable('pdv_medicion_eventos'));
        $this->assertSame(count(glob(database_path('migrations/*.php'))), DB::table('migrations')->count());
        $this->assertSame(3, count(DB::select("SELECT * FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pdv_medicion_eventos' AND CONSTRAINT_TYPE = 'CHECK'")));
        $this->assertCount(2, DB::select("SHOW TRIGGERS LIKE 'pdv_medicion_eventos'"));
        $columnas = collect(DB::select("SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, DATETIME_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pdv_medicion_eventos'"))->keyBy('COLUMN_NAME');
        foreach (['id', 'user_id', 'actor_id', 'sucursal_id', 'jornada_id', 'turno_id', 'atencion_id', 'intervalo_id', 'motivo_pausa_id', 'configuracion_id', 'causado_por_id', 'corrige_evento_id'] as $campo) {
            $this->assertSame('bigint unsigned', $columnas[$campo]->COLUMN_TYPE);
        }
        foreach (['estado_anterior', 'estado_nuevo', 'contexto'] as $campo) {
            $this->assertSame('json', $columnas[$campo]->DATA_TYPE);
        }
        foreach (['efectivo_at', 'registrado_at'] as $campo) {
            $this->assertSame(6, $columnas[$campo]->DATETIME_PRECISION);
        }
        $indices = collect(DB::select('SHOW INDEX FROM pdv_medicion_eventos'));
        foreach (['pdv_med_uuid_uq', 'pdv_med_idem_uq', 'pdv_med_secuencia_uq'] as $indice) {
            $this->assertSame(0, $indices->firstWhere('Key_name', $indice)->Non_unique);
        }
        $this->assertSame(11, (int) DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pdv_medicion_eventos' AND DELETE_RULE = 'RESTRICT'")->n);

    }

    public function test_privilegios_minimos_inmutabilidad_y_retencion(): void
    {
        $admin = DB::connection();
        $usuario = User::factory()->create();
        $actor = User::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $admin->statement("CREATE USER 'pdv_a331_app'@'%' IDENTIFIED BY ''");
        foreach (['users', 'sucursales', 'pdv_jornadas', 'pdv_intervalos_operativos', 'pdv_medicion_eventos', 'configuraciones_sistema'] as $tabla) {
            $admin->statement("GRANT SELECT ON gelia_medicion_a331_test.{$tabla} TO 'pdv_a331_app'@'%'");
        }
        foreach (['pdv_jornadas', 'pdv_intervalos_operativos'] as $tabla) {
            $admin->statement("GRANT INSERT, UPDATE ON gelia_medicion_a331_test.{$tabla} TO 'pdv_a331_app'@'%'");
        }
        $admin->statement("GRANT INSERT ON gelia_medicion_a331_test.pdv_medicion_eventos TO 'pdv_a331_app'@'%'");
        config(['database.connections.app_minima' => array_merge(config('database.connections.mysql'), ['username' => 'pdv_a331_app'])]);
        config(['punto_venta.medicion.estado' => 'activa', 'punto_venta.medicion.sucursales' => [$sucursal->id]]);
        Event::fake([JornadaAbierta::class]);
        $admin->statement("GRANT UPDATE (id) ON gelia_medicion_a331_test.users TO 'pdv_a331_app'@'%'");
        $concesionAmpliada = false;
        DB::setDefaultConnection('app_minima');
        Schema::clearResolvedInstance('db.schema');
        try {
            $resultado = app(AbrirJornadaPdvService::class)->abrirParaUsuario($usuario->id, $sucursal->id, Carbon::parse('2026-10-14 10:00:00'), $actor->id);
            $evento = MedicionEventoPdv::query()->where('user_id', $usuario->id)->sole();
            $this->assertSame($resultado['jornada']->id, $evento->jornada_id);
            foreach (["UPDATE pdv_medicion_eventos SET origen = 'personal' WHERE id = {$evento->id}",
                "DELETE FROM pdv_medicion_eventos WHERE id = {$evento->id}", 'TRUNCATE TABLE pdv_medicion_eventos'] as $sql) {
                try {
                    DB::statement($sql);
                    $this->fail('El usuario de aplicación no debe modificar o truncar evidencia.');
                } catch (QueryException $e) {
                    $this->assertSame(1142, (int) $e->errorInfo[1]);
                }
            }
            // Los triggers también protegen ante concesiones DML más amplias.
            $admin->statement("GRANT UPDATE, DELETE ON gelia_medicion_a331_test.pdv_medicion_eventos TO 'pdv_a331_app'@'%'");
            $concesionAmpliada = true;
            DB::purge('app_minima');
            foreach (["UPDATE pdv_medicion_eventos SET origen = 'personal' WHERE id = {$evento->id}",
                "DELETE FROM pdv_medicion_eventos WHERE id = {$evento->id}"] as $sql) {
                try {
                    DB::statement($sql);
                    $this->fail('Los triggers deben rechazar cambios aun con permiso DML.');
                } catch (QueryException $e) {
                    $this->assertSame('45000', $e->errorInfo[0]);
                }
            }
            $admin->table('users')->where('id', $actor->id)->update(['deleted_at' => now()]);
            $this->assertSame($actor->id, $evento->fresh()->actor_id);
            try {
                $admin->table('users')->where('id', $actor->id)->delete();
                $this->fail('RESTRICT debe conservar al actor referenciado.');
            } catch (QueryException $e) {
                $this->assertSame(1451, (int) $e->errorInfo[1]);
            }
            try {
                (require database_path('migrations/2026_10_14_180000_create_pdv_medicion_eventos_table.php'))->down();
                $this->fail('No se debe borrar evidencia al revertir funcionalmente.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('evidencia', $e->getMessage());
            }
            $this->assertSame(1, MedicionEventoPdv::query()->where('user_id', $usuario->id)->count());
        } finally {
            DB::setDefaultConnection('mysql');
            Schema::clearResolvedInstance('db.schema');
            DB::purge('app_minima');
            if ($concesionAmpliada) {
                $admin->statement("REVOKE UPDATE, DELETE ON gelia_medicion_a331_test.pdv_medicion_eventos FROM 'pdv_a331_app'@'%'");
            }
        }
    }

    public function test_fallo_ddl_parcial_y_recuperacion_solo_en_tabla_efimera_vacia(): void
    {
        $admin = DB::connection();
        $admin->statement('CREATE DATABASE gelia_medicion_a331_parcial');
        $padres = ['users', 'sucursales', 'pdv_jornadas', 'pdv_turnos', 'pdv_turno_atenciones',
            'pdv_intervalos_operativos', 'pdv_motivos_pausa', 'configuraciones_sistema'];
        foreach ($padres as $tabla) {
            $admin->statement("CREATE TABLE gelia_medicion_a331_parcial.{$tabla} LIKE gelia_medicion_a331_test.{$tabla}");
        }
        $admin->statement("CREATE USER 'pdv_a331_ddl'@'%' IDENTIFIED BY ''");
        $admin->statement("GRANT SELECT, CREATE, ALTER, INDEX, REFERENCES ON gelia_medicion_a331_parcial.* TO 'pdv_a331_ddl'@'%'");
        config(['database.connections.ddl_parcial' => array_merge(config('database.connections.mysql'),
            ['database' => 'gelia_medicion_a331_parcial', 'username' => 'pdv_a331_ddl'])]);
        config(['database.connections.admin_parcial' => array_merge(config('database.connections.mysql'),
            ['database' => 'gelia_medicion_a331_parcial'])]);
        $trustAnterior = (int) $admin->selectOne('SELECT @@GLOBAL.log_bin_trust_function_creators AS valor')->valor;
        $parcial = DB::connection('admin_parcial');
        $antes = [];
        foreach ($padres as $tabla) {
            $antes[$tabla] = (array) $parcial->selectOne("SHOW CREATE TABLE {$tabla}");
        }
        DB::setDefaultConnection('ddl_parcial');
        Schema::clearResolvedInstance('db.schema');
        $migracion = require database_path('migrations/2026_10_14_180000_create_pdv_medicion_eventos_table.php');
        try {
            try {
                $migracion->up();
                $this->fail('Sin TRIGGER debe fallar.');
            } catch (QueryException $e) {
                $this->assertContains((int) $e->errorInfo[1], [1142, 1419]);
                fwrite(STDOUT, "\nA331 DDL parcial codigo=".$e->errorInfo[1]."\n");
            }
            $this->assertTrue(Schema::hasTable('pdv_medicion_eventos'));
            $this->assertSame(0, $parcial->table('pdv_medicion_eventos')->count());
            $this->assertCount(0, $parcial->select("SHOW TRIGGERS LIKE 'pdv_medicion_eventos'"));
            try {
                $migracion->up();
                $this->fail('Repetir sin reconciliar no debe fingir éxito.');
            } catch (QueryException $e) {
                $this->assertSame(1050, (int) $e->errorInfo[1]);
            }
            // Recuperación ensayada solo donde se comprobó que no existe evidencia.
            DB::setDefaultConnection('admin_parcial');
            Schema::clearResolvedInstance('db.schema');
            $migracion->down();
            // Comprobar también TRIGGER por separado del requisito de binlog.
            $admin->statement('SET GLOBAL log_bin_trust_function_creators = 1');
            DB::setDefaultConnection('ddl_parcial');
            Schema::clearResolvedInstance('db.schema');
            try {
                $migracion->up();
                $this->fail('Sin TRIGGER debe fallar aun con trust_function_creators=1.');
            } catch (QueryException $e) {
                $this->assertSame(1142, (int) $e->errorInfo[1]);
            }
            $this->assertSame(0, $parcial->table('pdv_medicion_eventos')->count());
            DB::setDefaultConnection('admin_parcial');
            Schema::clearResolvedInstance('db.schema');
            $migracion->down();
            $admin->statement("GRANT TRIGGER ON gelia_medicion_a331_parcial.* TO 'pdv_a331_ddl'@'%'");
            DB::purge('ddl_parcial');
            DB::setDefaultConnection('ddl_parcial');
            Schema::clearResolvedInstance('db.schema');
            $migracion->up();
            $this->assertCount(2, $parcial->select("SHOW TRIGGERS LIKE 'pdv_medicion_eventos'"));
            foreach ($padres as $tabla) {
                $this->assertSame($antes[$tabla], (array) $parcial->selectOne("SHOW CREATE TABLE {$tabla}"));
            }
            $this->assertSame(11, (int) $parcial->selectOne("SELECT COUNT(*) AS n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pdv_medicion_eventos' AND DELETE_RULE = 'RESTRICT'")->n);
        } finally {
            DB::setDefaultConnection('mysql');
            Schema::clearResolvedInstance('db.schema');
            DB::purge('ddl_parcial');
            DB::purge('admin_parcial');
            $admin->statement('SET GLOBAL log_bin_trust_function_creators = '.$trustAnterior);
        }
    }
}

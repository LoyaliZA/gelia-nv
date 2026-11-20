<?php

namespace Tests\Feature\Activos;

use App\Models\ActivoMantenimiento;
use App\Models\Departamento;
use App\Models\User;
use App\Notifications\AlertaActivo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CompletarMantenimientoActivoTest extends TestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('sqlite', DB::getDefaultConnection());
        self::assertSame(':memory:', DB::connection()->getDatabaseName());

        // Esquema desechable en memoria, sin ejecutar migraciones.
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT);
            CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT, guard_name TEXT);
            CREATE TABLE areas (id INTEGER PRIMARY KEY, nombre TEXT);
            CREATE TABLE departamentos (
                id INTEGER PRIMARY KEY, nombre TEXT, codigo TEXT, es_demo INTEGER DEFAULT 0
            );
            CREATE TABLE departamento_user (user_id INTEGER, departamento_id INTEGER);
            CREATE TABLE catalogo_tipos_activo (id INTEGER PRIMARY KEY, nombre TEXT, categoria TEXT);
            CREATE TABLE activos (
                id INTEGER PRIMARY KEY, nombre TEXT, folio TEXT, departamento_id INTEGER,
                catalogo_tipo_activo_id INTEGER, area_id INTEGER, responsable_user_id INTEGER,
                estado TEXT, deleted_at TEXT, updated_at TEXT
            );
            CREATE TABLE activo_mantenimientos (
                id INTEGER PRIMARY KEY, activo_id INTEGER, usuario_id INTEGER, tipo TEXT, estado TEXT,
                fecha_programada TEXT, fecha_inicio TEXT, fecha_fin TEXT, proveedor TEXT,
                costo NUMERIC, descripcion TEXT, notas TEXT, proximo_mantenimiento TEXT,
                created_at TEXT, updated_at TEXT
            );
            CREATE TABLE activo_movimientos (
                id INTEGER PRIMARY KEY, activo_id INTEGER, usuario_id INTEGER, tipo TEXT,
                departamento_origen_id INTEGER, departamento_destino_id INTEGER, user_destino_id INTEGER,
                estado_anterior TEXT, estado_nuevo TEXT, motivo TEXT, notas TEXT, datos_snapshot TEXT,
                created_at TEXT, updated_at TEXT
            );
            INSERT INTO users VALUES (10,'Operador ficticio',NULL);
            INSERT INTO departamentos (id,nombre,codigo) VALUES
                (1,'TI ficticio','TI'), (2,'Ventas ficticio','VE');
            INSERT INTO departamento_user VALUES (10,1);
            INSERT INTO catalogo_tipos_activo VALUES (1,'Equipo ficticio','tecnologico');
            INSERT INTO activos VALUES
                (1,'Equipo A','FICTICIO-A',1,1,NULL,NULL,'mantenimiento',NULL,NULL),
                (2,'Equipo B','FICTICIO-B',1,1,NULL,NULL,'mantenimiento',NULL,NULL),
                (3,'Equipo C','FICTICIO-C',2,1,NULL,NULL,'mantenimiento',NULL,NULL);
            INSERT INTO activo_mantenimientos (id,activo_id,usuario_id,tipo,estado,notas) VALUES
                (1,1,10,'preventivo','programado','Notas A'),
                (2,2,10,'correctivo','en_proceso','Notas B'),
                (3,3,10,'preventivo','programado','Notas C');
            INSERT INTO activo_movimientos (id,activo_id,usuario_id,tipo,motivo) VALUES
                (1,1,10,'cambio_estado','Movimiento previo');
            SQL);

        Notification::fake();
        config(['activos.departamento_ti' => 'TI ficticio']);

        $this->actor = User::findOrFail(10);
        $this->actor->setRelation('roles', collect());
        $this->actor->setRelation('departamentos', Departamento::whereKey(1)->get());
        Gate::before(fn (User $user, string $ability) => match ($ability) {
            'activos.ver', 'activos.cambiar_estado' => true,
            'activos.ver_todos' => false,
            default => null,
        });
        $this->withoutMiddleware([
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\ActualizarActividadSesion::class,
        ]);
        $this->actingAs($this->actor)->from(route('activos.show', 1));
    }

    public function test_rechaza_mantenimiento_de_otro_activo_del_mismo_departamento(): void
    {
        $antes = $this->registros();

        $this->post(route('activos.mantenimiento.completar', [1, 2]), ['notas' => 'Intento incorrecto'])
            ->assertNotFound();

        self::assertSame($antes, $this->registros());
        Notification::assertNothingSent();
    }

    public function test_rechaza_mantenimiento_de_otro_departamento(): void
    {
        $antes = $this->registros();

        $this->post(route('activos.mantenimiento.completar', [1, 3]), ['notas' => 'Intento incorrecto'])
            ->assertNotFound();

        self::assertSame($antes, $this->registros());
        Notification::assertNothingSent();
    }

    public function test_sin_acceso_al_activo_conserva_el_rechazo_por_autorizacion(): void
    {
        $antes = $this->registros();

        $this->post(route('activos.mantenimiento.completar', [3, 3]))->assertForbidden();

        self::assertSame($antes, $this->registros());
        Notification::assertNothingSent();
    }

    public function test_completa_el_mantenimiento_correcto_y_conserva_los_demas_registros(): void
    {
        $otrosActivos = DB::table('activos')->whereIn('id', [2, 3])->orderBy('id')->get()->toJson();
        $otrosMantenimientos = DB::table('activo_mantenimientos')->whereIn('id', [2, 3])->orderBy('id')->get()->toJson();
        $movimientoPrevio = DB::table('activo_movimientos')->where('id', 1)->first();

        $this->post(route('activos.mantenimiento.completar', [1, 1]), ['notas' => 'Trabajo terminado'])
            ->assertRedirect(route('activos.show', 1))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Mantenimiento completado.');

        $this->assertDatabaseHas('activos', ['id' => 1, 'estado' => 'disponible']);
        $this->assertDatabaseHas('activo_mantenimientos', [
            'id' => 1, 'activo_id' => 1, 'estado' => 'completado',
            'notas' => 'Trabajo terminado',
        ]);
        self::assertSame(now()->toDateString(), ActivoMantenimiento::findOrFail(1)->fecha_fin->toDateString());
        $this->assertDatabaseCount('activo_movimientos', 2);
        $this->assertDatabaseHas('activo_movimientos', [
            'activo_id' => 1, 'usuario_id' => 10, 'tipo' => 'cambio_estado',
            'estado_anterior' => 'mantenimiento', 'estado_nuevo' => 'disponible',
            'motivo' => 'Mantenimiento completado', 'notas' => 'Trabajo terminado',
        ]);
        self::assertEquals($movimientoPrevio, DB::table('activo_movimientos')->where('id', 1)->first());
        self::assertSame($otrosActivos, DB::table('activos')->whereIn('id', [2, 3])->orderBy('id')->get()->toJson());
        self::assertSame($otrosMantenimientos, DB::table('activo_mantenimientos')->whereIn('id', [2, 3])->orderBy('id')->get()->toJson());
        Notification::assertSentTo($this->actor, AlertaActivo::class, fn ($aviso) =>
            $aviso->activo->id === 1 && $aviso->tipoAlerta === 'activo_mantenimiento'
        );
        Notification::assertCount(1);
    }

    private function registros(): array
    {
        return [
            DB::table('activos')->orderBy('id')->get()->toJson(),
            DB::table('activo_mantenimientos')->orderBy('id')->get()->toJson(),
            DB::table('activo_movimientos')->orderBy('id')->get()->toJson(),
        ];
    }
}

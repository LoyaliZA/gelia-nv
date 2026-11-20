<?php

namespace Tests\Feature\Activos;

use App\Models\Activo;
use App\Models\User;
use App\Notifications\AlertaActivo;
use App\Services\Activos\GuardarFotosRegistroActivoService;
use GdImage;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuardarRegistroActivoTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('sqlite', DB::getDefaultConnection());
        self::assertSame(':memory:', DB::connection()->getDatabaseName());

        // Esquema de prueba desechable, sin ejecutar migraciones.
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT);
            CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT, guard_name TEXT);
            CREATE TABLE areas (id INTEGER PRIMARY KEY, nombre TEXT);
            CREATE TABLE departamentos (id INTEGER PRIMARY KEY, nombre TEXT, codigo TEXT, es_demo INTEGER DEFAULT 0);
            CREATE TABLE departamento_user (user_id INTEGER, departamento_id INTEGER);
            CREATE TABLE catalogo_tipos_activo (
                id INTEGER PRIMARY KEY, nombre TEXT, categoria TEXT, slug TEXT, esquema_atributos TEXT
            );
            CREATE TABLE catalogo_marcas_activo (
                id INTEGER PRIMARY KEY, catalogo_tipo_activo_id INTEGER, nombre TEXT, slug TEXT,
                activo INTEGER, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE activos (
                id INTEGER PRIMARY KEY, nombre TEXT, folio TEXT UNIQUE, consulta_token TEXT UNIQUE,
                catalogo_tipo_activo_id INTEGER, catalogo_categoria_activo_id INTEGER,
                departamento_id INTEGER, area_id INTEGER, responsable_user_id INTEGER,
                activo_padre_id INTEGER, estado TEXT, descripcion TEXT, atributos TEXT,
                fecha_adquisicion TEXT, fecha_vencimiento TEXT, valor NUMERIC,
                registrado_por_id INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE activo_fotos (
                id INTEGER PRIMARY KEY, activo_id INTEGER, ruta TEXT, orden INTEGER,
                nombre_original TEXT, es_principal INTEGER, tamano_bytes INTEGER, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE activo_asignaciones (
                id INTEGER PRIMARY KEY, activo_id INTEGER, user_id INTEGER, asignado_por_id INTEGER,
                activa INTEGER, fecha_inicio TEXT, fecha_fin TEXT, notas TEXT, condiciones_entrega TEXT,
                firmado INTEGER, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE activo_movimientos (
                id INTEGER PRIMARY KEY, activo_id INTEGER, usuario_id INTEGER, tipo TEXT,
                departamento_origen_id INTEGER, departamento_destino_id INTEGER, user_destino_id INTEGER,
                estado_anterior TEXT, estado_nuevo TEXT, motivo TEXT, notas TEXT, datos_snapshot TEXT,
                created_at TEXT, updated_at TEXT
            );
            INSERT INTO departamentos (id,nombre,codigo) VALUES (1,'TI ficticio','TI');
            INSERT INTO users VALUES (10,'Operador',NULL), (11,'Responsable original',NULL), (12,'Responsable elegido',NULL);
            INSERT INTO departamento_user VALUES (11,1),(12,1);
            INSERT INTO catalogo_tipos_activo VALUES
                (1,'Equipo','tecnologico','equipo','{"fields":[{"key":"marca","label":"Marca","type":"text"}]}'),
                (2,'Accesorio','tecnologico','accesorio',NULL);
            SQL);

        $this->directorio = sys_get_temp_dir().'/gelia-alta-activos-'.Str::uuid();
        Storage::set('public', Storage::build(['driver' => 'local', 'root' => $this->directorio, 'throw' => true]));
        Notification::fake();

        $actor = User::findOrFail(10);
        $actor->setRelation('roles', collect());
        Gate::before(fn (User $user, string $ability) =>
            in_array($ability, ['activos.ver', 'activos.crear', 'activos.asignar'], true) ? true : null);
        $this->withoutMiddleware([
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\ActualizarActividadSesion::class,
        ]);
        $this->actingAs($actor)->from(route('activos.index'));
    }

    protected function tearDown(): void
    {
        Str::createUuidsNormally();
        if (isset($this->directorio)) {
            File::deleteDirectory($this->directorio);
        }
        parent::tearDown();
    }

    public function test_registra_activo_disponible_sin_fotos_ni_responsable(): void
    {
        $this->registrar()->assertRedirect(route('activos.show', 1))
            ->assertSessionHas('success', 'Activo registrado correctamente.');

        $this->assertDatabaseHas('activos', ['id' => 1, 'estado' => 'disponible', 'responsable_user_id' => null]);
        $this->assertDatabaseHas('activo_movimientos', ['activo_id' => 1, 'tipo' => 'creacion']);
        $this->assertDatabaseCount('activo_fotos', 0);
        $this->assertDatabaseCount('activo_asignaciones', 0);
        Notification::assertNothingSent();
    }

    public function test_registro_continuo_guarda_fotos_en_orden_sin_exigir_responsable(): void
    {
        $respuesta = $this->registrar([
            'registro_continuo' => true,
            'fotos' => [
                UploadedFile::fake()->image('frente.png', 1800, 900),
                UploadedFile::fake()->image('reverso.jpg'),
            ],
        ]);

        $activo = Activo::sole();
        $respuesta->assertRedirect(route('activos.index'))
            ->assertSessionHas('activo_registrado', ['id' => $activo->id, 'folio' => $activo->folio]);
        self::assertMatchesRegularExpression('/^TEC-TI-\d{4}-0001$/', $activo->folio);
        $fotos = $activo->fotos;
        self::assertSame(['frente.png', 'reverso.jpg'], $fotos->pluck('nombre_original')->all());
        self::assertSame([true, false], $fotos->pluck('es_principal')->all());
        self::assertSame([1, 2], $fotos->pluck('orden')->all());
        foreach ($fotos as $foto) {
            $ruta = Storage::disk('public')->path($foto->ruta);
            self::assertSame(IMAGETYPE_WEBP, getimagesize($ruta)[2]);
            self::assertSame(filesize($ruta), $foto->tamano_bytes);
        }
        self::assertSame([1600, 800], array_slice(getimagesize(Storage::disk('public')->path($fotos[0]->ruta)), 0, 2));
        $this->assertDatabaseCount('activo_asignaciones', 0);
    }

    public function test_registra_con_responsable_y_foto_y_avisa_despues_del_commit(): void
    {
        Event::listen(TransactionCommitting::class, fn () => Notification::assertNothingSent());

        $this->registrar(['user_id' => 12, 'fotos' => [UploadedFile::fake()->image('frente.png')]])
            ->assertRedirect(route('activos.show', 1))
            ->assertSessionHas('success', 'Activo registrado y asignado correctamente.');

        $this->assertDatabaseHas('activos', ['id' => 1, 'estado' => 'asignado', 'responsable_user_id' => 12]);
        $this->assertDatabaseHas('activo_asignaciones', ['activo_id' => 1, 'user_id' => 12, 'activa' => 1]);
        $this->assertDatabaseHas('activo_movimientos', ['activo_id' => 1, 'tipo' => 'asignacion']);
        Storage::disk('public')->assertExists(Activo::sole()->fotos->sole()->ruta);
        Notification::assertSentTo(User::findOrFail(12), AlertaActivo::class);
    }

    public function test_conserva_herencia_y_prioridad_del_responsable_explicito(): void
    {
        $this->registrar(['user_id' => 11])->assertRedirect(route('activos.show', 1));
        $accesorio = ['catalogo_tipo_activo_id' => 2, 'activo_padre_id' => 1];

        $this->registrar($accesorio)->assertRedirect(route('activos.show', 2));
        $this->registrar($accesorio + ['user_id' => 12])->assertRedirect(route('activos.show', 3));

        $this->assertDatabaseHas('activo_asignaciones', ['activo_id' => 2, 'user_id' => 11, 'activa' => 1]);
        $this->assertDatabaseHas('activo_asignaciones', ['activo_id' => 3, 'user_id' => 12, 'activa' => 1]);
    }

    public function test_falla_la_segunda_foto_y_reintentar_crea_un_solo_activo(): void
    {
        $token = Str::uuid();
        $rutaExistente = 'activos/1/'.Str::uuid().'.webp';
        $rutaPrimera = 'activos/1/'.($primeraFoto = Str::uuid()).'.webp';
        $fotoExistente = basename($rutaExistente, '.webp');
        Storage::disk('public')->put($rutaExistente, 'archivo ajeno al intento');
        Str::createUuidsUsingSequence([$token, $primeraFoto, $fotoExistente]);

        $this->registrar(['fotos' => [UploadedFile::fake()->image('una.png'), UploadedFile::fake()->image('dos.png')]])
            ->assertSessionHasErrors('fotos');

        $this->assertSinEscrituras();
        Storage::disk('public')->assertMissing($rutaPrimera);
        self::assertSame('archivo ajeno al intento', Storage::disk('public')->get($rutaExistente));
        self::assertSame([$rutaExistente], Storage::disk('public')->allFiles('activos'));

        Str::createUuidsNormally();
        $this->registrar(['fotos' => [UploadedFile::fake()->image('una.png')]])
            ->assertRedirect(route('activos.show', 1))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('activos', 1);
        $this->assertDatabaseCount('activo_fotos', 1);
        Storage::disk('public')->assertExists($rutaExistente);
    }

    public function test_limpia_el_archivo_cuando_falla_el_registro_de_la_foto(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER rechazar_foto BEFORE INSERT ON activo_fotos
            BEGIN SELECT RAISE(ABORT, 'Fallo simulado al guardar la fila de foto'); END;
            SQL);

        $this->registrar(['fotos' => [UploadedFile::fake()->image('frente.png')]])->assertServerError();

        $this->assertSinEscrituras();
        self::assertSame([], Storage::disk('public')->allFiles('activos'));
    }

    public function test_rechaza_y_limpia_una_escritura_incompleta_aunque_devuelva_exito(): void
    {
        $this->app->instance(GuardarFotosRegistroActivoService::class, new class extends GuardarFotosRegistroActivoService
        {
            protected function escribirWebp(GdImage $imagen, $destino): bool
            {
                fwrite($destino, 'RIFF');

                return true;
            }
        });

        $this->registrar(['fotos' => [UploadedFile::fake()->image('frente.png')]])
            ->assertSessionHasErrors(['fotos' => 'No se pudo verificar una de las fotografías guardadas.']);

        $this->assertSinEscrituras();
        self::assertSame([], Storage::disk('public')->allFiles('activos'));
    }

    public function test_falla_la_asignacion_y_revierte_incluso_catalogo_y_archivo(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER rechazar_asignacion BEFORE INSERT ON activo_asignaciones
            BEGIN SELECT RAISE(ABORT, 'Fallo simulado de asignación'); END;
            SQL);

        $this->registrar([
            'user_id' => 12, 'atributos' => ['marca' => 'Marca ficticia'],
            'fotos' => [UploadedFile::fake()->image('frente.png')],
        ])->assertServerError();

        $this->assertSinEscrituras();
        self::assertSame([], Storage::disk('public')->allFiles('activos'));
    }

    public function test_revierte_antes_del_commit_y_descarta_el_aviso_diferido(): void
    {
        Event::listen(TransactionCommitting::class, function (): void {
            Notification::assertNothingSent();
            throw ValidationException::withMessages(['user_id' => 'Fallo simulado antes de confirmar.']);
        });

        $this->registrar(['user_id' => 12, 'fotos' => [UploadedFile::fake()->image('frente.png')]])
            ->assertSessionHasErrors('user_id');

        $this->assertSinEscrituras();
        self::assertSame([], Storage::disk('public')->allFiles('activos'));
    }

    private function registrar(array $datos = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('activos.store'), $datos + [
            'catalogo_tipo_activo_id' => 1,
            'departamento_id' => 1,
            'nombre' => 'Equipo ficticio',
        ]);
    }

    private function assertSinEscrituras(): void
    {
        foreach (['activos', 'activo_fotos', 'activo_asignaciones', 'activo_movimientos', 'catalogo_marcas_activo'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
        Notification::assertNothingSent();
    }
}

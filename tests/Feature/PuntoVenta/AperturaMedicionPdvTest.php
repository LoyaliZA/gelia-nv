<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\JornadaAbierta;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\MedicionEventoPdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\GestionarEquipoOperativoPdvService;
use App\Services\PuntoVenta\Operacion\RegistrarTransicionMedicionPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AperturaMedicionPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $empleado;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-07 10:00:00', 'America/Mexico_City'));
        Role::findOrCreate('Super Admin', 'web');
        ConfiguracionSistema::query()->updateOrCreate(['clave' => PuntoVentaModulo::CLAVE_FLAG], ['valor' => '1']);
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        Event::fake([JornadaAbierta::class]);
        $this->sucursal = Sucursal::factory()->create();
        SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id, 'fecha_operativa' => now()->toDateString(), 'acepta_altas' => true,
        ]);
        $this->empleado = $this->persona();
        $this->gerente = $this->persona();
        config(['punto_venta.medicion.estado' => 'activa', 'punto_venta.medicion.sucursales' => [$this->sucursal->id]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_apertura_personal_conserva_referencias_fechas_sesion_y_cobertura_parcial(): void
    {
        $resultado = app(AbrirJornadaPdvService::class)->ejecutar($this->empleado, now());
        $evento = MedicionEventoPdv::query()->sole();
        $this->assertFalse($resultado['reintento']);
        $this->assertSame($this->empleado->id, $evento->user_id);
        $this->assertSame($this->empleado->id, $evento->actor_id);
        $this->assertSame('personal', $evento->origen);
        $this->assertSame($resultado['jornada']->id, $evento->jornada_id);
        $this->assertSame($resultado['intervalo']->id, $evento->intervalo_id);
        $this->assertSame($this->sucursal->id, $evento->sucursal_id);
        $this->assertTrue($evento->efectivo_at->equalTo($resultado['jornada']->apertura_at));
        $this->assertTrue($evento->efectivo_at->equalTo($resultado['intervalo']->inicio_at));
        $this->assertTrue(Str::isUuid($evento->sesion_uuid));
        $this->assertTrue(Str::isUuid($evento->correlacion_uuid));
        $this->assertSame(1, $evento->secuencia);
        $this->assertSame('2026-09-07', $evento->fecha_operativa);
        $this->assertNotNull($evento->registrado_at);
        $this->assertSame(['transiciones' => ['jornada.abierta'], 'completa' => false, 'metricas_v2' => false], $evento->contexto['cobertura']);
        Event::assertDispatchedTimes(JornadaAbierta::class, 1);
    }

    public function test_apertura_gerencial_distingue_actor_del_empleado(): void
    {
        $this->empleado->givePermissionTo(PuntoVentaModulo::PERMISO_TURNOS_ATENDER);
        $this->gerente->givePermissionTo(PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR);
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->empleado, now(), 'a33:activar');
        $evento = MedicionEventoPdv::query()->sole();
        $this->assertSame($this->empleado->id, $evento->user_id);
        $this->assertSame($this->gerente->id, $evento->actor_id);
        $this->assertSame('gerencial', $evento->origen);
    }

    public function test_reintento_devuelve_jornada_sin_evento_ni_dispatch_duplicado(): void
    {
        $servicio = app(AbrirJornadaPdvService::class);
        $uno = $servicio->ejecutar($this->empleado, now());
        $dos = $servicio->ejecutar($this->empleado, now()->addMinute());
        $this->assertSame($uno['jornada']->id, $dos['jornada']->id);
        $this->assertTrue($dos['reintento']);
        $this->assertDatabaseCount('pdv_medicion_eventos', 1);
        Event::assertDispatchedTimes(JornadaAbierta::class, 1);
    }

    public function test_activar_no_inventa_apertura_para_jornada_preexistente(): void
    {
        config(['punto_venta.medicion.estado' => 'desactivada']);
        $servicio = app(AbrirJornadaPdvService::class);
        $uno = $servicio->ejecutar($this->empleado, now());
        config(['punto_venta.medicion.estado' => 'activa']);
        $dos = $servicio->ejecutar($this->empleado, now());
        $this->assertSame($uno['jornada']->id, $dos['jornada']->id);
        $this->assertTrue($dos['reintento']);
        $this->assertDatabaseCount('pdv_medicion_eventos', 0);
    }

    public static function sinCobertura(): array
    {
        return ['desactivada' => ['desactivada', true], 'suspendida' => ['suspendida', true], 'fuera_cohorte' => ['activa', false]];
    }

    #[DataProvider('sinCobertura')]
    public function test_sin_instrumentacion_conserva_apertura_y_no_invoca_registrador(string $estado, bool $incluida): void
    {
        config(['punto_venta.medicion.estado' => $estado, 'punto_venta.medicion.sucursales' => $incluida ? [$this->sucursal->id] : []]);
        $this->mock(RegistrarTransicionMedicionPdvService::class)->shouldNotReceive('ejecutar');
        $resultado = app(AbrirJornadaPdvService::class)->ejecutar($this->empleado, now());
        $this->assertSame(EstadoJornadaPdv::Abierta, $resultado['jornada']->estado);
        $this->assertNotNull($resultado['intervalo']->id);
        $this->assertDatabaseCount('pdv_medicion_eventos', 0);
        Event::assertDispatchedTimes(JornadaAbierta::class, 1);
    }

    public function test_fallo_real_de_escritura_revierte_jornada_intervalo_y_evento(): void
    {
        DB::statement("CREATE TRIGGER prueba_fallo_medicion BEFORE INSERT ON pdv_medicion_eventos BEGIN SELECT RAISE(ABORT, 'fallo de escritura de prueba'); END");
        try {
            app(AbrirJornadaPdvService::class)->ejecutar($this->empleado, now());
            $this->fail('Debió fallar la escritura.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('fallo de escritura de prueba', $e->getMessage());
        }
        $this->assertDatabaseCount('pdv_jornadas', 0);
        $this->assertDatabaseCount('pdv_intervalos_operativos', 0);
        $this->assertDatabaseCount('pdv_medicion_eventos', 0);
        Event::assertNotDispatched(JornadaAbierta::class);
    }

    public function test_fallo_operativo_no_registra_ni_notifica(): void
    {
        $this->empleado->revokePermissionTo(PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR);
        try {
            app(AbrirJornadaPdvService::class)->ejecutar($this->empleado, now());
            $this->fail('Debió rechazar permisos.');
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertDatabaseCount('pdv_medicion_eventos', 0);
        $this->assertDatabaseCount('pdv_jornadas', 0);
        Event::assertNotDispatched(JornadaAbierta::class);
    }

    public function test_secuencia_por_persona_y_aislamiento_de_sucursales(): void
    {
        $otra = Sucursal::factory()->create();
        config(['punto_venta.medicion.sucursales' => [$this->sucursal->id, $otra->id]]);
        $servicio = app(AbrirJornadaPdvService::class);
        $servicio->abrirParaUsuario($this->empleado->id, $this->sucursal->id, now(), $this->gerente->id);
        $servicio->abrirParaUsuario($this->empleado->id, $otra->id, now(), $this->gerente->id);
        $servicio->abrirParaUsuario($this->gerente->id, $otra->id, now(), $this->gerente->id);
        $this->assertSame([1, 2], MedicionEventoPdv::query()->where('user_id', $this->empleado->id)->orderBy('secuencia')->pluck('secuencia')->all());
        $this->assertSame(1, MedicionEventoPdv::query()->where('user_id', $this->gerente->id)->sole()->secuencia);
        $this->assertDatabaseCount('pdv_medicion_eventos', 3);
    }

    public function test_registrado_at_usa_reloj_db_independiente_del_efectivo(): void
    {
        Carbon::setTestNow('2000-01-01 10:00:00');
        app(AbrirJornadaPdvService::class)->abrirParaUsuario($this->empleado->id, $this->sucursal->id, now(), $this->empleado->id);
        $evento = MedicionEventoPdv::query()->sole();
        $this->assertSame(2000, $evento->efectivo_at->year);
        $this->assertNotSame(2000, $evento->registrado_at->year);
    }

    private function persona(): User
    {
        $persona = User::factory()->create();
        $persona->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($persona, $this->sucursal->id);
        $persona->givePermissionTo([PuntoVentaModulo::PERMISO_ACCEDER, PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR]);

        return $persona;
    }
}

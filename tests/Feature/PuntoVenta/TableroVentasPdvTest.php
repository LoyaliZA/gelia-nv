<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TableroVentasPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        $this->activarModulo();
        $this->seedPermisos();
        $this->seedPlazos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Tablero']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Remota']);
        $this->vendedor = User::factory()->create(['name' => 'Vendedor Tablero']);

        $this->vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
        ]);
        $this->vendedor->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($this->vendedor, $this->sucursal->id);
    }

    public function test_datos_incluye_turno_asignado_del_vendedor(): void
    {
        $contexto = $this->crearTurnoAsignado($this->vendedor);

        $this->actingAs($this->vendedor)
            ->getJson(route('punto_venta.turnos.ventas.datos'))
            ->assertOk()
            ->assertJsonPath('turno_asignado.id', $contexto['turno']->id)
            ->assertJsonPath('turno_asignado.folio', $contexto['turno']->folio)
            ->assertJsonPath('turno_asignado.atencion.user_id', $this->vendedor->id)
            ->assertJsonPath('plazos.espera_inicial_minutos', 5);
    }

    public function test_aislamiento_por_sucursal_activa(): void
    {
        $otro = User::factory()->create(['name' => 'Otro Vendedor']);
        $otro->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
        ]);
        $otro->concederAccesoSucursal($this->otraSucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($otro, $this->otraSucursal->id);

        $this->crearTurnoAsignado($this->vendedor);

        $this->actingAs($otro)
            ->getJson(route('punto_venta.turnos.ventas.datos'))
            ->assertOk()
            ->assertJsonPath('turno_asignado', null);
    }

    public function test_refresco_repetido_no_muta_turno(): void
    {
        $contexto = $this->crearTurnoAsignado($this->vendedor);

        $this->actingAs($this->vendedor)->getJson(route('punto_venta.turnos.ventas.datos'))->assertOk();
        $this->actingAs($this->vendedor)->getJson(route('punto_venta.turnos.ventas.datos'))->assertOk();

        $contexto['turno']->refresh();
        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $contexto['turno']->estado);
        $this->assertSame(1, (int) $contexto['turno']->version);
    }

    public function test_etiquetas_prioridad_en_payload(): void
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'prioridad_vip' => true,
            'prioridad_adulto_mayor' => true,
        ]);

        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->vendedor->id,
            'inicio_at' => now()->subMinutes(2),
            'fin_at' => null,
        ]);

        $turno->update(['atencion_actual_id' => $turno->atenciones()->first()->id]);

        $this->actingAs($this->vendedor)
            ->getJson(route('punto_venta.turnos.ventas.datos'))
            ->assertOk()
            ->assertJsonPath('turno_asignado.prioridad_vip', true)
            ->assertJsonPath('turno_asignado.prioridad_adulto_mayor', true);
    }

    public function test_espera_inicial_vencida_calculada_en_servidor(): void
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
        ]);

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->vendedor->id,
            'inicio_at' => now()->subMinutes(6),
            'atencion_inicio_at' => null,
            'fin_at' => null,
        ]);

        $turno->update(['atencion_actual_id' => $atencion->id]);

        $this->actingAs($this->vendedor)
            ->getJson(route('punto_venta.turnos.ventas.datos'))
            ->assertOk()
            ->assertJsonPath('turno_asignado.atencion.espera_inicial_vencida', true);
    }

    public function test_sin_permiso_ver_devuelve_403(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(PuntoVentaModulo::PERMISO_ACCEDER);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        $this->actingAs($usuario)
            ->getJson(route('punto_venta.turnos.ventas.datos'))
            ->assertForbidden();
    }

    public function test_cola_contextual_muestra_reatencion_atendida_por_vendedor(): void
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
            'reatencion_expira_at' => now()->addHour(),
        ]);

        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->vendedor->id,
            'inicio_at' => now()->subHour(),
            'fin_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($this->vendedor)
            ->getJson(route('punto_venta.turnos.ventas.datos'))
            ->assertOk()
            ->assertJsonCount(1, 'cola_contextual')
            ->assertJsonPath('cola_contextual.0.id', $turno->id);
    }

    /**
     * @return array{turno: TurnoPdv, atencion: TurnoPdvAtencion}
     */
    private function crearTurnoAsignado(User $vendedor): array
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
        ]);

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $vendedor->id,
            'inicio_at' => now()->subMinutes(2),
            'fin_at' => null,
        ]);

        $turno->update(['atencion_actual_id' => $atencion->id]);

        return [
            'turno' => $turno->fresh(),
            'atencion' => $atencion->fresh(),
        ];
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    private function seedPlazos(): void
    {
        $config = new PlazosTurnosPdvConfig;
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PlazosTurnosPdvConfig::CLAVE],
            [
                'valor' => json_encode($config->configuracionInicialAprobada(), JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
            ]
        );
    }
}

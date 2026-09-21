<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\GestionarEquipoOperativoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GestionVendedoresPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $gerente;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        $this->activarModulo();
        $this->seedPermisos();
        Cache::forget(PlazosTurnosPdvConfig::CACHE_KEY);

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Gestión Vendedores']);
        $this->vendedor = $this->crearVendedor('Vendedor Panel');
        $this->gerente = $this->crearGerente('Gerente Panel');
    }

    public function test_vista_sin_permisos_de_consulta_queda_prohibida(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
        ]);
        $sinPermiso->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($sinPermiso, $this->sucursal->id);

        $this->actingAs($sinPermiso)
            ->get(route('punto_venta.operacion.index'))
            ->assertForbidden();
    }

    public function test_vendedores_redirige_a_operacion_general(): void
    {
        $this->actingAs($this->gerente)
            ->get(route('punto_venta.operacion.vendedores.index'))
            ->assertRedirect(route('punto_venta.operacion.index'));
    }

    public function test_vista_renderiza_con_equipo_ver(): void
    {
        $response = $this->actingAs($this->gerente)->get(route('punto_venta.operacion.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PuntoVenta/Operacion/OperacionGeneral', false)
                ->where('permisos.equipo_ver', true)
                ->where('permisos.equipo_gestionar', true)
                ->has('estado.resumen')
                ->has('estado.equipo')
                ->has('estado.clientes_en_fila')
                ->has('estado.plazos_turnos')
            );
    }

    public function test_consulta_solo_equipo_ver_no_expone_acciones(): void
    {
        $consulta = User::factory()->create(['name' => 'Solo consulta']);
        $consulta->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
        ]);
        $consulta->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($consulta, $this->sucursal->id);

        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());

        $response = $this->actingAs($consulta)->getJson(route('punto_venta.operacion.datos'));

        $response->assertOk();
        $miembro = collect($response->json('equipo'))->firstWhere('id', $this->vendedor->id);
        $this->assertIsArray($miembro);
        $this->assertSame([], $miembro['acciones']);
    }

    public function test_resumen_coincide_con_estados_del_equipo(): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());

        TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'servicio' => TurnoPdv::SERVICIO_VENTAS,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'atencion_actual_id' => null,
        ]);

        $response = $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.datos'));

        $response->assertOk()
            ->assertJsonPath('resumen.configurados', 1)
            ->assertJsonPath('resumen.activos_hoy', 1)
            ->assertJsonPath('resumen.disponibles', 1)
            ->assertJsonPath('clientes_en_fila', 1);

        $miembro = collect($response->json('equipo'))->firstWhere('id', $this->vendedor->id);
        $this->assertNotEmpty($miembro['acciones']);
    }

    public function test_atencion_actual_incluye_cliente_y_plazos(): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'snapshot_cliente_nombre' => 'Cliente Compacto',
            'snapshot_nombre_llamado' => 'Cliente Compacto',
        ]);

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->vendedor->id,
            'inicio_at' => now()->subMinutes(2),
            'atencion_inicio_at' => null,
            'fin_at' => null,
        ]);
        $turno->update(['atencion_actual_id' => $atencion->id]);

        $response = $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.datos'));
        $miembro = collect($response->json('equipo'))->firstWhere('id', $this->vendedor->id);

        $this->assertSame('atendiendo', $miembro['estado_vendedor']);
        $this->assertSame('Cliente Compacto', $miembro['atencion_actual']['cliente']);
        $this->assertSame($turno->folio, $miembro['atencion_actual']['folio']);
        $this->assertFalse($miembro['atencion_actual']['atencion_en_curso']);
        $this->assertNotEmpty($miembro['atencion_actual']['espera_inicial_expira_at']);
        $this->assertArrayHasKey('plazos_turnos', $response->json());
        $this->assertSame(5, $response->json('plazos_turnos.espera_inicial_minutos'));
    }

    public function test_gerencia_consulta_plazos_turnos(): void
    {
        $this->actingAs($this->gerente)
            ->getJson(route('punto_venta.operacion.configuracion.plazos_turnos.consultar'))
            ->assertOk()
            ->assertJsonPath('plazos_turnos.espera_inicial_minutos', 5)
            ->assertJsonPath('plazos_turnos.prorroga_minutos', 20);
    }

    public function test_consulta_equipo_ver_no_puede_consultar_plazos(): void
    {
        $consulta = User::factory()->create(['name' => 'Solo consulta plazos GET']);
        $consulta->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
        ]);
        $consulta->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($consulta, $this->sucursal->id);

        $this->actingAs($consulta)
            ->getJson(route('punto_venta.operacion.configuracion.plazos_turnos.consultar'))
            ->assertForbidden();
    }

    public function test_gerencia_actualiza_plazos_turnos(): void
    {
        $payload = [
            'espera_inicial_minutos' => 7,
            'prorroga_minutos' => 25,
            'ventana_reatencion_minutos' => 60,
            'aviso_tolerancia_espera_minutos' => 3,
            'aviso_tolerancia_prorroga_minutos' => 4,
            'inicio_atencion_automatico' => true,
        ];

        $this->actingAs($this->gerente)
            ->putJson(route('punto_venta.operacion.configuracion.plazos_turnos'), $payload)
            ->assertOk()
            ->assertJsonPath('plazos_turnos.espera_inicial_minutos', 7)
            ->assertJsonPath('plazos_turnos.aviso_tolerancia_espera_minutos', 3)
            ->assertJsonPath('plazos_turnos.inicio_atencion_automatico', true);

        $this->assertSame(
            7,
            app(PlazosTurnosPdvConfig::class)->obtener()['espera_inicial_minutos'],
        );
    }

    public function test_consulta_equipo_ver_no_puede_actualizar_plazos(): void
    {
        $consulta = User::factory()->create(['name' => 'Solo consulta plazos']);
        $consulta->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
        ]);
        $consulta->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($consulta, $this->sucursal->id);

        $this->actingAs($consulta)
            ->putJson(route('punto_venta.operacion.configuracion.plazos_turnos'), [
                'espera_inicial_minutos' => 7,
                'prorroga_minutos' => 25,
                'ventana_reatencion_minutos' => 60,
                'aviso_tolerancia_espera_minutos' => 3,
                'aviso_tolerancia_prorroga_minutos' => 4,
                'inicio_atencion_automatico' => false,
            ])
            ->assertForbidden();
    }

    public function test_accion_gerencial_refresca_estado_tras_conflicto_de_version(): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.desactivar', $this->vendedor), [
                'version' => 999,
            ])
            ->assertStatus(422);

        $this->actingAs($this->gerente)
            ->getJson(route('punto_venta.operacion.datos'))
            ->assertOk()
            ->assertJsonPath('equipo.0.estado_vendedor', 'disponible');
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

    private function crearVendedor(string $nombre): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }

    private function crearGerente(string $nombre): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }
}

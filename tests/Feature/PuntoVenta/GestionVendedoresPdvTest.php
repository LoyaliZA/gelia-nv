<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\GestionarEquipoOperativoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Gestión Vendedores']);
        $this->vendedor = $this->crearVendedor('Vendedor Panel');
        $this->gerente = $this->crearGerente('Gerente Panel');
    }

    public function test_vista_requiere_permiso_equipo_ver(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ]);
        $sinPermiso->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($sinPermiso, $this->sucursal->id);

        $this->actingAs($sinPermiso)
            ->get(route('punto_venta.operacion.vendedores.index'))
            ->assertForbidden();
    }

    public function test_vista_renderiza_con_equipo_ver(): void
    {
        $response = $this->actingAs($this->gerente)->get(route('punto_venta.operacion.vendedores.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PuntoVenta/Operacion/GestionVendedores', false)
                ->where('permisos.equipo_ver', true)
                ->where('permisos.equipo_gestionar', true)
                ->has('estado.resumen')
                ->has('estado.equipo')
                ->has('estado.clientes_en_fila')
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

        $response = $this->actingAs($consulta)->getJson(route('punto_venta.operacion.vendedores.datos'));

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

        $response = $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.vendedores.datos'));

        $response->assertOk()
            ->assertJsonPath('resumen.configurados', 1)
            ->assertJsonPath('resumen.activos_hoy', 1)
            ->assertJsonPath('resumen.disponibles', 1)
            ->assertJsonPath('clientes_en_fila', 1);

        $miembro = collect($response->json('equipo'))->firstWhere('id', $this->vendedor->id);
        $this->assertNotEmpty($miembro['acciones']);
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
            ->getJson(route('punto_venta.operacion.vendedores.datos'))
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

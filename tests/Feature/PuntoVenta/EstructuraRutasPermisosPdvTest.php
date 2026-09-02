<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EstructuraRutasPermisosPdvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
    }

    public function test_rutas_registradas_con_prefijo_y_nombres_consistentes(): void
    {
        $this->assertTrue(Route::has('punto_venta.resguardos.index'));
        $this->assertTrue(Route::has('punto_venta.resguardos.listado'));
        $this->assertTrue(Route::has('punto_venta.resguardos.exportaciones.store'));
        $this->assertTrue(Route::has('punto_venta.resguardos.exportaciones.descargar'));
        $this->assertTrue(Route::has('punto_venta.resguardos.auditoria'));
        $this->assertTrue(Route::has('punto_venta.resguardos.show'));
        $this->assertTrue(Route::has('punto_venta.resguardos.etiquetas.descargar'));
        $this->assertTrue(Route::has('punto_venta.resguardos.etiquetas.resolver'));
        $this->assertTrue(Route::has('punto_venta.reportes.index'));
        $this->assertTrue(Route::has('control_pedidos.index'));

        $this->assertSame('/punto-venta/resguardos', route('punto_venta.resguardos.index', absolute: false));
        $this->assertSame('/punto-venta/reportes', route('punto_venta.reportes.index', absolute: false));
    }

    public function test_catalogo_incluye_acceder_y_permisos_0b(): void
    {
        $nombres = Permission::query()->whereIn('name', PuntoVentaModulo::permisosIniciales())->pluck('name');

        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            $this->assertTrue($nombres->contains($permiso), $permiso);
        }
    }

    public function test_invitado_es_redirigido_a_login(): void
    {
        $this->activarModulo();

        $this->get(route('punto_venta.resguardos.index'))->assertRedirect();
        $this->get(route('punto_venta.reportes.index'))->assertRedirect();
    }

    public function test_flag_desactivado_oculta_el_modulo_con_404(): void
    {
        $usuario = $this->usuarioPiso();

        $this->actingAs($usuario)->get(route('punto_venta.resguardos.index'))->assertNotFound();
        $this->actingAs($usuario)->get(route('punto_venta.reportes.index'))->assertNotFound();
    }

    public function test_sin_permiso_acceder_no_entra(): void
    {
        $this->activarModulo();

        $usuario = User::factory()->create();
        $usuario->givePermissionTo(PuntoVentaModulo::PERMISO_RESGUARDOS_VER);
        $usuario->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: true);

        $this->actingAs($usuario)->get(route('punto_venta.resguardos.index'))->assertForbidden();
    }

    public function test_piso_exige_ver_y_sucursal_activa(): void
    {
        $this->activarModulo();

        $sinSucursal = User::factory()->create();
        $sinSucursal->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $this->actingAs($sinSucursal)->get(route('punto_venta.resguardos.index'))->assertForbidden();

        $sinVer = User::factory()->create();
        $sinVer->givePermissionTo(PuntoVentaModulo::PERMISO_ACCEDER);
        $sinVer->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: true);
        $this->actingAs($sinVer)->get(route('punto_venta.resguardos.index'))->assertForbidden();

        $this->withoutVite();
        $this->actingAs($this->usuarioPiso())
            ->get(route('punto_venta.resguardos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PuntoVenta/Resguardos/Index', false));

        $this->actingAs($this->usuarioPiso())
            ->getJson(route('punto_venta.resguardos.index'))
            ->assertOk()
            ->assertJsonStructure(['bandeja', 'resguardos', 'metricas', 'filtros']);
    }

    public function test_reportes_global_sin_sucursal_y_sin_permiso_global_se_niega(): void
    {
        $this->activarModulo();

        $soloPiso = $this->usuarioPiso();
        $this->actingAs($soloPiso)->get(route('punto_venta.reportes.index'))->assertForbidden();

        $global = User::factory()->create();
        $global->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
        ]);
        $this->actingAs($global)->get(route('punto_venta.reportes.index'))->assertNoContent();
    }

    public function test_super_admin_sin_permiso_directo_no_accede(): void
    {
        $this->activarModulo();

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $admin->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: true);

        $this->actingAs($admin)->get(route('punto_venta.resguardos.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('punto_venta.reportes.index'))->assertForbidden();
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->where('clave', PuntoVentaModulo::CLAVE_FLAG)->update(['valor' => '1']);
    }

    private function usuarioPiso(): User
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $usuario->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: true);

        return $usuario;
    }
}

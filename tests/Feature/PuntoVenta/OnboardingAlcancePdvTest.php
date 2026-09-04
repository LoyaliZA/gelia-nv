<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnboardingAlcancePdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursalA = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->sucursalB = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
    }

    public function test_sin_sucursal_asignada_muestra_onboarding(): void
    {
        $this->withoutVite();

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.index'))
            ->assertRedirect(route('punto_venta.alcance.configurar'));

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.alcance.configurar'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/ConfigurarSucursal', false)
                ->where('sin_asignacion', true));
    }

    public function test_varias_sucursales_sin_principal_redirige_a_onboarding(): void
    {
        $this->usuario->concederAccesoSucursal($this->sucursalA);
        $this->usuario->concederAccesoSucursal($this->sucursalB);

        $this->withoutVite();

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.index'))
            ->assertRedirect(route('punto_venta.alcance.configurar'));

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.alcance.configurar'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('requiere_seleccion', true)
                ->has('sucursales_asignadas', 2));
    }

    public function test_con_principal_configurada_entra_directo_a_resguardos(): void
    {
        $this->usuario->concederAccesoSucursal($this->sucursalA, esPrincipal: true);
        $this->usuario->concederAccesoSucursal($this->sucursalB);

        $this->withoutVite();

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PuntoVenta/Resguardos/Index', false));

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.alcance.configurar'))
            ->assertRedirect(route('punto_venta.resguardos.index'));
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
        Permission::findOrCreate(AlcancePdv::PERMISO_ALCANCE_GLOBAL, 'web');
    }
}

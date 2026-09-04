<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SelectorSucursalActivaPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

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
        $this->usuario->concederAccesoSucursal($this->sucursalA, esPrincipal: true);
        $this->usuario->concederAccesoSucursal($this->sucursalB);
    }

    public function test_establece_sucursal_asignada_y_activa(): void
    {
        $this->actingAs($this->usuario)
            ->putJson(route('punto_venta.sucursal_activa.establecer'), [
                'sucursal_id' => $this->sucursalB->id,
            ])
            ->assertOk()
            ->assertJsonPath('sucursal_activa.id', $this->sucursalB->id)
            ->assertJsonPath('sucursal_activa.nombre', 'Sucursal Sur');

        $this->assertSame(
            $this->sucursalB->id,
            app(AlcancePdv::class)->sucursalActivaId($this->usuario)
        );
    }

    public function test_rechaza_sucursal_no_asignada(): void
    {
        $ajena = Sucursal::factory()->create();

        $this->actingAs($this->usuario)
            ->putJson(route('punto_venta.sucursal_activa.establecer'), [
                'sucursal_id' => $ajena->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sucursal_id']);
    }

    public function test_rechaza_sucursal_inactiva_asignada(): void
    {
        $inactiva = Sucursal::factory()->inactiva()->create();
        $this->usuario->concederAccesoSucursal($inactiva);

        $this->actingAs($this->usuario)
            ->putJson(route('punto_venta.sucursal_activa.establecer'), [
                'sucursal_id' => $inactiva->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sucursal_id']);
    }

    public function test_solo_global_sin_asignacion_no_puede_establecer_ni_entrar_a_piso(): void
    {
        $soloGlobal = User::factory()->create();
        $soloGlobal->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);

        $this->actingAs($soloGlobal)
            ->putJson(route('punto_venta.sucursal_activa.establecer'), [
                'sucursal_id' => $this->sucursalA->id,
            ])
            ->assertForbidden();

        $this->actingAs($soloGlobal)
            ->get(route('punto_venta.resguardos.index'))
            ->assertForbidden();
    }

    public function test_cambio_de_activa_filtra_listado(): void
    {
        $norte = $this->crearResguardo($this->sucursalA, [
            'snapshot_folio' => 'REM-NORTE-SEL',
        ]);
        $sur = $this->crearResguardo($this->sucursalB, [
            'snapshot_folio' => 'REM-SUR-SEL',
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.listado', [
                'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
            ]))
            ->assertOk()
            ->assertJsonPath('resguardos.total', 1)
            ->assertJsonPath('resguardos.data.0.id', $norte->id);

        $this->actingAs($this->usuario)
            ->putJson(route('punto_venta.sucursal_activa.establecer'), [
                'sucursal_id' => $this->sucursalB->id,
            ])
            ->assertOk();

        $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.listado', [
                'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
            ]))
            ->assertOk()
            ->assertJsonPath('resguardos.total', 1)
            ->assertJsonPath('resguardos.data.0.id', $sur->id);
    }

    public function test_index_expone_sucursales_asignadas_para_selector(): void
    {
        $this->withoutVite();

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Resguardos/Index', false)
                ->has('sucursales_asignadas', 2)
                ->where('sucursal_activa.id', $this->sucursalA->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearResguardo(Sucursal $sucursal, array $overrides = []): ResguardoPdv
    {
        return ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $sucursal->id,
            'salida_cedis_at' => now(),
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
        ], $overrides));
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

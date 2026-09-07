<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Reportes\MetricaResguardoPdvIds;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReporteMetricasPdvUiTest extends TestCase
{
    use RefreshDatabase;

    private User $analistaResguardos;

    private User $analistaTurnos;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Centro']);

        $this->analistaResguardos = User::factory()->create();
        $this->analistaResguardos->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
            PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR,
        ]);

        $this->analistaTurnos = User::factory()->create();
        $this->analistaTurnos->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ]);
        $this->analistaTurnos->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    public function test_index_redirige_a_vista_disponible(): void
    {
        $this->actingAs($this->analistaResguardos)
            ->get(route('punto_venta.reportes.index'))
            ->assertRedirect(route('punto_venta.reportes.resguardos'));

        $this->actingAs($this->analistaTurnos)
            ->get(route('punto_venta.reportes.index'))
            ->assertRedirect(route('punto_venta.reportes.turnos_operacion'));
    }

    public function test_resguardos_requiere_alcance_global(): void
    {
        $piso = User::factory()->create();
        $piso->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $piso->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->actingAs($piso)
            ->get(route('punto_venta.reportes.resguardos'))
            ->assertForbidden();
    }

    public function test_ui_resguardos_presenta_payload_backend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 12:00:00', 'America/Mexico_City'));

        ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
        ]);

        $this->withoutVite();

        $this->actingAs($this->analistaResguardos)
            ->get(route('punto_venta.reportes.resguardos', [
                'desde' => '2026-09-01T00:00:00-06:00',
                'hasta' => '2026-09-08T00:00:00-06:00',
                'sucursal_id' => $this->sucursal->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Reportes/Resguardos', false)
                ->has('payload.resguardos.metricas')
                ->where('permisos.exportar', true)
                ->where('tipo_reporte', 'resguardos'));

        $json = $this->actingAs($this->analistaResguardos)
            ->getJson(route('punto_venta.reportes.resguardos', [
                'desde' => '2026-09-01T00:00:00-06:00',
                'hasta' => '2026-09-08T00:00:00-06:00',
                'sucursal_id' => $this->sucursal->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame(
            1,
            $json['payload']['resguardos']['metricas'][MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR]['valor']
        );
        $this->assertArrayHasKey(MetricaResguardoPdvIds::R_04_REZAGADOS, $json['definiciones_metricas']);
    }

    public function test_turnos_operacion_sin_permiso_exportar_oculta_permiso(): void
    {
        $this->withoutVite();

        $this->actingAs($this->analistaTurnos)
            ->get(route('punto_venta.reportes.turnos_operacion'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Reportes/TurnosOperacion', false)
                ->where('permisos.exportar', false));
    }

    public function test_conjunto_requiere_ambos_dominios(): void
    {
        $this->actingAs($this->analistaTurnos)
            ->get(route('punto_venta.reportes.conjunto'))
            ->assertForbidden();

        $this->withoutVite();

        $conjunto = User::factory()->create();
        $conjunto->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            AlcancePdv::PERMISO_ALCANCE_GLOBAL,
        ]);

        $this->actingAs($conjunto)
            ->get(route('punto_venta.reportes.conjunto'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Reportes/Conjunto', false)
                ->has('payload.resguardos')
                ->has('payload.turnos_operacion'));
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->where('clave', PuntoVentaModulo::CLAVE_FLAG)->update(['valor' => '1']);
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        Permission::findOrCreate(AlcancePdv::PERMISO_ALCANCE_GLOBAL, 'web');
    }
}

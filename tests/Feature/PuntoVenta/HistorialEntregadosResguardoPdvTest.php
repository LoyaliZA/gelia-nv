<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HistorialEntregadosResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    private User $operador;

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

        $this->auditor = User::factory()->create();
        $this->auditor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER_HISTORIAL_ENTREGAS,
        ]);
        $this->auditor->concederAccesoSucursal($this->sucursalA, esPrincipal: true);

        $this->operador = User::factory()->create();
        $this->operador->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $this->operador->concederAccesoSucursal($this->sucursalA, esPrincipal: true);
    }

    public function test_lista_solo_entregados_de_sucursal_activa(): void
    {
        $propio = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'snapshot_folio' => 'REM-ENT-001',
            'entrega_completada_at' => now(),
        ]);
        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'snapshot_folio' => 'REM-CUST-001',
        ]);
        $this->crearResguardo($this->sucursalB, [
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'snapshot_folio' => 'REM-SUR-001',
            'entrega_completada_at' => now(),
        ]);

        $response = $this->actingAs($this->auditor)->getJson(route('punto_venta.resguardos.entregados.listado'));

        $response->assertOk();
        $ids = collect($response->json('resguardos.data'))->pluck('id');
        $this->assertTrue($ids->contains($propio->id));
        $this->assertCount(1, $ids);
    }

    public function test_filtro_fecha_y_busqueda(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));

        $dentro = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'snapshot_folio' => 'FOLIO-BUSCAR-99',
            'entrega_completada_at' => Carbon::parse('2026-09-10 15:00:00'),
        ]);
        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'snapshot_folio' => 'OTRO-FOLIO',
            'entrega_completada_at' => Carbon::parse('2026-09-01 10:00:00'),
        ]);

        $this->actingAs($this->auditor)
            ->getJson(route('punto_venta.resguardos.entregados.listado', [
                'desde' => '2026-09-09',
                'hasta' => '2026-09-11',
            ]))
            ->assertOk()
            ->assertJsonPath('resguardos.data.0.id', $dentro->id)
            ->assertJsonCount(1, 'resguardos.data');

        $this->actingAs($this->auditor)
            ->getJson(route('punto_venta.resguardos.entregados.listado', [
                'q' => 'BUSCAR-99',
            ]))
            ->assertOk()
            ->assertJsonPath('resguardos.data.0.id', $dentro->id);

        Carbon::setTestNow();
    }

    public function test_sin_permiso_historial_rechaza(): void
    {
        $this->actingAs($this->operador)
            ->get(route('punto_venta.resguardos.entregados.index'))
            ->assertForbidden();

        $this->actingAs($this->operador)
            ->getJson(route('punto_venta.resguardos.entregados.listado'))
            ->assertForbidden();
    }

    public function test_auditor_puede_ver_detalle_y_auditoria_de_entregado(): void
    {
        $entregado = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'entrega_completada_at' => now(),
        ]);
        $enCustodia = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
        ]);

        $this->actingAs($this->auditor)
            ->getJson(route('punto_venta.resguardos.show', $entregado))
            ->assertOk()
            ->assertJsonPath('resguardo.id', $entregado->id)
            ->assertJsonPath('modo_auditoria', true);

        $this->actingAs($this->auditor)
            ->getJson(route('punto_venta.resguardos.auditoria', $entregado))
            ->assertOk();

        $this->actingAs($this->auditor)
            ->getJson(route('punto_venta.resguardos.show', $enCustodia))
            ->assertNotFound();
    }

    public function test_auditor_sin_permiso_bandeja_operativa(): void
    {
        $this->actingAs($this->auditor)
            ->get(route('punto_venta.resguardos.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearResguardo(Sucursal $sucursal, array $overrides = []): ResguardoPdv
    {
        return ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $sucursal->id,
            'salida_cedis_at' => now(),
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
    }
}

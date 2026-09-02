<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UiBandejasResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

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
    }

    public function test_index_renderiza_inertia_con_catalogos(): void
    {
        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-UI-001',
        ]);

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.index', [
                'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
                'q' => 'REM-UI',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Resguardos/Index', false)
                ->has('resguardos.data', 1)
                ->where('bandeja', BandejaResguardoPdv::POR_RECIBIR)
                ->where('filtros.q', 'REM-UI')
                ->has('catalogos.bandejas')
                ->has('catalogos.estados')
                ->has('permisos.ver_vencidos'));
    }

    public function test_index_json_sigue_disponible(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.index'))
            ->assertOk()
            ->assertJsonStructure(['bandeja', 'resguardos', 'metricas', 'filtros']);
    }

    public function test_show_renderiza_detalle_y_timeline(): void
    {
        $resguardo = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-DET-001',
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA,
            'estado_anterior' => null,
            'estado_nuevo' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'actor_id' => $this->usuario->id,
            'ocurrido_at' => now(),
            'idempotency_key' => 'ui-test-'.$resguardo->id,
        ]);

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.show', $resguardo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Resguardos/Show', false)
                ->where('resguardo.id', $resguardo->id)
                ->where('resguardo.snapshot_folio', 'REM-DET-001')
                ->has('timeline', 1)
                ->where('timeline.0.tipo_evento', ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA));
    }

    public function test_show_otra_sucursal_devuelve_404(): void
    {
        $ajeno = $this->crearResguardo($this->sucursalB, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
        ]);

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.show', $ajeno))
            ->assertNotFound();
    }

    public function test_listado_mantiene_filtros_en_respuesta(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.listado', [
                'bandeja' => BandejaResguardoPdv::EN_CUSTODIA,
                'antiguedad' => 'proximo_a_vencer',
                'q' => 'folio-test',
            ]))
            ->assertOk()
            ->assertJsonPath('filtros.bandeja', BandejaResguardoPdv::EN_CUSTODIA)
            ->assertJsonPath('filtros.antiguedad', 'proximo_a_vencer')
            ->assertJsonPath('filtros.q', 'folio-test');
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

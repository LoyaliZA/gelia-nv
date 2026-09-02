<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\EvaluarVencimientosResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\PlazosCustodiaResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VencimientosResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Carbon::setTestNow(Carbon::parse('2026-08-28 12:00:00', 'America/Mexico_City'));

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_filtro_rezagado_aplica_con_plazos_configurados(): void
    {
        $rezagado = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'salida_cedis_at' => Carbon::parse('2026-07-01 10:00:00'),
            'snapshot_folio' => 'REM-REZ-001',
        ]);
        $reciente = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'salida_cedis_at' => Carbon::parse('2026-08-27 10:00:00'),
            'snapshot_folio' => 'REM-REC-002',
        ]);

        $response = $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
            'antiguedad' => AntiguedadOperativaResguardoPdv::REZAGADO,
        ]));

        $response->assertOk()
            ->assertJsonPath('filtros.antiguedad', AntiguedadOperativaResguardoPdv::REZAGADO)
            ->assertJsonPath('resguardos.total', 1)
            ->assertJsonPath('resguardos.data.0.id', $rezagado->id)
            ->assertJsonPath('metricas.rezagado', 1);

        $ids = collect($response->json('resguardos.data'))->pluck('id');
        $this->assertFalse($ids->contains($reciente->id));
    }

    public function test_en_custodia_excluye_vencidos_sin_permiso(): void
    {
        $vigente = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-08-27 10:00:00'),
            'snapshot_folio' => 'REM-VIG-001',
        ]);
        $vencido = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-07-01 10:00:00'),
            'snapshot_folio' => 'REM-VEN-002',
        ]);

        $response = $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::EN_CUSTODIA,
        ]));

        $response->assertOk()
            ->assertJsonPath('resguardos.total', 1)
            ->assertJsonPath('resguardos.data.0.id', $vigente->id)
            ->assertJsonPath('metricas.vencido', 1);

        $ids = collect($response->json('resguardos.data'))->pluck('id');
        $this->assertFalse($ids->contains($vencido->id));
    }

    public function test_entregado_no_aparece_como_custodia_vencida(): void
    {
        $entregado = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'recepcion_fisica_at' => Carbon::parse('2026-07-01 10:00:00'),
            'entrega_completada_at' => Carbon::parse('2026-08-01 10:00:00'),
        ]);

        $response = $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::EN_CUSTODIA,
            'antiguedad' => AntiguedadOperativaResguardoPdv::VENCIDO,
        ]));

        $response->assertOk()->assertJsonPath('resguardos.total', 0);
        $this->assertFalse(
            collect($response->json('resguardos.data'))->pluck('id')->contains($entregado->id)
        );
    }

    public function test_job_emite_eventos_idempotentes(): void
    {
        $vencido = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);
        $rezagado = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'salida_cedis_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        $service = app(EvaluarVencimientosResguardoPdvService::class);
        $primera = $service->ejecutar();
        $segunda = $service->ejecutar();

        $this->assertSame(1, $primera['vencidos']);
        $this->assertSame(1, $primera['rezagados']);
        $this->assertSame(0, $segunda['vencidos']);
        $this->assertSame(0, $segunda['rezagados']);

        $this->assertSame(
            1,
            ResguardoPdvEvento::query()
                ->where('resguardo_id', $vencido->id)
                ->where('tipo_evento', ResguardoPdvEvento::TIPO_MARCADO_VENCIDO)
                ->count()
        );
        $this->assertSame(
            1,
            ResguardoPdvEvento::query()
                ->where('resguardo_id', $rezagado->id)
                ->where('tipo_evento', ResguardoPdvEvento::TIPO_MARCADO_REZAGADO)
                ->count()
        );
    }

    public function test_detalle_expone_clasificaciones_coherentes(): void
    {
        $resguardo = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.show', $resguardo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('resguardo.clasificaciones.vencido', true)
                ->where('resguardo.antiguedad_configurada', true)
                ->has('resguardo.clasificaciones_etiquetas', 1));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearResguardo(array $overrides = []): ResguardoPdv
    {
        return ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $this->sucursal->id,
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

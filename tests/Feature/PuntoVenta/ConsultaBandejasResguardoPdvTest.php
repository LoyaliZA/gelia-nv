<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\PlazosCustodiaResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConsultaBandejasResguardoPdvTest extends TestCase
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
        $this->usuario->concederAccesoSucursal($this->sucursalA, esPrincipal: true);
    }

    public function test_lista_por_recibir_solo_de_sucursal_activa(): void
    {
        $propio = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-NORTE-001',
        ]);
        $this->crearResguardo($this->sucursalB, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-SUR-001',
        ]);

        $response = $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
        ]));

        $response->assertOk();
        $ids = collect($response->json('resguardos.data'))->pluck('id');
        $this->assertTrue($ids->contains($propio->id));
        $this->assertCount(1, $ids);
    }

    public function test_busqueda_por_folio_remision_y_nombre_cliente(): void
    {
        $coincide = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-UNICA-777',
            'snapshot_cliente_nombre' => 'Cliente Alfa',
        ]);
        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-OTRA-888',
            'snapshot_cliente_nombre' => 'Cliente Beta',
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.listado', ['q' => 'UNICA-777']))
            ->assertOk()
            ->assertJsonPath('resguardos.total', 1)
            ->assertJsonPath('resguardos.data.0.id', $coincide->id);

        $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.listado', ['q' => 'Alfa']))
            ->assertOk()
            ->assertJsonPath('resguardos.total', 1)
            ->assertJsonPath('resguardos.data.0.id', $coincide->id);
    }

    public function test_filtro_antiguedad_persiste_sin_aplicar_sin_plazos_configurados(): void
    {
        $this->sinPlazosCustodia();

        $antiguo = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'salida_cedis_at' => Carbon::parse('2026-07-01 10:00:00'),
            'snapshot_folio' => 'REM-REZ-001',
        ]);
        $reciente = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'salida_cedis_at' => Carbon::parse('2026-08-28 10:00:00'),
            'snapshot_folio' => 'REM-REC-002',
        ]);

        $response = $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
            'antiguedad' => AntiguedadOperativaResguardoPdv::REZAGADO,
        ]));

        $response->assertOk()
            ->assertJsonPath('filtros.antiguedad', AntiguedadOperativaResguardoPdv::REZAGADO)
            ->assertJsonPath('resguardos.total', 2)
            ->assertJsonMissingPath('metricas.rezagado')
            ->assertJsonPath('metricas.por_recibir', 2);

        $ids = collect($response->json('resguardos.data'))->pluck('id');
        $this->assertTrue($ids->contains($antiguo->id));
        $this->assertTrue($ids->contains($reciente->id));
        $this->assertFalse($response->json('resguardos.data.0.clasificaciones.rezagado'));
    }

    public function test_bandeja_en_custodia_lista_todos_cuando_plazos_no_configurados(): void
    {
        $this->sinPlazosCustodia();

        $vigente = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-08-28 10:00:00'),
            'snapshot_folio' => 'REM-VIG-001',
        ]);
        $antiguo = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-07-01 10:00:00'),
            'snapshot_folio' => 'REM-VEN-002',
        ]);

        $response = $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::EN_CUSTODIA,
        ]));

        $response->assertOk()
            ->assertJsonPath('resguardos.total', 2)
            ->assertJsonPath('metricas.en_custodia', 2)
            ->assertJsonMissingPath('metricas.vencido');

        $ids = collect($response->json('resguardos.data'))->pluck('id');
        $this->assertTrue($ids->contains($vigente->id));
        $this->assertTrue($ids->contains($antiguo->id));
    }

    public function test_bandeja_incidencias_lista_solo_abiertas(): void
    {
        $conIncidencia = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => now()->subDay(),
        ]);
        ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $conIncidencia->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_DANO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Caja golpeada',
            'reportado_at' => now(),
        ]);

        $cerrada = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => now()->subDays(2),
        ]);
        ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $cerrada->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_FALTANTE,
            'estado' => ResguardoPdvIncidencia::ESTADO_CERRADA,
            'descripcion' => 'Resuelta',
            'reportado_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::INCIDENCIAS,
        ]));

        $response->assertOk()
            ->assertJsonPath('resguardos.total', 1)
            ->assertJsonPath('resguardos.data.0.id', $conIncidencia->id)
            ->assertJsonPath('metricas.incidencias', 1);
    }

    public function test_paginacion_estable_y_get_no_modifica_datos(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->crearResguardo($this->sucursalA, [
                'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
                'snapshot_folio' => 'REM-PAG-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'salida_cedis_at' => now()->subMinutes($i),
            ]);
        }

        $eventosAntes = ResguardoPdvEvento::query()->count();
        $versionAntes = ResguardoPdv::query()->sum('version');

        $paginaUno = $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
            'page' => 1,
        ]));
        $paginaDos = $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
            'page' => 2,
        ]));

        $paginaUno->assertOk()->assertJsonPath('resguardos.per_page', 15)->assertJsonPath('resguardos.total', 16);
        $paginaDos->assertOk()->assertJsonPath('resguardos.data', fn ($data) => count($data) === 1);

        $idsPaginaUno = collect($paginaUno->json('resguardos.data'))->pluck('id');
        $idsPaginaDos = collect($paginaDos->json('resguardos.data'))->pluck('id');
        $this->assertEmpty($idsPaginaUno->intersect($idsPaginaDos));

        $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.index'));

        $this->assertSame($eventosAntes, ResguardoPdvEvento::query()->count());
        $this->assertSame($versionAntes, ResguardoPdv::query()->sum('version'));
    }

    public function test_consulta_no_genera_n_mas_uno_relevante(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->crearResguardo($this->sucursalA, [
                'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
                'snapshot_folio' => 'REM-N1-'.$i,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->usuario)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
        ]))->assertOk();

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(60, $consultas);
    }

    public function test_sucursal_no_autorizada_en_filtro_rechaza(): void
    {
        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.listado', ['sucursal_id' => $this->sucursalB->id]))
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

    private function sinPlazosCustodia(): void
    {
        ConfiguracionSistema::query()
            ->where('clave', PlazosCustodiaResguardoPdvConfig::CLAVE)
            ->delete();

        Cache::forget(PlazosCustodiaResguardoPdvConfig::CACHE_KEY);
    }
}

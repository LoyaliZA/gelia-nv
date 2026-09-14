<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioCsvArtefacto;
use App\Models\Tiendanube\TiendanubePrecioCsvPerfil;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeCatalogoWipeService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshDatabaseSafe;
use Tests\Support\TiendanubePrecioMotorFixtures;
use Tests\TestCase;

class TiendanubePrecioHistorialTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        config([
            'tiendanube.api_base' => null,
            'tiendanube.api_host' => 'https://api.tiendanube.com',
            'tiendanube.api_version' => '2025-03',
            'tiendanube.retry_sleep_ms' => 0,
            'tiendanube.precio_lote_sync_max' => 200,
            'tiendanube.precios_restauracion_habilitada' => true,
        ]);

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
            'config_generation' => 1,
        ])->save();

        foreach ([
            'tiendanube.ver',
            'tiendanube.configurar',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
            'tiendanube.precios.reglas.ver',
            'tiendanube.precios.reglas.administrar',
            'tiendanube.precios.aprobar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'tiendanube.ver',
            'tiendanube.configurar',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
            'tiendanube.precios.reglas.ver',
            'tiendanube.precios.reglas.administrar',
            'tiendanube.precios.aprobar',
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_csv_descargado_no_aparece_como_aplicado(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->aprobarLote([1000]);
        $this->marcarCsvDescargado($lote['lote_id'], (int) $lote['revision']['id']);

        $listado = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.historial.operaciones'))
            ->assertOk();

        $fila = collect($listado->json('data'))->firstWhere('lote_id', $lote['lote_id']);
        $this->assertNotNull($fila);
        $this->assertSame('archivo_descargado', $fila['evidencia']);
        $this->assertNotSame('aplicado_api', $fila['evidencia']);
        $this->assertSame('csv', $fila['canal']);
    }

    public function test_conciliacion_detecta_discrepancia_sin_escribir_remotamente(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->aprobarLote([1000]);
        $this->fakeProducto(100, 1000, '200.00', null, '40.00');

        $res = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.historial.conciliar', $lote['lote_id']), [
                'evidencia_tipo' => 'lectura_api',
                'variante_ids' => [1000],
            ])
            ->assertOk();

        $normal = collect($res->json('filas'))->firstWhere('campo', 'normal');
        $this->assertSame('difiere', $normal['resultado']);
        $this->assertDatabaseCount('tiendanube_precio_conciliaciones', 3);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET');
        Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['PUT', 'POST', 'PATCH', 'DELETE'], true));
        $this->assertEquals(100.0, (float) TiendanubeProductoVariante::query()->find(1000)?->getRawOriginal('price'));
    }

    public function test_restauracion_parcial_solo_incluye_campos_elegidos(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', '80.00', '40.00');
        $lote = $this->aprobarLote([1000]);
        $this->fakeProducto(100, 1000, '110.00', '80.00', '40.00');
        $this->conciliar($lote['lote_id']);

        $res = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.historial.restaurar', $lote['lote_id']), [
                'filas' => [
                    ['variante_id' => 1000, 'campos' => ['normal']],
                ],
                'motivo' => 'Solo normal',
            ])
            ->assertCreated();

        $compensacionId = $res->json('lote.lote_id');
        $this->assertNotSame($lote['lote_id'], $compensacionId);
        $this->assertSame('restauracion', $res->json('lote.origen'));
        $this->assertSame($lote['lote_id'], $res->json('lote.lote_origen_id'));

        $items = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.items', $compensacionId))
            ->assertOk();

        $this->assertSame('establecer', $items->json('data.0.campos.normal.intencion'));
        $this->assertSame('100.00', $items->json('data.0.campos.normal.propuesto'));
        $this->assertSame('conservar', $items->json('data.0.campos.promocional.intencion'));

        $origen = TiendanubePrecioLote::query()->find($lote['lote_id']);
        $this->assertSame(TiendanubePrecioLote::ESTADO_APROBADO, $origen?->estado);
        $this->assertSame('calculo', $origen?->origen);
    }

    public function test_cambio_posterior_provoca_conflicto_antes_de_restaurar(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->aprobarLote([1000]);
        $this->fakeProducto(100, 1000, '250.00', null, '40.00');
        $this->conciliar($lote['lote_id']);

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.historial.restaurar', $lote['lote_id']), [
                'filas' => [
                    ['variante_id' => 1000, 'campos' => ['normal']],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_posterior');
    }

    public function test_promocion_originalmente_ausente_se_restaura_como_eliminacion(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->aprobarLote([1000]);
        $this->fakeProducto(100, 1000, '110.00', '90.00', '40.00');
        $this->conciliar($lote['lote_id']);

        $prep = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.historial.restaurar.preparar', $lote['lote_id']), [
                'filas' => [
                    ['variante_id' => 1000, 'campos' => ['promocional']],
                ],
            ])
            ->assertOk();

        $this->assertSame('eliminar', $prep->json('filas.0.campos.promocional.intencion'));
        $this->assertNull($prep->json('filas.0.campos.promocional.restaurar_a'));

        $res = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.historial.restaurar', $lote['lote_id']), [
                'filas' => [
                    ['variante_id' => 1000, 'campos' => ['promocional']],
                ],
                'aceptar_conflicto' => true,
            ])
            ->assertCreated();

        $items = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.lotes.items', $res->json('lote.lote_id')))
            ->assertOk();
        $this->assertSame('eliminar', $items->json('data.0.campos.promocional.intencion'));
        $this->assertNull($items->json('data.0.campos.promocional.propuesto'));
    }

    public function test_costo_antiguo_no_publicable_queda_bloqueado(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, null);
        $lote = $this->aprobarLote([1000]);
        $this->fakeProducto(100, 1000, '110.00', null, '40.00');
        $this->conciliar($lote['lote_id']);

        $prep = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.historial.restaurar.preparar', $lote['lote_id']), [
                'filas' => [
                    ['variante_id' => 1000, 'campos' => ['costo_remoto']],
                ],
            ])
            ->assertOk();

        $this->assertFalse($prep->json('filas.0.campos.costo_remoto.restaurable'));
    }

    public function test_usuario_sin_acceso_a_costos_no_los_obtiene_por_historial(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->aprobarLote([1000]);

        $sinCosto = User::factory()->create();
        $sinCosto->givePermissionTo([
            'tiendanube.ver',
            'tiendanube.precios.reglas.ver',
        ]);

        $detalle = $this->actingAs($sinCosto)
            ->getJson(route('tiendanube.precios.historial.operaciones.show', $lote['lote_id']))
            ->assertOk();

        $this->assertNull($detalle->json('items.0.campos.costo_remoto.actual'));
        $this->assertNull($detalle->json('items.0.campos.costo_remoto.propuesto'));
        $this->assertArrayNotHasKey('costo_local', $detalle->json('items.0'));
    }

    public function test_wipe_del_espejo_conserva_historial(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->aprobarLote([1000]);

        app(TiendanubeCatalogoWipeService::class)->wipe();

        $this->assertNull(TiendanubeProductoVariante::query()->find(1000));
        $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.historial.operaciones'))
            ->assertOk()
            ->assertJsonPath('data.0.lote_id', $lote['lote_id']);
        $this->assertDatabaseHas('tiendanube_precio_lote_items', [
            'variante_id' => 1000,
            'variante_sku' => 'SKU-A',
        ]);
    }

    public function test_sku_en_otra_tienda_no_habilita_restauracion(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->aprobarLote([1000]);

        TiendanubeConfiguracion::obtener()->fill(['store_id' => 999999])->save();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.historial.restaurar.preparar', $lote['lote_id']), [])
            ->assertStatus(404)
            ->assertJsonPath('codigo', 'no_encontrada');
    }

    public function test_restauracion_deshabilitada_mantiene_consulta(): void
    {
        config(['tiendanube.precios_restauracion_habilitada' => false]);
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->aprobarLote([1000]);

        $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.historial.operaciones'))
            ->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.historial.restaurar', $lote['lote_id']), [])
            ->assertStatus(403)
            ->assertJsonPath('codigo', 'restauracion_deshabilitada');
    }

    /**
     * @param  list<int>  $varianteIds
     * @return array<string, mixed>
     */
    private function aprobarLote(array $varianteIds): array
    {
        $selection = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => $varianteIds,
            ])
            ->assertCreated();

        $lote = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.store'), [
                'selection_id' => $selection->json('selection_id'),
                'selection_version' => $selection->json('version'),
                'definicion' => TiendanubePrecioMotorFixtures::regla(
                    'temp',
                    'precio_normal_actual',
                    'aumentar_porcentaje',
                    'normal',
                    '10'
                ),
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.aprobar', $lote['lote_id']), [
                'checksum' => $lote['revision']['checksum'],
            ])
            ->assertOk();

        return $lote;
    }

    private function conciliar(string $loteId): void
    {
        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.historial.conciliar', $loteId), [
                'evidencia_tipo' => 'lectura_api',
            ])
            ->assertOk();
    }

    private function fakeProducto(int $productoId, int $varianteId, string $price, ?string $promo, ?string $cost): void
    {
        Http::fake(function (Request $request) use ($productoId, $varianteId, $price, $promo, $cost) {
            if ($request->method() !== 'GET' || ! str_contains($request->url(), '/products/'.$productoId)) {
                return Http::response(['error' => 'unexpected'], 500);
            }

            return Http::response([
                'id' => $productoId,
                'variants' => [[
                    'id' => $varianteId,
                    'price' => $price,
                    'promotional_price' => $promo,
                    'cost' => $cost,
                ]],
            ], 200);
        });
    }

    private function marcarCsvDescargado(string $loteId, int $revisionId): void
    {
        $perfil = TiendanubePrecioCsvPerfil::query()->create([
            'store_id' => 8004291,
            'version' => 1,
            'estado' => TiendanubePrecioCsvPerfil::ESTADO_VALIDADO,
            'encabezados_canonicos' => ['SKU'],
            'presets' => [],
        ]);
        TiendanubePrecioCsvArtefacto::query()->create([
            'lote_id' => $loteId,
            'revision_id' => $revisionId,
            'perfil_id' => $perfil->id,
            'perfil_version' => 1,
            'store_id' => 8004291,
            'preset_usado' => 'solo_precios',
            'columnas_exportadas' => ['SKU'],
            'columnas_hash' => hash('sha256', 'sku'),
            'estado' => TiendanubePrecioCsvArtefacto::ESTADO_DESCARGADO,
            'archivo_path' => 'csv/test.csv',
            'nombre_archivo' => 'test.csv',
            'hash_sha256' => hash('sha256', 'contenido'),
            'descargado_at' => now(),
        ]);
    }

    private function crearVariante(
        int $id,
        int $productoId,
        string $sku,
        string $precio,
        ?string $promo,
        ?string $costo,
    ): void {
        TiendanubeProducto::query()->create([
            'id' => $productoId,
            'name' => ['es' => 'Producto '.$productoId],
            'published' => true,
            'synced_at' => now(),
        ]);

        TiendanubeProductoVariante::query()->create([
            'id' => $id,
            'producto_id' => $productoId,
            'sku' => $sku,
            'price' => $precio,
            'promotional_price' => $promo,
            'cost' => $costo,
            'values' => ['Talla M'],
            'stock' => 5,
        ]);
    }
}

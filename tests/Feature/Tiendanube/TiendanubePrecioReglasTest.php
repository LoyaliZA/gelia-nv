<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioLista;
use App\Models\Tiendanube\TiendanubePrecioRegla;
use App\Models\Tiendanube\TiendanubePrecioReglaVersion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorCalculoService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshDatabaseSafe;
use Tests\Support\TiendanubePrecioMotorFixtures;
use Tests\TestCase;

class TiendanubePrecioReglasTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
        ])->save();

        foreach ([
            'tiendanube.ver',
            'tiendanube.precios.ver',
            'tiendanube.precios.reglas.ver',
            'tiendanube.precios.reglas.administrar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'tiendanube.ver',
            'tiendanube.precios.ver',
            'tiendanube.precios.reglas.ver',
            'tiendanube.precios.reglas.administrar',
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_guardar_regla_no_escribe_precios_remotos(): void
    {
        Queue::fake();
        $this->crearVariante(1000, 100, 'SKU-A', '50.00', '40.00', '10.00');

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.store'), $this->payloadRegla())
            ->assertCreated();

        $variante = TiendanubeProductoVariante::find(1000);
        $this->assertEquals(50.0, (float) $variante?->getRawOriginal('price'));
        $this->assertEquals(40.0, (float) $variante?->getRawOriginal('promotional_price'));
        $this->assertEquals(10.0, (float) $variante?->getRawOriginal('cost'));
        Queue::assertNothingPushed();
    }

    public function test_editar_crea_version_nueva_sin_mutar_anterior(): void
    {
        $created = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.store'), $this->payloadRegla())
            ->assertCreated();

        $reglaId = $created->json('id');
        $versionInicialId = $created->json('version_actual.id');
        $definicionInicial = $created->json('version_actual.definicion');

        $updated = $this->actingAs($this->user)
            ->putJson(route('tiendanube.precios.reglas.update', $reglaId), [
                'version' => $created->json('version'),
                'definicion' => array_merge($definicionInicial, [
                    'parametro' => '8',
                ]),
            ])
            ->assertOk();

        $this->assertSame(2, $updated->json('version_actual.numero'));
        $this->assertNotSame($versionInicialId, $updated->json('version_actual.id'));

        $versionAnterior = TiendanubePrecioReglaVersion::find($versionInicialId);
        $this->assertSame('6', $versionAnterior?->definicion['parametro']);
        $this->assertSame(TiendanubePrecioMotorCalculoService::MOTOR_VERSION, $updated->json('version_actual.contract_version'));
    }

    public function test_formula_invalida_rechazada(): void
    {
        $payload = $this->payloadRegla();
        $payload['definicion']['destino'] = 'costo_remoto';
        $payload['definicion']['operacion'] = 'eliminar';

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.store'), $payload)
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'validacion');
    }

    public function test_duplicar_crea_regla_independiente(): void
    {
        $created = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.store'), $this->payloadRegla())
            ->assertCreated();

        $dup = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.duplicar', $created->json('id')))
            ->assertCreated();

        $this->assertNotSame($created->json('id'), $dup->json('id'));
        $this->assertSame(2, TiendanubePrecioRegla::query()->count());
    }

    public function test_archivar_conserva_historial(): void
    {
        $created = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.store'), $this->payloadRegla())
            ->assertCreated();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.archivar', $created->json('id')))
            ->assertOk()
            ->assertJsonPath('archivada', true);

        $this->assertDatabaseHas('tiendanube_precio_regla_versiones', [
            'id' => $created->json('version_actual.id'),
        ]);

        $listado = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.reglas.listar'))
            ->assertOk();
        $this->assertCount(0, $listado->json('reglas'));

        $conArchivadas = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.reglas.listar', ['incluir_archivadas' => 1]))
            ->assertOk();
        $this->assertCount(1, $conArchivadas->json('reglas'));
    }

    public function test_dos_actualizaciones_concurrentes_devuelven_conflicto(): void
    {
        $created = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.store'), $this->payloadRegla())
            ->assertCreated();

        $reglaId = $created->json('id');
        $version = $created->json('version');
        $def = $created->json('version_actual.definicion');

        $this->actingAs($this->user)
            ->putJson(route('tiendanube.precios.reglas.update', $reglaId), [
                'version' => $version,
                'definicion' => array_merge($def, ['parametro' => '7']),
            ])
            ->assertOk();

        $this->actingAs($this->user)
            ->putJson(route('tiendanube.precios.reglas.update', $reglaId), [
                'version' => $version,
                'definicion' => array_merge($def, ['parametro' => '9']),
            ])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto');
    }

    public function test_lista_de_otra_tienda_rechazada(): void
    {
        $listaOtra = TiendanubePrecioLista::query()->create([
            'store_id' => 999999,
            'nombre' => 'Lista ajena',
        ]);

        $payload = $this->payloadRegla();
        $payload['definicion']['base'] = 'lista_referencia';
        $payload['definicion']['base_lista_id'] = $listaOtra->id;

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.store'), $payload)
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'tienda_invalida');
    }

    public function test_regla_habilitada_no_dispara_jobs(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.store'), $this->payloadRegla())
            ->assertCreated()
            ->assertJsonPath('habilitada', true);

        Queue::assertNothingPushed();
    }

    public function test_preview_con_seleccion_devuelve_muestra_y_disclaimer(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '50.00', '40.00', '10.00');

        $selection = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => [1000],
            ])
            ->assertCreated();

        $preview = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.previsualizar'), [
                'selection_id' => $selection->json('selection_id'),
                'definicion' => $this->payloadRegla()['definicion'],
            ])
            ->assertOk();

        $preview->assertJsonPath('mensaje', 'Muestra; aún no revisada para aplicar');
        $this->assertGreaterThanOrEqual(1, count($preview->json('filas')));
        $this->assertSame(1000, $preview->json('filas.0.variante_id'));
    }

    public function test_sin_permiso_costo_no_revela_valores_en_preview(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '50.00', '40.00', '10.00');

        $soloReglas = User::factory()->create();
        $soloReglas->givePermissionTo(['tiendanube.ver', 'tiendanube.precios.reglas.ver']);

        $selection = $this->actingAs($soloReglas)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => [1000],
            ])
            ->assertCreated();

        $def = TiendanubePrecioMotorFixtures::regla(
            'temp-1',
            'costo_remoto_actual',
            'aumentar_porcentaje',
            'normal',
            '10'
        );

        $preview = $this->actingAs($soloReglas)
            ->postJson(route('tiendanube.precios.reglas.previsualizar'), [
                'selection_id' => $selection->json('selection_id'),
                'definicion' => $def,
            ])
            ->assertOk();

        $fila = $preview->json('filas.0');
        $this->assertTrue($fila['base_faltante']);
        $this->assertArrayNotHasKey('antes', $fila);
        $this->assertArrayNotHasKey('despues', $fila);
    }

    public function test_lista_archivada_marca_version_no_utilizable(): void
    {
        $lista = TiendanubePrecioLista::query()->create([
            'store_id' => 8004291,
            'nombre' => 'Lista prueba',
        ]);

        $payload = $this->payloadRegla();
        $payload['definicion']['base'] = 'lista_referencia';
        $payload['definicion']['base_lista_id'] = $lista->id;

        $created = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.reglas.store'), $payload)
            ->assertCreated();
        $this->assertTrue($created->json('version_actual.utilizable'));

        $lista->update(['archived_at' => now()]);

        $updated = $this->actingAs($this->user)
            ->putJson(route('tiendanube.precios.reglas.update', $created->json('id')), [
                'version' => $created->json('version'),
                'definicion' => $payload['definicion'],
            ])
            ->assertOk();

        $this->assertFalse($updated->json('version_actual.utilizable'));
        $this->assertStringContainsString('archivada', (string) $updated->json('version_actual.motivo_no_utilizable'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadRegla(): array
    {
        return [
            'nombre' => 'Descuento menor 100',
            'descripcion' => 'Ejemplo TN-07C',
            'definicion' => TiendanubePrecioMotorFixtures::regla(
                'temp',
                'precio_normal_actual',
                'reducir_porcentaje',
                'promocional',
                '6',
                [
                    [
                        'campo' => 'precio_normal_actual',
                        'operador' => '<',
                        'valor' => '100',
                    ],
                ]
            ),
        ];
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
            'name' => ['es' => 'Producto'],
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
            'values' => [],
        ]);
    }
}

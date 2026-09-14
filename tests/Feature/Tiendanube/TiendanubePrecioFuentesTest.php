<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioImportItem;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\Precios\TiendanubePrecioImporte;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
use Tests\Support\TiendanubePrecioFuenteFixtures;
use Tests\TestCase;

class TiendanubePrecioFuentesTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
        ])->save();

        foreach ([
            'tiendanube.ver',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
            'tiendanube.precios.importar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'tiendanube.ver',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
            'tiendanube.precios.importar',
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_captura_330_persiste_sin_modificar_costo_remoto(): void
    {
        $this->crearVariante(100, 10, 'SKU330', '12.50');

        $remotoAntes = TiendanubeProductoVariante::find(100)?->getRawOriginal('cost');

        $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('tiendanube.precios.costos.store'), [
                'variante_id' => 100,
                'valor' => '330.00',
                'moneda' => 'MXN',
                'motivo' => 'captura ensayo',
            ])
            ->assertCreated()
            ->assertJsonPath('version.valor_decimal', '330.00')
            ->assertJsonPath('version.version', 1);

        $this->assertDatabaseHas('tiendanube_precio_fuente_versiones', [
            'variante_id' => 100,
            'tipo' => 'costo_local',
            'version' => 1,
        ]);
        $this->assertSame($remotoAntes, TiendanubeProductoVariante::find(100)?->getRawOriginal('cost'));
    }

    public function test_cero_y_ausencia_son_distintos_y_negativos_se_rechazan(): void
    {
        $this->crearVariante(101, 11, 'SKU0', null);

        $resolver = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.fuentes.resolver'), [
                'variante_ids' => [101],
                'tipos' => ['costo_local'],
            ]);
        $resolver->assertOk();
        $this->assertTrue($resolver->json('variantes.0.fuentes.0.faltante'));
        $this->assertNull($resolver->json('variantes.0.fuentes.0.valor_decimal'));

        $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('tiendanube.precios.costos.store'), [
                'variante_id' => 101,
                'valor' => '0.00',
                'moneda' => 'MXN',
            ])
            ->assertCreated()
            ->assertJsonPath('version.valor_decimal', '0.00');

        $despues = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.fuentes.resolver'), [
                'variante_ids' => [101],
                'tipos' => ['costo_local'],
            ]);
        $this->assertFalse($despues->json('variantes.0.fuentes.0.faltante'));
        $this->assertSame('0.00', $despues->json('variantes.0.fuentes.0.valor_decimal'));

        $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('tiendanube.precios.costos.store'), [
                'variante_id' => 101,
                'valor' => '-1',
                'moneda' => 'MXN',
            ])
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'negativo');
    }

    public function test_lista_renombrada_conserva_id_y_versiones(): void
    {
        $this->crearVariante(102, 12, 'SKUL', null);

        $crear = $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('tiendanube.precios.listas.store'), ['nombre' => 'Mayorista']);
        $crear->assertCreated();
        $listaId = $crear->json('id');

        $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('tiendanube.precios.costos.store'), [
                'variante_id' => 102,
                'valor' => '400.00',
                'moneda' => 'MXN',
                'tipo' => 'lista_referencia',
                'lista_id' => $listaId,
            ])
            ->assertCreated();

        $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->putJson(route('tiendanube.precios.listas.update', $listaId), ['nombre' => 'Mayoreo 2026'])
            ->assertOk()
            ->assertJsonPath('id', $listaId)
            ->assertJsonPath('nombre', 'Mayoreo 2026');

        $this->assertDatabaseHas('tiendanube_precio_fuente_versiones', [
            'lista_id' => $listaId,
            'variante_id' => 102,
            'version' => 1,
            'tipo' => 'lista_referencia',
        ]);
    }

    public function test_sku_00123_conserva_ceros(): void
    {
        $this->crearVariante(103, 13, '00123', null);

        $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.variantes.resolver', ['sku' => '00123']))
            ->assertOk()
            ->assertJsonPath('estado', 'encontrado')
            ->assertJsonPath('sku', '00123')
            ->assertJsonPath('variante_id', 103);

        $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.variantes.resolver', ['sku' => '123']))
            ->assertOk()
            ->assertJsonPath('estado', 'no_encontrado');
    }

    public function test_sku_ambiguo_no_elige_el_primero(): void
    {
        $this->crearVariante(104, 14, 'DUP', null);
        $this->crearVariante(105, 15, 'DUP', null);

        $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.variantes.resolver', ['sku' => 'DUP']))
            ->assertOk()
            ->assertJsonPath('estado', 'ambiguo')
            ->assertJsonPath('variante_id', null)
            ->assertJsonCount(2, 'candidatos');
    }

    public function test_csv_duplicado_y_numero_ambiguo_informan_error_antes_de_confirmar(): void
    {
        $this->crearVariante(106, 16, '00123', null);

        $csv = "sku,costo\n00123,330.00\n00123,331.00\n";
        $dup = $this->importarCsv($csv);
        $dup->assertOk();
        $this->assertTrue(collect($dup->json('items'))->contains(fn ($i) => $i['motivo'] === 'duplicado'));
        $this->assertDatabaseCount('tiendanube_precio_fuente_versiones', 0);

        $csvAmbiguo = "sku,costo\n00123,1.234\n";
        $amb = $this->importarCsv($csvAmbiguo);
        $amb->assertOk();
        $this->assertTrue(collect($amb->json('items'))->contains(fn ($i) => $i['motivo'] === 'numero_ambiguo'));
        $this->assertDatabaseCount('tiendanube_precio_fuente_versiones', 0);
    }

    public function test_usuario_sin_permiso_no_recibe_costo(): void
    {
        $this->crearVariante(107, 17, 'HIDE', '88.00');

        $soloVer = User::factory()->create();
        $soloVer->givePermissionTo('tiendanube.ver');

        $this->actingAs($soloVer)
            ->getJson(route('tiendanube.productos.show', 17))
            ->assertOk()
            ->assertJsonMissingPath('variantes.0.cost');

        $this->actingAs($soloVer)
            ->postJson(route('tiendanube.precios.fuentes.resolver'), ['variante_ids' => [107]])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->getJson(route('tiendanube.productos.show', 17))
            ->assertOk()
            ->assertJsonPath('variantes.0.cost', 88);
    }

    public function test_monedas_incompatibles_no_se_mezclan(): void
    {
        $this->crearVariante(108, 18, 'MXN1', null);

        $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('tiendanube.precios.costos.store'), [
                'variante_id' => 108,
                'valor' => '10.00',
                'moneda' => 'MXN',
            ])
            ->assertCreated();

        $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('tiendanube.precios.costos.store'), [
                'variante_id' => 108,
                'valor' => '10.00',
                'moneda' => 'USD',
            ])
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'moneda_incompatible');
    }

    public function test_importacion_valida_confirma_solo_seleccionadas(): void
    {
        $this->crearVariante(109, 19, 'OKSKU', '1.00');
        $remotoAntes = TiendanubeProductoVariante::find(109)?->getRawOriginal('cost');

        $preview = $this->importarCsv("sku,costo\nOKSKU,50.00\n");
        $preview->assertOk();
        $itemId = $preview->json('items.0.id');

        $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('tiendanube.precios.importar.confirmar', $preview->json('id')), [
                'item_ids' => [$itemId],
            ])
            ->assertOk()
            ->assertJsonPath('estado', 'confirmado');

        $this->assertDatabaseHas('tiendanube_precio_fuente_versiones', [
            'variante_id' => 109,
            'origen' => 'archivo',
        ]);
        $this->assertSame($remotoAntes, TiendanubeProductoVariante::find(109)?->getRawOriginal('cost'));
        $this->assertSame(
            TiendanubePrecioImportItem::ESTADO_CONFIRMADO,
            TiendanubePrecioImportItem::find($itemId)?->estado
        );
    }

    public function test_pagina_fuentes_inertia_y_fixtures_contrato(): void
    {
        $this->actingAs($this->user)
            ->get(route('tiendanube.precios.fuentes'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tiendanube/Precios/Fuentes', false));

        $this->assertSame('330.00', TiendanubePrecioFuenteFixtures::costoLocal330()['valor_decimal']);
        $this->assertTrue(TiendanubePrecioFuenteFixtures::costoLocalAusente()['faltante']);
        $this->assertSame('0.00', TiendanubePrecioFuenteFixtures::costoLocalCero()['valor_decimal']);
    }

    public function test_importe_parser_rechaza_ambiguos(): void
    {
        $this->assertSame('330.00', TiendanubePrecioImporte::parse('330.00')['valor']);
        $this->assertSame('0.00', TiendanubePrecioImporte::parse('0')['valor']);
        $this->assertSame('numero_ambiguo', TiendanubePrecioImporte::parse('1.234')['error']);
        $this->assertSame('numero_ambiguo', TiendanubePrecioImporte::parse('1,234', '.')['error']);
        $this->assertSame('negativo', TiendanubePrecioImporte::parse('-3')['error']);
        $this->assertNull(TiendanubePrecioImporte::parse('')['valor']);
    }

    public function test_costo_local_es_predeterminado_sin_sustituir_si_falta(): void
    {
        $this->crearVariante(110, 20, 'DEF', '9.00');

        $sinLocal = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.fuentes.resolver'), [
                'variante_ids' => [110],
            ]);
        $costoLocal = collect($sinLocal->json('variantes.0.fuentes'))->firstWhere('tipo', 'costo_local');
        $remoto = collect($sinLocal->json('variantes.0.fuentes'))->firstWhere('tipo', 'costo_remoto_actual');
        $this->assertTrue($costoLocal['faltante']);
        $this->assertFalse($costoLocal['predeterminada']);
        $this->assertSame('9.00', $remoto['valor_decimal']);
        $this->assertFalse($remoto['predeterminada']);

        $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('tiendanube.precios.costos.store'), [
                'variante_id' => 110,
                'valor' => '330.00',
                'moneda' => 'MXN',
            ])
            ->assertCreated();

        $conLocal = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.fuentes.resolver'), [
                'variante_ids' => [110],
            ]);
        $costoLocal = collect($conLocal->json('variantes.0.fuentes'))->firstWhere('tipo', 'costo_local');
        $this->assertTrue($costoLocal['predeterminada']);
        $this->assertSame('330.00', $costoLocal['valor_decimal']);
    }

    private function crearVariante(int $id, int $productoId, string $sku, ?string $cost): void
    {
        if (! TiendanubeProducto::query()->find($productoId)) {
            TiendanubeProducto::create([
                'id' => $productoId,
                'name' => ['es' => 'Prod '.$productoId],
                'published' => true,
            ]);
        }

        TiendanubeProductoVariante::create([
            'id' => $id,
            'producto_id' => $productoId,
            'sku' => $sku,
            'price' => '10.00',
            'cost' => $cost,
        ]);
    }

    private function importarCsv(string $csv)
    {
        $file = UploadedFile::fake()->createWithContent('costos.csv', $csv);

        return $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('tiendanube.precios.importar'), [
                'archivo' => $file,
                'delimiter' => ',',
                'decimal_sep' => '.',
                'identificador' => 'sku',
                'columna_identificador' => 'sku',
                'columnas_importes' => ['costo_local' => 'costo'],
                'moneda_fija' => 'MXN',
            ], ['Accept' => 'application/json']);
    }
}

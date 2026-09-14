<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioCsvArtefacto;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Support\Tiendanube\Precios\TiendanubePrecioCsvColumnaCatalogo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshDatabaseSafe;
use Tests\Support\TiendanubePrecioMotorFixtures;
use Tests\TestCase;

class TiendanubePrecioCsvExportTest extends TestCase
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
            'tiendanube.precios.exportar',
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
            'tiendanube.precios.exportar',
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['tiendanube.precio_lote_sync_max' => 200]);
    }

    public function test_sin_perfil_devuelve_error_accionable(): void
    {
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00');
        $lote = $this->aprobarLote([1000]);

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.exportaciones_csv', $lote['lote_id']), [
                'preset' => 'solo_precios',
            ])
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'perfil_faltante')
            ->assertJsonPath('message', 'Configura una exportación de ejemplo de tu tienda');
    }

    public function test_exporta_csv_nativo_desde_revision_aprobada_sin_http(): void
    {
        Http::fake(fn () => Http::response('blocked', 503));
        $this->validarPerfil();
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00', 'producto-100');
        $lote = $this->aprobarLote([1000]);

        $gen = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.exportaciones_csv', $lote['lote_id']), [
                'preset' => 'solo_precios',
            ])
            ->assertCreated();

        Http::assertNothingSent();
        $this->assertSame('generado', $gen->json('estado'));
        $this->assertCount(5, $gen->json('columnas'));
        $this->assertSame(TiendanubePrecioLote::ESTADO_APROBADO, TiendanubePrecioLote::query()->find($lote['lote_id'])?->estado);

        $csv = $this->leerCsv($gen->json('id'));
        $this->assertSame([
            'Identificador de URL',
            'Precio',
            'Precio promocional',
            'SKU',
            'Costo',
        ], $csv[0]);
        $this->assertSame('producto-100', $csv[1][0]);
        $this->assertSame('110.00', $csv[1][1]);
        $this->assertSame('', $csv[1][2]);
        $this->assertSame('SKU-A', $csv[1][3]);
        $this->assertSame('40.00', $csv[1][4]);

        $otra = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.exportaciones_csv', $lote['lote_id']), [
                'preset' => 'solo_precios',
            ])
            ->assertCreated();
        $this->assertSame($gen->json('hash_sha256'), $otra->json('hash_sha256'));
        $this->assertSame($gen->json('id'), $otra->json('id'));
    }

    public function test_preset_completo_usa_encabezados_canonicos_y_no_altera_stock(): void
    {
        Http::fake();
        $this->validarPerfil();
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00', 'producto-100');
        $lote = $this->aprobarLote([1000]);
        TiendanubeProductoVariante::query()->where('id', 1000)->update(['stock' => 99]);

        $gen = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.exportaciones_csv', $lote['lote_id']), [
                'preset' => 'producto_completo',
            ])
            ->assertCreated();

        $csv = $this->leerCsv($gen->json('id'));
        $this->assertSame(TiendanubePrecioCsvColumnaCatalogo::encabezados(), $csv[0]);
        $this->assertCount(25, $csv[0]);
        $this->assertSame('99', $csv[1][9]);
        $this->assertSame(99, (int) TiendanubeProductoVariante::query()->find(1000)?->stock);
        Http::assertNothingSent();
    }

    public function test_quitar_promocion_exporta_guion(): void
    {
        Http::fake();
        $this->validarPerfil();
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', '80.00', '40.00', 'producto-100');
        $lote = $this->aprobarLote([1000], TiendanubePrecioMotorFixtures::regla(
            'temp',
            'precio_promocional_actual',
            'eliminar',
            'promocional'
        ));

        $gen = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.exportaciones_csv', $lote['lote_id']), [
                'preset' => 'solo_precios',
            ])
            ->assertCreated();

        $csv = $this->leerCsv($gen->json('id'));
        $this->assertSame('-', $csv[1][2]);
    }

    public function test_sku_con_ceros_y_columnas_personalizadas_respetan_orden(): void
    {
        Http::fake();
        $this->validarPerfil();
        $this->crearVariante(1000, 100, '00123', '100.00', null, '40.00', 'producto-100');
        $lote = $this->aprobarLote([1000]);

        $gen = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.exportaciones_csv', $lote['lote_id']), [
                'preset' => 'personalizado',
                'columnas' => ['Costo', 'SKU', 'Precio', 'Identificador de URL'],
            ])
            ->assertCreated();

        $csv = $this->leerCsv($gen->json('id'));
        $this->assertSame(['Identificador de URL', 'Precio', 'SKU', 'Costo'], $csv[0]);
        $this->assertSame('00123', $csv[1][2]);
    }

    public function test_sin_handle_bloquea_exportacion(): void
    {
        Http::fake();
        $this->validarPerfil();
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00', null);
        $lote = $this->aprobarLote([1000]);

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.exportaciones_csv', $lote['lote_id']), [
                'preset' => 'solo_precios',
            ])
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'identidad_faltante');
    }

    public function test_descarga_no_cambia_estado_del_lote(): void
    {
        Http::fake();
        $this->validarPerfil();
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00', 'producto-100');
        $lote = $this->aprobarLote([1000]);
        $gen = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.lotes.exportaciones_csv', $lote['lote_id']), [
                'preset' => 'solo_precios',
            ])
            ->assertCreated();

        $this->actingAs($this->user)
            ->get(route('tiendanube.precios.exportaciones_csv.descargar', $gen->json('id')))
            ->assertOk();

        $this->assertSame(TiendanubePrecioLote::ESTADO_APROBADO, TiendanubePrecioLote::query()->find($lote['lote_id'])?->estado);
        $this->assertSame(
            TiendanubePrecioCsvArtefacto::ESTADO_DESCARGADO,
            TiendanubePrecioCsvArtefacto::query()->find($gen->json('id'))?->estado
        );
        $this->assertSame(1, TiendanubePrecioLoteEvento::query()->where('tipo', TiendanubePrecioLoteEvento::TIPO_CSV_DESCARGADO)->count());

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.exportaciones_csv.declarar', $gen->json('id')))
            ->assertOk()
            ->assertJsonPath('estado', 'importacion_declarada');
    }

    public function test_reporte_revision_no_es_el_csv_nativo(): void
    {
        Http::fake();
        $this->validarPerfil();
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00', 'producto-100');
        $lote = $this->aprobarLote([1000]);

        $res = $this->actingAs($this->user)
            ->get(route('tiendanube.precios.lotes.reporte_revision', $lote['lote_id']))
            ->assertOk();

        $contenido = $res->streamedContent();
        $this->assertStringContainsString('NO_IMPORTABLE', $contenido);
        $this->assertStringContainsString('REPORTE INTERNO', $contenido);
        $this->assertStringNotContainsString('Identificador de URL', $contenido);
    }

    public function test_exporta_desde_catalogo_con_preset_completo(): void
    {
        Http::fake();
        $this->validarPerfil();
        $this->crearVariante(1000, 100, 'SKU-A', '100.00', null, '40.00', 'producto-100');

        $gen = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.catalogo.exportaciones_csv'), [
                'variante_ids' => [1000],
                'preset' => 'producto_completo',
            ])
            ->assertCreated();

        $csv = $this->leerCsv($gen->json('id'));
        $this->assertCount(25, $csv[0]);
        $this->assertSame('producto-100', $csv[1][0]);
        $this->assertSame('SKU-A', $csv[1][10]);
        Http::assertNothingSent();
    }

    private function validarPerfil(): void
    {
        $path = base_path('tests/fixtures/tiendanube/csv_plantilla_canonica.csv');
        $archivo = UploadedFile::fake()->createWithContent('plantilla.csv', (string) file_get_contents($path));

        $this->actingAs($this->user)
            ->post(route('tiendanube.precios.csv_perfil.validar'), [
                'plantilla' => $archivo,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('estado', 'validado');
    }

    /**
     * @param  list<int>  $varianteIds
     * @param  array<string, mixed>|null  $definicion
     * @return array<string, mixed>
     */
    private function aprobarLote(array $varianteIds, ?array $definicion = null): array
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
                'definicion' => $definicion ?? TiendanubePrecioMotorFixtures::regla(
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

    /**
     * @return list<list<string>>
     */
    private function leerCsv(int $artefactoId): array
    {
        $artefacto = TiendanubePrecioCsvArtefacto::query()->findOrFail($artefactoId);
        $abs = Storage::disk('local')->path($artefacto->archivo_path);
        $filas = [];
        $in = fopen($abs, 'r');
        $this->assertNotFalse($in);
        while (($fila = fgetcsv($in)) !== false) {
            $filas[] = $fila;
        }
        fclose($in);

        return $filas;
    }

    private function crearVariante(
        int $id,
        int $productoId,
        string $sku,
        string $precio,
        ?string $promo,
        ?string $costo,
        ?string $handle = '__auto__',
    ): void {
        TiendanubeProducto::query()->create([
            'id' => $productoId,
            'name' => ['es' => 'Producto '.$productoId],
            'handle' => $handle === null ? null : ['es' => $handle === '__auto__' ? 'producto-'.$productoId : $handle],
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

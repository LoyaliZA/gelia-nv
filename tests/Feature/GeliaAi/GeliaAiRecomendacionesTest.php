<?php

namespace Tests\Feature\GeliaAi;

use App\Models\FaseOlfativa;
use App\Models\NotaOlfativa;
use App\Models\Producto;
use App\Models\ProductoNotaOlfativa;
use App\Models\User;
use App\Services\GeliaAi\Skills\RecomendacionesPerfumeria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeliaAiRecomendacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'deepseek.api_token' => 'test-token',
            'deepseek.base_url' => 'https://chat.deepseek.test',
            'gelia_ai.recomendaciones.enabled' => true,
            'gelia_ai.recomendaciones.search_enabled' => true,
            'gelia_ai.recomendaciones.search_base_url' => 'https://search.deepseek.test',
            'gelia_ai.recomendaciones.fabricantes' => [],
        ]);
        Http::preventStrayRequests();
    }

    public function test_notas_locales_evitan_internet_y_tienen_prioridad_sobre_cache(): void
    {
        Http::fake(['search.deepseek.test/*' => Http::response($this->fuentes())]);
        $user = $this->usuario();
        $producto = $this->producto('CLOUD1');
        $skill = app(RecomendacionesPerfumeria::class);
        $this->assertSame('investigacion_web', $skill->ejecutar($user, ['skus' => ['CLOUD1']])['perfiles'][0]['origen']);

        $fase = FaseOlfativa::firstOrCreate(['codigo' => 'salida'], ['nombre' => 'Salida', 'orden' => 1, 'estado' => true]);
        $nota = NotaOlfativa::create(['nombre' => 'Pera', 'slug' => 'pera-prueba', 'estado' => true]);
        ProductoNotaOlfativa::create(['producto_id' => $producto->id, 'nota_olfativa_id' => $nota->id, 'fase_olfativa_id' => $fase->id, 'orden' => 1]);

        $res = $skill->ejecutar($user, ['skus' => ['CLOUD1']]);
        $this->assertSame('ficha_gelia', $res['perfiles'][0]['origen']);
        $this->assertSame(['Pera'], $res['perfiles'][0]['notas']['salida']);
        Http::assertSentCount(1);
    }

    public function test_busca_solo_si_falta_conocimiento_y_reutiliza_evidencia_sin_datos_privados(): void
    {
        Http::fake(['search.deepseek.test/*' => Http::response($this->fuentes())]);
        $user = $this->usuario();
        $this->producto('CLOUD1');
        $skill = app(RecomendacionesPerfumeria::class);
        $first = $skill->ejecutar($user, ['skus' => ['CLOUD1']]);
        $second = $skill->ejecutar($user, ['skus' => ['CLOUD1']]);

        $this->assertSame('investigacion_web', $first['perfiles'][0]['origen']);
        $this->assertSame('investigacion_en_cache', $second['perfiles'][0]['origen']);
        $this->assertSame('externa_no_validada', $second['perfiles'][0]['validacion']);
        $this->assertSame('https://fabricante.example/cloud', $second['perfiles'][0]['fuentes'][0]['url']);
        $this->assertStringContainsString('no se han comprobado existencias', $first['disponibilidad']);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($user) {
            $data = $request->data();
            $this->assertSame(['query', 'count', 'need_content'], array_keys($data));
            $this->assertLessThanOrEqual(128, mb_strlen($data['query']));
            $this->assertStringNotContainsString($user->email, json_encode($data));
            $this->assertStringNotContainsString('CLOUD1', $data['query']);

            return $request->url() === 'https://search.deepseek.test/v1/tools/search';
        });
    }

    public function test_sin_permiso_o_sku_inexistente_no_investiga(): void
    {
        Http::fake();
        $this->producto('CLOUD1');
        $skill = app(RecomendacionesPerfumeria::class);
        $this->assertFalse($skill->ejecutar(User::factory()->create(), ['skus' => ['CLOUD1']])['ok']);
        $res = $skill->ejecutar($this->usuario(), ['skus' => ['INVENTADO']]);
        $this->assertFalse($res['perfiles'][0]['ok']);
        Http::assertNothingSent();
    }

    public function test_compara_solo_productos_del_catalogo_y_limita_la_investigacion(): void
    {
        Http::fake(['search.deepseek.test/*' => Http::response($this->fuentes())]);
        $user = $this->usuario();
        foreach (['A', 'B', 'C', 'D'] as $sku) {
            $this->producto($sku);
        }
        $skill = app(RecomendacionesPerfumeria::class);
        $this->assertFalse($skill->ejecutar($user, ['skus' => []])['ok']);
        $this->assertFalse($skill->ejecutar($user, ['skus' => ['A', 'B', 'C', 'D']])['ok']);
        $res = $skill->ejecutar($user, ['skus' => ['A', 'B', 'C']]);
        $this->assertCount(3, $res['perfiles']);
        $fourth = $skill->ejecutar($user, ['skus' => ['D']]);
        $this->assertSame('limite_busquedas', $fourth['perfiles'][0]['error_code']);
        Http::assertSentCount(3);
    }

    public function test_error_del_buscador_no_se_guarda_como_conocimiento(): void
    {
        Http::fake(['search.deepseek.test/*' => Http::sequence()->push([], 429)->push($this->fuentes())]);
        $this->producto('CLOUD1');
        $user = $this->usuario();
        $skill = app(RecomendacionesPerfumeria::class);
        $res = $skill->ejecutar($user, ['skus' => ['CLOUD1']]);
        $this->assertFalse($res['perfiles'][0]['ok']);
        $this->assertSame('sin_informacion', $res['perfiles'][0]['origen']);
        $this->assertSame('investigacion_web', $skill->ejecutar($user, ['skus' => ['CLOUD1']])['perfiles'][0]['origen']);
        Http::assertSentCount(2);
    }

    public function test_busqueda_desactivada_no_hace_llamadas_y_fuentes_invalidas_se_descartan(): void
    {
        Http::fake(['search.deepseek.test/*' => Http::response(['results' => [
            ['url' => 'javascript:alert(1)', 'summary' => 'No usar'],
            ['url' => 'https://fabricante.example/empty', 'summary' => ''],
        ]])]);
        $this->producto('CLOUD1');
        $user = $this->usuario();
        $skill = app(RecomendacionesPerfumeria::class);
        config(['gelia_ai.recomendaciones.search_enabled' => false]);
        $this->assertSame('busqueda_desactivada', $skill->ejecutar($user, ['skus' => ['CLOUD1']])['perfiles'][0]['error_code']);
        Http::assertNothingSent();
        config(['gelia_ai.recomendaciones.search_enabled' => true]);
        $this->assertSame([], $skill->ejecutar($user, ['skus' => ['CLOUD1']])['perfiles'][0]['fuentes']);
    }

    public function test_chat_encadena_skill_y_conserva_el_modelo_configurado(): void
    {
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
        $user = $this->usuario();
        $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
        $this->producto('CLOUD1');
        config(['gelia_ai.model' => 'deepseek-v4-pro', 'gelia_ai.acceso_modo' => 'super_admin']);
        Http::fake([
            'search.deepseek.test/*' => Http::response($this->fuentes()),
            'chat.deepseek.test/*' => Http::sequence()
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
                    'id' => 'perfil1', 'type' => 'function', 'function' => ['name' => 'consultar_perfiles_perfumes', 'arguments' => '{"skus":["CLOUD1"]}'],
                ]]], 'finish_reason' => 'tool_calls']]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Es una opción del catálogo; disponibilidad por confirmar. [Fuente](https://fabricante.example/cloud)'], 'finish_reason' => 'stop']]]),
        ]);

        $this->actingAs($user)->postJson(route('gelia_ai.chat'), ['message' => 'Investiga las notas de Cloud y recomiéndame cuándo usarlo'])
            ->assertOk()->assertJsonPath('usage.gelia_tools.0', 'consultar_perfiles_perfumes');
        $chat = Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), 'chat.deepseek.test'))->values();
        $this->assertCount(2, $chat);
        $this->assertSame('deepseek-v4-pro', $chat[1][0]['model']);
        $this->assertStringContainsString('externa_no_validada', json_encode($chat[1][0]['messages']));
        $this->assertStringContainsString('CLOUD1', json_encode($chat[1][0]['messages']));
    }

    public function test_escanear_investiga_sin_confirmacion_y_recuerda_el_modo_del_chat(): void
    {
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
        $user = $this->usuario();
        $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
        config(['gelia_ai.acceso_modo' => 'super_admin']);
        $this->producto('CLOUD1')->update(['codigo_barras' => '812256028611']);
        Http::fake([
            'search.deepseek.test/*' => Http::response($this->fuentes()),
            'chat.deepseek.test/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Ficha para explicárselo al cliente.'], 'finish_reason' => 'stop']]]),
        ]);
        $res = $this->actingAs($user)->postJson(route('gelia_ai.chat'), ['message' => '812256028611', 'modo' => 'recomendaciones'])
            ->assertOk()->assertJsonPath('usage.gelia_mode', 'prefetch_recomendacion')->assertJsonPath('modo', 'recomendaciones');
        $convId = $res->json('conversacion_id');
        $this->actingAs($user)->postJson(route('gelia_ai.chat'), ['message' => '812256028611', 'conversacion_id' => $convId])
            ->assertOk()->assertJsonPath('usage.gelia_mode', 'prefetch_recomendacion');
        $this->actingAs($user)->getJson(route('gelia_ai.conversaciones.show', $convId))->assertOk()->assertJsonPath('modo', 'recomendaciones');

        $calls = Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), 'search.deepseek.test'));
        $this->assertCount(1, $calls);
        $chat = Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), 'chat.deepseek.test'))->values();
        $system = json_encode($chat[0][0]['messages'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('Ficha de asesoría obtenida automáticamente', $system);
        $this->assertStringContainsString('CLOUD1', $system);
        $this->assertStringContainsString('fabricante.example/cloud', $system);
        $this->assertStringNotContainsString('Consulta previa del servidor buscar_producto_inventario', $system);
    }

    public function test_codigo_inexistente_no_investiga_ni_reutiliza_el_producto_anterior(): void
    {
        Http::fake();
        $res = app(RecomendacionesPerfumeria::class)->porCodigo($this->usuario(), '812256028611');
        $this->assertSame('producto_no_encontrado', $res['error_code']);
        Http::assertNothingSent();
    }

    public function test_busqueda_expande_alias_y_prioriza_el_fabricante_para_la_variante(): void
    {
        config(['gelia_ai.recomendaciones.fabricantes' => [
            ['aliases' => ['Ariana Grande', 'Ariana G.', 'Ariana G'], 'nombre' => 'Ariana Grande', 'dominio' => 'arianagrandefragrances.com'],
        ]]);
        Http::fake(['search.deepseek.test/*' => Http::response(['results' => [
            ['title' => 'Cloud', 'url' => 'https://arianagrandefragrances.com/products/cloud', 'summary' => 'El menú menciona Cloud 2.0; esta ficha es de Cloud estándar.'],
            ['title' => 'Cloud 2.0', 'url' => 'https://comercio.example/cloud-2-0', 'summary' => 'Notas de otro perfume mezcladas.'],
            ['title' => 'Cloud 2.0 Intense', 'url' => 'https://arianagrandefragrances.com/products/cloud-2-0-intense', 'summary' => 'Notas de ejemplo para la variante 2.0.'],
        ]])]);
        $this->producto('CLOUD2')->update(['descripcion' => 'D ARIANA G. CLOUD 2.0 100ML. EDP']);
        $res = app(RecomendacionesPerfumeria::class)->ejecutar($this->usuario(), ['skus' => ['CLOUD2']]);
        $fuentes = $res['perfiles'][0]['fuentes'];
        $this->assertCount(1, $fuentes);
        $this->assertSame('fabricante', $fuentes[0]['tipo']);
        $this->assertStringContainsString('cloud-2-0-intense', $fuentes[0]['url']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r['query'], 'Ariana Grande') && str_contains($r['query'], 'CLOUD 2.0')
            && str_contains($r['query'], 'site:arianagrandefragrances.com') && ! str_contains($r['query'], '100ML'));
    }

    public function test_fuente_primaria_ausente_habilita_busqueda_general_automaticamente(): void
    {
        config(['gelia_ai.recomendaciones.fabricantes' => [
            ['aliases' => ['Ariana G.'], 'nombre' => 'Ariana Grande', 'dominio' => 'arianagrandefragrances.com'],
        ]]);
        Http::fake(['search.deepseek.test/*' => Http::sequence()->push(['results' => []])->push($this->fuentes())]);
        $this->producto('CLOUD1');
        $res = app(RecomendacionesPerfumeria::class)->ejecutar($this->usuario(), ['skus' => ['CLOUD1']]);
        $this->assertSame('referencia_externa', $res['perfiles'][0]['fuentes'][0]['tipo']);
        Http::assertSentCount(2);
    }

    private function usuario(): User
    {
        Permission::findOrCreate('almacenes.productos.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('almacenes.productos.ver');

        return $user;
    }

    private function producto(string $sku): Producto
    {
        return Producto::create(['sku' => $sku, 'folio' => 900000 + Producto::count(), 'descripcion' => 'D ARIANA G. CLOUD 100ML EDP', 'activo' => true]);
    }

    private function fuentes(): array
    {
        return ['results' => [['title' => 'Cloud EDP', 'url' => 'https://fabricante.example/cloud', 'summary' => 'Cloud EDP: pera y lavanda. Datos ficticios para pruebas.']]];
    }
}

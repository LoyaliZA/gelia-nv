<?php

namespace Tests\Feature\GeliaAi;

use App\Jobs\Almacenes\ImportarAlmacenCatalogoJob;
use App\Models\Almacen;
use App\Models\GeliaAiConversacion;
use App\Models\GeliaAiMensaje;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\GeliaAi\Acciones\PropuestasAccionGeliaAi;
use App\Services\GeliaAi\GeliaAiArchivoService;
use App\Services\GeliaAi\InspeccionarArchivoGeliaAiService;
use App\Services\GeliaAi\Tools\BuscarProductoInventarioTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeliaAiAgenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
        Role::findOrCreate('Super Admin', 'web');
        foreach (['almacenes.costos.importar', 'almacenes.inventarios.importar', 'listados.ver', 'almacenes.productos.ver'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        config([
            'deepseek.api_token' => 'test-token',
            'deepseek.base_url' => 'https://api.deepseek.test',
            'gelia_ai.acceso_modo' => 'super_admin',
            'gelia_ai.model' => 'deepseek-chat',
        ]);
        Storage::fake('local');
    }

    public function test_pregunta_de_solicitudes_o_creditos_no_busca_productos(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->texto('Aún no puedo consultar solicitudes.'))
            ->push($this->texto('Aún no puedo consultar créditos.'))]);

        foreach (['¿Cuántas solicitudes de crédito están pendientes esta semana?', 'Revisa el estado de la solicitud 12345678'] as $pregunta) {
            $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => $pregunta])
                ->assertOk()
                ->assertJsonPath('usage.gelia_mode', 'solicitudes_creditos')
                ->assertJsonPath('usage.gelia_tools', []);
        }

        foreach ($this->peticiones() as $data) {
            $this->assertSame(['consultar_ayuda_modulo'], $this->nombresTools($data));
            $sistema = $this->textoSistema($data);
            $this->assertStringNotContainsString('Consulta previa', $sistema);
            $this->assertStringContainsString('todavía no puede consultar', $sistema);
        }
    }

    public function test_si_el_modelo_intenta_buscar_productos_en_solicitudes_se_rechaza(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['c1', 'buscar_producto_inventario', ['q' => 'solicitud']]]))
            ->push($this->texto('No puedo consultar solicitudes todavía.'))]);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => 'Analiza el crédito del cliente'])->assertOk();

        $tool = $this->mensajesTool($this->peticiones()[1])['c1'];
        $this->assertSame('herramienta_desconocida', $tool['error_code']);
        $this->assertStringNotContainsString('MSKY100', json_encode($this->peticiones()[1]));
    }

    public function test_cambio_de_tema_no_arrastra_inventario(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->texto('Hay 10 disponibles.'))
            ->push($this->tools([['a1', 'consultar_ayuda_modulo', ['modulo' => 'listados']]]))
            ->push($this->texto('Para generar un listado sube existencias...'))
            ->push($this->texto('Aún no puedo consultar créditos.'))
            ->push($this->texto('¡Hola!'))]);

        $convId = $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Cuántos MSKY100 hay?'])
            ->assertOk()
            ->assertJsonPath('usage.gelia_mode', 'prefetch_inventario')
            ->json('conversacion_id');
        $this->assertSame('MSKY100', GeliaAiConversacion::find($convId)->contexto['productos'][0]['sku']);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), [
            'message' => '¿Cómo se genera un listado de resurtido?',
            'conversacion_id' => $convId,
        ])->assertOk()
            ->assertJsonPath('usage.gelia_mode', 'general')
            ->assertJsonPath('usage.gelia_tools', ['consultar_ayuda_modulo']);
        $this->assertSame('ayuda:listados', GeliaAiConversacion::find($convId)->contexto['tema']);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), [
            'message' => '¿Y cuántos créditos tiene pendientes el cliente?',
            'conversacion_id' => $convId,
        ])->assertOk();

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => 'hola'])->assertOk();

        $peticiones = $this->peticiones();
        $this->assertStringNotContainsString('Consulta previa', $this->textoSistema($peticiones[1]));
        $this->assertStringNotContainsString('productos_identificados', $this->textoSistema($peticiones[3]));
        $this->assertStringNotContainsString('MSKY100', json_encode($peticiones[4]));
    }

    public function test_continuacion_conserva_producto_almacen_y_filtros(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['b1', 'buscar_producto_inventario', ['q' => 'armaf mandarin sky', 'almacen' => 'CEDIS']]]))
            ->push($this->texto('Hay 10 disponibles en CEDIS.'))
            ->push($this->texto('Hay 2 apartados en CEDIS.'))]);

        $convId = $this->actingAs($admin)->postJson(route('gelia_ai.chat'), [
            'message' => '¿Cuánto hay del Armaf Mandarin Sky en el CEDIS?',
        ])->assertOk()->json('conversacion_id');

        $resultado = $this->mensajesTool($this->peticiones()[1])['b1'];
        $this->assertSame('MSKY100', $resultado['items'][0]['sku']);
        $this->assertSame(10.0, (float) $resultado['items'][0]['stock']['total']['d']);
        $this->assertSame('CEDIS', $resultado['almacen_filtro']['codigo']);

        $ctx = GeliaAiConversacion::find($convId)->contexto;
        $this->assertSame('MSKY100', $ctx['productos'][0]['sku']);
        $this->assertSame('CEDIS', $ctx['almacen']['codigo']);
        $this->assertSame(['q' => 'armaf mandarin sky', 'almacen' => 'CEDIS'], $ctx['filtros']);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), [
            'message' => '¿y cuántos están apartados?',
            'conversacion_id' => $convId,
            'messages' => [['role' => 'assistant', 'content' => 'TEXTO_CLIENTE_NO_CONFIABLE']],
        ])->assertOk();

        $segunda = $this->peticiones()[2];
        $sistema = $this->textoSistema($segunda);
        $this->assertStringContainsString('MSKY100', $sistema);
        $this->assertStringContainsString('CEDIS', $sistema);
        $this->assertStringContainsString('Armaf Mandarin Sky en el CEDIS', json_encode($segunda, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('TEXTO_CLIENTE_NO_CONFIABLE', json_encode($segunda));
    }

    public function test_producto_a_en_cedis_y_luego_b_sin_resultados_no_conserva_a_ni_cedis(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['b1', 'buscar_producto_inventario', ['q' => 'MSKY100', 'almacen' => 'CEDIS']]]))
            ->push($this->texto('Hay 10 disponibles en CEDIS.'))
            ->push($this->tools([['b2', 'buscar_producto_inventario', ['q' => 'ZZZ Fantasma']]]))
            ->push($this->texto('No encontré ese producto.'))
            ->push($this->texto('¿Qué otro producto busco?'))]);

        $convId = $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Cuánto hay del Mandarin Sky en el CEDIS?'])
            ->assertOk()->json('conversacion_id');
        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Y del ZZZ Fantasma?', 'conversacion_id' => $convId])
            ->assertOk();

        $ctx = GeliaAiConversacion::find($convId)->contexto;
        $this->assertSame([], $ctx['productos']);
        $this->assertSame('ninguna', $ctx['coincidencia']);
        $this->assertNull($ctx['almacen']);
        $this->assertSame(['q' => 'ZZZ Fantasma'], $ctx['filtros']);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿y cuánto cuesta?', 'conversacion_id' => $convId])->assertOk();
        $estado = $this->estadoGuardado($this->peticiones()[4]);
        $this->assertStringNotContainsString('MSKY100', $estado);
        $this->assertStringNotContainsString('CEDIS', $estado);
    }

    public function test_consulta_de_almacen_seguida_de_consulta_global_retira_el_almacen(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['b1', 'buscar_producto_inventario', ['q' => 'MSKY100', 'almacen' => 'CEDIS']]]))
            ->push($this->texto('En CEDIS hay 10.'))
            ->push($this->tools([['b2', 'buscar_producto_inventario', ['q' => 'MSKY100']]]))
            ->push($this->texto('En total hay 10.'))]);

        $convId = $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Cuánto Mandarin Sky hay en CEDIS?'])
            ->assertOk()->json('conversacion_id');
        $this->assertSame('CEDIS', GeliaAiConversacion::find($convId)->contexto['almacen']['codigo']);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Y en todos los almacenes?', 'conversacion_id' => $convId])
            ->assertOk();

        $ctx = GeliaAiConversacion::find($convId)->contexto;
        $this->assertNull($ctx['almacen']);
        $this->assertSame(['q' => 'MSKY100'], $ctx['filtros']);
        $this->assertSame('MSKY100', $ctx['productos'][0]['sku']);
    }

    public function test_precarga_por_codigo_respeta_almacen_pedido_y_heredado(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->texto('En CEDIS hay 10.'))
            ->push($this->texto('En CEDIS hay 2 apartados.'))
            ->push($this->texto('No encontré ese código.'))]);

        $convId = $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Cuántos MSKY100 hay en CEDIS?'])
            ->assertOk()
            ->assertJsonPath('usage.gelia_mode', 'prefetch_inventario')
            ->json('conversacion_id');
        $this->assertStringContainsString('buscar_producto_inventario(q=MSKY100, almacen=CEDIS)', $this->textoSistema($this->peticiones()[0]));
        $this->assertSame('CEDIS', GeliaAiConversacion::find($convId)->contexto['almacen']['codigo']);

        // Continuación con el mismo producto: hereda el almacén.
        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Y cuántos MSKY100 están apartados?', 'conversacion_id' => $convId])
            ->assertOk()->assertJsonPath('usage.gelia_mode', 'prefetch_inventario');
        $this->assertStringContainsString('buscar_producto_inventario(q=MSKY100, almacen=CEDIS)', $this->textoSistema($this->peticiones()[1]));

        // Otro producto sin almacén pedido: consulta global, no hereda CEDIS.
        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Cuántos ZX9000 hay?', 'conversacion_id' => $convId])
            ->assertOk();
        $this->assertStringContainsString('buscar_producto_inventario(q=ZX9000):', $this->textoSistema($this->peticiones()[2]));
        $ctx = GeliaAiConversacion::find($convId)->contexto;
        $this->assertNull($ctx['almacen']);
        $this->assertSame([], $ctx['productos']);
    }

    public function test_precarga_con_varios_almacenes_nombrados_deja_que_la_ia_pregunte(): void
    {
        $admin = $this->adminConInventario();
        Almacen::create(['codigo' => 'TIENDA1', 'nombre' => 'Tienda Uno', 'activo' => true, 'sucursal_id' => Almacen::where('codigo', 'CEDIS')->value('sucursal_id')]);
        Http::fake(['api.deepseek.test/*' => Http::response($this->texto('¿En cuál de los dos almacenes?'))]);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Cuántos MSKY100 hay en CEDIS y en TIENDA1?'])
            ->assertOk()
            ->assertJsonPath('usage.gelia_mode', 'general');
        $this->assertStringNotContainsString('Consulta previa', $this->textoSistema($this->peticiones()[0]));
    }

    public function test_tarea_multipaso_usa_varias_herramientas(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['l1', 'listar_almacenes', ['filtro' => 'central']]]))
            ->push($this->tools([['b1', 'buscar_producto_inventario', ['q' => 'mandarin sky', 'almacen' => 'CEDIS']]]))
            ->push($this->texto('En CEDIS Central hay 10 disponibles.'))]);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), [
            'message' => 'Quiero saber el stock del Mandarin Sky en el almacén central',
        ])->assertOk()
            ->assertJsonPath('reply', 'En CEDIS Central hay 10 disponibles.')
            ->assertJsonPath('usage.gelia_acc.rounds', 3)
            ->assertJsonPath('usage.gelia_tools', ['listar_almacenes', 'buscar_producto_inventario']);

        [$p1, $p2, $p3] = $this->peticiones();
        $this->assertSame('auto', $p2['tool_choice']);
        $this->assertContains('buscar_producto_inventario', $this->nombresTools($p2));
        $this->assertSame('CEDIS', $this->mensajesTool($p2)['l1']['items'][0]['codigo']);
        $this->assertSame(['l1', 'b1'], array_keys($this->mensajesTool($p3)));
    }

    public function test_toda_llamada_de_herramienta_recibe_respuesta_valida(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([
                ['c1', 'herramienta_inexistente', []],
                ['c2', 'buscar_producto_inventario', '{"q": mal'],
                ['c3', 'buscar_producto_inventario', ['limit' => 'muchos']],
                ['c4', 'consultar_ayuda_modulo', ['modulo' => 'listados']],
                ['c5', 'consultar_ayuda_modulo', ['modulo' => 'inventario']],
            ]))
            ->push($this->texto('Listo.'))]);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => 'Explícame listados e inventario'])->assertOk();

        $p2 = $this->peticiones()[1];
        $tools = $this->mensajesTool($p2);
        $this->assertSame(['c1', 'c2', 'c3', 'c4', 'c5'], array_keys($tools));
        foreach ($tools as $r) {
            $this->assertIsArray($r);
            $this->assertArrayHasKey('ok', $r);
        }
        $this->assertSame('herramienta_desconocida', $tools['c1']['error_code']);
        $this->assertSame('argumentos_invalidos', $tools['c2']['error_code']);
        $this->assertSame('argumentos_invalidos', $tools['c3']['error_code']);
        $this->assertTrue($tools['c4']['ok']);
        $this->assertSame('limite_herramientas', $tools['c5']['error_code']);
        $this->assertSame('none', $p2['tool_choice']);
    }

    public function test_sin_permiso_de_inventario_no_se_ofrece_ni_ejecuta(): void
    {
        config(['gelia_ai.acceso_modo' => 'general']);
        $this->adminConInventario();
        $user = User::factory()->create();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['b1', 'buscar_producto_inventario', ['q' => 'mandarin sky']]]))
            ->push($this->texto('No tienes permiso para consultar inventario.'))]);

        $this->actingAs($user)->postJson(route('gelia_ai.chat'), ['message' => 'stock del mandarin sky'])->assertOk();

        [$p1, $p2] = $this->peticiones();
        $this->assertSame(['consultar_ayuda_modulo'], $this->nombresTools($p1));
        $this->assertStringContainsString('consultar inventario', $this->textoSistema($p1));
        $this->assertSame('herramienta_desconocida', $this->mensajesTool($p2)['b1']['error_code']);
        $this->assertStringNotContainsString('MSKY100', json_encode($p2));
    }

    public function test_importacion_a_almacen_no_operable_se_rechaza(): void
    {
        Queue::fake();
        $admin = $this->adminConInventario();
        $ajeno = Almacen::create(['codigo' => 'AJENO', 'nombre' => 'Ajeno', 'activo' => true, 'sucursal_id' => Sucursal::factory()->create()->id]);
        $meta = $this->subir($admin, 'costos.csv', "sku,costo\nMSKY100,9.9\n");

        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['i1', 'proponer_importar_costos', ['file_id' => $meta['file_id'], 'almacen' => 'AJENO']]]))
            ->push($this->texto('No tienes acceso a ese almacén.'))]);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), [
            'message' => 'Importa estos costos al almacén AJENO',
            'file_ids' => [$meta['file_id']],
        ])->assertOk()->assertJsonPath('propuesta_accion', null);
        $this->assertSame('sin_permiso_almacen', $this->mensajesTool($this->peticiones()[1])['i1']['error_code']);

        // Aunque exista una propuesta guardada con ese almacén, al ejecutar se vuelve a validar el acceso.
        $propuesta = app(PropuestasAccionGeliaAi::class)->registrar($admin, 'importar_costos', [
            'file_id' => $meta['file_id'], 'almacen_id' => $ajeno->id, 'mapping' => ['sku' => 'sku', 'costo' => 'costo'],
        ], 'x');
        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), [
            'propuesta_id' => $propuesta['id'],
            'confirmado' => true,
        ])->assertForbidden()->assertJsonPath('estado_propuesta', 'fallida');

        Queue::assertNothingPushed();
    }

    public function test_propuesta_de_otra_persona_no_se_puede_confirmar(): void
    {
        Queue::fake();
        $duena = $this->adminConInventario();
        $otra = User::factory()->create();
        $otra->assignRole('Super Admin');
        $propuesta = app(PropuestasAccionGeliaAi::class)->registrar($duena, 'importar_costos', ['file_id' => (string) Str::uuid()], 'x');

        $this->actingAs($otra)->postJson(route('gelia_ai.acciones.ejecutar'), [
            'propuesta_id' => $propuesta['id'],
            'confirmado' => true,
        ])->assertNotFound()
            ->assertJsonPath('estado_propuesta', 'inexistente')
            ->assertJsonPath('message', 'No encontramos esta propuesta en tu cuenta. Pide a GELIA que la prepare de nuevo.');

        // La propuesta sigue intacta para su dueña.
        $this->assertNotNull(app(PropuestasAccionGeliaAi::class)->pendiente($duena, $propuesta['id']));
        Queue::assertNothingPushed();
    }

    public function test_error_del_proveedor_responde_honesto_y_no_persiste(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Cómo funcionan los listados?'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'El servicio de IA no está disponible por ahora. Intenta de nuevo en unos momentos.');

        $this->assertSame(0, GeliaAiMensaje::count());
    }

    public function test_llamadas_repetidas_sin_progreso_fuerzan_cierre(): void
    {
        $admin = $this->adminConInventario();
        $llamada = ['q' => 'MSKY100'];
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['r1', 'buscar_producto_inventario', $llamada]]))
            ->push($this->tools([['r2', 'buscar_producto_inventario', $llamada]]))
            ->push($this->texto('Hay 10 disponibles.'))]);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => 'stock del mandarin sky por favor'])
            ->assertOk()
            ->assertJsonPath('usage.gelia_tools', ['buscar_producto_inventario']);

        [, $p2, $p3] = $this->peticiones();
        $this->assertSame('auto', $p2['tool_choice']);
        $this->assertSame('llamada_repetida', $this->mensajesTool($p3)['r2']['error_code']);
        $this->assertSame('none', $p3['tool_choice']);
    }

    public function test_limite_de_pasos_termina_con_explicacion_honesta(): void
    {
        config(['gelia_ai.max_steps' => 2]);
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['b1', 'buscar_producto_inventario', ['q' => 'mandarin']]]))
            ->push($this->tools([['b2', 'listar_almacenes', []]]))]);

        $reply = $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => 'stock del mandarin en todos lados'])
            ->assertOk()
            ->assertJsonPath('usage.gelia_acc.stop', 'vacia')
            ->assertJsonPath('incompleta', true)
            ->json('reply');

        $this->assertStringContainsString('no devolvió una respuesta utilizable', $reply);
        $this->assertStringContainsString('Búsqueda de inventario: completada', $reply);
        $this->assertStringNotContainsString('buscar_producto_inventario', $reply);
        $this->assertSame('none', $this->peticiones()[1]['tool_choice']);
    }

    public function test_respuesta_truncada_se_indica(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::response($this->texto('Los listados sirven para', 'length'))]);

        $reply = $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => 'Explícame los listados a detalle'])
            ->assertOk()->json('reply');

        $this->assertStringContainsString('se recortó por longitud', $reply);
    }

    public function test_falla_interna_de_herramienta_se_reporta_sin_inventar(): void
    {
        $admin = $this->adminConInventario();
        $real = app(BuscarProductoInventarioTool::class);
        $this->mock(BuscarProductoInventarioTool::class, function ($m) use ($real) {
            $m->shouldReceive('name')->andReturn($real->name());
            $m->shouldReceive('schema')->andReturn($real->schema());
            $m->shouldReceive('disponiblePara')->andReturn(true);
            $m->shouldReceive('ejecutar')->andThrow(new RuntimeException('db caída'));
        });
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['b1', 'buscar_producto_inventario', ['q' => 'mandarin']]]))
            ->push($this->texto('La consulta de inventario falló.'))]);

        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => 'stock del mandarin sky'])->assertOk();

        $r = $this->mensajesTool($this->peticiones()[1])['b1'];
        $this->assertFalse($r['ok']);
        $this->assertSame('error_interno', $r['error_code']);
        $this->assertStringNotContainsString('db caída', json_encode($r));
    }

    public function test_generar_listado_propone_confirma_y_devuelve_archivo_correcto(): void
    {
        $admin = $this->adminConInventario();
        $exist = $this->subir($admin, 'existencias.csv', implode("\n", [
            ',Almacen,,Folio,Código,Descripcion,Marca,,,,Existencia',
            ',CEDIS,,1001,0001234,PERFUME UNO,MARCA,,,,5',
            ',CEDIS,,1002,5678,PERFUME DOS,MARCA,,,,3',
        ])."\n");
        $precios = $this->subir($admin, 'precios.csv', implode("\n", [
            ',CODIGO_DEL_PRODUCTO,,,,,,PRECIO',
            ',1234,,,,,,100',
            ',5678,,,,,,200',
        ])."\n");

        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['g1', 'proponer_generar_listado', ['tipo_lista' => 'Resurtido']]]))
            ->push($this->texto('Siguen disponibles tus dos archivos.'))]);

        $res = $this->actingAs($admin)->postJson(route('gelia_ai.chat'), [
            'message' => 'Genera la lista de resurtido',
            'file_ids' => [$exist['file_id'], $precios['file_id']],
        ])->assertOk()
            ->assertJsonPath('propuesta_accion.accion', 'generar_listado')
            ->assertJsonPath('propuesta_accion.puede', true)
            ->assertJsonPath('propuesta_accion.detalle.confirmar', 'Generar listado')
            ->assertJsonPath('propuesta_accion.detalle.archivos.0.rol', 'Existencias')
            ->assertJsonMissingPath('propuesta_accion.payload')
            ->assertJsonCount(2, 'archivos');
        Http::assertSentCount(1);
        $this->assertStringContainsString('aún no se ha ejecutado', $res->json('reply'));
        $this->assertStringContainsString('existencias=«existencias.csv»', $res->json('propuesta_accion.resumen_corto'));
        $this->assertStringContainsString('precios=«precios.csv»', $res->json('propuesta_accion.resumen_corto'));
        $convId = $res->json('conversacion_id');
        $this->assertSame('esperando_confirmacion', GeliaAiConversacion::find($convId)->contexto['tarea_pendiente']['estado']);

        // Siguiente turno sin reenviar archivos: siguen disponibles desde el servidor.
        $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => '¿Qué archivos tengo?', 'conversacion_id' => $convId])->assertOk();
        $sistema = $this->textoSistema($this->peticiones()[1]);
        $this->assertStringContainsString($exist['file_id'], $sistema);
        $this->assertStringContainsString($precios['file_id'], $sistema);
        $this->assertStringNotContainsString('PERFUME UNO', json_encode($this->peticiones()));

        $ejec = $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), [
            'propuesta_id' => $res->json('propuesta_accion.id'),
            'conversacion_id' => $convId,
            'confirmado' => true,
        ])->assertOk()
            ->assertJsonPath('accion', 'generar_listado')
            ->assertJsonPath('reporte.estado', 'completado')
            ->assertJsonPath('reporte.conteos.ok', 2)
            ->assertJsonPath('reporte.conteos.error', 0);

        $temp = storage_path('app/temp/'.$ejec->json('reporte.temp_file'));
        $this->assertFileExists($temp);
        $this->assertStringContainsString('temp_file='.$ejec->json('reporte.temp_file'), $ejec->json('reporte.download_url'));
        $filas = (new FastExcel)->import($temp)->keyBy('SKU');
        @unlink($temp);
        $this->assertEquals(100, $filas[1234]['PG']);
        $this->assertEquals(200, $filas[5678]['PG']);
        $this->assertEquals(5, $filas[1234]['Existencia']);

        $conv = GeliaAiConversacion::find($convId);
        $this->assertNull($conv->contexto['tarea_pendiente']);
        $this->assertSame('generar_listado', $conv->contexto['ultima_accion']['accion']);
        $this->assertStringContainsString('generado', $conv->mensajes()->get()->last()->content);

        $this->actingAs($admin)->postJson(route('gelia_ai.acciones.ejecutar'), [
            'propuesta_id' => $res->json('propuesta_accion.id'),
            'confirmado' => true,
        ])->assertStatus(409)->assertJsonPath('estado_propuesta', 'ejecutada');
    }

    public function test_listado_sin_existencias_pide_solo_el_archivo_faltante(): void
    {
        $admin = $this->adminConInventario();
        Http::fake(['api.deepseek.test/*' => Http::sequence()
            ->push($this->tools([['g1', 'proponer_generar_listado', ['tipo_lista' => 'meli']]]))
            ->push($this->texto('Adjunta el archivo de existencias.'))]);

        $convId = $this->actingAs($admin)->postJson(route('gelia_ai.chat'), ['message' => 'Genera la lista MELI'])
            ->assertOk()
            ->assertJsonPath('propuesta_accion', null)
            ->json('conversacion_id');

        $r = $this->mensajesTool($this->peticiones()[1])['g1'];
        $this->assertSame('faltan_datos', $r['error_code']);
        $this->assertSame(['archivo_existencias'], array_column($r['faltantes'], 'dato'));
        $this->assertSame('faltan_datos', GeliaAiConversacion::find($convId)->contexto['tarea_pendiente']['estado']);
    }

    private function adminConInventario(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $sucursal = Sucursal::factory()->create();
        $admin->concederAccesoSucursal($sucursal, true);
        $almacen = Almacen::create(['codigo' => 'CEDIS', 'nombre' => 'CEDIS Central', 'activo' => true, 'sucursal_id' => $sucursal->id]);
        $producto = Producto::create([
            'uuid' => (string) Str::uuid(),
            'sku' => 'MSKY100',
            'descripcion' => 'ARMAF ODYSSEY MANDARIN SKY 100ML EDP',
            'folio' => 990001,
            'codigo_barras' => '6290000000001',
            'activo' => true,
        ]);
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'existencia' => 12, 'apartado' => 2]);

        return $admin;
    }

    /** @return array<string, mixed> */
    private function subir(User $user, string $nombre, string $contenido): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gelia');
        file_put_contents($tmp, $contenido);

        return app(GeliaAiArchivoService::class)->guardarUno(
            $user,
            new UploadedFile($tmp, $nombre, 'text/csv', null, true),
            app(InspeccionarArchivoGeliaAiService::class),
        );
    }

    /** @return array<string, mixed> */
    private function texto(string $contenido, string $finish = 'stop'): array
    {
        return [
            'choices' => [['finish_reason' => $finish, 'message' => ['role' => 'assistant', 'content' => $contenido]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'total_tokens' => 120],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: array<string, mixed>|string}>  $calls
     * @return array<string, mixed>
     */
    private function tools(array $calls): array
    {
        return [
            'choices' => [[
                'finish_reason' => 'tool_calls',
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => array_map(fn ($c) => [
                        'id' => $c[0],
                        'type' => 'function',
                        'function' => ['name' => $c[1], 'arguments' => is_string($c[2]) ? $c[2] : json_encode((object) $c[2])],
                    ], $calls),
                ],
            ]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'total_tokens' => 120],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function peticiones(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_contains($r->url(), 'api.deepseek.test'))
            ->map(fn (Request $r) => $r->data())
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function nombresTools(array $data): array
    {
        return array_map(fn ($t) => $t['function']['name'], $data['tools'] ?? []);
    }

    private function textoSistema(array $data): string
    {
        return implode("\n", array_map(
            fn ($m) => (string) $m['content'],
            array_filter($data['messages'], fn ($m) => $m['role'] === 'system')
        ));
    }

    private function estadoGuardado(array $data): string
    {
        return (string) collect($data['messages'])
            ->first(fn ($m) => $m['role'] === 'system' && str_starts_with((string) $m['content'], 'Estado guardado'))['content'] ?? '';
    }

    /** @return array<string, mixed> tool_call_id => resultado decodificado */
    private function mensajesTool(array $data): array
    {
        $out = [];
        foreach ($data['messages'] as $m) {
            if ($m['role'] === 'tool') {
                $out[$m['tool_call_id']] = json_decode($m['content'], true);
            }
        }

        return $out;
    }
}

<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BusquedaRemisionDelegadoTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshIsolatedDatabase;
    }

    private User $delegado;
    private User $auxiliar;
    private PedidoBma $a;
    private PedidoBma $b;
    private PedidoBma $c;
    private CatalogoPaqueteriaPedido $paqueteria;
    private array $estatus = [];
    private int $pedidoNumero = 0;

    public function refreshDatabase(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->refreshIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Storage::fake('local');
        Storage::fake('public');
        config(['filesystems.default' => 'local']);
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->delegado = User::factory()->create();
        $this->auxiliar = User::factory()->create();
        foreach (['control_pedidos.delegado', 'control_pedidos.delegado.importar'] as $permiso) {
            $this->delegado->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $this->auxiliar->givePermissionTo(Permission::findOrCreate('control_pedidos.auditar', 'web'));
        foreach (['PENDIENTE_AUXILIAR', 'PENDIENTE_DE_GUIA', 'PENDIENTE_DE_ENVIO', 'EN_CEDIS', 'ENVIADO', 'BORRADOR'] as $fase) {
            $this->estatus[$fase] = CatalogoEstatusPedido::firstOrCreate(['fase_ciclo' => $fase], [
                'codigo_interno' => 'Q7_'.$fase, 'nombre_visual' => $fase, 'color_hex' => '#64748B', 'activo' => true, 'orden' => 1,
            ])->id;
        }
        $this->paqueteria = CatalogoPaqueteriaPedido::create(['nombre' => 'Comercial sintética Q7', 'categoria' => 'comercial', 'activo' => true]);
        $this->a = $this->pedido(['folio' => 'PBMA-2026-00077', 'folio_remision' => 'PED-772', 'numero_remision' => null, 'catalogo_estatus_pedido_id' => $this->estatus['PENDIENTE_AUXILIAR']]);
        $this->actingAs($this->auxiliar)->put(route('control_pedidos.auditar.numero_remision.update', $this->a), ['numero_remision' => 'REM-00935'])
            ->assertRedirect()->assertSessionHas('success');
        $this->a->refresh()->update(['catalogo_estatus_pedido_id' => $this->estatus['PENDIENTE_DE_GUIA']]);
        $this->b = $this->pedido(['folio' => 'PBMA-2026-00078', 'folio_remision' => 'REM-00935', 'numero_remision' => 'REM-00888']);
        // C matches both searches but is outside the existing eligible phases.
        $this->c = $this->pedido(['folio_remision' => 'REM-00935', 'numero_remision' => 'REM-00935', 'catalogo_estatus_pedido_id' => $this->estatus['BORRADOR']]);
        $this->actingAs($this->delegado);
    }

    public function test_numero_remision_encuentra_a_y_conserva_el_criterio_en_respuesta(): void
    {
        $respuesta = $this->listado('numero_remision', 'REM-00935');
        $this->assertSame([$this->a->id], array_column($respuesta['pedidos']['data'], 'id'));
        $this->assertSame('numero_remision', $respuesta['filtros']['q_campo']);
        $this->assertSame('REM-00935', $respuesta['pedidos']['data'][0]['numero_remision']);
        $this->assertSame('PED-772', $respuesta['pedidos']['data'][0]['folio_remision']);
    }

    public function test_folio_documental_conserva_b_y_general_encuentra_a_y_b_sin_c(): void
    {
        $this->assertSame([$this->b->id], $this->ids('folio_remision', 'REM-00935'));
        $ids = $this->ids('general', 'REM-00935');
        sort($ids);
        $this->assertSame([$this->a->id, $this->b->id], $ids);
        $this->assertSame([$this->a->id], $this->ids('folio_remision', 'PED-772'));
    }

    public function test_coincidencia_parcial_trim_y_normalizacion_del_campo_conservan_ceros(): void
    {
        $this->assertSame([$this->a->id], $this->ids(' NUMERO_REMISION ', ' 00935 '));
        $this->assertSame('REM-00935', $this->a->fresh()->numero_remision);
        $this->assertSame([$this->a->id], $this->ids('folio', '00077'));
        $this->assertSame([], $this->ids('numero_remision', 'PED-772'));
    }

    public function test_sin_numero_remision_siguen_funcionando_los_criterios_anteriores(): void
    {
        $sin = $this->pedido(['folio' => 'Q7-SIN-REMISION', 'folio_remision' => 'DOC-SIN-NUMERO', 'numero_remision' => null]);
        $this->assertSame([$sin->id], $this->ids('folio', 'Q7-SIN-REMISION'));
        $this->assertSame([$sin->id], $this->ids('folio_remision', 'DOC-SIN-NUMERO'));
        $this->assertSame([$sin->id], $this->ids('general', 'DOC-SIN-NUMERO'));
        $this->assertSame([], $this->ids('numero_remision', 'DOC-SIN-NUMERO'));
    }

    public function test_coincidir_no_elude_permiso_fase_pago_documento_o_guia_cliente(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('control_pedidos.delegado.listado', ['q_campo' => 'numero_remision', 'q' => 'REM-00935']))->assertForbidden();
        $this->actingAs($this->delegado);
        $this->pedido(['numero_remision' => 'REM-00935', 'pago_validado_at' => null]);
        $sinDocumento = $this->pedido(['numero_remision' => 'REM-00935']);
        $sinDocumento->documentos()->delete();
        $this->pedido(['numero_remision' => 'REM-00935', 'cliente_proporciona_guia' => true]);
        $this->assertSame([$this->a->id], $this->ids('numero_remision', 'REM-00935', ['tab' => 'TODOS']));
        $this->assertNotContains($this->c->id, $this->ids('general', 'REM-00935', ['tab' => 'TODOS']));
    }

    public function test_asignar_y_actualizar_por_id_no_modifica_b_ni_identidades(): void
    {
        $seleccionado = $this->ids('numero_remision', 'REM-00935');
        $this->assertSame([$this->a->id], $seleccionado);
        $antesB = $this->b->fresh()->getAttributes();
        $this->post(route('control_pedidos.delegado.asignar_guia', $seleccionado[0]), ['numero_rastreo' => 'GUIA-Q7-001'])->assertRedirect()->assertSessionHas('success');
        $this->post(route('control_pedidos.delegado.actualizar_guia', $seleccionado[0]), ['numero_rastreo' => 'GUIA-Q7-002'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('GUIA-Q7-002', $this->a->fresh()->numero_rastreo);
        $this->assertSame($antesB, $this->b->fresh()->getAttributes());
        $this->assertIdentidades();
        $this->assertSame([$this->a->id], $this->ids('guia', 'GUIA-Q7-002', ['tab' => 'PENDIENTES_ENVIO']));
    }

    public function test_paginacion_y_filtros_combinados_con_remision(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->pedido(['numero_remision' => 'Q7-PAG-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }
        $otraPaq = CatalogoPaqueteriaPedido::create(['nombre' => 'Otra comercial Q7', 'categoria' => 'comercial', 'activo' => true]);
        $this->pedido(['numero_remision' => 'Q7-PAG-FUERA', 'catalogo_paqueteria_id' => $otraPaq->id]);
        $filtros = ['tab' => 'PENDIENTES_GUIA', 'paqueteria_ids' => [$this->paqueteria->id], 'situacion' => 'sin_guia', 'ordenar' => 'fecha_asc'];
        $uno = $this->listado('numero_remision', 'Q7-PAG-', $filtros);
        $dos = $this->listado('numero_remision', 'Q7-PAG-', $filtros + ['page' => 2]);
        $this->assertSame(16, $uno['pedidos']['total']);
        $this->assertCount(15, $uno['pedidos']['data']);
        $this->assertSame(2, $dos['pedidos']['current_page']);
        $this->assertCount(1, $dos['pedidos']['data']);
        $this->assertSame([], array_intersect(array_column($uno['pedidos']['data'], 'id'), array_column($dos['pedidos']['data'], 'id')));
        $this->assertStringContainsString('q_campo=numero_remision', $uno['pedidos']['next_page_url']);
        $this->assertSame([], $this->ids('numero_remision', 'Q7-PAG-', ['paqueteria_ids' => [999999]]));
        $this->assertSame([], $this->ids('numero_remision', 'Q7-PAG-', ['situacion' => 'con_guia']));
    }

    public function test_importacion_y_exportacion_conservan_contrato_csv_por_id(): void
    {
        $csv = "ID_Pedido,Folio,Paqueteria,Cliente,Guia_Rastreo\n{$this->a->id},REM-00935,ignorado,ignorado,GUIA-CSV-Q7\nREM-00935,PED-772,,,NO-ASIGNAR\n";
        $this->post(route('control_pedidos.delegado.importar'), ['archivo' => UploadedFile::fake()->createWithContent('guias-q7.csv', $csv)])
            ->assertRedirect()->assertSessionHas('import_resultado.actualizados', 1)->assertSessionHas('import_resultado.omitidos', 1);
        $this->assertSame('GUIA-CSV-Q7', $this->a->fresh()->numero_rastreo);
        $this->assertNull($this->b->fresh()->numero_rastreo);
        $this->assertIdentidades();
        $contenido = $this->get(route('control_pedidos.delegado.exportar'))->assertOk()->streamedContent();
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        $filas = array_map('str_getcsv', preg_split('/\r?\n/', trim($contenido)));
        $this->assertSame(['ID_Pedido', 'Folio', 'Paqueteria', 'Cliente', 'Guia_Rastreo'], $filas[0]);
        $filaB = collect(array_slice($filas, 1))->first(fn ($fila) => $fila[0] === (string) $this->b->id);
        $this->assertSame('REM-00935', $filaB[1]);
    }

    public function test_captura_auxiliar_conserva_folios_y_referencia_remision(): void
    {
        $this->assertIdentidades();
        $this->assertDatabaseHas('pedido_bma_referencias', ['pedido_bma_id' => $this->a->id, 'tipo' => 'REMISION', 'folio' => 'REM-00935', 'vigente' => true]);
    }

    private function assertIdentidades(): void
    {
        $a = $this->a->fresh();
        $this->assertSame('PBMA-2026-00077', $a->folio);
        $this->assertSame('PED-772', $a->folio_remision);
        $this->assertSame('REM-00935', $a->numero_remision);
    }

    private function listado(string $campo, string $q, array $extra = []): array
    {
        return $this->getJson(route('control_pedidos.delegado.listado', array_merge(['q_campo' => $campo, 'q' => $q], $extra)))->assertOk()->json();
    }

    private function ids(string $campo, string $q, array $extra = []): array
    {
        return array_column($this->listado($campo, $q, $extra)['pedidos']['data'], 'id');
    }

    private function pedido(array $attrs = []): PedidoBma
    {
        $pedido = PedidoBma::create(array_merge([
            'folio' => 'Q7-'.(++$this->pedidoNumero), 'folio_remision' => 'DOC-Q7', 'fecha' => '2026-10-14',
            'vendedor_id' => $this->auxiliar->id, 'catalogo_estatus_pedido_id' => $this->estatus['PENDIENTE_DE_GUIA'],
            'catalogo_paqueteria_id' => $this->paqueteria->id, 'pago_validado_at' => now(), 'cliente_proporciona_guia' => false,
        ], $attrs));
        $pedido->documentos()->create(['tipo' => 'remision', 'ruta_archivo' => 'q7/remision.pdf', 'nombre_original' => 'remision.pdf', 'mime_type' => 'application/pdf', 'tamano_bytes' => 10, 'activo' => true]);

        return $pedido;
    }
}

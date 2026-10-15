<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Services\ControlPedidos\ConfirmarCaratulaColocadaService;
use App\Services\ControlPedidos\InvalidarCaratulasVigentesService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConfirmarCaratulaIdentidadTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshIsolatedDatabase;
    }

    private User $usuario;

    private PedidoBmaTareaPreparacion $tarea;

    private PedidoBmaCaratula $caratula;

    // Guard before RefreshDatabase can prepare any schema.
    public function refreshDatabase(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('testing', $_ENV['APP_ENV']);
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->refreshIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 0));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Storage::fake('local');
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->usuario = User::factory()->create();
        foreach (['control_pedidos.tienda.ver', 'control_pedidos.tienda.confirmar_caratula'] as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $almacen = Almacen::create(['codigo' => 'Q1-PDV', 'nombre' => 'Almacén sintético Q1', 'activo' => true]);
        $origen = CatalogoOrigenPedido::create(['codigo' => 'Q1', 'nombre' => 'Origen sintético Q1', 'requiere_logistica' => false, 'activo' => true]);
        $estatus = CatalogoEstatusPedido::create(['codigo_interno' => 'Q1_PREP', 'nombre_visual' => 'Consulta Q1', 'color_hex' => '#EAB308', 'fase_ciclo' => 'PESAJE_PENDIENTE', 'orden' => 1, 'activo' => true]);
        $pedido = PedidoBma::create(['folio' => 'Q1-SINTETICO', 'fecha' => now()->toDateString(), 'vendedor_id' => $this->usuario->id, 'origen_id' => $origen->id, 'almacen_id' => $almacen->id, 'catalogo_estatus_pedido_id' => $estatus->id, 'estatus_envio' => 'pendiente_pesaje']);
        $modalidad = CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'ENVIO_MUNICIPIO'], ['nombre' => 'Municipio Q1', 'activo' => true]);
        $paqueteria = CatalogoPaqueteriaPedido::create(['nombre' => 'Transporte sintético Q1', 'categoria' => 'local_regional', 'activo' => true]);
        $this->tarea = PedidoBmaTareaPreparacion::create([
            'pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $almacen->id, 'catalogo_paqueteria_id' => $paqueteria->id,
            'estado' => 'LISTA_PARA_CARATULA', 'version' => 4, 'solicitada_por_id' => $this->usuario->id,
            'destinatario_nombre' => 'Destinatario Q1', 'destinatario_telefono' => '2221234567',
            'municipio_destino' => 'Municipio Q1', 'modalidad_cobro' => 'PAGADO',
        ]);
        $this->tarea->productos()->create(['descripcion_snapshot' => 'Piezas sintéticas Q1', 'cantidad_solicitada' => 2, 'cantidad_encontrada' => 2, 'estado_fisico' => 'bueno', 'orden' => 1]);
        $this->caratula = $this->crearCaratula($this->tarea);
    }

    public function test_confirma_caratula_vigente_y_sincroniza_transicion_historial_y_cumplimiento(): void
    {
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->once();
        $resultado = app(ConfirmarCaratulaColocadaService::class)->ejecutar($this->tarea, $this->usuario, 4, $this->caratula->id);

        $this->assertSame('RESPONDIDA', $resultado->estado);
        $this->assertSame(5, $resultado->version);
        $caratula = $this->caratula->fresh();
        $this->assertSame('COLOCADA', $caratula->estado);
        $this->assertSame($this->usuario->id, $caratula->colocada_por_id);
        $this->assertTrue($caratula->colocada_at->equalTo(now()));
        $historial = $resultado->historial()->sole();
        $this->assertSame('LISTA_PARA_CARATULA', $historial->estado_anterior);
        $this->assertSame('RESPONDIDA', $historial->estado_nuevo);
        $this->assertSame('caratula_colocada', $historial->accion);
        $this->assertSame($caratula->id, $historial->meta_json['caratula_id']);
        $this->assertSame(1, DB::table('pedido_bma_historial_estados')->where('pedido_bma_id', $this->tarea->pedido_bma_id)->count());
        $pedido = $resultado->pedido->fresh();
        $this->assertNotNull($pedido->pesaje_respondido_at);
        $this->assertSame(2, $pedido->revisionesProducto()->count());
        $this->assertSame('SEPARADA', $resultado->cumplimientoFisico->estado);
        $this->assertSame(2, $resultado->cumplimientoFisico->cantidad);
    }

    public function test_endpoint_acepta_identificador_omitido_y_elige_la_ultima_generada(): void
    {
        $nueva = $this->crearCaratula($this->tarea, 2);
        $this->actingAs($this->usuario)->post(route('control_pedidos.tienda.caratula.confirmar', $this->tarea), ['version' => 4])
            ->assertRedirect(route('control_pedidos.tienda.index', ['tab' => 'RESPONDIDAS_HOY']))
            ->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertSame('COLOCADA', $nueva->fresh()->estado);
        $this->assertSame('GENERADA', $this->caratula->fresh()->estado);
        $this->assertSame('RESPONDIDA', $this->tarea->fresh()->estado);
    }

    public function test_endpoint_rechaza_caratula_de_otra_tarea_sin_alterar_datos(): void
    {
        $otra = $this->tarea->replicate();
        $otra->save();
        $ajena = $this->crearCaratula($otra);
        $antes = $this->snapshot();
        $this->actingAs($this->usuario)->from('/control-pedidos/tienda')
            ->post(route('control_pedidos.tienda.caratula.confirmar', $this->tarea), ['version' => 4, 'caratula_id' => $ajena->id])
            ->assertRedirect('/control-pedidos/tienda')->assertSessionHas('error', 'La carátula indicada no está disponible para colocar.');
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_rechaza_caratula_invalidada_por_el_servicio_existente(): void
    {
        app(InvalidarCaratulasVigentesService::class)->ejecutar($this->tarea, 'Cambio de destino Q1', $this->usuario);
        $this->assertSame('INVALIDADA', $this->caratula->fresh()->estado);
        $this->rechazoSinCambios('caratula_id', $this->caratula->id);
    }

    public function test_rechaza_una_generada_anterior_a_la_vigente(): void
    {
        $this->crearCaratula($this->tarea, 2);
        $this->rechazoSinCambios('caratula_id', $this->caratula->id);
    }

    public function test_rechaza_identificador_inexistente(): void
    {
        $this->rechazoSinCambios('caratula_id', $this->caratula->id + 10000);
    }

    public function test_version_incorrecta_revierte_incluso_la_colocacion(): void
    {
        $this->rechazoSinCambios('version', $this->caratula->id, 3);
    }

    public function test_rechaza_tarea_sin_caratulas(): void
    {
        $this->caratula->delete();
        $this->rechazoSinCambios('caratula', null);
    }

    public function test_rechaza_caratula_sin_ruta_pdf(): void
    {
        $this->caratula->update(['ruta_pdf' => null]);
        $this->rechazoSinCambios('caratula', $this->caratula->id);
    }

    public function test_rechaza_caratula_cuyo_archivo_pdf_no_existe(): void
    {
        Storage::disk('local')->delete($this->caratula->ruta_pdf);
        $this->rechazoSinCambios('caratula', $this->caratula->id);
    }

    public function test_endpoint_sin_permiso_retorna_403_sin_transicion(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.ver', 'web'));
        $antes = $this->snapshot();
        $this->actingAs($sinPermiso)->post(route('control_pedidos.tienda.caratula.confirmar', $this->tarea), ['version' => 4, 'caratula_id' => $this->caratula->id])->assertForbidden();
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_servicio_conserva_su_validacion_de_permiso(): void
    {
        $antes = $this->snapshot();
        try {
            app(ConfirmarCaratulaColocadaService::class)->ejecutar($this->tarea, User::factory()->create(), 4, $this->caratula->id);
            $this->fail('Debió rechazar al usuario sin permiso.');
        } catch (\RuntimeException $e) {
            $this->assertSame('No tiene permiso para confirmar colocación de carátula.', $e->getMessage());
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_reintento_conserva_rechazo_actual_sin_segunda_transicion_ni_historial(): void
    {
        app(ConfirmarCaratulaColocadaService::class)->ejecutar($this->tarea, $this->usuario, 4, $this->caratula->id);
        $this->rechazoSinCambios('estado', $this->caratula->id, 5);
    }

    public function test_fallo_tardio_revierte_caratula_tarea_pedido_cumplimiento_e_historial(): void
    {
        $antes = $this->snapshot();
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->once()->andReturnUsing(function () {
            // Prove the failure occurs after all DB mutations, inside the transaction.
            $this->assertSame('COLOCADA', $this->caratula->fresh()->estado);
            $this->assertSame('RESPONDIDA', $this->tarea->fresh()->estado);
            $this->assertSame(1, $this->tarea->historial()->count());
            $this->assertSame(2, $this->tarea->pedido->revisionesProducto()->count());
            $this->assertSame(1, DB::table('pedido_bma_cumplimiento_fisico')->count());
            $this->assertSame(1, DB::table('pedido_bma_historial_estados')->count());
            throw new \RuntimeException('Fallo transaccional sintético Q1');
        });
        try {
            app(ConfirmarCaratulaColocadaService::class)->ejecutar($this->tarea, $this->usuario, 4, $this->caratula->id);
            $this->fail('Debió propagar el fallo transaccional.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo transaccional sintético Q1', $e->getMessage());
        }
        $this->assertSame($antes, $this->snapshot());
    }

    private function crearCaratula(PedidoBmaTareaPreparacion $tarea, int $version = 1): PedidoBmaCaratula
    {
        $ruta = "q1/caratula-{$tarea->id}-{$version}.pdf";
        $contenido = "%PDF-1.4\n% Fixture sintético Q1\n%%EOF";
        Storage::disk('local')->put($ruta, $contenido);

        return PedidoBmaCaratula::create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id, 'pedido_bma_id' => $tarea->pedido_bma_id,
            'version' => $version, 'estado' => 'GENERADA', 'ruta_pdf' => $ruta,
            'hash_sha256' => hash('sha256', $contenido), 'catalogo_paqueteria_id' => $tarea->catalogo_paqueteria_id,
            'destinatario_nombre' => $tarea->destinatario_nombre, 'destinatario_telefono' => $tarea->destinatario_telefono,
            'municipio_destino' => $tarea->municipio_destino, 'modalidad_cobro' => 'PAGADO',
            'generada_por_id' => $this->usuario->id, 'generada_at' => now(),
        ]);
    }

    private function rechazoSinCambios(string $campo, ?int $caratulaId, int $version = 4): void
    {
        $antes = $this->snapshot();
        try {
            app(ConfirmarCaratulaColocadaService::class)->ejecutar($this->tarea, $this->usuario, $version, $caratulaId);
            $this->fail("Debió rechazar la confirmación por {$campo}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }
        $this->assertSame($antes, $this->snapshot());
    }

    private function snapshot(): array
    {
        $resultado = [];
        foreach (['pedido_bma_tareas_preparacion', 'pedido_bma_caratulas', 'pedidos_bma', 'pedido_bma_tarea_historial', 'pedido_bma_historial_estados', 'pedido_bma_revisiones_producto', 'pedido_bma_cumplimiento_fisico'] as $tabla) {
            $resultado[$tabla] = DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
        }

        return $resultado;
    }
}

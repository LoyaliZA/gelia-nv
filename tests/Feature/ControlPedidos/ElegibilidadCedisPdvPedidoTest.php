<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\CalcularProgresoPedidoBmaService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Services\ControlPedidos\SolicitarPesajePedidoBmaService;
use App\Services\ControlPedidos\ValidarAlmacenSalidaPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ElegibilidadCedisPdvPedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->usuario = User::factory()->create();
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PreparacionTiendaConfig::CLAVE_FLAG],
            ['valor' => json_encode([
                'activo' => true,
                'modalidades_habilitadas' => ['RECOGE_TIENDA', 'ENVIO_MUNICIPIO'],
                'departamentos_habilitados' => [],
                'almacenes_habilitados' => [],
                'usuarios_piloto' => [],
            ])]
        );
        app(PreparacionTiendaConfig::class)->olvidarCache();
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE],
            ['codigo_interno' => 'PESAJE_Q4', 'nombre_visual' => 'Pesaje', 'color_hex' => '#999999', 'orden' => 2, 'activo' => true]
        );
    }

    public function test_tienda_con_salida_cedis_consulta_a_cedis_sin_crear_tarea(): void
    {
        $pedido = $this->pedido(false);

        $resultado = app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id);

        $this->assertSame(PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE, $resultado->estatus_envio);
        $this->assertSame(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE, $resultado->estatus->fase_ciclo);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->where('pedido_bma_id', $pedido->id)->count());
        $this->assertFalse($resultado->usaPreparacionTienda());
        $progreso = app(CalcularProgresoPedidoBmaService::class)->calcular($resultado->fresh());
        $this->assertStringContainsString('CEDIS', $progreso['accion_recomendada']);
        $bloqueos = $progreso['bloqueos'];
        $this->assertTrue(collect($bloqueos)->contains(fn ($texto) => str_contains($texto, 'CEDIS')));
        $this->assertFalse(collect($bloqueos)->contains(fn ($texto) => str_contains($texto, 'Tienda')));
    }

    public function test_tarea_historica_no_abre_preparacion_vigente_y_la_vigente_si(): void
    {
        $liberada = $this->pedido(false);
        $this->tarea($liberada, PedidoBmaTareaPreparacion::ESTADO_LIBERADA);
        $this->assertFalse($liberada->fresh()->usaPreparacionTienda());
        $this->assertFalse($liberada->fresh()->preparacionTiendaRespondida());

        $respondida = $this->pedido(false);
        $this->tarea($respondida, PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA);
        $this->assertFalse($respondida->fresh()->usaPreparacionTienda());
        $this->assertTrue($respondida->fresh()->preparacionTiendaRespondida());
        $this->assertTrue($respondida->fresh()->enRutaPreparacionTienda());

        $vigente = $this->pedido(false);
        $this->tarea($vigente, PedidoBmaTareaPreparacion::ESTADO_PENDIENTE);
        $this->assertTrue($vigente->fresh()->usaPreparacionTienda());

        try {
            app(SolicitarPesajePedidoBmaService::class)->ejecutar($vigente->fresh(), $this->usuario->id);
            $this->fail('Una tarea vigente no debe aceptar otra consulta CEDIS.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no puede solicitar', $e->getMessage());
        }
    }

    public function test_salida_pdv_sigue_bloqueando_la_consulta_cedis(): void
    {
        $pdv = Almacen::query()->create([
            'codigo' => 'PDV',
            'nombre' => 'PDV Q4',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);
        $pedido = $this->pedido(false, $pdv->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('almacén de Tienda');
        app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido, $this->usuario->id);
    }

    public function test_almacen_receptor_no_es_salida_nueva_y_el_enviado_no_vuelve_a_preparacion(): void
    {
        $receptor = Almacen::query()->create([
            'codigo' => 'CC-Q4',
            'nombre' => 'Solo destino',
            'activo' => true,
            'permite_salida_pedidos' => false,
        ]);
        $validador = app(ValidarAlmacenSalidaPedido::class);
        $validador->ejecutar($receptor->id, $receptor->id);
        try {
            $validador->ejecutar($receptor->id);
            $this->fail('Un almacén receptor no puede ser salida nueva.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no permite salida', $e->errors()['almacen_id'][0]);
        }

        $enviado = $this->pedido(false);
        $estatus = CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_ENVIADO],
            ['codigo_interno' => 'ENVIADO_Q4', 'nombre_visual' => 'Enviado', 'color_hex' => '#999999', 'orden' => 8, 'activo' => true]
        );
        $enviado->update(['catalogo_estatus_pedido_id' => $estatus->id, 'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO]);
        $this->tarea($enviado, PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA);
        $fresco = $enviado->fresh();
        $this->assertFalse($fresco->usaPreparacionTienda());
        $this->assertFalse($fresco->puedeSolicitarPesaje());
        $this->assertSame((int) $enviado->almacen_id, (int) $fresco->almacen_id);
        $this->assertSame((int) $enviado->sucursal_destino_id, (int) $fresco->sucursal_destino_id);
    }

    public function test_municipio_en_cedis_no_crea_tarea_al_consultar(): void
    {
        $paqueteria = CatalogoPaqueteriaPedido::query()->create([
            'nombre' => 'Municipal Q4',
            'categoria' => 'local_regional',
            'activo' => true,
        ]);
        $pedido = $this->pedido(true);
        $pedido->update(['catalogo_paqueteria_id' => $paqueteria->id, 'solicitud_preparacion_json' => [
            'version' => 1,
            'codigo_modalidad' => 'ENVIO_MUNICIPIO',
        ]]);

        app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);

        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->where('pedido_bma_id', $pedido->id)->count());
        $this->assertSame('ENVIO_MUNICIPIO', $pedido->fresh()->solicitud_preparacion_json['codigo_modalidad']);
    }

    private function pedido(bool $logistica, ?int $almacenId = null): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR],
            ['codigo_interno' => 'BORRADOR_Q4', 'nombre_visual' => 'Borrador', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'Q4-'.uniqid(),
            'nombre' => ($logistica ? 'Envío ' : 'Tienda ').uniqid(),
            'requiere_logistica' => $logistica,
            'activo' => true,
        ]);
        $almacen = $almacenId ?: Almacen::query()->create([
            'codigo' => 'CEDIS-'.uniqid(),
            'nombre' => 'CEDIS Q4',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ])->id;
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        $pedido = PedidoBma::query()->create([
            'folio' => 'Q4-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen,
            'sucursal_destino_id' => $sucursal->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ]);
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'q4/pedido.pdf',
            'nombre_original' => 'pedido.pdf',
            'mime_type' => 'application/pdf',
        ]);

        return $pedido->fresh();
    }

    private function tarea(PedidoBma $pedido, string $estado): void
    {
        $modalidad = CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL],
            ['nombre' => 'Envío bodega', 'activo' => true]
        );
        PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $pedido->almacen_id,
            'area_responsable_codigo' => 'TIENDA',
            'estado' => $estado,
            'solicitada_por_id' => $this->usuario->id,
            'solicitada_at' => now(),
            'version' => 1,
        ]);
    }
}

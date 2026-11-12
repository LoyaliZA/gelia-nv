<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoBanco;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Departamento;
use App\Models\User;
use App\Services\ControlPedidos\BancosDepartamentoService;
use App\Services\ControlPedidos\BancosGestionPedidosConfig;
use App\Services\ControlPedidos\ListarPedidosCedisService;
use App\Services\ControlPedidos\ListarTareasTiendaService;
use App\Services\ControlPedidos\PrioridadAtencionPedidosConfig;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PoliticasOperativasPedidosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') === 'sqlite') {
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        }
    }

    public function test_bancos_del_modulo_filtran_por_departamento_sin_modificar_catalogo_general(): void
    {
        $a = Departamento::create(['nombre' => 'Departamento A', 'codigo' => 'PA', 'activo' => true]);
        $b = Departamento::create(['nombre' => 'Departamento B', 'codigo' => 'PB', 'activo' => true]);
        $jose = CatalogoBanco::create(['nombre' => 'Cuenta A', 'activo' => true, 'compartido' => true]);
        $hermanos = CatalogoBanco::create(['nombre' => 'Cuenta compartida', 'activo' => true, 'compartido' => true]);
        $banorte = CatalogoBanco::create(['nombre' => 'Cuenta B', 'activo' => true, 'compartido' => true]);
        $oculto = CatalogoBanco::create(['nombre' => 'Otro banco', 'activo' => true, 'compartido' => true]);
        $config = app(BancosGestionPedidosConfig::class);
        $generalesAntes = app(BancosDepartamentoService::class)->disponibles($a->id)->pluck('id')->all();
        $config->guardar([
            ['id' => $jose->id, 'habilitado' => true, 'compartido' => false, 'departamento_ids' => [$a->id]],
            ['id' => $hermanos->id, 'habilitado' => true, 'compartido' => false, 'departamento_ids' => [$a->id, $b->id]],
            ['id' => $banorte->id, 'habilitado' => true, 'compartido' => false, 'departamento_ids' => [$b->id]],
        ]);
        $this->assertEqualsCanonicalizing([$jose->id, $hermanos->id], $config->catalogo([$a->id])->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$banorte->id, $hermanos->id], $config->catalogo([$b->id])->pluck('id')->all());
        $this->assertCount(3, $config->catalogo());
        $this->assertCount(0, $config->catalogo([null]));
        $this->assertSame($generalesAntes, app(BancosDepartamentoService::class)->disponibles($a->id)->pluck('id')->all());
        $this->assertTrue($oculto->fresh()->activo);
        $nuevo = CatalogoBanco::create(['nombre' => 'Banco nuevo', 'activo' => true, 'compartido' => true]);
        $this->assertFalse($config->catalogo()->contains('id', $nuevo->id));
        $pedido = new PedidoBma(['departamento_operacion_id' => $b->id]);
        $pedido->setRelation('vendedor', new User(['departamento_id' => $a->id]));
        CoberturaPagoPedidoBmaService::assertBancoPermitido($pedido, $banorte->id);
        try {
            CoberturaPagoPedidoBmaService::assertBancoPermitido($pedido, $jose->id);
            $this->fail('El servidor debe rechazar el banco del otro departamento.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('catalogo_banco_id', $e->errors());
        }
        $banorte->update(['activo' => false]);
        $this->assertFalse($config->catalogo([$b->id])->contains('id', $banorte->id));
        $config->guardar([]);
        $this->assertCount(0, $config->catalogo());
    }

    private function pedido(string $folio, array $attrs = []): PedidoBma
    {
        $vendedor = User::factory()->create();
        $origen = CatalogoOrigenPedido::firstOrCreate(['nombre' => 'Prueba políticas'], ['activo' => true]);
        $estatus = CatalogoEstatusPedido::firstOrCreate(['codigo_interno' => 'POLITICAS'], ['nombre_visual' => 'Pendiente', 'color_hex' => '#000000', 'fase_ciclo' => 'PESAJE_PENDIENTE', 'orden' => 1, 'activo' => true]);

        return PedidoBma::create(array_merge([
            'folio' => $folio, 'fecha' => now()->toDateString(), 'vendedor_id' => $vendedor->id,
            'origen_id' => $origen->id, 'catalogo_estatus_pedido_id' => $estatus->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'created_at' => now()->subDays(2),
        ], $attrs));
    }

    public function test_prioridad_antes_de_paginar_en_cedis_y_cambio_de_orden_efectivo(): void
    {
        $nacionales = [];
        for ($i = 0; $i < 16; $i++) {
            $nacionales[] = $this->pedido('NACIONAL-'.$i)->id;
        }
        $resguardo = $this->pedido('RESGUARDO', ['es_resguardo' => true]);
        $tienda = $this->pedido('TIENDA', ['solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda']]);
        $municipal = $this->pedido('MUNICIPAL', ['solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal']]);
        $md = $this->pedido('MD', ['prioridad_md' => true, 'created_at' => now()]);
        $listar = app(ListarPedidosCedisService::class);
        $pagina = $listar->ejecutar(['tab' => 'PENDIENTES_PESAJE']);
        $this->assertSame(20, $pagina->total());
        $this->assertSame([$md->id, $municipal->id, $tienda->id, $resguardo->id], $pagina->getCollection()->take(4)->pluck('id')->all());
        app(PrioridadAtencionPedidosConfig::class)->guardar(['nacional', 'resguardo', 'tienda', 'municipal', 'mismo_dia']);
        $reordenados = $listar->ejecutar(['tab' => 'TODOS'], false)->pluck('id');
        $this->assertSame($md->id, $reordenados->first());
        $this->assertSame($nacionales, $reordenados->skip(1)->take(16)->values()->all());
    }

    public function test_tareas_sin_metadata_se_clasifican_por_modalidad_y_desempatan_por_antiguedad(): void
    {
        $tienda = $this->pedido('TAREA-TIENDA', ['solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda']]);
        $municipal = $this->pedido('TAREA-MUNICIPAL', ['solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal']]);
        $md = $this->pedido('TAREA-MD', ['prioridad_md' => true, 'solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda']]);
        $modalidad = CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'RECOGE_TIENDA'], ['nombre' => 'Recoge', 'activo' => true]);
        $almacen = Almacen::create(['codigo' => 'POL', 'nombre' => 'Almacén de prueba', 'activo' => true]);
        foreach ([$tienda, $municipal, $md] as $pedido) {
            PedidoBmaTareaPreparacion::create(['pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id,
                'almacen_id' => $almacen->id, 'estado' => 'PENDIENTE', 'version' => 1, 'solicitada_por_id' => $pedido->vendedor_id, 'solicitada_at' => now()]);
        }
        $config = app(PrioridadAtencionPedidosConfig::class);
        $query = PedidoBmaTareaPreparacion::query();
        $config->ordenar($query, true);
        $this->assertSame([$md->id, $municipal->id, $tienda->id], $query->pluck('pedido_bma_id')->all());
        $config->guardar(['tienda', 'resguardo', 'nacional', 'municipal', 'mismo_dia']);
        $query = PedidoBmaTareaPreparacion::query();
        $config->ordenar($query, true);
        $this->assertSame([$md->id, $tienda->id, $municipal->id], $query->pluck('pedido_bma_id')->all());
    }

    public function test_mismo_dia_luego_actualizacion_luego_consulta_nueva_antes_del_resto(): void
    {
        $respondida = [
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'pesaje_respondido_at' => now()->subDay(),
            'consulta_cerrada_at' => now()->subDay(),
            'consulta_actualizacion_pendiente' => false,
            'created_at' => now()->subDays(3),
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'nacional', 'revision_disponibilidad' => ['estado' => 'sin_excepciones']],
        ];
        $antiguas = [];
        for ($i = 0; $i < 16; $i++) {
            $antiguas[] = $this->pedido('RESP-'.$i, $respondida)->id;
        }
        $actualizada = $this->pedido('ACTUALIZADA', [
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'consulta_actualizacion_pendiente' => true,
            'created_at' => now(),
            'solicitud_preparacion_json' => [
                'modalidad_entrega' => 'tienda',
                'revision_disponibilidad' => ['estado' => 'sin_excepciones', 'actualizacion_pendiente' => true],
                'anexo_sin_remision' => [['entrada' => 'documento']],
            ],
        ]);
        $nueva = $this->pedido('NUEVA-TIENDA', [
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'created_at' => now()->subHour(),
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda'],
        ]);
        $md = $this->pedido('MD-RESPONDIDO', [
            'prioridad_md' => true,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'pesaje_respondido_at' => now(),
            'consulta_cerrada_at' => now(),
            'created_at' => now(),
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda', 'revision_disponibilidad' => ['estado' => 'sin_excepciones']],
        ]);
        app(PrioridadAtencionPedidosConfig::class)->guardar(['nacional', 'resguardo', 'tienda', 'municipal', 'mismo_dia']);

        $pagina = app(ListarPedidosCedisService::class)->ejecutar(['tab' => 'PENDIENTES_PESAJE']);
        $ids = $pagina->getCollection()->pluck('id')->all();
        $this->assertSame([$md->id, $actualizada->id, $nueva->id], array_slice($ids, 0, 3));
        $this->assertNotContains($antiguas[15], $ids);

        $modalidad = CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'RECOGE_TIENDA'], ['nombre' => 'Recoge', 'activo' => true]);
        $almacen = Almacen::create(['codigo' => 'URG', 'nombre' => 'Almacén de urgencia', 'activo' => true]);
        $crear = function (PedidoBma $pedido, $cuando) use ($modalidad, $almacen) {
            PedidoBmaTareaPreparacion::create([
                'pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id,
                'almacen_id' => $almacen->id, 'estado' => 'PENDIENTE', 'version' => 1,
                'area_responsable_codigo' => 'TIENDA', 'solicitada_por_id' => $pedido->vendedor_id, 'solicitada_at' => $cuando,
            ]);
        };
        foreach (array_slice($antiguas, 0, 16) as $indice => $id) {
            $crear(PedidoBma::query()->findOrFail($id), now()->subDays(4)->addMinutes($indice));
        }
        $crear($actualizada, now());
        $crear($nueva, now()->subHour());
        $crear($md, now());
        $tareas = app(ListarTareasTiendaService::class)->ejecutar(User::factory()->create(), ['tab' => 'TODOS']);
        $ordenTareas = $tareas->getCollection()->pluck('pedido_bma_id')->all();
        $this->assertSame([$md->id, $actualizada->id, $nueva->id], array_slice($ordenTareas, 0, 3));
    }
}

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
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
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
        $this->assertSame($nacionales, $listar->ejecutar(['tab' => 'TODOS'], false)->take(16)->pluck('id')->all());
        $this->assertSame($md->id, $listar->ejecutar(['tab' => 'TODOS'], false)->last()->id);
    }

    public function test_tareas_sin_metadata_se_clasifican_por_modalidad_y_desempatan_por_antiguedad(): void
    {
        $tienda = $this->pedido('TAREA-TIENDA');
        $md = $this->pedido('TAREA-MD', ['prioridad_md' => true]);
        $modalidad = CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'RECOGE_TIENDA'], ['nombre' => 'Recoge', 'activo' => true]);
        $almacen = Almacen::create(['codigo' => 'POL', 'nombre' => 'Almacén de prueba', 'activo' => true]);
        foreach ([$tienda, $md] as $pedido) {
            PedidoBmaTareaPreparacion::create(['pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id,
                'almacen_id' => $almacen->id, 'estado' => 'PENDIENTE', 'version' => 1, 'solicitada_por_id' => $pedido->vendedor_id, 'solicitada_at' => now()]);
        }
        $config = app(PrioridadAtencionPedidosConfig::class);
        $query = PedidoBmaTareaPreparacion::query();
        $config->ordenar($query, true);
        $this->assertSame([$md->id, $tienda->id], $query->pluck('pedido_bma_id')->all());
        $config->guardar(['tienda', 'municipal', 'resguardo', 'nacional', 'mismo_dia']);
        $query = PedidoBmaTareaPreparacion::query();
        $config->ordenar($query, true);
        $this->assertSame([$tienda->id, $md->id], $query->pluck('pedido_bma_id')->all());
    }
}

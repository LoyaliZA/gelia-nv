<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion as Tarea;
use App\Models\User;
use App\Services\ControlPedidos\ListarPedidosCedisService;
use App\Services\ControlPedidos\ListarTareasTiendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BandejasOperativasTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(string $folio, array $datos = []): PedidoBma
    {
        $vendedora = User::factory()->create(['name' => 'Ana de ejemplo']);
        $origen = CatalogoOrigenPedido::firstOrCreate(['nombre' => 'Ejemplo bandeja'], ['activo' => true]);
        $estatus = CatalogoEstatusPedido::firstOrCreate(['codigo_interno' => 'BANDEJA'], ['nombre_visual' => 'Consulta', 'color_hex' => '#000000', 'fase_ciclo' => 'PESAJE_PENDIENTE', 'orden' => 1, 'activo' => true]);
        return PedidoBma::create(array_merge([
            'folio' => $folio, 'fecha' => '2026-11-08', 'vendedor_id' => $vendedora->id,
            'origen_id' => $origen->id, 'catalogo_estatus_pedido_id' => $estatus->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'created_at' => '2026-11-01 10:00:00',
        ], $datos));
    }

    public function test_cedis_filtra_tipos_excluyentes_y_conserva_legados_en_todos(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $tienda = $this->pedido('TIENDA', ['solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda']]);
        $md = $this->pedido('MD', ['prioridad_md' => true, 'solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda']]);
        $municipal = $this->pedido('MUNICIPAL', ['solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal']]);
        $nacional = $this->pedido('LEGADO');
        $resguardo = $this->pedido('RESGUARDO', ['es_resguardo' => true]);
        $listar = app(ListarPedidosCedisService::class);
        foreach (['tienda' => [$tienda->id, $resguardo->id], 'mismo_dia' => [$md->id], 'municipal' => [$municipal->id], 'nacional' => [$nacional->id]] as $tipo => $ids) {
            $this->assertEqualsCanonicalizing($ids, $listar->ejecutar(['tipo' => $tipo], false)->pluck('id')->all());
        }
        $metricas = $listar->metricas();
        $this->assertSame(5, $metricas['todos']);
        $this->assertSame(5, array_sum($metricas['tipos']));
        $this->assertCount(5, $listar->ejecutar([], false));
    }

    public function test_cedis_fecha_de_solicitud_vendedora_y_orden_antes_de_paginar(): void
    {
        $antiguo = $this->pedido('ANTIGUO', ['pesaje_solicitado_at' => '2026-11-07 09:00:00']);
        $hoy = $this->pedido('HOY', ['pesaje_solicitado_at' => '2026-11-08 23:59:59']);
        $this->pedido('OTRA', ['vendedor_id' => User::factory()->create(['name' => 'Beatriz ejemplo'])->id, 'pesaje_solicitado_at' => '2026-11-08 11:00:00']);
        $listar = app(ListarPedidosCedisService::class);
        $filtros = ['desde' => '2026-11-08', 'hasta' => '2026-11-08', 'vendedora' => 'Ana'];
        $this->assertSame([$hoy->id], $listar->ejecutar($filtros, false)->pluck('id')->all());
        $this->assertSame(1, $listar->metricas(null, $filtros)['todos']);
        $this->assertSame([$antiguo->id, $hoy->id], $listar->ejecutar(['vendedora' => 'Ana'])->pluck('id')->all());
        $this->assertSame([$hoy->id, $antiguo->id], $listar->ejecutar(['vendedora' => 'Ana', 'orden' => 'recientes'])->pluck('id')->all());
        $this->assertCount(3, $listar->ejecutar(['desde' => '2026-99-99', 'hasta' => 'incorrecta'], false));
    }

    public function test_tienda_todos_incluye_cada_etapa_y_contadores_coinciden_con_las_bandejas(): void
    {
        $usuario = User::factory()->create();
        $modalidad = CatalogoModalidadPreparacionPedido::firstOrCreate(['codigo' => 'RECOGE_TIENDA'], ['nombre' => 'Recoge', 'activo' => true]);
        $almacen = Almacen::create(['codigo' => 'QA-BAN', 'nombre' => 'Sucursal de ejemplo', 'activo' => true]);
        $estados = ['PENDIENTE', 'EN_ATENCION', 'CON_INCIDENCIA', 'LISTA_PARA_TRASLADO', 'LISTA_PARA_CARATULA', 'EN_TRASLADO', 'RECHAZADA_CEDIS', 'LIBERACION_SOLICITADA', 'RESPONDIDA', 'RECIBIDA_CEDIS', 'LIBERADA', 'CANCELADA', 'SUSTITUIDA'];
        foreach ($estados as $i => $estado) {
            $pedido = $this->pedido('TAREA-'.$i);
            Tarea::create(['pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id, 'almacen_id' => $almacen->id,
                'area_responsable_codigo' => 'TIENDA', 'estado' => $estado, 'version' => 1, 'solicitada_por_id' => $pedido->vendedor_id,
                'solicitada_at' => $i === 0 ? '2026-11-07 09:00:00' : '2026-11-08 10:00:00']);
        }
        $listar = app(ListarTareasTiendaService::class);
        $metricas = $listar->metricas($usuario);
        foreach (['TODOS' => 'todos', 'PENDIENTES' => 'pendientes', 'EN_ATENCION' => 'en_atencion_operativa', 'ATENDIDOS' => 'atendidos'] as $tab => $key) {
            $this->assertSame($metricas[$key], $listar->ejecutar($usuario, ['tab' => $tab])->total());
        }
        $this->assertSame(13, $listar->ejecutar($usuario)->total());
        $this->assertSame(13, $metricas['pendientes'] + $metricas['en_atencion_operativa'] + $metricas['atendidos']);
        $filtros = ['desde' => '2026-11-08', 'hasta' => '2026-11-08', 'vendedora' => 'Ana'];
        $this->assertSame(12, $listar->ejecutar($usuario, $filtros)->total());
        $this->assertSame(12, $listar->metricas($usuario, $filtros)['todos']);
        $this->assertSame(0, $listar->ejecutar($usuario, ['vendedora' => 'No existe'])->total());
        $this->assertSame('PENDIENTE', $listar->ejecutar($usuario)->first()->estado);
        $this->assertNotSame('PENDIENTE', $listar->ejecutar($usuario, ['orden' => 'recientes'])->first()->estado);
    }
}

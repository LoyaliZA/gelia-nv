<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\User;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Sucursal;
use App\Services\ControlPedidos\Municipal\ResolverSucursalDestinoMunicipal;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecorridoFisicoMunicipalTest extends TestCase
{
    use RefreshDatabase;

    private function almacen(string $tipo, string $codigo, ?Sucursal $sucursal = null, array $extra = []): Almacen
    {
        $tipoId = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => $tipo], ['activo' => true])->id;

        return Almacen::query()->create(array_merge([
            'nombre' => $codigo, 'codigo' => $codigo, 'tipo_almacen_id' => $tipoId,
            'sucursal_id' => $sucursal?->id, 'activo' => true, 'visible_en_pedidos' => true,
        ], $extra));
    }

    private function pedido(string $modalidad, array $atributos = []): PedidoBma
    {
        static $n = 0;
        $vendedor = User::factory()->create();
        $lista = CatalogoListaDescuento::query()->firstOrCreate(['nombre' => 'PUBLICO GENERAL'], ['monto_requerido' => 0, 'activo' => true]);
        $cliente = Cliente::query()->create(['numero_cliente' => 'RFM-C'.($n + 1), 'nombre' => 'Cliente', 'lista_actual_id' => $lista->id,
            'vendedor_id' => $vendedor->id, 'monto_venta_actual' => 0]);
        $estatus = CatalogoEstatusPedido::query()->create(['codigo_interno' => 'RFM_'.($n + 1), 'nombre_visual' => 'B', 'color_hex' => '#64748B',
            'fase_ciclo' => 'BORRADOR', 'orden' => 1, 'activo' => true]);
        $pedido = new PedidoBma(array_merge([
            'folio' => 'RFM-'.++$n, 'vendedor_id' => $vendedor->id, 'cliente_id' => $cliente->id, 'catalogo_estatus_pedido_id' => $estatus->id, 'fecha' => now()->toDateString(), 'total_mercancia' => 100,
            'solicitud_preparacion_json' => ['modalidad_entrega' => $modalidad] + ($modalidad === 'municipal' ? ['recorrido_fisico_municipal' => true] : []),
        ], $atributos));
        $pedido->save();

        return $pedido->fresh();
    }

    private function piloto(array $almacenes): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PreparacionTiendaConfig::CLAVE_FLAG],
            ['valor' => json_encode(['activo' => true, 'almacenes_habilitados' => $almacenes])]
        );
        app(PreparacionTiendaConfig::class)->olvidarCache();
    }

    public function test_municipal_con_cedis_y_sucursal_es_traslado_interno(): void
    {
        $sucursal = Sucursal::factory()->create();
        $cedis = $this->almacen('CEDIS', 'CEDIS-1');
        $pedido = $this->pedido('municipal', ['almacen_id' => $cedis->id, 'sucursal_destino_id' => $sucursal->id]);

        $this->assertTrue($pedido->esTrasladoInternoAPdv());
        $this->assertTrue($pedido->requiereSucursalDestino());
        $this->assertFalse($pedido->prohibeSucursalDestino());
        $this->assertFalse($pedido->ofreceRastreo());
    }

    public function test_municipal_desde_pdv_de_la_misma_sucursal_no_es_traslado_interno(): void
    {
        $sucursal = Sucursal::factory()->create();
        $pdv = $this->almacen('PDV', 'PDV-1', $sucursal);
        $pedido = $this->pedido('municipal', ['almacen_id' => $pdv->id, 'sucursal_destino_id' => $sucursal->id]);

        $this->assertFalse($pedido->esTrasladoInternoAPdv());
    }

    public function test_municipal_sin_sucursal_no_es_traslado_interno(): void
    {
        $cedis = $this->almacen('CEDIS', 'CEDIS-1');
        $pedido = $this->pedido('municipal', ['almacen_id' => $cedis->id]);

        $this->assertFalse($pedido->esTrasladoInternoAPdv());
    }

    public function test_nacional_y_tienda_no_cambian(): void
    {
        $sucursal = Sucursal::factory()->create();
        $cedis = $this->almacen('CEDIS', 'CEDIS-1');

        $nacional = $this->pedido('nacional', ['almacen_id' => $cedis->id, 'sucursal_destino_id' => $sucursal->id]);
        $this->assertFalse($nacional->requiereSucursalDestino());
        $this->assertFalse($nacional->prohibeSucursalDestino());
        $this->assertFalse(RecorridoFisicoMunicipal::aplica($nacional));

        $tienda = $this->pedido('tienda', ['sucursal_destino_id' => $sucursal->id]);
        $this->assertTrue($tienda->requiereSucursalDestino());
        $this->assertFalse($tienda->prohibeSucursalDestino());
        $this->assertFalse(RecorridoFisicoMunicipal::aplica($tienda));
    }

    public function test_municipal_con_tarea_envio_municipio_abierta_no_aplica(): void
    {
        $sucursal = Sucursal::factory()->create();
        $cedis = $this->almacen('CEDIS', 'CEDIS-1');
        $pedido = $this->pedido('municipal', ['almacen_id' => $cedis->id, 'sucursal_destino_id' => $sucursal->id]);
        $modalidad = CatalogoModalidadPreparacionPedido::query()->where('codigo', CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO)->firstOrFail();
        PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $cedis->id, 'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            'area_responsable_codigo' => 'TIENDA', 'version' => 1,
        ]);

        $pedido = $pedido->fresh();
        $this->assertFalse(RecorridoFisicoMunicipal::aplica($pedido));
        $this->assertFalse($pedido->esTrasladoInternoAPdv());
        $this->assertFalse($pedido->requiereSucursalDestino());
        $this->assertTrue($pedido->prohibeSucursalDestino());
    }

    public function test_resolver_usa_la_unica_sucursal_habilitada(): void
    {
        $sucursal = Sucursal::factory()->create();
        $this->almacen('PDV', 'PDV-1', $sucursal);

        $this->assertSame($sucursal->id, app(ResolverSucursalDestinoMunicipal::class)->ejecutar(null));
        $this->assertSame($sucursal->id, app(ResolverSucursalDestinoMunicipal::class)->ejecutar($sucursal->id));
    }

    public function test_resolver_exige_sucursal_si_hay_varias(): void
    {
        $a = Sucursal::factory()->create();
        $b = Sucursal::factory()->create();
        $this->almacen('PDV', 'PDV-1', $a);
        $this->almacen('PDV', 'PDV-2', $b);
        $resolver = app(ResolverSucursalDestinoMunicipal::class);

        $this->assertSame($b->id, $resolver->ejecutar($b->id));
        try {
            $resolver->ejecutar(null);
            $this->fail('Debía exigir la sucursal.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sucursal_destino_id', $e->errors());
        }
    }

    public function test_resolver_rechaza_city_center_oculta_o_fuera_de_lista_piloto(): void
    {
        $visible = Sucursal::factory()->create();
        $oculta = Sucursal::factory()->create();
        $fuera = Sucursal::factory()->create();
        $pdvVisible = $this->almacen('PDV', 'PDV-1', $visible);
        $this->almacen('PDV', 'PDV-CC', $oculta, ['visible_en_pedidos' => false]);
        $this->almacen('PDV', 'PDV-3', $fuera);
        $this->piloto([$pdvVisible->id]);
        $resolver = app(ResolverSucursalDestinoMunicipal::class);

        $this->assertSame($visible->id, $resolver->ejecutar($visible->id));
        foreach ([$oculta->id, $fuera->id] as $id) {
            try {
                $resolver->ejecutar($id);
                $this->fail('Debía rechazar la sucursal.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('sucursal_destino_id', $e->errors());
            }
        }
    }

    public function test_habilitadas_lista_las_sucursales_pdv_del_catalogo(): void
    {
        $resolver = app(ResolverSucursalDestinoMunicipal::class);
        $this->assertSame([], $resolver->habilitadas());

        $sucursal = Sucursal::factory()->create();
        $this->almacen('PDV', 'PDV-1', $sucursal);
        $this->assertSame([$sucursal->id], $resolver->habilitadas());
    }

    public function test_proyeccion_de_lectura_expone_marca_y_salida_a_terminal_solo_en_municipal(): void
    {
        $salida = ['at' => '2026-11-21T10:00:00-06:00', 'receptor' => 'Terminal ficticia', 'actor_id' => 1];
        $pedido = $this->pedido('municipal');
        $pedido->update(['solicitud_preparacion_json' => array_merge($pedido->solicitud_preparacion_json, ['salida_terminal' => $salida])]);

        $solicitud = $pedido->fresh()->solicitud_preparacion;
        $this->assertTrue($solicitud['recorrido_fisico_municipal']);
        $this->assertSame($salida, $solicitud['salida_terminal']);

        $tienda = $this->pedido('tienda');
        $tienda->update(['solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda', 'recorrido_fisico_municipal' => true, 'salida_terminal' => $salida]]);
        $this->assertArrayNotHasKey('salida_terminal', $tienda->fresh()->solicitud_preparacion ?? []);
    }

    public function test_evaluar_aplica_varias_veces_no_repite_consultas(): void
    {
        $sucursal = Sucursal::factory()->create();
        $cedis = $this->almacen('CEDIS', 'CEDIS-1');
        $id = $this->pedido('municipal', ['almacen_id' => $cedis->id, 'sucursal_destino_id' => $sucursal->id])->id;
        $consultas = function (PedidoBma $pedido): int {
            \Illuminate\Support\Facades\DB::flushQueryLog();
            \Illuminate\Support\Facades\DB::enableQueryLog();
            $this->assertTrue(RecorridoFisicoMunicipal::aplica($pedido));
            $this->assertTrue(RecorridoFisicoMunicipal::aplica($pedido));
            $total = count(\Illuminate\Support\Facades\DB::getQueryLog());
            \Illuminate\Support\Facades\DB::disableQueryLog();

            return $total;
        };

        // Una sola carga de la tarea vigente por instancia; con la relación ya cargada (listas), ninguna.
        $this->assertSame(1, $consultas(PedidoBma::query()->findOrFail($id)));
        $this->assertSame(0, $consultas(PedidoBma::query()->with('tareaPreparacionVigente.modalidad')->findOrFail($id)));
    }
}

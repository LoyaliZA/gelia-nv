<?php

namespace Tests\Unit\PuntoVenta;

use App\Models\Almacen;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModeloBaseResguardosPdvTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_crea_tablas_del_contrato(): void
    {
        foreach ([
            'pdv_resguardos',
            'pdv_resguardo_bultos',
            'pdv_resguardo_eventos',
            'pdv_resguardo_incidencias',
            'pdv_resguardo_entregas',
            'pdv_resguardo_entrega_bultos',
            'pdv_resguardo_evidencias',
        ] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), $tabla);
        }

        $this->assertTrue(Schema::hasColumns('pdv_resguardos', [
            'pedido_bma_id',
            'cliente_id',
            'sucursal_id',
            'almacen_id',
            'estado',
            'salida_cedis_at',
            'recepcion_fisica_at',
            'snapshot_json',
            'version',
        ]));
    }

    public function test_relaciones_con_fuentes_maestras_y_casts(): void
    {
        $sucursal = Sucursal::factory()->create();
        $almacen = Almacen::create([
            'codigo' => 'PDV-A1',
            'nombre' => 'Piso sucursal',
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);
        $pedido = $this->crearPedido($sucursal);
        $actor = User::factory()->create();
        $salida = now()->subDays(2);
        $recepcion = now()->subDay();

        $resguardo = ResguardoPdv::factory()->create([
            'pedido_bma_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
            'sucursal_id' => $sucursal->id,
            'almacen_id' => $almacen->id,
            'salida_cedis_at' => $salida,
            'recepcion_fisica_at' => $recepcion,
            'snapshot_cliente_nombre' => $pedido->cliente->nombre,
            'snapshot_json' => ['folio' => $pedido->folio],
            'version' => 3,
        ]);

        $this->assertTrue($resguardo->pedido->is($pedido));
        $this->assertTrue($resguardo->cliente->is($pedido->cliente));
        $this->assertTrue($resguardo->sucursal->is($sucursal));
        $this->assertTrue($resguardo->almacen->is($almacen));
        $this->assertTrue($pedido->resguardosPdv()->whereKey($resguardo->id)->exists());
        $this->assertTrue($sucursal->resguardosPdv()->whereKey($resguardo->id)->exists());
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $resguardo->salida_cedis_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $resguardo->recepcion_fisica_at);
        $this->assertFalse($resguardo->salida_cedis_at->equalTo($resguardo->created_at));
        $this->assertSame(['folio' => $pedido->folio], $resguardo->snapshot_json);
        $this->assertSame(3, $resguardo->version);
        $this->assertFalse((bool) $pedido->es_resguardo);

        $bulto = ResguardoPdvBulto::factory()->create([
            'resguardo_id' => $resguardo->id,
            'pedido_bma_id' => $pedido->id,
            'folio' => 'CJA-1',
            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
            'recepcion_at' => $recepcion,
            'recepcion_por_id' => $actor->id,
        ]);

        $evento = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'bulto_id' => $bulto->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'estado_anterior' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $actor->id,
            'ocurrido_at' => $recepcion,
            'snapshot_json' => ['bultos' => 1],
            'idempotency_key' => 'evt-recepcion-1',
        ]);

        $incidencia = ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'bulto_id' => $bulto->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_DANO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Caja golpeada en transporte',
            'reportado_por_id' => $actor->id,
            'reportado_at' => $recepcion,
            'idempotency_key' => 'inc-dano-1',
            'version' => 1,
        ]);

        $entrega = ResguardoPdvEntrega::query()->create([
            'resguardo_id' => $resguardo->id,
            'pedido_bma_id' => $pedido->id,
            'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Persona titular',
            'entregado_por_id' => $actor->id,
            'entregado_at' => now(),
            'incidencia_autorizada_id' => $incidencia->id,
            'snapshot_json' => ['relacion' => 'titular'],
            'idempotency_key' => 'ent-pedido-1',
            'version' => 1,
        ]);
        $entrega->bultos()->attach($bulto->id);

        $evidencia = ResguardoPdvEvidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'evento_id' => $evento->id,
            'bulto_id' => $bulto->id,
            'incidencia_id' => $incidencia->id,
            'entrega_id' => $entrega->id,
            'tipo' => ResguardoPdvEvidencia::TIPO_FOTO,
            'ruta_interna' => 'pdv/resguardos/1.jpg',
            'nombre_original' => 'dano.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 1200,
            'hash_sha256' => str_repeat('a', 64),
            'actor_id' => $actor->id,
            'capturado_at' => $recepcion,
            'inmutable' => true,
            'metadata_json' => ['origen' => 'camara'],
        ]);

        $this->assertTrue($resguardo->bultos()->whereKey($bulto->id)->exists());
        $this->assertTrue($resguardo->eventos()->whereKey($evento->id)->exists());
        $this->assertTrue($resguardo->incidencias()->whereKey($incidencia->id)->exists());
        $this->assertTrue($resguardo->entregas()->whereKey($entrega->id)->exists());
        $this->assertTrue($resguardo->evidencias()->whereKey($evidencia->id)->exists());
        $this->assertTrue($entrega->bultos()->whereKey($bulto->id)->exists());
        $this->assertTrue($bulto->pedido->is($pedido));
        $this->assertTrue($evento->actor->is($actor));
        $this->assertTrue($evidencia->inmutable);
        $this->assertSame(['origen' => 'camara'], $evidencia->fresh()->metadata_json);
        $this->assertDatabaseHas('pdv_resguardo_evidencias', ['id' => $evidencia->id, 'hash_sha256' => str_repeat('a', 64)]);
    }

    public function test_unicidad_resguardo_esperado_por_pedido_y_destino(): void
    {
        $sucursal = Sucursal::factory()->create();
        $pedido = $this->crearPedido($sucursal);

        ResguardoPdv::factory()->create([
            'pedido_bma_id' => $pedido->id,
            'sucursal_id' => $sucursal->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        ResguardoPdv::factory()->create([
            'pedido_bma_id' => $pedido->id,
            'sucursal_id' => $sucursal->id,
        ]);
    }

    public function test_permite_resguardo_sin_pedido_por_excepcion(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'pedido_bma_id' => null,
            'cliente_id' => null,
        ]);

        $this->assertNull($resguardo->pedido_bma_id);
        $this->assertNull($resguardo->cliente_id);
        $this->assertNotNull($resguardo->sucursal_id);
    }

    public function test_idempotency_key_de_eventos_es_unica(): void
    {
        $resguardo = ResguardoPdv::factory()->create();

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA,
            'estado_nuevo' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'ocurrido_at' => now(),
            'idempotency_key' => 'handoff-1',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA,
            'estado_nuevo' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'ocurrido_at' => now(),
            'idempotency_key' => 'handoff-1',
        ]);
    }

    public function test_no_elimina_resguardo_con_evidencia_confirmada(): void
    {
        $resguardo = ResguardoPdv::factory()->create();
        ResguardoPdvEvidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo' => ResguardoPdvEvidencia::TIPO_FIRMA,
            'ruta_interna' => 'pdv/resguardos/firma.png',
            'nombre_original' => 'firma.png',
            'hash_sha256' => str_repeat('b', 64),
            'capturado_at' => now(),
            'inmutable' => true,
        ]);

        $this->expectException(QueryException::class);
        $resguardo->delete();
    }

    public function test_fk_exige_sucursal_existente(): void
    {
        $this->expectException(QueryException::class);

        ResguardoPdv::query()->create([
            'sucursal_id' => 999999,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'cantidad_bultos_esperada' => 1,
            'version' => 1,
        ]);
    }

    private function crearPedido(Sucursal $destino): PedidoBma
    {
        $listaId = CatalogoListaDescuento::query()->value('id');
        if ($listaId === null) {
            $listaId = CatalogoListaDescuento::query()->create([
                'nombre' => 'PUBLICO GENERAL PDV',
            ])->id;
        }

        $cliente = Cliente::query()->create([
            'numero_cliente' => (string) fake()->unique()->numerify('90###'),
            'nombre' => 'Cliente resguardo PDV',
            'lista_actual_id' => $listaId,
            'monto_venta_actual' => 0,
        ]);

        return PedidoBma::query()->create([
            'folio' => 'PDV-'.fake()->unique()->numerify('#####'),
            'fecha' => now()->toDateString(),
            'vendedor_id' => User::factory()->create()->id,
            'cliente_id' => $cliente->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::query()->value('id'),
            'sucursal_destino_id' => $destino->id,
            'total_mercancia' => 10,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'total_a_cobrar' => 10,
            'es_resguardo' => false,
        ]);
    }
}

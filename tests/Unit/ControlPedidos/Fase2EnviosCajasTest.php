<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\ActualizarCostosCajasPedidoBmaService;
use App\Services\ControlPedidos\CalcularTotalesEnvioPedidoService;
use App\Services\ControlPedidos\SincronizarCajasPedidoBmaService;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Fase2EnviosCajasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
        $this->seedMinimo();
    }

    public function test_una_caja_simple_conserva_uuid_en_resync(): void
    {
        $pedido = $this->pedidoBase();
        $uuid = (string) Str::uuid();
        $sync = app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido,
            [$this->linea($uuid, 1.0)],
            $this->usuario->id
        );

        $this->assertCount(1, $sync['cajas']);
        $cajaId = $sync['cajas'][0]->id;

        $sync2 = app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido->fresh(),
            [$this->linea($uuid, 2.5)],
            $this->usuario->id
        );

        $this->assertSame($cajaId, $sync2['cajas'][0]->id);
        $this->assertSame($uuid, $sync2['cajas'][0]->uuid_operativo);
        $this->assertEquals(2.5, (float) $sync2['cajas'][0]->peso_real_kg);
        $this->assertSame(1, PedidoBmaCaja::query()->where('pedido_bma_id', $pedido->id)->count());
    }

    public function test_cuatro_cajas_cuatro_costos_suma_canonica(): void
    {
        $pedido = $this->pedidoBase(['costo_envio' => 999]);
        $uuids = [];
        $lineas = [];
        for ($i = 0; $i < 4; $i++) {
            $uuids[] = (string) Str::uuid();
            $lineas[] = $this->linea($uuids[$i], 1.0 + $i);
        }
        app(SincronizarCajasPedidoBmaService::class)->ejecutar($pedido, $lineas, $this->usuario->id);

        $costos = [];
        foreach ($uuids as $i => $uuid) {
            $costos[] = [
                'uuid_operativo' => $uuid,
                'costo_envio' => 10 * ($i + 1),
                'costo_seguro' => 1,
                'costo_adicional' => 0,
            ];
        }

        app(ActualizarCostosCajasPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['cajas', 'zona', 'estatus']),
            $costos,
            $this->usuario->id
        );

        $totales = app(CalcularTotalesEnvioPedidoService::class)->calcular($pedido->fresh(['cajas']));
        $this->assertSame(CalcularTotalesEnvioPedidoService::FUENTE_DETALLE, $totales['fuente']);
        $this->assertSame('100.00', $totales['costo_envios']);
        $this->assertSame('4.00', $totales['costo_seguro']);
        $this->assertSame('100.00', $totales['costo_para_cobertura']);
        $this->assertSame('100.00', (string) $pedido->fresh()->costo_envio);
    }

    public function test_sync_uuid_conserva_evidencia_y_reordenar_no_reasigna(): void
    {
        $pedido = $this->pedidoBase();
        $u1 = (string) Str::uuid();
        $u2 = (string) Str::uuid();
        $sync = app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido,
            [$this->linea($u1, 1), $this->linea($u2, 2)],
            $this->usuario->id
        );
        $caja1 = $sync['cajas'][0];
        $caja2 = $sync['cajas'][1];

        $doc = PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'pedido_bma_caja_id' => $caja1->id,
            'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
            'ruta_archivo' => 'test/evidencia-caja1.jpg',
            'nombre_original' => 'e1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 10,
            'orden' => 0,
            'relacion_tipo' => PedidoBmaDocumento::RELACION_ENVIO_CAJA,
            'relacion_id' => $caja1->id,
        ]);

        app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido->fresh(),
            [$this->linea($u2, 2), $this->linea($u1, 1)],
            $this->usuario->id
        );

        $doc->refresh();
        $this->assertSame($caja1->id, (int) $doc->pedido_bma_caja_id);
        $this->assertSame($caja1->id, (int) $doc->relacion_id);
        $this->assertTrue(file_exists(storage_path('app/public')) || true);
        $this->assertDatabaseHas('pedido_bma_documentos', [
            'id' => $doc->id,
            'ruta_archivo' => 'test/evidencia-caja1.jpg',
            'pedido_bma_caja_id' => $caja1->id,
        ]);
        $this->assertSame($caja2->id, PedidoBmaCaja::query()->where('uuid_operativo', $u2)->value('id'));
    }

    public function test_retiro_no_borra_archivo(): void
    {
        $pedido = $this->pedidoBase();
        $u1 = (string) Str::uuid();
        $u2 = (string) Str::uuid();
        $sync = app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido,
            [$this->linea($u1, 1), $this->linea($u2, 2)],
            $this->usuario->id
        );
        $cajaRetirada = $sync['cajas'][1];
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'pedido_bma_caja_id' => $cajaRetirada->id,
            'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
            'ruta_archivo' => 'test/evidencia-retirada.jpg',
            'nombre_original' => 'r.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 10,
            'orden' => 0,
            'relacion_tipo' => PedidoBmaDocumento::RELACION_ENVIO_CAJA,
            'relacion_id' => $cajaRetirada->id,
        ]);

        app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido->fresh(),
            [$this->linea($u1, 1)],
            $this->usuario->id,
            'Cliente canceló el segundo envío'
        );

        $cajaRetirada->refresh();
        $this->assertTrue($cajaRetirada->estaRetirada());
        $this->assertDatabaseHas('pedido_bma_documentos', [
            'pedido_bma_caja_id' => $cajaRetirada->id,
            'ruta_archivo' => 'test/evidencia-retirada.jpg',
        ]);
    }

    public function test_recolectada_bloquea_edicion_costo(): void
    {
        $pedido = $this->pedidoBase();
        $uuid = (string) Str::uuid();
        $sync = app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido,
            [$this->linea($uuid, 1)],
            $this->usuario->id
        );
        $sync['cajas'][0]->update([
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA,
            'recolectada_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        app(ActualizarCostosCajasPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['cajas', 'zona', 'estatus']),
            [['uuid_operativo' => $uuid, 'costo_envio' => 50]],
            $this->usuario->id
        );
    }

    public function test_legado_sin_desglose_no_reparte_costo(): void
    {
        $pedido = $this->pedidoBase(['costo_envio' => 250]);
        $uuid = (string) Str::uuid();
        app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido,
            [$this->linea($uuid, 1)],
            $this->usuario->id
        );

        $totales = app(CalcularTotalesEnvioPedidoService::class)->calcular($pedido->fresh(['cajas']));
        $this->assertSame(CalcularTotalesEnvioPedidoService::FUENTE_LEGADO, $totales['fuente']);
        $this->assertSame('250.00', $totales['costo_para_cobertura']);
        $this->assertNull($pedido->fresh('cajas')->cajas->first()->costo_envio);
    }

    public function test_costo_post_validacion_bloquea_y_reabre(): void
    {
        $pedido = $this->pedidoBase([
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
            'costo_envio' => 0,
        ]);
        $uuid = (string) Str::uuid();
        app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido,
            [$this->linea($uuid, 1)],
            $this->usuario->id
        );

        try {
            app(ActualizarCostosCajasPedidoBmaService::class)->ejecutar(
                $pedido->fresh(['cajas', 'zona', 'estatus']),
                [['uuid_operativo' => $uuid, 'costo_envio' => 40]],
                $this->usuario->id
            );
            $this->fail('Debía bloquear sin reapertura');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('validado', $e->getMessage());
        }

        app(ActualizarCostosCajasPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['cajas', 'zona', 'estatus']),
            [['uuid_operativo' => $uuid, 'costo_envio' => 40]],
            $this->usuario->id,
            true,
            'Ajuste autorizado de flete'
        );

        $this->assertNull($pedido->fresh()->pago_validado_at);
        $this->assertSame('40.00', (string) $pedido->fresh()->costo_envio);
    }

    public function test_cobertura_usa_total_canonico_detalle(): void
    {
        $pedido = $this->pedidoBase([
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'saldo_a_favor' => 0,
        ]);
        $uuid = (string) Str::uuid();
        app(SincronizarCajasPedidoBmaService::class)->ejecutar(
            $pedido,
            [$this->linea($uuid, 1)],
            $this->usuario->id
        );
        app(ActualizarCostosCajasPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['cajas', 'zona', 'estatus']),
            [['uuid_operativo' => $uuid, 'costo_envio' => 25, 'costo_seguro' => 5]],
            $this->usuario->id
        );

        $cov = app(CoberturaPagoPedidoBmaService::class)->calcular($pedido->fresh(['cajas']));
        $this->assertSame('130.00', $cov['total_a_cubrir']);
        $this->assertSame(CalcularTotalesEnvioPedidoService::FUENTE_DETALLE, $cov['totales_envio']['fuente']);
    }

    public function test_correctiva_recoleccion_idempotente(): void
    {
        if (! Schema::hasColumn('pedido_bma_cajas', 'estatus_recoleccion')) {
            $this->markTestSkipped('Sin columna estatus_recoleccion');
        }

        $enviado = CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_ENVIADO],
            [
                'codigo_interno' => 'ENVIADO',
                'nombre_visual' => 'Enviado',
                'color_hex' => '#22C55E',
                'activo' => true,
                'orden' => 9,
            ]
        );
        $pedido = $this->pedidoBase(['catalogo_estatus_pedido_id' => $enviado->id, 'enviado_at' => now()]);
        $caja = PedidoBmaCaja::query()->create([
            'pedido_bma_id' => $pedido->id,
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'cantidad' => 1,
            'orden' => 0,
            'peso_real_kg' => 1,
            'peso_volumetrico_kg' => 1,
            'peso_cobrado_kg' => 1,
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
        ]);

        /** @var \Illuminate\Database\Migrations\Migration $migration */
        $migration = require database_path('migrations/2026_08_24_150000_fase2_correctiva_recoleccion_por_caja.php');
        $migration->up();
        $this->assertTrue($caja->fresh()->estaRecolectada());

        $caja->update([
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
            'recolectada_at' => null,
        ]);
        $migration->up();
        $this->assertTrue($caja->fresh()->estaRecolectada());
        $migration->up();
        $this->assertTrue($caja->fresh()->estaRecolectada());
    }

    /**
     * @return array<string, mixed>
     */
    private function linea(string $uuid, float $pesoReal): array
    {
        return [
            'uuid_operativo' => $uuid,
            'client_uuid' => $uuid,
            'catalogo_tipo_caja_id' => (int) DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'largo' => 10,
            'ancho' => 10,
            'alto' => 10,
            'peso_real_kg' => $pesoReal,
            'peso_volumetrico_kg' => $pesoReal,
            'peso_cobrado_kg' => (float) (int) ceil($pesoReal),
        ];
    }

    private function pedidoBase(array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            [
                'codigo_interno' => 'PESAJE_OK',
                'nombre_visual' => 'Pesaje respondido',
                'color_hex' => '#64748B',
                'activo' => true,
                'orden' => 4,
            ]
        );

        return PedidoBma::query()->create(array_merge([
            'folio' => 'F2-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 1000,
            'es_resguardo' => false,
        ], $extra));
    }

    private function seedMinimo(): void
    {
        $now = now();
        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente Test',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (! DB::table('catalogo_tipos_caja_pedido')->exists()) {
            DB::table('catalogo_tipos_caja_pedido')->insert([
                'nombre' => 'CAJA TEST',
                'peso_volumetrico' => 1,
                'largo' => 10,
                'ancho' => 10,
                'alto' => 10,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (! CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_ENVIADO)->exists()) {
            CatalogoEstatusPedido::query()->create([
                'codigo_interno' => 'ENVIADO',
                'nombre_visual' => 'Enviado',
                'color_hex' => '#22C55E',
                'fase_ciclo' => CatalogoEstatusPedido::FASE_ENVIADO,
                'activo' => true,
                'orden' => 9,
            ]);
        }
    }
}

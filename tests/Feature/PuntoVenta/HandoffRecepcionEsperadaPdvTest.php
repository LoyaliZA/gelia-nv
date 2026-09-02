<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\RecepcionEsperadaPdvCreada;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\PuntoVenta\Resguardos\CrearRecepcionEsperadaPdvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HandoffRecepcionEsperadaPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->seedCatalogosMinimos();
        $this->sucursal = Sucursal::factory()->create(['activo' => true]);
    }

    public function test_primer_handoff_crea_recepcion_esperada_auditable(): void
    {
        Event::fake([RecepcionEsperadaPdvCreada::class]);

        $pedido = $this->pedidoMostradorListoParaEnvio();

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id
        );

        $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->first();
        $this->assertNotNull($resguardo);
        $this->assertSame($this->sucursal->id, $resguardo->sucursal_id);
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->estado);
        $this->assertNotNull($resguardo->salida_cedis_at);
        $this->assertSame(1, ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->count());

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertNotNull($evento);
        $this->assertSame(ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA, $evento->tipo_evento);
        $this->assertSame(
            CrearRecepcionEsperadaPdvService::claveIdempotencia((int) $pedido->id, (int) $this->sucursal->id),
            $evento->idempotency_key
        );

        Event::assertDispatched(RecepcionEsperadaPdvCreada::class, function (RecepcionEsperadaPdvCreada $e) use ($pedido, $resguardo) {
            return $e->resguardo->is($resguardo)
                && $e->pedidoBmaId === (int) $pedido->id
                && $e->sucursalId === (int) $this->sucursal->id;
        });
    }

    public function test_reintento_devuelve_el_mismo_resguardo_sin_duplicar_eventos(): void
    {
        Event::fake([RecepcionEsperadaPdvCreada::class]);

        $pedido = $this->pedidoMostradorListoParaEnvio();

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id
        );

        $primero = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->firstOrFail();

        $segundo = app(CrearRecepcionEsperadaPdvService::class)->ejecutar(
            $pedido->fresh(['estatus', 'origen']),
            $this->usuario->id
        );

        $this->assertTrue($primero->is($segundo));
        $this->assertSame(1, ResguardoPdv::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        Event::assertDispatchedTimes(RecepcionEsperadaPdvCreada::class, 1);
    }

    public function test_transaccion_fallida_no_deja_registros_ni_publica_el_evento(): void
    {
        $pedido = $this->pedidoMostradorListoParaEnvio();
        $enviado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENVIADO);
        $pedido->update(['catalogo_estatus_pedido_id' => $enviado->id]);

        $publicado = false;
        Event::listen(RecepcionEsperadaPdvCreada::class, function () use (&$publicado) {
            $publicado = true;
        });

        try {
            DB::transaction(function () use ($pedido) {
                app(CrearRecepcionEsperadaPdvService::class)->ejecutar(
                    $pedido->fresh(['estatus', 'origen']),
                    $this->usuario->id
                );
                throw new \RuntimeException('fallo forzado');
            });
            $this->fail('Debía revertir la transacción');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo forzado', $e->getMessage());
        }

        $this->assertSame(0, ResguardoPdv::query()->count());
        $this->assertSame(0, ResguardoPdvEvento::query()->count());
        $this->assertFalse($publicado);
    }

    public function test_pedido_no_elegible_no_crea_resguardo(): void
    {
        Event::fake([RecepcionEsperadaPdvCreada::class]);

        $pedido = $this->pedidoForaneoListoParaEnvio();

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id
        );

        $this->assertSame(0, ResguardoPdv::query()->count());
        Event::assertNotDispatched(RecepcionEsperadaPdvCreada::class);

        $this->expectException(ValidationException::class);
        app(CrearRecepcionEsperadaPdvService::class)->ejecutar($pedido->fresh(['estatus', 'origen']));
    }

    public function test_mostrador_sin_destino_no_marca_enviado_ni_crea_resguardo(): void
    {
        $pedido = $this->pedidoMostradorListoParaEnvio(['sucursal_destino_id' => null]);

        try {
            app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
                $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
                $this->usuario->id
            );
            $this->fail('Debía rechazar el pedido sin sucursal destino');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sucursal_destino_id', $e->errors());
        }

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $pedido->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertSame(0, ResguardoPdv::query()->count());
    }

    public function test_recoleccion_parcial_no_ejecuta_el_handoff(): void
    {
        Event::fake([RecepcionEsperadaPdvCreada::class]);

        $pedido = $this->pedidoMostradorListoParaEnvioConCajas(2);
        $cajas = $pedido->cajas()->orderBy('orden')->get();

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id,
            [['id' => $cajas[0]->id]]
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $pedido->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertSame(0, ResguardoPdv::query()->count());
        Event::assertNotDispatched(RecepcionEsperadaPdvCreada::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pedidoMostradorListoParaEnvio(array $overrides = []): PedidoBma
    {
        $pedido = $this->crearPedidoEnCedis(array_merge([
            'origen_id' => $this->origenMostrador()->id,
            'catalogo_paqueteria_id' => null,
            'sucursal_destino_id' => $this->sucursal->id,
            'numero_cajas' => null,
            'costo_envio' => 0,
        ], $overrides));

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        return $pedido->fresh(['estatus', 'origen', 'cajas', 'cliente']);
    }

    private function pedidoMostradorListoParaEnvioConCajas(int $n): PedidoBma
    {
        $pedido = $this->pedidoMostradorListoParaEnvio(['numero_cajas' => $n]);
        $tipoCajaId = DB::table('catalogo_tipos_caja_pedido')->value('id');

        for ($i = 0; $i < $n; $i++) {
            PedidoBmaCaja::query()->create([
                'pedido_bma_id' => $pedido->id,
                'catalogo_tipo_caja_id' => $tipoCajaId,
                'cantidad' => 1,
                'orden' => $i,
                'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
            ]);
        }

        return $pedido->fresh(['estatus', 'origen', 'cajas']);
    }

    private function pedidoForaneoListoParaEnvio(): PedidoBma
    {
        $pedido = $this->crearPedidoEnCedis([
            'origen_id' => $this->origenForaneo()->id,
            'catalogo_paqueteria_id' => $this->paqueteriaLocalRegionalId(),
            'sucursal_destino_id' => null,
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        return $pedido->fresh(['estatus', 'origen', 'cajas', 'paqueteria']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearPedidoEnCedis(array $overrides = []): PedidoBma
    {
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);

        $pedido = PedidoBma::query()->create(array_merge([
            'folio' => 'PED-PDV-'.uniqid(),
            'folio_remision' => 'REM-PDV-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenForaneo()->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 1.5,
            'catalogo_paqueteria_id' => $this->paqueteriaLocalRegionalId(),
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'catalogo_estatus_pedido_id' => $enCedis->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ], $overrides));

        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'test/remision.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 0,
        ]);

        return $pedido;
    }

    private function origenForaneo(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::query()->firstOrCreate(
            ['nombre' => 'Envío Foráneo'],
            ['requiere_logistica' => true, 'activo' => true]
        );
    }

    private function origenMostrador(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::query()->firstOrCreate(
            ['nombre' => 'Mostrador'],
            ['requiere_logistica' => false, 'activo' => true]
        );
    }

    private function paqueteriaLocalRegionalId(): int
    {
        return (int) DB::table('catalogo_paqueterias_pedido')
            ->where('categoria', 'local_regional')
            ->value('id');
    }

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::query()->exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'AZUL ①', 'color_hex' => '#3B82F6', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 2],
                ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'AMARILLO', 'color_hex' => '#EAB308', 'fase_ciclo' => 'EN_CEDIS', 'orden' => 3],
                ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'color_hex' => '#A855F7', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 7],
                ['codigo_interno' => 'PENDIENTE_ENVIO', 'nombre_visual' => 'Pendiente de envío', 'color_hex' => '#0EA5E9', 'fase_ciclo' => 'PENDIENTE_DE_ENVIO', 'orden' => 10],
                ['codigo_interno' => 'ENTREGADO', 'nombre_visual' => 'Entregado', 'color_hex' => '#10B981', 'fase_ciclo' => 'ENTREGADO', 'orden' => 8],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
            ] as $row) {
                CatalogoEstatusPedido::query()->create(array_merge($row, ['activo' => true]));
            }
        }

        $this->origenMostrador();
        $this->origenForaneo();

        if (! DB::table('catalogo_bancos')->exists()) {
            DB::table('catalogo_bancos')->insert([
                'nombre' => 'BBVA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_paqueterias_pedido')->where('categoria', 'local_regional')->exists()) {
            $existente = DB::table('catalogo_paqueterias_pedido')->where('nombre', 'TAXI FRONTERA')->first();
            if ($existente) {
                DB::table('catalogo_paqueterias_pedido')
                    ->where('id', $existente->id)
                    ->update(['categoria' => 'local_regional', 'updated_at' => $now]);
            } else {
                DB::table('catalogo_paqueterias_pedido')->insert([
                    'nombre' => 'TAXI FRONTERA',
                    'categoria' => 'local_regional',
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (! DB::table('catalogo_tipos_caja_pedido')->exists()) {
            DB::table('catalogo_tipos_caja_pedido')->insert([
                'nombre' => 'CAJA TEST',
                'peso_volumetrico' => 1,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_tipos_guia_pedido')->exists()) {
            DB::table('catalogo_tipos_guia_pedido')->insert([
                'nombre' => 'Terrestre', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_zonas_pedido')->exists()) {
            DB::table('catalogo_zonas_pedido')->insert([
                'nombre' => 'Sin reexpedición', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente Test',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'vendedor_id' => $this->usuario->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('almacenes')->exists()) {
            DB::table('almacenes')->insert([
                'codigo' => 'VTA',
                'nombre' => 'CEDIS',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

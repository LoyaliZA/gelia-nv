<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ControlPedidosRecoleccionParcialTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->seedCatalogosMinimos();
    }

    public function test_parcial_no_marca_pedido_enviado(): void
    {
        $pedido = $this->pedidoListoParaEnvioConCajas(3);

        $cajas = $pedido->cajas()->orderBy('orden')->get();
        $parcial = app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id,
            [['id' => $cajas[0]->id]]
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $parcial->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertTrue($cajas[0]->fresh()->estaRecolectada());
        $this->assertTrue($cajas[1]->fresh()->estaPendiente());
        $this->assertTrue($cajas[2]->fresh()->estaPendiente());
        $this->assertSame(1, $parcial->fresh('cajas')->cajas_recolectadas);
        $this->assertSame(2, $parcial->fresh('cajas')->cajas_pendientes);
    }

    public function test_completar_cajas_marca_enviado(): void
    {
        $pedido = $this->pedidoListoParaEnvioConCajas(3);
        $cajas = $pedido->cajas()->orderBy('orden')->get();

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id,
            [['id' => $cajas[0]->id]]
        );

        $enviado = app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id,
            [
                ['id' => $cajas[1]->id],
                ['id' => $cajas[2]->id],
            ]
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_ENVIADO,
            $enviado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertSame(0, $enviado->fresh('cajas')->cajas_pendientes);
        $this->assertSame(3, $enviado->fresh('cajas')->cajas_recolectadas);
    }

    public function test_multi_caja_sin_seleccion_falla(): void
    {
        $pedido = $this->pedidoListoParaEnvioConCajas(2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Selecciona qué envíos se recolectaron');

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id,
            null
        );
    }

    public function test_una_caja_sin_seleccion_marca_enviado(): void
    {
        $pedido = $this->pedidoListoParaEnvioConCajas(1);

        $enviado = app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id,
            null
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_ENVIADO,
            $enviado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertTrue($pedido->cajas()->first()->fresh()->estaRecolectada());
    }

    public function test_peso_cobrado_sigue_siendo_max_por_caja(): void
    {
        $this->assertSame(5.0, PedidoBma::calcularPesoCobradoGuia(3.0, 5.0));
        $this->assertSame(4.0, PedidoBma::calcularPesoCobradoGuia(4.0, 2.0));

        $cobrado1 = PedidoBma::calcularPesoCobradoGuia(5.0, 3.0);
        $cobrado2 = PedidoBma::calcularPesoCobradoGuia(1.0, 10.0);
        $this->assertSame(15.0, round($cobrado1 + $cobrado2, 4));
    }

    public function test_guia_opcional_por_caja(): void
    {
        $pedido = $this->pedidoListoParaEnvioConCajas(2);
        $cajas = $pedido->cajas()->orderBy('orden')->get();

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id,
            [['id' => $cajas[0]->id, 'numero_rastreo' => 'GUIA-CAJA-1']]
        );

        $this->assertSame('GUIA-CAJA-1', $cajas[0]->fresh()->numero_rastreo);
        $this->assertNull($cajas[1]->fresh()->numero_rastreo);
    }

    private function pedidoListoParaEnvioConCajas(int $n): PedidoBma
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
            'numero_cajas' => $n,
        ]);

        $tipoCajaId = DB::table('catalogo_tipos_caja_pedido')->value('id');
        for ($i = 0; $i < $n; $i++) {
            PedidoBmaCaja::create([
                'pedido_bma_id' => $pedido->id,
                'catalogo_tipo_caja_id' => $tipoCajaId,
                'cantidad' => 1,
                'orden' => $i,
                'largo' => 30,
                'ancho' => 20,
                'alto' => 15,
                'peso_real_kg' => 2 + $i,
                'peso_volumetrico_kg' => 3,
                'peso_cobrado_kg' => PedidoBma::calcularPesoCobradoGuia(2 + $i, 3),
                'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
            ]);
        }

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'GUIA-MULTI-'.$pedido->id,
            $this->usuario->id
        );

        return $pedido->fresh(['estatus', 'cajas', 'paqueteria', 'origen']);
    }

    private function origenForaneo(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::firstOrCreate(
            ['nombre' => 'Envío Foráneo'],
            ['requiere_logistica' => true, 'activo' => true]
        );
    }

    private function origenMostrador(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::firstOrCreate(
            ['nombre' => 'Mostrador'],
            ['requiere_logistica' => false, 'activo' => true]
        );
    }

    private function paqueteriaComercialId(): int
    {
        return (int) DB::table('catalogo_paqueterias_pedido')
            ->where('categoria', 'comercial')
            ->value('id');
    }

    private function crearPedidoAprobadoCedis(array $overrides = []): PedidoBma
    {
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);

        $pedido = PedidoBma::create(array_merge([
            'folio' => 'PED-REC-'.uniqid(),
            'folio_remision' => 'REM-REC-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenForaneo()->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 1.5,
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'catalogo_envio_tienda_id' => DB::table('catalogo_envios_tienda')->value('id'),
            'codigo_postal' => '86000',
            'domicilio_entrega' => 'Calle Test 123',
            'total_mercancia' => 1000,
            'costo_envio' => 150,
            'catalogo_estatus_pedido_id' => $enCedis->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ], $overrides));

        PedidoBmaDocumento::create([
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

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'AZUL ①', 'color_hex' => '#3B82F6', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 2],
                ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'AMARILLO', 'color_hex' => '#EAB308', 'fase_ciclo' => 'EN_CEDIS', 'orden' => 3],
                ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'color_hex' => '#A855F7', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 7],
                ['codigo_interno' => 'PENDIENTE_ENVIO', 'nombre_visual' => 'Pendiente de envío', 'color_hex' => '#0EA5E9', 'fase_ciclo' => 'PENDIENTE_DE_ENVIO', 'orden' => 10],
                ['codigo_interno' => 'ENTREGADO', 'nombre_visual' => 'Entregado', 'color_hex' => '#10B981', 'fase_ciclo' => 'ENTREGADO', 'orden' => 8],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
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

        if (! DB::table('catalogo_paqueterias_pedido')->where('categoria', 'comercial')->exists()) {
            $existente = DB::table('catalogo_paqueterias_pedido')->where('nombre', 'FEDEX')->first();
            if ($existente) {
                DB::table('catalogo_paqueterias_pedido')
                    ->where('id', $existente->id)
                    ->update(['categoria' => 'comercial', 'updated_at' => $now]);
            } else {
                DB::table('catalogo_paqueterias_pedido')->insert([
                    'nombre' => 'FEDEX',
                    'categoria' => 'comercial',
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

        if (! DB::table('catalogo_envios_tienda')->exists()) {
            DB::table('catalogo_envios_tienda')->insert([
                'nombre' => 'Tienda', 'es_otro' => false, 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}

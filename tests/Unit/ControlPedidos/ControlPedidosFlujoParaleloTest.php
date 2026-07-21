<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosDelegadoService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ControlPedidosFlujoParaleloTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->seedCatalogosMinimos();
    }

    public function test_delegado_lista_pedido_en_cedis_sin_empacar(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        $this->assertNull($pedido->empacado_at);

        $resultado = app(ListarPedidosDelegadoService::class)->ejecutar([], false);

        $this->assertTrue($resultado->contains('id', $pedido->id));
    }

    public function test_asignar_guia_falla_en_resguardo(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
            'es_resguardo' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('resguardo');

        app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen']),
            'GUIA-RESG-001',
            $this->usuario->id
        );
    }

    public function test_asignar_guia_desde_en_cedis_conserva_fase(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
            'es_resguardo' => false,
        ]);

        $actualizado = app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen']),
            'GUIA-PARALELO-001',
            $this->usuario->id
        );

        $this->assertSame('GUIA-PARALELO-001', $actualizado->numero_rastreo);
        $this->assertNotNull($actualizado->guia_subida_at);
        $this->assertSame(
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertNull($actualizado->empacado_at);
        $this->assertTrue($actualizado->fresh('estatus')->guiaSoloLecturaHastaEmpaque());
    }

    public function test_empacar_con_guia_previa_va_a_pendiente_de_envio(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen']),
            'GUIA-ANTES-EMPAQUE',
            $this->usuario->id
        );

        $empacado = app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen']),
            $this->usuario->id
        );

        $this->assertNotNull($empacado->empacado_at);
        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $empacado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_empacar_sin_guia_comercial_va_a_pendiente_de_guia(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        $empacado = app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen']),
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            $empacado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_etiqueta_semantica_borrador_con_resguardo_sigue_siendo_borrador(): void
    {
        $borrador = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)
            ?? CatalogoEstatusPedido::create([
                'codigo_interno' => 'BORRADOR',
                'nombre_visual' => 'Borrador',
                'color_hex' => '#94A3B8',
                'fase_ciclo' => 'BORRADOR',
                'orden' => 1,
                'activo' => true,
            ]);

        $this->assertSame('Borrador', $borrador->etiquetaSemantica(true));
        $this->assertSame('Borrador', $borrador->etiquetaSemantica(false));
    }

    public function test_etiqueta_semantica_en_cedis_con_resguardo_dice_resguardo(): void
    {
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);

        $this->assertSame('Resguardo', $enCedis->etiquetaSemantica(true));
        $this->assertSame('En CEDIS', $enCedis->etiquetaSemantica(false));
    }

    private function origenForaneo(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::firstOrCreate(
            ['nombre' => 'Envío Foráneo'],
            ['requiere_logistica' => true, 'activo' => true]
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
            'folio' => 'PED-PAR-' . uniqid(),
            'folio_remision' => 'REM-PAR-001',
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

        if (!CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'AMARILLO', 'color_hex' => '#EAB308', 'fase_ciclo' => 'EN_CEDIS', 'orden' => 3],
                ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'color_hex' => '#A855F7', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 7],
                ['codigo_interno' => 'PENDIENTE_ENVIO', 'nombre_visual' => 'Pendiente de envío', 'color_hex' => '#0EA5E9', 'fase_ciclo' => 'PENDIENTE_DE_ENVIO', 'orden' => 10],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
            }
        }

        $this->origenForaneo();

        if (!DB::table('catalogo_bancos')->exists()) {
            DB::table('catalogo_bancos')->insert([
                'nombre' => 'BBVA',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('catalogo_paqueterias_pedido')->where('categoria', 'comercial')->exists()) {
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

        if (!DB::table('catalogo_tipos_caja_pedido')->exists()) {
            DB::table('catalogo_tipos_caja_pedido')->insert([
                'nombre' => 'CAJA TEST',
                'peso_volumetrico' => 1,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('catalogo_tipos_guia_pedido')->exists()) {
            DB::table('catalogo_tipos_guia_pedido')->insert([
                'nombre' => 'Terrestre',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('catalogo_zonas_pedido')->exists()) {
            DB::table('catalogo_zonas_pedido')->insert([
                'nombre' => 'Sin reexpedición',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente Test',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'vendedor_id' => $this->usuario->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('almacenes')->exists()) {
            DB::table('almacenes')->insert([
                'codigo' => 'VTA',
                'nombre' => 'CEDIS',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('catalogo_envios_tienda')->exists()) {
            DB::table('catalogo_envios_tienda')->insert([
                'nombre' => 'Tienda',
                'es_otro' => false,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

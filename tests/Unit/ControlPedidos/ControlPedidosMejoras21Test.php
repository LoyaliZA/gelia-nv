<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ControlPedidosMejoras21Test extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
    }

    public function test_enviar_falla_sin_folio_remision(): void
    {
        $pedido = $this->crearPedidoBase(['folio_remision' => null]);
        $this->agregarComprobante($pedido);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('folio de pedido');

        app(EnviarPedidoBmaService::class)->ejecutar($pedido->fresh(['origen']), $this->usuario->id);
    }

    public function test_enviar_exitoso_con_folio_remision(): void
    {
        $pedido = $this->crearPedidoBase(['folio_remision' => 'REM-12345']);
        $this->agregarComprobante($pedido);

        $actualizado = app(EnviarPedidoBmaService::class)->ejecutar($pedido->fresh(['origen']), $this->usuario->id);

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_busqueda_encuentra_por_folio_remision(): void
    {
        $pedido = $this->crearPedidoBase(['folio_remision' => 'REM-UNICO-XYZ']);

        $resultado = app(ListarPedidosBmaService::class)->ejecutar($this->usuario, ['q' => 'REM-UNICO-XYZ'], false);

        $this->assertTrue($resultado->contains('id', $pedido->id));
    }

    private function origenForaneo(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::firstOrCreate(
            ['nombre' => 'Envío Foráneo'],
            ['requiere_logistica' => true, 'activo' => true]
        );
    }

    private function crearPedidoBase(array $overrides = []): PedidoBma
    {
        $this->seedCatalogosMinimos();

        $borrador = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)
            ?? CatalogoEstatusPedido::first();

        $defaults = [
            'folio' => 'PED-TEST-' . uniqid(),
            'folio_remision' => 'REM-TEST-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenForaneo()->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 1.5,
            'catalogo_paqueteria_id' => DB::table('catalogo_paqueterias_pedido')->value('id'),
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'catalogo_envio_tienda_id' => DB::table('catalogo_envios_tienda')->value('id'),
            'codigo_postal' => '86000',
            'domicilio_entrega' => 'Calle Test 123',
            'total_mercancia' => 1000,
            'costo_envio' => 150,
            'catalogo_estatus_pedido_id' => $borrador->id,
            'es_resguardo' => false,
        ];

        return PedidoBma::create(array_merge($defaults, $overrides));
    }

    private function agregarComprobante(PedidoBma $pedido): void
    {
        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_COMPROBANTE,
            'ruta_archivo' => 'test/comprobante.jpg',
            'nombre_original' => 'comprobante.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100,
            'orden' => 0,
        ]);
    }

    private function seedCatalogosMinimos(): void
    {
        static $seeded = false;
        if ($seeded) {
            return;
        }

        $now = now();

        if (!CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'AZUL ①', 'color_hex' => '#3B82F6', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 2],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
            }
        }

        $this->origenForaneo();

        if (!DB::table('catalogo_bancos')->exists()) {
            DB::table('catalogo_bancos')->insert(['nombre' => 'BBVA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert(['nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!DB::table('catalogo_paqueterias_pedido')->exists()) {
            DB::table('catalogo_paqueterias_pedido')->insert(['nombre' => 'FEDEX', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!DB::table('catalogo_tipos_caja_pedido')->exists()) {
            DB::table('catalogo_tipos_caja_pedido')->insert(['nombre' => 'CAJA TEST', 'peso_volumetrico' => 1, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!DB::table('catalogo_tipos_guia_pedido')->exists()) {
            DB::table('catalogo_tipos_guia_pedido')->insert(['nombre' => 'Terrestre', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!DB::table('catalogo_zonas_pedido')->exists()) {
            DB::table('catalogo_zonas_pedido')->insert(['nombre' => 'Sin reexpedición', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
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
            DB::table('almacenes')->insert(['codigo' => 'VTA', 'nombre' => 'CEDIS', 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!DB::table('catalogo_envios_tienda')->exists()) {
            DB::table('catalogo_envios_tienda')->insert(['nombre' => 'Tienda', 'es_otro' => false, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $seeded = true;
    }
}

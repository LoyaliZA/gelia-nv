<?php

namespace Tests\Unit\ControlPedidos\Direcciones;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SnapshotDireccionPedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
        config(['control_pedidos.direcciones_normalizadas' => true]);
    }

    public function test_enviar_crea_snapshot_y_proyecta_domicilio(): void
    {
        $this->seedCatalogosMinimos();
        $cliente = $this->crearCliente();
        $direccion = app(GestionDireccionesClienteService::class)->crearPrimeraDireccion($cliente->id, [
            'nombre_destinatario' => 'Receptor',
            'calle' => 'Insurgentes',
            'numero_exterior' => '100',
            'colonia' => 'Del Valle',
            'codigo_postal' => '03100',
            'municipio' => 'BJ',
            'estado' => 'CDMX',
            'pais' => 'México',
        ], ['verificar' => true]);

        $pedido = $this->crearPedidoBase([
            'cliente_id' => $cliente->id,
            'cliente_direccion_id' => $direccion->id,
            'origen_id' => $this->origenForaneo()->id,
        ]);
        $this->agregarComprobante($pedido);

        $actualizado = app(EnviarPedidoBmaService::class)->ejecutar($pedido->fresh(['origen']), $this->usuario->id);

        $this->assertDatabaseHas('pedido_bma_direcciones', [
            'pedido_bma_id' => $actualizado->id,
            'es_vigente' => 1,
            'cliente_direccion_id' => $direccion->id,
        ]);
        $this->assertStringContainsString('Insurgentes', (string) $actualizado->fresh()->domicilio_entrega);
        $this->assertSame('03100', $actualizado->fresh()->codigo_postal);
    }

    public function test_resguardo_no_permite_empacar(): void
    {
        $this->seedCatalogosMinimos();
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);
        $pedido = $this->crearPedidoBase([
            'catalogo_estatus_pedido_id' => $enCedis->id,
            'es_resguardo' => true,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ]);
        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'test/remision.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('resguardo');

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($pedido->fresh(['paqueteria', 'origen', 'estatus']), $this->usuario->id);
    }

    private function crearCliente(): Cliente
    {
        $lista = CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );

        return Cliente::create([
            'numero_cliente' => (string) random_int(80000, 89999),
            'nombre' => 'Cliente Snapshot',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
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
        $borrador = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)
            ?? CatalogoEstatusPedido::first();

        $defaults = [
            'folio' => 'PED-SNAP-'.uniqid(),
            'folio_remision' => 'REM-SNAP-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id') ?: $this->crearCliente()->id,
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
            'domicilio_entrega' => 'Calle Legacy',
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
            'ruta_archivo' => 'test/comp.jpg',
            'nombre_original' => 'comp.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 10,
            'orden' => 0,
        ]);
    }

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'AZUL ①', 'color_hex' => '#3B82F6', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 2],
                ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'AMARILLO', 'color_hex' => '#EAB308', 'fase_ciclo' => 'EN_CEDIS', 'orden' => 3],
                ['codigo_interno' => 'NARANJA', 'nombre_visual' => 'Pendiente guía', 'color_hex' => '#F97316', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 4],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
            }
        }

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
        if (! DB::table('catalogo_paqueterias_pedido')->exists()) {
            DB::table('catalogo_paqueterias_pedido')->insert([
                'nombre' => 'FEDEX', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('catalogo_tipos_caja_pedido')->exists()) {
            DB::table('catalogo_tipos_caja_pedido')->insert([
                'nombre' => 'CAJA TEST', 'peso_volumetrico' => 1, 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
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
        if (! DB::table('almacenes')->exists()) {
            DB::table('almacenes')->insert([
                'codigo' => 'VTA', 'nombre' => 'CEDIS', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('catalogo_envios_tienda')->exists()) {
            DB::table('catalogo_envios_tienda')->insert([
                'nombre' => 'Tienda', 'es_otro' => false, 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('clientes')->exists()) {
            $this->crearCliente();
        }
    }
}

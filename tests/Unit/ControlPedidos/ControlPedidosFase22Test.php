<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\ActualizarGuiaPedidoBmaService;
use App\Services\ControlPedidos\ImportarGuiasPedidoService;
use App\Services\ControlPedidos\ListarPedidosCedisService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\ControlPedidos\RevertirEmpacadoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class ControlPedidosFase22Test extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->seedCatalogosMinimos();
    }

    public function test_empacar_paqueteria_comercial_pasa_a_pendiente_de_guia(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        $actualizado = app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_empacar_comercial_aparece_en_tab_empacados_cedis(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $pendientes = app(ListarPedidosCedisService::class)->ejecutar(['tab' => 'PENDIENTES'], false);
        $this->assertFalse($pendientes->contains('id', $pedido->id));

        $empacados = app(ListarPedidosCedisService::class)->ejecutar(['tab' => 'EMPACADOS'], false);
        $this->assertTrue($empacados->contains('id', $pedido->id));
    }

    public function test_empacar_paqueteria_local_pasa_a_pendiente_de_envio(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaLocalId(),
        ]);

        $actualizado = app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_empacar_mostrador_sin_paqueteria_pasa_a_pendiente_de_envio(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'origen_id' => $this->origenMostrador()->id,
            'catalogo_paqueteria_id' => null,
        ]);

        $actualizado = app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_asignar_guia_individual_pasa_a_pendiente_de_envio(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $actualizado = app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'GUIA-IND-001',
            $this->usuario->id
        );

        $this->assertSame('GUIA-IND-001', $actualizado->numero_rastreo);
        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_importar_csv_valido_asigna_guia_y_pasa_a_pendiente_de_envio(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $ruta = $this->crearCsvTemporal([
            ['ID_Pedido' => $pedido->id, 'Folio' => $pedido->folio_remision, 'Paqueteria' => 'FEDEX', 'Cliente' => 'Test', 'Guia_Rastreo' => 'TRACK123456'],
        ]);

        $resultado = app(ImportarGuiasPedidoService::class)->ejecutar($ruta, $this->usuario->id);

        $this->assertSame(1, $resultado['actualizados']);
        $this->assertSame(0, $resultado['omitidos']);

        $pedido->refresh();
        $this->assertSame('TRACK123456', $pedido->numero_rastreo);
        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $pedido->fresh('estatus')->estatus->fase_ciclo
        );

        @unlink($ruta);
    }

    public function test_importar_csv_omite_filas_invalidas(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $ruta = $this->crearCsvTemporal([
            ['ID_Pedido' => 99999, 'Guia_Rastreo' => 'X'],
            ['ID_Pedido' => $pedido->id, 'Guia_Rastreo' => ''],
        ]);

        $resultado = app(ImportarGuiasPedidoService::class)->ejecutar($ruta, $this->usuario->id);

        $this->assertSame(0, $resultado['actualizados']);
        $this->assertSame(2, $resultado['omitidos']);
        $this->assertCount(2, $resultado['errores']);

        @unlink($ruta);
    }

    public function test_revertir_desde_pendiente_de_guia_vuelve_a_cedis(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $actualizado = app(RevertirEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_no_revertir_desde_pendiente_de_envio_con_guia(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $ruta = $this->crearCsvTemporal([
            ['ID_Pedido' => $pedido->id, 'Guia_Rastreo' => 'GUIA-FINAL'],
        ]);

        app(ImportarGuiasPedidoService::class)->ejecutar($ruta, $this->usuario->id);
        @unlink($ruta);

        $this->expectException(\RuntimeException::class);

        app(RevertirEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            $this->usuario->id
        );
    }

    public function test_cedis_marcar_enviado_desde_pendiente_de_envio(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'GUIA-ENVIO-FINAL',
            $this->usuario->id
        );

        $enviado = app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen']),
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_ENVIADO,
            $enviado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_actualizar_guia_en_pendiente_de_envio_conserva_fase(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'GUIA-ORIGINAL',
            $this->usuario->id
        );

        $actualizado = app(ActualizarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'GUIA-CORREGIDA',
            $this->usuario->id
        );

        $this->assertSame('GUIA-CORREGIDA', $actualizado->numero_rastreo);
        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    private function origenMostrador(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::firstOrCreate(
            ['nombre' => 'Mostrador'],
            ['requiere_logistica' => false, 'activo' => true]
        );
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

    private function paqueteriaLocalId(): int
    {
        return (int) DB::table('catalogo_paqueterias_pedido')
            ->where('categoria', 'local_regional')
            ->value('id');
    }

    private function crearPedidoAprobadoCedis(array $overrides = []): PedidoBma
    {
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);

        $pedido = PedidoBma::create(array_merge([
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

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function crearCsvTemporal(array $filas): string
    {
        $ruta = sys_get_temp_dir() . '/guias_test_' . uniqid() . '.csv';
        (new FastExcel(collect($filas)))->export($ruta);

        return $ruta;
    }

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (!CatalogoEstatusPedido::exists()) {
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
            DB::table('catalogo_paqueterias_pedido')->insert([
                'nombre' => 'FEDEX',
                'categoria' => 'comercial',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('catalogo_paqueterias_pedido')->where('categoria', 'local_regional')->exists()) {
            DB::table('catalogo_paqueterias_pedido')->insert([
                'nombre' => 'TAXI FRONTERA',
                'categoria' => 'local_regional',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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

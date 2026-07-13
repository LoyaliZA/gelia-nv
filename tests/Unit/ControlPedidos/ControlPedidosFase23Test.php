<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\GestionarGuiaPdfPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosCedisService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ControlPedidosFase23Test extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->seedCatalogosMinimos();
    }

    public function test_cedis_lista_pedido_empacado_en_tab_empacados(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $empacados = app(ListarPedidosCedisService::class)->ejecutar(['tab' => 'EMPACADOS'], false);

        $this->assertTrue($empacados->contains('id', $pedido->id));
        $this->assertNotNull($pedido->fresh()->empacado_at);
    }

    public function test_subir_pdf_guia_crea_documento_tipo_guia(): void
    {
        Storage::fake('public');

        $pedido = $this->empacarComercialPendienteGuia();
        $archivo = UploadedFile::fake()->create('guia-test.pdf', 100, 'application/pdf');

        $actualizado = app(GestionarGuiaPdfPedidoBmaService::class)->subir(
            $pedido->fresh('estatus'),
            $archivo
        );

        $this->assertTrue($actualizado->tieneGuiaPdf());
        $this->assertDatabaseHas('pedido_bma_documentos', [
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_GUIA,
        ]);
    }

    public function test_asignar_numero_setea_guia_subida_at(): void
    {
        $pedido = $this->empacarComercialPendienteGuia();

        $this->assertNull($pedido->guia_subida_at);

        $actualizado = app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'GUIA-FASE23-001',
            $this->usuario->id
        );

        $this->assertSame('GUIA-FASE23-001', $actualizado->numero_rastreo);
        $this->assertNotNull($actualizado->guia_subida_at);
    }

    public function test_pdf_guia_persiste_tras_pasar_a_pendiente_de_envio(): void
    {
        Storage::fake('public');

        $pedido = $this->empacarComercialPendienteGuia();
        $archivo = UploadedFile::fake()->create('guia-persist.pdf', 100, 'application/pdf');

        app(GestionarGuiaPdfPedidoBmaService::class)->subir(
            $pedido->fresh('estatus'),
            $archivo
        );

        $pendienteEnvio = app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'TRACK-PERSIST',
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $pendienteEnvio->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertTrue($pendienteEnvio->fresh(['documentos'])->tieneGuiaPdf());
    }

    public function test_cedis_tab_empacados_incluye_pendiente_de_envio(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaLocalId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );

        $empacados = app(ListarPedidosCedisService::class)->ejecutar(['tab' => 'EMPACADOS'], false);

        $this->assertTrue($empacados->contains('id', $pedido->id));
    }

    public function test_marcar_enviado_requiere_guia_si_ofrece_rastreo(): void
    {
        $pedido = $this->empacarComercialPendienteGuia();

        $this->expectException(\RuntimeException::class);

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen']),
            $this->usuario->id
        );
    }

    public function test_no_subir_pdf_fuera_de_fases_permitidas(): void
    {
        Storage::fake('public');

        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        $archivo = UploadedFile::fake()->create('guia-invalida.pdf', 100, 'application/pdf');

        $this->expectException(\RuntimeException::class);

        app(GestionarGuiaPdfPedidoBmaService::class)->subir(
            $pedido->fresh('estatus'),
            $archivo
        );
    }

    private function empacarComercialPendienteGuia(): PedidoBma
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        return app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
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

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (!CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'AMARILLO', 'color_hex' => '#EAB308', 'fase_ciclo' => 'EN_CEDIS', 'orden' => 3],
                ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'color_hex' => '#A855F7', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 7],
                ['codigo_interno' => 'PENDIENTE_ENVIO', 'nombre_visual' => 'Pendiente de envío', 'color_hex' => '#0EA5E9', 'fase_ciclo' => 'PENDIENTE_DE_ENVIO', 'orden' => 10],
                ['codigo_interno' => 'ENTREGADO', 'nombre_visual' => 'Entregado', 'color_hex' => '#10B981', 'fase_ciclo' => 'ENTREGADO', 'orden' => 8],
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

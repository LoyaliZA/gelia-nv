<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\User;
use App\Services\ControlPedidos\CargarGuiaClientePedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosDelegadoService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ControlPedidosGuiaClienteTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->seedCatalogosMinimos();
        Storage::fake('public');
    }

    public function test_validacion_omite_costo_direccion_y_exige_pesaje(): void
    {
        $origen = new CatalogoOrigenPedido(['requiere_logistica' => true]);

        $sinPesaje = new PedidoBma([
            'cliente_proporciona_guia' => true,
            'folio_remision' => 'R-1',
            'cliente_id' => 1,
            'origen_id' => 1,
            'catalogo_banco_id' => 1,
            'almacen_id' => 1,
            'total_mercancia' => 100,
        ]);
        $sinPesaje->setRelation('origen', $origen);
        $sinPesaje->setRelation('tipoOperacionEnvio', null);

        $probe = new class {
            use ValidacionCamposPedidoBma;

            public function check(PedidoBma $p): void
            {
                $this->validarCamposRequeridos($p);
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pesaje');
        $probe->check($sinPesaje);
    }

    public function test_validacion_guia_cliente_con_pesaje_sin_costo_ni_direccion(): void
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

        $pedido = PedidoBma::create([
            'folio' => 'PED-GC-VAL-'.uniqid(),
            'folio_remision' => 'REM-GC-VAL',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenForaneo()->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 2,
            'peso_volumetrico_kg' => 1.5,
            'catalogo_estatus_pedido_id' => $borrador->id,
            'total_mercancia' => 1000,
            'costo_envio' => null,
            'cliente_proporciona_guia' => true,
            'aplica_seguro' => false,
            'pesaje_respondido_at' => now(),
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
        ]);

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_COMPROBANTE,
            'ruta_archivo' => 'test/comp.jpg',
            'nombre_original' => 'comp.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 50,
            'orden' => 0,
        ]);

        DB::table('pedido_bma_cajas')->insert([
            'pedido_bma_id' => $pedido->id,
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'peso_real_kg' => 2,
            'peso_volumetrico_kg' => 1.5,
            'orden' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $probe = new class {
            use ValidacionCamposPedidoBma;

            public function check(PedidoBma $p): void
            {
                $this->validarCamposRequeridos($p);
            }
        };

        $probe->check($pedido->fresh(['origen', 'cajas', 'documentos']));
        $this->assertTrue(true);
    }

    public function test_empacar_guia_cliente_pasa_a_pendiente_guia_cliente_sin_notificar_delegado(): void
    {
        $mock = Mockery::mock(NotificarPedidoBmaService::class);
        $mock->shouldReceive('ejecutar')->never();
        $this->app->instance(NotificarPedidoBmaService::class, $mock);

        $pedido = $this->crearPedidoAprobadoCedis([
            'cliente_proporciona_guia' => true,
            'costo_envio' => null,
            'catalogo_tipo_guia_id' => null,
            'catalogo_zona_id' => null,
            'codigo_postal' => null,
            'domicilio_entrega' => null,
        ]);

        $actualizado = app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen', 'estatus']),
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );

        $enDelegado = app(ListarPedidosDelegadoService::class)->ejecutar(['tab' => 'PENDIENTES_GUIA'], false);
        $this->assertFalse($enDelegado->contains('id', $pedido->id));
    }

    public function test_cargar_guia_cliente_pasa_a_envio_notifica_cedis_y_bitacora(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'cliente_proporciona_guia' => true,
            'costo_envio' => null,
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen', 'estatus']),
            $this->usuario->id
        );

        $mockCarga = Mockery::mock(NotificarPedidoBmaService::class);
        $mockCarga->shouldReceive('ejecutar')
            ->once()
            ->withArgs(function ($pedidoArg, $tipo, $mensaje, $perms) {
                return $tipo === 'pedido_guia_asignada'
                    && $perms === ['control_pedidos.cedis']
                    && str_contains($mensaje, 'CLIENTE-123');
            })
            ->andReturnNull();
        $this->app->instance(NotificarPedidoBmaService::class, $mockCarga);

        $pdf = UploadedFile::fake()->create('guia-cliente.pdf', 100, 'application/pdf');

        $actualizado = app(CargarGuiaClientePedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'CLIENTE-123',
            $pdf,
            $this->usuario->id
        );

        $this->assertSame('CLIENTE-123', $actualizado->numero_rastreo);
        $this->assertNotNull($actualizado->guia_subida_at);
        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertTrue($actualizado->tieneGuiaPdf());

        $historial = PedidoBmaHistorialEstado::where('pedido_bma_id', $pedido->id)
            ->where('usuario_id', $this->usuario->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($historial);
        $this->assertStringContainsString('Guía del cliente cargada: CLIENTE-123', (string) $historial->comentarios);
    }

    public function test_puede_cargar_guia_cliente_gate(): void
    {
        $ok = new PedidoBma([
            'cliente_proporciona_guia' => true,
            'numero_rastreo' => null,
            'es_resguardo' => false,
        ]);
        $ok->setRelation('estatus', new CatalogoEstatusPedido([
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
        ]));
        $this->assertTrue($ok->puedeCargarGuiaCliente());

        $sinFlag = new PedidoBma([
            'cliente_proporciona_guia' => false,
            'numero_rastreo' => null,
        ]);
        $sinFlag->setRelation('estatus', new CatalogoEstatusPedido([
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
        ]));
        $this->assertFalse($sinFlag->puedeCargarGuiaCliente());
    }

    public function test_ofrece_rastreo_con_guia_cliente_sin_paqueteria(): void
    {
        $pedido = new PedidoBma([
            'cliente_proporciona_guia' => true,
            'catalogo_paqueteria_id' => null,
        ]);
        $pedido->setRelation('paqueteria', null);
        $pedido->setRelation('origen', new CatalogoOrigenPedido(['requiere_logistica' => true]));

        $this->assertTrue($pedido->ofreceRastreo());
    }

    private function crearPedidoAprobadoCedis(array $overrides = []): PedidoBma
    {
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);

        $pedido = PedidoBma::create(array_merge([
            'folio' => 'PED-GC-'.uniqid(),
            'folio_remision' => 'REM-GC-001',
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
            'cliente_proporciona_guia' => false,
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

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'Pendiente Auxiliar', 'color_hex' => '#3B82F6', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 2],
                ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'En CEDIS', 'color_hex' => '#EAB308', 'fase_ciclo' => 'EN_CEDIS', 'orden' => 3],
                ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'color_hex' => '#A855F7', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 7],
                ['codigo_interno' => 'PENDIENTE_GUIA_CLIENTE', 'nombre_visual' => 'Pendiente de guía del cliente', 'color_hex' => '#C026D3', 'fase_ciclo' => 'PENDIENTE_GUIA_CLIENTE', 'orden' => 11],
                ['codigo_interno' => 'PENDIENTE_ENVIO', 'nombre_visual' => 'Pendiente de envío', 'color_hex' => '#0EA5E9', 'fase_ciclo' => 'PENDIENTE_DE_ENVIO', 'orden' => 10],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
            }
        } elseif (! CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE)) {
            CatalogoEstatusPedido::create([
                'codigo_interno' => 'PENDIENTE_GUIA_CLIENTE',
                'nombre_visual' => 'Pendiente de guía del cliente',
                'color_hex' => '#C026D3',
                'fase_ciclo' => 'PENDIENTE_GUIA_CLIENTE',
                'orden' => 11,
                'activo' => true,
            ]);
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
                'nombre' => 'Caja 1',
                'peso_volumetrico' => 1,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_tipos_guia_pedido')->exists()) {
            DB::table('catalogo_tipos_guia_pedido')->insert([
                'nombre' => 'Estándar', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_zonas_pedido')->exists()) {
            DB::table('catalogo_zonas_pedido')->insert([
                'nombre' => 'Sin reexpedición', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_envios_tienda')->exists()) {
            DB::table('catalogo_envios_tienda')->insert([
                'nombre' => 'Envío', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
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
    }
}

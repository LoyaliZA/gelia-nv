<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Notifications\AlertaPedidoBma;
use App\Services\ControlPedidos\AprobarPedidoBmaService;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\RechazarPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Check de handoffs: mensajes de voz + que cada transición llama NotificarPedidoBmaService.
 * (Notification::fake + afterCommit no dispara bajo RefreshDatabase.)
 */
class ControlPedidosHandoffAlertasTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedora;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendedora = User::factory()->create(['name' => 'Vendedora Test']);
        $this->actor = User::factory()->create(['name' => 'Actor Test']);
        $this->seedCatalogosMinimos();
    }

    public function test_mensajes_voz_handoffs(): void
    {
        $pedido = new PedidoBma([
            'id' => 99,
            'folio' => 'PED-99',
            'folio_remision' => 'REM-99',
        ]);
        $pedido->setRelation('vendedor', null);
        $pedido->setRelation('cliente', null);
        $pedido->setRelation('estatus', null);

        $user = new User(['name' => 'Ana López']);

        $casos = [
            'pedido_pendiente_auxiliar' => 'pendiente de auditoría',
            'pedido_aprobado' => 'fue aprobado',
            'pedido_rechazado_auxiliar' => 'fue rechazado',
            'pedido_incidencia_cedis' => 'error de empaque',
            'pedido_pendiente_guia' => 'pendiente de guía',
            'pedido_pendiente_envio' => 'pendiente de envío',
            'pedido_guia_asignada' => 'se asignó guía',
            'pedido_enviado' => 'marcado como enviado',
            'pedido_resguardo_liberado' => 'liberó el resguardo',
        ];

        foreach ($casos as $tipo => $fragmento) {
            $alerta = new AlertaPedidoBma($pedido, $tipo, 'Mensaje base');
            $data = $alerta->toBroadcast($user)->data;
            $this->assertStringContainsString(
                $fragmento,
                $data['mensaje_voz'],
                "Voz incorrecta para {$tipo}"
            );
            $this->assertSame($tipo, $data['tipo']);
        }
    }

    public function test_enviar_notifica_pendiente_auxiliar(): void
    {
        $this->expectNotificar('pedido_pendiente_auxiliar', ['control_pedidos.auditar'], false, $this->vendedora->id);

        $pedido = $this->crearPedidoBorradorListoParaEnviar();

        app(EnviarPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'origen', 'documentos', 'comprobantes']),
            $this->vendedora->id
        );
    }

    public function test_aprobar_notifica_cedis_y_vendedora(): void
    {
        $this->expectNotificar('pedido_aprobado', ['control_pedidos.cedis'], true, $this->actor->id);

        $pedido = $this->crearPedidoPendienteAuxiliar();

        app(AprobarPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->actor->id
        );
    }

    public function test_rechazar_notifica_vendedora(): void
    {
        $this->expectNotificar('pedido_rechazado_auxiliar', [], true, $this->actor->id);

        $pedido = $this->crearPedidoPendienteAuxiliar();

        app(RechazarPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->actor->id,
            'Falta comprobante'
        );
    }

    public function test_empacar_notifica_pendiente_guia(): void
    {
        $this->expectNotificar('pedido_pendiente_guia', ['control_pedidos.delegado'], false, $this->actor->id);

        $pedido = $this->crearPedidoEnCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'documentos']),
            $this->actor->id
        );
    }

    /**
     * @param  list<string>  $permisos
     */
    private function expectNotificar(string $tipo, array $permisos, bool $incluirVendedora, ?int $excluirId = null): void
    {
        $excluirEsperado = $excluirId ?? $this->actor->id;
        $mock = Mockery::mock(NotificarPedidoBmaService::class);
        $mock->shouldReceive('ejecutar')
            ->once()
            ->withArgs(function (
                PedidoBma $pedido,
                string $tipoAlerta,
                string $mensaje,
                array $perms,
                ?int $excluirUsuarioId,
                bool $incluirVend,
            ) use ($tipo, $permisos, $incluirVendedora, $excluirEsperado) {
                return $tipoAlerta === $tipo
                    && $perms === $permisos
                    && $incluirVend === $incluirVendedora
                    && $excluirUsuarioId === $excluirEsperado
                    && $pedido->id > 0;
            });

        $this->app->instance(NotificarPedidoBmaService::class, $mock);
    }

    private function crearPedidoBorradorListoParaEnviar(): PedidoBma
    {
        $pedido = PedidoBma::create([
            'folio' => 'PED-HO-'.uniqid(),
            'folio_remision' => 'REM-HO-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->vendedora->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenSinLogistica()->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(
                CatalogoEstatusPedido::FASE_BORRADOR
            )->id,
            'es_resguardo' => false,
        ]);

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_COMPROBANTE,
            'ruta_archivo' => 'pedidos_bma/comprobantes/test.jpg',
            'nombre_original' => 'pago.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100,
            'orden' => 1,
        ]);

        $pedido->update(['total_a_cobrar' => 1000, 'saldo_a_favor' => 0]);

        \App\Models\SaldosAFavor\PedidoBmaPago::create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 1000,
            'ruta_archivo' => 'pedidos_bma/pagos/test.jpg',
            'nombre_original' => 'pago.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100,
            'estado_revision' => \App\Models\SaldosAFavor\PedidoBmaPago::REVISION_PENDIENTE,
            'capturado_por_id' => $this->vendedora->id,
        ]);

        return $pedido->fresh();
    }

    private function crearPedidoPendienteAuxiliar(): PedidoBma
    {
        $pedido = PedidoBma::create([
            'folio' => 'PED-PA-'.uniqid(),
            'folio_remision' => 'REM-PA-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->vendedora->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenSinLogistica()->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(
                CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR
            )->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->actor->id,
        ]);

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'pedidos_bma/remisiones/test.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 1,
        ]);

        return $pedido->fresh();
    }

    private function crearPedidoEnCedis(array $overrides = []): PedidoBma
    {
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);

        $pedido = PedidoBma::create(array_merge([
            'folio' => 'PED-CE-'.uniqid(),
            'folio_remision' => 'REM-CE-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->vendedora->id,
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
            'pago_validado_por_id' => $this->actor->id,
        ], $overrides));

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'pedidos_bma/remisiones/test.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 1,
        ]);

        return $pedido->fresh();
    }

    private function origenForaneo(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::firstOrCreate(
            ['nombre' => 'Envío Foráneo'],
            ['requiere_logistica' => true, 'activo' => true]
        );
    }

    private function origenSinLogistica(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::firstOrCreate(
            ['nombre' => 'Entrega local'],
            ['requiere_logistica' => false, 'activo' => true]
        );
    }

    private function paqueteriaComercialId(): int
    {
        $id = DB::table('catalogo_paqueterias_pedido')
            ->where('categoria', 'comercial')
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        $existente = DB::table('catalogo_paqueterias_pedido')->where('nombre', 'FEDEX')->first();
        if ($existente) {
            DB::table('catalogo_paqueterias_pedido')
                ->where('id', $existente->id)
                ->update(['categoria' => 'comercial', 'updated_at' => now()]);

            return (int) $existente->id;
        }

        return (int) DB::table('catalogo_paqueterias_pedido')->insertGetId([
            'nombre' => 'FEDEX',
            'categoria' => 'comercial',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
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
                ['codigo_interno' => 'ROJO', 'nombre_visual' => 'Incidencia', 'color_hex' => '#EF4444', 'fase_ciclo' => 'INCIDENCIA_CEDIS', 'orden' => 4],
                ['codigo_interno' => 'NARANJA', 'nombre_visual' => 'Rechazado', 'color_hex' => '#F97316', 'fase_ciclo' => 'RECHAZADO_VENDEDORA', 'orden' => 5],
                ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'color_hex' => '#A855F7', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 7],
                ['codigo_interno' => 'PENDIENTE_ENVIO', 'nombre_visual' => 'Pendiente de envío', 'color_hex' => '#0EA5E9', 'fase_ciclo' => 'PENDIENTE_DE_ENVIO', 'orden' => 10],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
            }
        }

        $this->origenForaneo();
        $this->origenSinLogistica();

        if (! DB::table('catalogo_bancos')->exists()) {
            DB::table('catalogo_bancos')->insert([
                'nombre' => 'BBVA',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->paqueteriaComercialId();

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
                'nombre' => 'Terrestre',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_zonas_pedido')->exists()) {
            DB::table('catalogo_zonas_pedido')->insert([
                'nombre' => 'Sin reexpedición',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente Test',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'vendedor_id' => $this->vendedora->id,
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
                'nombre' => 'Tienda',
                'es_otro' => false,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

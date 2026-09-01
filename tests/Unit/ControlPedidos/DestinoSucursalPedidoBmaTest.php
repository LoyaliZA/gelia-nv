<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\AsignarSucursalDestinoPedidoService;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\ValidarSucursalDestinoPedidoBma;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DestinoSucursalPedidoBmaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        Permission::findOrCreate('control_pedidos.crear', 'web');
        Permission::findOrCreate('control_pedidos.editar', 'web');
        $this->usuario->givePermissionTo(['control_pedidos.crear', 'control_pedidos.editar']);
    }

    public function test_pedido_a_sucursal_exige_destino_valido_al_enviar(): void
    {
        $pedido = $this->crearPedidoMostradorListo(['sucursal_destino_id' => null]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sucursal destino');

        app(EnviarPedidoBmaService::class)->ejecutar($pedido->fresh(['origen']), $this->usuario->id);
    }

    public function test_pedido_a_sucursal_envia_con_destino_activo(): void
    {
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        $pedido = $this->crearPedidoMostradorListo(['sucursal_destino_id' => $sucursal->id]);

        $actualizado = app(EnviarPedidoBmaService::class)->ejecutar($pedido->fresh(['origen']), $this->usuario->id);

        $this->assertSame($sucursal->id, $actualizado->sucursal_destino_id);
        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertTrue($actualizado->sucursalDestino()->exists());
    }

    public function test_pedido_que_no_va_a_sucursal_conserva_destino_nulo(): void
    {
        $pedido = $this->crearPedidoForaneoListo();

        $this->assertNull($pedido->sucursal_destino_id);

        app(ValidarSucursalDestinoPedidoBma::class)->ejecutar($pedido->fresh('origen'), null);

        $cargado = PedidoBma::query()->with('sucursalDestino')->findOrFail($pedido->id);
        $this->assertNull($cargado->sucursal_destino_id);
        $this->assertNull($cargado->sucursalDestino);
    }

    public function test_no_asigna_destino_artificial_a_envio(): void
    {
        $pedido = $this->crearPedidoForaneoListo();
        $sucursal = Sucursal::factory()->create();

        $this->expectException(ValidationException::class);

        app(AsignarSucursalDestinoPedidoService::class)->ejecutar(
            $pedido->fresh('origen'),
            $this->usuario,
            $sucursal->id
        );
    }

    public function test_rechaza_sucursal_inactiva(): void
    {
        $pedido = $this->crearPedidoMostradorListo(['sucursal_destino_id' => null]);
        $inactiva = Sucursal::factory()->inactiva()->create();

        $this->expectException(ValidationException::class);

        app(AsignarSucursalDestinoPedidoService::class)->ejecutar(
            $pedido->fresh('origen'),
            $this->usuario,
            $inactiva->id
        );
    }

    public function test_modalidad_recoge_tienda_exige_destino(): void
    {
        $pedido = $this->crearPedidoForaneoListo();

        $this->expectException(ValidationException::class);

        app(ValidarSucursalDestinoPedidoBma::class)->ejecutar(
            $pedido,
            null,
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA,
            true
        );
    }

    public function test_modalidad_envio_bodega_prohibe_destino(): void
    {
        $pedido = $this->crearPedidoMostradorListo([
            'sucursal_destino_id' => Sucursal::factory()->create()->id,
        ]);

        $this->expectException(ValidationException::class);

        app(ValidarSucursalDestinoPedidoBma::class)->ejecutar(
            $pedido->fresh(['origen', 'sucursalDestino']),
            (int) $pedido->sucursal_destino_id,
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL
        );
    }

    public function test_cambio_de_destino_se_audita_y_autoriza(): void
    {
        $primera = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $segunda = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);
        $pedido = $this->crearPedidoMostradorListo(['sucursal_destino_id' => $primera->id]);

        app(AsignarSucursalDestinoPedidoService::class)->ejecutar(
            $pedido->fresh(['origen', 'estatus']),
            $this->usuario,
            $segunda->id
        );

        $this->assertSame($segunda->id, $pedido->fresh()->sucursal_destino_id);

        $historial = PedidoBmaHistorialEstado::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('accion', AccionesHistorialPedidoBma::CAMBIO_SUCURSAL_DESTINO)
            ->latest('id')
            ->first();

        $this->assertNotNull($historial);
        $this->assertSame($this->usuario->id, $historial->usuario_id);
        $this->assertStringContainsString('Sucursal Norte', (string) $historial->comentarios);
        $this->assertStringContainsString('Sucursal Sur', (string) $historial->comentarios);

        $intruso = User::factory()->create();
        $this->expectException(\RuntimeException::class);
        app(AsignarSucursalDestinoPedidoService::class)->ejecutar(
            $pedido->fresh('origen'),
            $intruso,
            $primera->id
        );
    }

    public function test_pedidos_existentes_sin_destino_continuan_cargando(): void
    {
        $pedido = $this->crearPedidoForaneoListo();

        $cargado = PedidoBma::query()->findOrFail($pedido->id);

        $this->assertSame($pedido->folio, $cargado->folio);
        $this->assertNull($cargado->sucursal_destino_id);
        $this->assertFalse($cargado->requiereSucursalDestino());
        $this->assertTrue($cargado->prohibeSucursalDestino());
        $this->assertNotSame($cargado->almacen_id, $cargado->sucursal_destino_id);
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

    private function crearPedidoMostradorListo(array $overrides = []): PedidoBma
    {
        $pedido = $this->crearPedidoBase(array_merge([
            'origen_id' => $this->origenMostrador()->id,
            'catalogo_paqueteria_id' => null,
            'catalogo_tipo_caja_id' => null,
            'catalogo_tipo_guia_id' => null,
            'catalogo_zona_id' => null,
            'numero_cajas' => null,
            'costo_envio' => null,
            'codigo_postal' => null,
            'domicilio_entrega' => null,
        ], $overrides));

        $this->agregarComprobante($pedido);

        return $pedido->fresh(['origen', 'estatus']);
    }

    private function crearPedidoForaneoListo(): PedidoBma
    {
        $pedido = $this->crearPedidoBase([
            'origen_id' => $this->origenForaneo()->id,
            'sucursal_destino_id' => null,
            'costo_envio' => 150,
            'pesaje_respondido_at' => now(),
        ]);
        $this->agregarComprobante($pedido);

        return $pedido->fresh(['origen', 'estatus']);
    }

    private function crearPedidoBase(array $overrides = []): PedidoBma
    {
        $this->seedCatalogosMinimos();

        $borrador = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)
            ?? CatalogoEstatusPedido::first();

        return PedidoBma::create(array_merge([
            'folio' => 'PED-DEST-' . uniqid(),
            'folio_remision' => 'REM-DEST-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenForaneo()->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'total_mercancia' => 1000,
            'catalogo_estatus_pedido_id' => $borrador->id,
            'es_resguardo' => false,
            'consulta_cerrada_at' => now(),
            'consulta_cerrada_por_id' => $this->usuario->id,
        ], $overrides));
    }

    private function agregarComprobante(PedidoBma $pedido): void
    {
        $pedido->refresh();
        $total = (float) ($pedido->total_a_cobrar ?? 0);
        if ($total <= 0.01) {
            $total = round((float) $pedido->total_mercancia + (float) ($pedido->costo_envio ?? 0), 2);
            $pedido->update(['total_a_cobrar' => $total]);
        }

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_COMPROBANTE,
            'ruta_archivo' => 'test/comprobante.jpg',
            'nombre_original' => 'comprobante.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100,
            'orden' => 0,
        ]);

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'test/pedido.pdf',
            'nombre_original' => 'pedido.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 1,
        ]);

        \App\Models\SaldosAFavor\PedidoBmaPago::create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => $total,
            'ruta_archivo' => 'test/comprobante.jpg',
            'nombre_original' => 'comprobante.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100,
            'estado_revision' => \App\Models\SaldosAFavor\PedidoBmaPago::REVISION_PENDIENTE,
            'capturado_por_id' => $this->usuario->id,
        ]);
    }

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'AZUL ①', 'color_hex' => '#3B82F6', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 2],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
            }
        }

        $this->origenMostrador();
        $this->origenForaneo();

        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test',
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

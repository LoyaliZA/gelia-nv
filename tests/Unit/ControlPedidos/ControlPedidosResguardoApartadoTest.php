<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\MarcarResguardoApartadoPedidoBmaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ControlPedidosResguardoApartadoTest extends TestCase
{
    // Sin RefreshDatabase: Sail usa MySQL con datos reales.

    public function test_marcar_apartado_guarda_evidencia(): void
    {
        Storage::fake('public');
        Notification::fake();

        $usuario = User::query()->first() ?? User::factory()->create();
        $pedido = $this->crearPedidoResguardoCedis($usuario);
        $foto = UploadedFile::fake()->image('apartado.jpg', 400, 300);

        try {
            $actualizado = app(MarcarResguardoApartadoPedidoBmaService::class)->ejecutar(
                $pedido->fresh('estatus'),
                $usuario->id,
                [$foto],
                'Rack B-2'
            );

            $this->assertNotNull($actualizado->resguardo_apartado_at);
            $this->assertSame('Rack B-2', $actualizado->detalle_resguardo_apartado);
            $this->assertTrue((bool) $actualizado->es_resguardo);
            $this->assertFalse($actualizado->puedeMarcarResguardoApartado());
            $this->assertTrue(
                $actualizado->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_APARTADO)->exists()
            );
        } finally {
            $this->limpiarPedido($pedido->id);
        }
    }

    public function test_no_apartar_sin_evidencia(): void
    {
        Notification::fake();
        $usuario = User::query()->first() ?? User::factory()->create();
        $pedido = $this->crearPedidoResguardoCedis($usuario);

        try {
            $this->expectException(\InvalidArgumentException::class);
            app(MarcarResguardoApartadoPedidoBmaService::class)->ejecutar(
                $pedido->fresh('estatus'),
                $usuario->id,
                [],
                ''
            );
        } finally {
            $this->limpiarPedido($pedido->id);
        }
    }

    public function test_no_apartar_si_no_es_resguardo(): void
    {
        Storage::fake('public');
        Notification::fake();
        $usuario = User::query()->first() ?? User::factory()->create();
        $pedido = $this->crearPedidoResguardoCedis($usuario, ['es_resguardo' => false]);

        try {
            $this->expectException(\RuntimeException::class);
            app(MarcarResguardoApartadoPedidoBmaService::class)->ejecutar(
                $pedido->fresh('estatus'),
                $usuario->id,
                [UploadedFile::fake()->image('a.jpg')],
                ''
            );
        } finally {
            $this->limpiarPedido($pedido->id);
        }
    }

    private function crearPedidoResguardoCedis(User $usuario, array $overrides = []): PedidoBma
    {
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);
        $this->assertNotNull($enCedis, 'Falta estatus EN_CEDIS en catálogo');

        $pedido = PedidoBma::create(array_merge([
            'folio' => 'PED-AP-'.uniqid(),
            'folio_remision' => 'REM-AP-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => DB::table('origenes_pedido')->value('id'),
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
            'domicilio_entrega' => 'Calle Test Apartado',
            'total_mercancia' => 1000,
            'costo_envio' => 150,
            'catalogo_estatus_pedido_id' => $enCedis->id,
            'es_resguardo' => true,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $usuario->id,
        ], $overrides));

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'pedidos_bma/remisiones/test-apartado.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 1,
        ]);

        return $pedido->fresh();
    }

    private function limpiarPedido(int $pedidoId): void
    {
        PedidoBmaDocumento::where('pedido_bma_id', $pedidoId)->delete();
        DB::table('pedido_bma_historial_estados')->where('pedido_bma_id', $pedidoId)->delete();
        PedidoBma::withTrashed()->where('id', $pedidoId)->forceDelete();
    }
}

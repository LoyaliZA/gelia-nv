<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Prueba HTTP de responder pesaje (consulta mercancía) para diagnóstico/regresión.
 */
class ResponderPesajeCedisDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_responder_pesaje_consulta_mercancia_con_evidencia(): void
    {
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        Storage::fake('public');
        Permission::findOrCreate('control_pedidos.cedis', 'web');
        $cedis = User::factory()->create();
        $cedis->givePermissionTo('control_pedidos.cedis');

        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'TIENDA_DBG',
            'nombre' => 'Tienda debug',
            'requiere_logistica' => false,
            'activo' => true,
        ]);

        $estatus = CatalogoEstatusPedido::query()->create([
            'codigo_interno' => 'PES_PEND_DBG',
            'nombre_visual' => 'Pesaje pendiente',
            'color_hex' => '#EAB308',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            'orden' => 1,
            'activo' => true,
        ]);

        $pedido = PedidoBma::query()->create([
            'folio' => 'PED-DEBUG-'.uniqid(),
            'folio_remision' => 'REM-DEBUG-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $cedis->id,
            'origen_id' => $origen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
        ]);

        $file = UploadedFile::fake()->image('lote.jpg', 800, 600);

        $response = $this->actingAs($cedis)->post(
            route('control_pedidos.cedis.responder_pesaje', $pedido),
            [
                'numero_cajas' => 2,
                'estado_fisico_general' => 'bueno',
                'comentario_fisico_general' => '',
                'revisiones' => [[
                    'descripcion_producto' => 'SKU-DBG — Producto debug',
                    'sku' => 'SKU-DBG',
                    'estado_fisico' => 'bueno',
                    'comentario' => '',
                    'unica_pieza' => '0',
                    'mejor_ejemplar' => '0',
                    'client_uuid' => '11111111-1111-4111-8111-111111111111',
                ]],
                'evidencias_generales' => [$file],
            ]
        );

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
            $pedido->fresh()->estatus_envio
        );
    }

    public function test_post_sin_evidencia_falla_con_error_de_negocio(): void
    {
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        Permission::findOrCreate('control_pedidos.cedis', 'web');
        $cedis = User::factory()->create();
        $cedis->givePermissionTo('control_pedidos.cedis');

        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'TIENDA_DBG2',
            'nombre' => 'Tienda debug 2',
            'requiere_logistica' => false,
            'activo' => true,
        ]);

        $estatus = CatalogoEstatusPedido::query()->create([
            'codigo_interno' => 'PES_PEND_DBG2',
            'nombre_visual' => 'Pesaje pendiente',
            'color_hex' => '#EAB308',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            'orden' => 2,
            'activo' => true,
        ]);

        $pedido = PedidoBma::query()->create([
            'folio' => 'PED-DEBUG2-'.uniqid(),
            'folio_remision' => 'REM-DEBUG2-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $cedis->id,
            'origen_id' => $origen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
        ]);

        $response = $this->actingAs($cedis)->post(
            route('control_pedidos.cedis.responder_pesaje', $pedido),
            [
                'numero_cajas' => 2,
                'revisiones' => [[
                    'descripcion_producto' => 'SKU — sin foto',
                    'estado_fisico' => 'bueno',
                    'client_uuid' => '22222222-2222-4222-8222-222222222222',
                ]],
            ]
        );

        $response->assertRedirect();
        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            $pedido->fresh()->estatus_envio
        );
    }
}

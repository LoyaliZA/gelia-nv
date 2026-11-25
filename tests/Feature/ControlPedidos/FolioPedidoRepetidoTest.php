<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FolioPedidoRepetidoTest extends TestCase
{
    use RefreshDatabase;

    private PedidoBma $uno;
    private PedidoBma $dos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $estado = CatalogoEstatusPedido::firstOrCreate(['fase_ciclo' => 'BORRADOR'], ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]);
        $origen = CatalogoOrigenPedido::create(['codigo' => 'F1', 'nombre' => 'F1', 'requiere_logistica' => true, 'activo' => true]);
        $almacen = Almacen::create(['codigo' => 'F1', 'nombre' => 'F1', 'activo' => true, 'permite_salida_pedidos' => true]);
        $base = ['fecha' => '2026-11-25', 'vendedor_id' => $usuario->id, 'catalogo_estatus_pedido_id' => $estado->id, 'origen_id' => $origen->id, 'almacen_id' => $almacen->id];
        $this->uno = PedidoBma::create($base + ['folio' => 'F-UNO', 'folio_remision' => 'WIZ-100']);
        $this->dos = PedidoBma::create($base + ['folio' => 'F-DOS', 'folio_remision' => 'WIZ-200']);
        $this->actingAs($usuario);
    }

    public function test_editar_rechaza_el_numero_de_otro_pedido_y_permite_conservar_el_propio(): void
    {
        $this->putJson(route('control_pedidos.update', $this->dos), ['folio_remision' => ' wiz-100 '])
            ->assertStatus(422)->assertJsonValidationErrors('folio_remision');
        $this->putJson(route('control_pedidos.update', $this->dos), ['folio_remision' => 'WIZ-200'])
            ->assertJsonMissingValidationErrors('folio_remision');
        $this->putJson(route('control_pedidos.update', $this->dos), ['folio_remision' => 'WIZ-300'])
            ->assertJsonMissingValidationErrors('folio_remision');
    }

    public function test_autoguardado_de_un_pedido_existente_aplica_la_misma_regla(): void
    {
        $this->postJson('/control-pedidos/autoguardar', ['pedido_id' => $this->dos->id, 'folio_remision' => 'WIZ-100'])
            ->assertStatus(422)->assertJsonValidationErrors('folio_remision');
    }
}

<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\AlcanceConsultaMunicipal;
use App\Support\ControlPedidos\FilasConsultaMunicipalTienda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmpaqueMunicipalTiendaTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(string $area = 'tienda'): PedidoBma
    {
        $origen = CatalogoOrigenPedido::firstOrCreate(['nombre' => 'Origen empaque'], ['activo' => true]);
        $fase = fn (string $f) => CatalogoEstatusPedido::firstOrCreate(['codigo_interno' => $f], ['nombre_visual' => $f, 'color_hex' => '#000000', 'fase_ciclo' => $f, 'orden' => 1, 'activo' => true]);
        $fase('EN_CEDIS');
        $fase('PENDIENTE_DE_ENVIO');
        $pedido = PedidoBma::create([
            'folio' => 'PBMA-EMP-1', 'fecha' => '2026-11-23', 'vendedor_id' => User::factory()->create()->id,
            'origen_id' => $origen->id, 'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('EN_CEDIS')->id,
            'pago_validado_at' => now(), 'folio_remision' => 'WIZ-1',
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal', 'consulta_municipal' => ['area' => $area, 'estado' => 'cerrada']],
        ]);
        $pedido->documentos()->create(['tipo' => 'remision', 'ruta_archivo' => 'e/remision.pdf', 'nombre_original' => 'remision.pdf', 'mime_type' => 'application/pdf', 'tamano_bytes' => 10, 'activo' => true]);

        return $pedido;
    }

    private function usuario(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo(Permission::findOrCreate('control_pedidos.municipal.responder_consulta', 'web'));
        $u->assignRole(Role::findOrCreate('Administrador', 'web'));

        return $u;
    }

    public function test_aprobado_municipal_de_pdv_aparece_en_tienda_y_empacar_concluye_el_flujo(): void
    {
        $pedido = $this->pedido();
        $u = $this->usuario();

        $this->assertSame([$pedido->id], collect(AlcanceConsultaMunicipal::porEmpacarTienda($u))->pluck('id')->all());
        $this->assertSame('empaque_municipal', FilasConsultaMunicipalTienda::filas($u, ['tab' => 'TODOS'])[0]['tipo_fila']);

        $this->actingAs($u)->postJson(route('control_pedidos.municipal.empacar_tienda', $pedido))->assertOk();

        $pedido->refresh();
        $this->assertNotNull($pedido->empacado_at);
        $this->assertSame($u->id, (int) $pedido->empacado_por_id);
        $this->assertSame([], AlcanceConsultaMunicipal::porEmpacarTienda($u));
        $this->actingAs($u)->postJson(route('control_pedidos.municipal.empacar_tienda', $pedido))->assertStatus(422);
    }

    public function test_municipal_de_cedis_no_se_empaca_en_tienda(): void
    {
        $pedido = $this->pedido('cedis');
        $u = $this->usuario();

        $this->assertSame([], AlcanceConsultaMunicipal::porEmpacarTienda($u));
        $this->actingAs($u)->postJson(route('control_pedidos.municipal.empacar_tienda', $pedido))->assertStatus(422);
    }
}

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
        $this->assertSame('Aprobado: por empacar', FilasConsultaMunicipalTienda::filas($u, ['tab' => 'PENDIENTES'])[0]['estado_label']);
        $this->assertSame([], FilasConsultaMunicipalTienda::filas($u, ['tab' => 'ATENDIDOS']));

        $this->actingAs($u)->postJson(route('control_pedidos.municipal.empacar_tienda', $pedido))->assertOk();

        $pedido->refresh();
        $this->assertNotNull($pedido->empacado_at);
        $this->assertSame($u->id, (int) $pedido->empacado_por_id);
        $this->assertSame([], AlcanceConsultaMunicipal::porEmpacarTienda($u));
        $this->assertSame([$pedido->id], collect(AlcanceConsultaMunicipal::empacadosTienda($u))->pluck('id')->all());
        $this->assertSame([], FilasConsultaMunicipalTienda::filas($u, ['tab' => 'PENDIENTES']));
        $atendido = FilasConsultaMunicipalTienda::filas($u, ['tab' => 'ATENDIDOS'])[0];
        $this->assertSame('empaque_municipal', $atendido['tipo_fila']);
        $this->assertSame('Empacado', $atendido['estado_label']);
        $this->actingAs($u)->getJson(route('control_pedidos.disponibilidad_tienda.show', $pedido))->assertOk();
        $this->actingAs($u)->postJson(route('control_pedidos.municipal.empacar_tienda', $pedido))->assertStatus(422);
    }

    private function consultaSinAprobar(string $estado, string $area = 'tienda'): PedidoBma
    {
        $origen = CatalogoOrigenPedido::firstOrCreate(['nombre' => 'Origen empaque'], ['activo' => true]);
        $estatus = CatalogoEstatusPedido::firstOrCreate(['codigo_interno' => 'PESAJE_RESPONDIDO'], ['nombre_visual' => 'Pesaje respondido', 'color_hex' => '#000000', 'fase_ciclo' => 'PESAJE_RESPONDIDO', 'orden' => 1, 'activo' => true]);

        return PedidoBma::create([
            'folio' => 'PBMA-CONS-'.uniqid(), 'fecha' => '2026-11-24', 'vendedor_id' => User::factory()->create()->id,
            'origen_id' => $origen->id, 'catalogo_estatus_pedido_id' => $estatus->id, 'folio_remision' => 'WIZ-'.uniqid(),
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal', 'consulta_municipal' => ['area' => $area, 'estado' => $estado, 'enviada_at' => now()->subHour()->toIso8601String()]],
        ]);
    }

    public function test_una_consulta_respondida_sin_aprobar_sigue_visible_en_atendidos(): void
    {
        $pedido = $this->consultaSinAprobar('respondida');
        $u = $this->usuario();

        $this->assertSame([], FilasConsultaMunicipalTienda::filas($u, ['tab' => 'PENDIENTES']));
        $atendidos = FilasConsultaMunicipalTienda::filas($u, ['tab' => 'ATENDIDOS']);
        $this->assertCount(1, $atendidos);
        $this->assertSame('consulta_municipal', $atendidos[0]['tipo_fila']);
        $this->assertSame('RESPONDIDA', $atendidos[0]['estado']);
        $this->assertSame('Consulta respondida', $atendidos[0]['estado_label']);
        $this->assertSame($pedido->id, $atendidos[0]['pedido']['id']);
        $this->assertSame(1, FilasConsultaMunicipalTienda::sumarMetricas([], $u, [])['atendidos']);
    }

    public function test_una_consulta_cerrada_por_ventas_antes_de_aprobarse_no_desaparece(): void
    {
        $this->consultaSinAprobar('cerrada');
        $u = $this->usuario();

        $atendidos = FilasConsultaMunicipalTienda::filas($u, ['tab' => 'ATENDIDOS']);
        $this->assertCount(1, $atendidos);
        $this->assertSame('Consulta cerrada', $atendidos[0]['estado_label']);
        $this->assertCount(1, FilasConsultaMunicipalTienda::filas($u, ['tab' => 'TODOS']));
    }

    public function test_la_consulta_atendida_no_se_duplica_ni_mezcla_otras_areas_o_pendientes(): void
    {
        $this->consultaSinAprobar('pendiente');
        $this->consultaSinAprobar('respondida', 'cedis');
        $u = $this->usuario();
        $aprobado = $this->pedido();

        $this->assertSame([], FilasConsultaMunicipalTienda::filas($u, ['tab' => 'ATENDIDOS']));
        $todos = FilasConsultaMunicipalTienda::filas($u, ['tab' => 'TODOS']);
        $this->assertEqualsCanonicalizing(['consulta_municipal', 'empaque_municipal'], array_column($todos, 'tipo_fila'));
        $this->assertSame($aprobado->id, collect($todos)->firstWhere('tipo_fila', 'empaque_municipal')['pedido']['id']);
    }

    public function test_municipal_de_cedis_no_se_empaca_en_tienda(): void
    {
        $pedido = $this->pedido('cedis');
        $u = $this->usuario();

        $this->assertSame([], AlcanceConsultaMunicipal::porEmpacarTienda($u));
        $this->actingAs($u)->postJson(route('control_pedidos.municipal.empacar_tienda', $pedido))->assertStatus(422);
    }
}

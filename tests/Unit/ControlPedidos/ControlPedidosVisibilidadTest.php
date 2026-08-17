<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Departamento;
use App\Models\User;
use App\Services\ControlPedidos\ListarPedidosAuditoriaService;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlPedidosVisibilidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedora_no_ve_pedido_ajeno_y_gerente_si_consulta(): void
    {
        $vendedoraA = User::factory()->create(['name' => 'Vendedora A']);
        $vendedoraB = User::factory()->create(['name' => 'Vendedora B']);
        $gerente = User::factory()->create(['name' => 'Gerente']);
        $gerente->colaboradores()->attach($vendedoraA->id);

        $estatus = CatalogoEstatusPedido::create([
            'codigo_interno' => 'BORRADOR_VIS',
            'nombre_visual' => 'Borrador',
            'color_hex' => '#94A3B8',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
            'orden' => 1,
            'activo' => true,
        ]);

        $pedidoA = PedidoBma::create([
            'folio' => 'PED-A-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedoraA->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $pedidoB = PedidoBma::create([
            'folio' => 'PED-B-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedoraB->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $listadoA = app(ListarPedidosBmaService::class)->ejecutar($vendedoraA, [], false);
        $this->assertTrue($listadoA->contains('id', $pedidoA->id));
        $this->assertFalse($listadoA->contains('id', $pedidoB->id));

        $listadoGerente = app(ListarPedidosBmaService::class)->ejecutar($gerente, [], false);
        $this->assertTrue($listadoGerente->contains('id', $pedidoA->id));
        $this->assertFalse($listadoGerente->contains('id', $pedidoB->id));

        $this->assertTrue(VisibilidadPedidoBma::puedeConsultarEnListadoBma($gerente, $pedidoA));
        $this->assertFalse(VisibilidadPedidoBma::puedeMutarComoVendedora($gerente, $pedidoA));
        $this->assertTrue(VisibilidadPedidoBma::puedeMutarComoVendedora($vendedoraA, $pedidoA));
        $this->assertFalse(VisibilidadPedidoBma::puedeMutarComoVendedora($vendedoraB, $pedidoA));

        $pedidoGerente = $listadoGerente->firstWhere('id', $pedidoA->id);
        $this->assertFalse((bool) $pedidoGerente->puede_editar);
    }

    public function test_documento_requiere_consulta_autorizada(): void
    {
        Storage::fake('public');

        $vendedora = User::factory()->create();
        $ajeno = User::factory()->create();

        $estatus = CatalogoEstatusPedido::create([
            'codigo_interno' => 'BORRADOR_DOC',
            'nombre_visual' => 'Borrador',
            'color_hex' => '#94A3B8',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
            'orden' => 1,
            'activo' => true,
        ]);

        $pedido = PedidoBma::create([
            'folio' => 'PED-DOC-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedora->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 50,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $ruta = "pedidos_bma/remisiones/{$pedido->id}/demo.pdf";
        Storage::disk('public')->put($ruta, '%PDF-1.4 demo');

        $doc = PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => $ruta,
            'nombre_original' => 'demo.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 12,
            'orden' => 0,
        ]);

        $this->actingAs($vendedora)
            ->get(route('control_pedidos.documentos.show', [$pedido, $doc]))
            ->assertOk();

        $this->actingAs($ajeno)
            ->get(route('control_pedidos.documentos.show', [$pedido, $doc]))
            ->assertForbidden();
    }

    public function test_gerente_y_auxiliar_ven_pedidos_del_departamento(): void
    {
        Permission::findOrCreate('control_pedidos.auditar', 'web');
        $role = Role::findOrCreate('Gerente', 'web');

        $deptoA = Departamento::create(['nombre' => 'Depto SAF A '.uniqid(), 'activo' => true]);
        $deptoB = Departamento::create(['nombre' => 'Depto SAF B '.uniqid(), 'activo' => true]);

        $vendedoraA = User::factory()->create(['departamento_id' => $deptoA->id]);
        $vendedoraB = User::factory()->create(['departamento_id' => $deptoB->id]);
        $gerente = User::factory()->create(['departamento_id' => $deptoA->id]);
        $gerente->assignRole($role);
        $auxiliar = User::factory()->create(['departamento_id' => $deptoA->id]);
        $auxiliar->givePermissionTo('control_pedidos.auditar');

        $borrador = CatalogoEstatusPedido::create([
            'codigo_interno' => 'BORRADOR_DEPT',
            'nombre_visual' => 'Borrador',
            'color_hex' => '#94A3B8',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
            'orden' => 1,
            'activo' => true,
        ]);
        $auxiliarFase = CatalogoEstatusPedido::create([
            'codigo_interno' => 'PEND_AUX_DEPT',
            'nombre_visual' => 'Pendiente auxiliar',
            'color_hex' => '#3B82F6',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            'orden' => 2,
            'activo' => true,
        ]);

        $pedidoA = PedidoBma::create([
            'folio' => 'PED-DA-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedoraA->id,
            'catalogo_estatus_pedido_id' => $auxiliarFase->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);
        $pedidoB = PedidoBma::create([
            'folio' => 'PED-DB-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedoraB->id,
            'catalogo_estatus_pedido_id' => $auxiliarFase->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);
        PedidoBma::create([
            'folio' => 'PED-BORR-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedoraA->id,
            'catalogo_estatus_pedido_id' => $borrador->id,
            'total_mercancia' => 50,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $listadoGerente = app(ListarPedidosBmaService::class)->ejecutar($gerente, [], false);
        $this->assertTrue($listadoGerente->contains('id', $pedidoA->id));
        $this->assertFalse($listadoGerente->contains('id', $pedidoB->id));

        $audA = app(ListarPedidosAuditoriaService::class)->ejecutar([], false, $auxiliar);
        $this->assertTrue($audA->contains('id', $pedidoA->id));
        $this->assertFalse($audA->contains('id', $pedidoB->id));

        $this->assertTrue(VisibilidadPedidoBma::puedeConsultar($auxiliar, $pedidoA));
        $this->assertFalse(VisibilidadPedidoBma::puedeConsultar($auxiliar, $pedidoB));
    }

    public function test_vendedora_no_consulta_pagos_de_pedido_ajeno_por_id(): void
    {
        Permission::findOrCreate('control_pedidos.crear', 'web');
        $propia = User::factory()->create();
        $ajena = User::factory()->create();
        $propia->givePermissionTo('control_pedidos.crear');
        $ajena->givePermissionTo('control_pedidos.crear');

        $estatus = CatalogoEstatusPedido::create([
            'codigo_interno' => 'BORRADOR_IDOR',
            'nombre_visual' => 'Borrador',
            'color_hex' => '#94A3B8',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
            'orden' => 1,
            'activo' => true,
        ]);
        $pedido = PedidoBma::create([
            'folio' => 'PED-IDOR-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $propia->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 80,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $this->actingAs($propia)
            ->getJson(route('control_pedidos.pagos.resumen', $pedido))
            ->assertOk();

        $this->actingAs($ajena)
            ->getJson(route('control_pedidos.pagos.resumen', $pedido))
            ->assertForbidden();

        $this->actingAs($ajena)
            ->post(route('control_pedidos.pagos.store', $pedido), [
                'monto' => 10,
                'forma_pago' => 'efectivo',
            ])
            ->assertForbidden();
    }
}

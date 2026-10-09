<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\GestionarRemisionPedidoBmaService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RemisionAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $auxiliar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Permission::findOrCreate('control_pedidos.auditar', 'web');
        $this->auxiliar = User::factory()->create();
        $this->auxiliar->givePermissionTo('control_pedidos.auditar');
        Storage::fake('public');
    }

    public function test_eliminar_y_volver_a_adjuntar_conserva_historial_y_solo_una_remision_vigente(): void
    {
        $pedido = $this->pedido();
        $service = app(GestionarRemisionPedidoBmaService::class);
        $service->subir($pedido, UploadedFile::fake()->create('anterior.pdf', 10, 'application/pdf'), $this->auxiliar->id);
        $anterior = $pedido->remision()->firstOrFail();

        $this->actingAs($this->auxiliar)->delete(route('control_pedidos.auditar.remision.destroy', $pedido))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($anterior->fresh()->activo);
        $this->assertNull($pedido->remision()->first());
        Storage::disk('public')->assertExists($anterior->ruta_archivo);

        $service->subir($pedido->fresh(), UploadedFile::fake()->create('correcta.pdf', 10, 'application/pdf'), $this->auxiliar->id);
        $this->assertSame('correcta.pdf', $pedido->remision()->firstOrFail()->nombre_original);
        $this->assertSame(2, $pedido->documentos()->where('tipo', 'remision')->count());
        $this->assertSame(1, $pedido->documentos()->where('tipo', 'remision')->vigente()->count());
    }

    public function test_auxiliar_registra_numero_de_remision_y_consulta_detalle_fuera_del_filtro(): void
    {
        $pedido = $this->pedido();
        $this->actingAs($this->auxiliar)->put(route('control_pedidos.auditar.numero_remision.update', $pedido), [
            'numero_remision' => 'RM-1234',
        ])->assertRedirect()->assertSessionHas('success', 'Número de remisión actualizado.');

        $this->assertSame('RM-1234', $pedido->fresh()->numero_remision);
        $this->assertSame('PED-5625859', $pedido->fresh()->folio_remision);
        $this->assertDatabaseHas('pedido_bma_referencias', [
            'pedido_bma_id' => $pedido->id, 'tipo' => 'REMISION', 'folio' => 'RM-1234', 'vigente' => true,
        ]);
        $this->actingAs($this->auxiliar)->getJson(route('control_pedidos.auditar.detalle', $pedido).'?tab=APROBADOS&q=no-coincide')
            ->assertOk()->assertJsonPath('pedido.id', $pedido->id)->assertJsonPath('pedido.numero_remision', 'RM-1234')->assertJsonPath('pedido.folio_remision', 'PED-5625859');
    }

    public function test_numero_de_pedido_no_se_puede_modificar_desde_la_captura_auxiliar(): void
    {
        $pedido = $this->pedido();
        foreach (['folio_remision.update', 'numero_remision.update'] as $ruta) {
            $this->actingAs($this->auxiliar)->putJson(route('control_pedidos.auditar.'.$ruta, $pedido), [
                'numero_remision' => 'RM-1234', 'folio_remision' => 'PED-SOBRESCRITO', 'tipo_referencia' => 'PEDIDO',
            ])->assertUnprocessable()->assertJsonValidationErrors(['folio_remision', 'tipo_referencia']);
        }
        $this->assertSame('PED-5625859', $pedido->fresh()->folio_remision);
        $this->assertNull($pedido->fresh()->numero_remision);
    }

    public function test_corregir_remision_no_cambia_referencia_visible_ni_resuelve_error_de_numero_de_pedido(): void
    {
        $pedido = $this->pedido();
        $pedido->update(['campos_incorrectos' => ['folio_remision', 'numero_remision']]);
        PedidoBmaReferencia::registrar($pedido, PedidoBmaReferencia::TIPO_PEDIDO, 'PED-5625859', $this->auxiliar->id);
        $service = app(GestionarRemisionPedidoBmaService::class);
        $service->actualizarNumeroRemision($pedido, 'RM-1234', $this->auxiliar->id);
        $service->actualizarNumeroRemision($pedido->fresh(), 'RM-5678', $this->auxiliar->id);

        $this->assertSame('PED-5625859', $pedido->fresh()->folio_remision);
        $this->assertSame('RM-5678', $pedido->fresh()->numero_remision);
        $this->assertSame(['folio_remision'], $pedido->fresh()->campos_incorrectos);
        $this->assertSame(CamposIncorrectosPedidoBma::DUENO_VENDEDORA, CamposIncorrectosPedidoBma::duenoDe('folio_remision'));
        $this->assertSame(CamposIncorrectosPedidoBma::DUENO_AUXILIAR, CamposIncorrectosPedidoBma::duenoDe('numero_remision'));
        $this->assertSame('PED-5625859', PedidoBmaReferencia::visible($pedido->fresh())['folio']);
        $this->assertSame('PED-5625859', PedidoBmaReferencia::visible($pedido->fresh(['referencias']))['folio']);
        $this->assertDatabaseHas('pedido_bma_referencias', [
            'pedido_bma_id' => $pedido->id, 'tipo' => 'REMISION', 'folio' => 'RM-1234', 'vigente' => false,
        ]);
    }

    public function test_captura_anterior_no_puede_sobrescribir_pedido_desde_el_servicio(): void
    {
        $pedido = $this->pedido();
        try {
            app(GestionarRemisionPedidoBmaService::class)->actualizarFolioRemision($pedido, 'RM-NUEVA', $this->auxiliar->id, 'REMISION');
            $this->fail('La captura anterior debe rechazar la modificación.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('número de pedido no se modifica', $e->getMessage());
        }
        $this->assertSame('PED-5625859', $pedido->fresh()->folio_remision);
        $this->assertNull($pedido->fresh()->numero_remision);
    }

    public function test_detalle_respeta_permiso_y_alcance_de_la_auxiliar(): void
    {
        $pedido = $this->pedido();
        $ajena = User::factory()->create();
        $this->actingAs($ajena)->getJson(route('control_pedidos.auditar.detalle', $pedido))->assertForbidden();
        $ajena->givePermissionTo('control_pedidos.auditar');
        $this->actingAs($ajena)->getJson(route('control_pedidos.auditar.detalle', $pedido))->assertForbidden();
    }

    public function test_no_elimina_remision_de_un_pedido_que_ya_salio_de_revision(): void
    {
        $pedido = $this->pedido();
        $service = app(GestionarRemisionPedidoBmaService::class);
        $service->subir($pedido, UploadedFile::fake()->create('remision.pdf', 10, 'application/pdf'), $this->auxiliar->id);
        $estatus = CatalogoEstatusPedido::create([
            'codigo_interno' => 'CEDIS-TEST', 'nombre_visual' => 'En CEDIS', 'fase_ciclo' => 'EN_CEDIS',
            'color_hex' => '#64748B', 'activo' => true, 'orden' => 2,
        ]);
        $pedido->update(['catalogo_estatus_pedido_id' => $estatus->id]);
        $this->actingAs($this->auxiliar)->delete(route('control_pedidos.auditar.remision.destroy', $pedido))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertTrue($pedido->remision()->firstOrFail()->activo);
    }

    private function pedido(): PedidoBma
    {
        $now = now();
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId(['nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $clienteId = DB::table('clientes')->insertGetId([
            'numero_cliente' => '1001', 'nombre' => 'Cliente Test', 'lista_actual_id' => $listaId,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $estatus = CatalogoEstatusPedido::create([
            'codigo_interno' => 'AUX-TEST', 'nombre_visual' => 'Pendiente auxiliar', 'fase_ciclo' => 'PENDIENTE_AUXILIAR',
            'color_hex' => '#64748B', 'activo' => true, 'orden' => 1,
        ]);

        return PedidoBma::create([
            'folio' => 'BMA-TEST', 'folio_remision' => 'PED-5625859', 'fecha' => $now->toDateString(), 'cliente_id' => $clienteId,
            'vendedor_id' => $this->auxiliar->id, 'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100, 'total_a_cobrar' => 100,
        ]);
    }
}

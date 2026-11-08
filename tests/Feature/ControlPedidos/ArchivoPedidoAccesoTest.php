<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivoPedidoAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_derivado_revalida_permisos_y_pertenencia_antes_del_304(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('public');
        Storage::fake('local');
        $dueno = User::factory()->create();
        $ajeno = User::factory()->create();
        $ahora = now();
        $lista = DB::table('catalogo_listas_descuento')->insertGetId(['nombre' => 'Lista QA', 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
        $cliente = DB::table('clientes')->insertGetId(['numero_cliente' => 'QA-ARCHIVO', 'nombre' => 'Cliente ficticio', 'lista_actual_id' => $lista, 'created_at' => $ahora, 'updated_at' => $ahora]);
        $estatus = CatalogoEstatusPedido::create(['codigo_interno' => 'ARCHIVO-QA', 'nombre_visual' => 'Borrador', 'fase_ciclo' => 'BORRADOR', 'activo' => true, 'orden' => 1]);
        $pedido = PedidoBma::create(['folio' => 'QA-ARCHIVO', 'fecha' => $ahora->toDateString(), 'cliente_id' => $cliente, 'vendedor_id' => $dueno->id, 'catalogo_estatus_pedido_id' => $estatus->id, 'total_mercancia' => 100, 'total_a_cobrar' => 100]);
        $foto = UploadedFile::fake()->image('captura.png', 900, 600);
        $ruta = $foto->store('pedidos_bma/qa', 'public');
        $doc = $pedido->documentos()->create(['tipo' => 'pdf_pedido', 'ruta_archivo' => $ruta, 'nombre_original' => 'captura-123e4567-e89b-12d3-a456-426614174000.png', 'mime_type' => 'image/png', 'tamano_bytes' => $foto->getSize()]);
        $url = route('control_pedidos.documentos.show', [$pedido, $doc]);
        $primera = $this->actingAs($dueno)->get($url.'?vista=miniatura')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $etag = $primera->headers->get('ETag');
        $this->get($url.'?vista=miniatura', ['If-None-Match' => $etag])->assertStatus(304);
        $original = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('pedido-'.$pedido->id.'-'.$doc->id.'.png', $original->headers->get('Content-Disposition'));
        $this->actingAs($ajeno)->get($url.'?vista=miniatura', ['If-None-Match' => $etag])->assertForbidden();
        $otro = $pedido->replicate();
        $otro->folio = 'QA-OTRO';
        $otro->save();
        $this->actingAs($dueno)->get(route('control_pedidos.documentos.show', [$otro, $doc]).'?vista=miniatura')->assertNotFound();
    }
}

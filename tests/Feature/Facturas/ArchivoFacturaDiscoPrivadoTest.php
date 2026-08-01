<?php

namespace Tests\Feature\Facturas;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\SolicitudFactura;
use App\Models\User;
use App\Support\Facturas\FacturaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ArchivoFacturaDiscoPrivadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_archivo_fiscal_en_disco_local_no_es_publico_y_se_sirve_autenticado(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        foreach (['facturas.ver_listado'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $lista = CatalogoListaDescuento::create([
            'nombre' => 'Lista',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'numero_cliente' => '9001',
            'nombre' => 'Cliente Archivo',
            'lista_actual_id' => $lista->id,
        ]);

        $estado = CatalogoEstadoSolicitud::firstOrCreate(
            ['nombre' => 'Borrador'],
            ['color' => '#000000', 'activo' => true]
        );

        $user = User::factory()->create();
        $user->givePermissionTo('facturas.ver_listado');

        $path = 'facturas/fiscales/demo.xlsx';
        Storage::disk('local')->put($path, 'contenido-fiscal-secreto');

        $factura = SolicitudFactura::create([
            'folio' => 'FAC-TEST-001',
            'vendedor_id' => $user->id,
            'cliente_id' => $cliente->id,
            'catalogo_estado_solicitud_id' => $estado->id,
            'razon_social' => 'Test SA',
            'archivo_fiscal_path' => $path,
        ]);

        $this->assertTrue(FacturaStorage::exists($path));
        $this->assertFalse(Storage::disk('public')->exists($path));

        // Sin auth no debe entregarse el contenido (404 o bloqueo del host).
        $publico = $this->get('/storage/'.$path);
        $this->assertNotSame(200, $publico->status());

        $this->actingAs($user)
            ->get(route('facturas.archivo', ['factura' => $factura->id, 'tipo' => 'fiscal']))
            ->assertOk();
    }
}

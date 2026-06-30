<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\User;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Clientes\RegistrarHistorialMontoClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditoriaMontosClienteTest extends TestCase
{
    use RefreshDatabase;

    private function sincronizarListas(): void
    {
        CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
        ]);
    }

    public function test_importacion_registra_historial_con_origen_y_usuario(): void
    {
        Storage::fake('local');
        $this->sincronizarListas();

        $user = User::factory()->create();
        $listaPg = CatalogoListaDescuento::first();

        Cliente::create([
            'numero_cliente' => '500',
            'nombre' => 'Cliente Monto',
            'lista_actual_id' => $listaPg->id,
            'monto_venta_actual' => 100,
        ]);

        $importacion = ImportacionCliente::create([
            'usuario_id' => $user->id,
            'nombre_archivo_original' => 'test.csv',
            'ruta_almacenamiento' => 'importaciones_clientes/test.csv',
        ]);

        $path = storage_path('app/test-audit-import.csv');
        file_put_contents($path, "numero_cliente,nombre,monto_venta_actual\n500,Cliente Monto,250\n");
        $archivo = new UploadedFile($path, 'test.csv', 'text/csv', null, true);

        app(ImportarClientesWizerpService::class)->ejecutar($archivo, $importacion);

        $this->assertDatabaseHas('historial_montos_clientes', [
            'origen' => RegistrarHistorialMontoClienteService::ORIGEN_CARGA_MASIVA,
            'usuario_id' => $user->id,
            'importacion_cliente_id' => $importacion->id,
            'monto_anterior' => 100,
            'monto_nuevo' => 250,
        ]);
    }

    public function test_servicio_registra_historial_solicitud(): void
    {
        $this->sincronizarListas();
        $listaPg = CatalogoListaDescuento::first();
        $user = User::factory()->create();

        $cliente = Cliente::create([
            'numero_cliente' => '600',
            'nombre' => 'Cliente Solicitud',
            'lista_actual_id' => $listaPg->id,
            'monto_venta_actual' => 500,
        ]);

        app(RegistrarHistorialMontoClienteService::class)->registrar(
            $cliente,
            750,
            RegistrarHistorialMontoClienteService::ORIGEN_SOLICITUD_APROBACION,
            $user->id,
            null,
            null,
            250,
            'Solicitante: Vendedora Test',
        );

        $this->assertDatabaseHas('historial_montos_clientes', [
            'cliente_id' => $cliente->id,
            'origen' => RegistrarHistorialMontoClienteService::ORIGEN_SOLICITUD_APROBACION,
            'usuario_id' => $user->id,
            'monto_operacion' => 250,
        ]);

        $this->assertSame(1, HistorialMontoCliente::count());
    }

    public function test_endpoint_auditoria_datos_responde_json(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('clientes.ver', 'web');
        $role = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $role->givePermissionTo('clientes.ver');
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->getJson(route('admin.clientes.auditoria.datos'));

        $response->assertOk();
        $response->assertJsonStructure([
            'importaciones',
            'auditoriaMontos',
            'usuariosFiltro',
            'filtrosAuditoria',
        ]);
    }
}

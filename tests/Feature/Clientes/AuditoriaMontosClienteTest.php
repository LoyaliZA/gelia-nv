<?php

namespace Tests\Feature\Clientes;

use App\Models\CambioListaImportacionCliente;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ErroresImportacionCliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\User;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Clientes\ProcesarFilaClienteAction;
use App\Services\Clientes\RegistrarHistorialMontoClienteService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
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

    private function usuarioConPermisoClientes(): User
    {
        \Spatie\Permission\Models\Permission::findOrCreate('clientes.ver', 'web');
        $role = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $role->givePermissionTo('clientes.ver');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function crearCliente(string $numero, float $monto = 100): Cliente
    {
        $listaPg = CatalogoListaDescuento::first();

        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => "Cliente {$numero}",
            'lista_actual_id' => $listaPg->id,
            'monto_venta_actual' => $monto,
        ]);
    }

    private function registrarHistorialCargaMasiva(Cliente $cliente, ImportacionCliente $importacion, User $user): void
    {
        HistorialMontoCliente::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $user->id,
            'monto_anterior' => 100,
            'monto_nuevo' => 200,
            'diferencia_aplicada' => 100,
            'origen' => RegistrarHistorialMontoClienteService::ORIGEN_CARGA_MASIVA,
            'importacion_cliente_id' => $importacion->id,
        ]);
    }

    private function registrarHistorialSolicitud(Cliente $cliente, User $user): void
    {
        HistorialMontoCliente::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $user->id,
            'monto_anterior' => 100,
            'monto_nuevo' => 150,
            'diferencia_aplicada' => 50,
            'origen' => RegistrarHistorialMontoClienteService::ORIGEN_SOLICITUD_APROBACION,
            'monto_operacion' => 50,
            'notas' => 'Solicitante: Test',
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
        $user = $this->usuarioConPermisoClientes();

        $response = $this->actingAs($user)->getJson(route('admin.clientes.auditoria.datos'));

        $response->assertOk();
        $response->assertJsonStructure([
            'importaciones',
            'auditoriaMontos',
            'usuariosFiltro',
            'filtrosAuditoria',
        ]);
    }

    public function test_datos_auditoria_excluye_registros_de_carga_masiva(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisoClientes();
        $clienteCarga = $this->crearCliente('700');
        $clienteSolicitud = $this->crearCliente('701');

        $importacion = ImportacionCliente::create([
            'usuario_id' => $user->id,
            'nombre_archivo_original' => 'carga.csv',
            'ruta_almacenamiento' => 'importaciones_clientes/carga.csv',
        ]);

        $this->registrarHistorialCargaMasiva($clienteCarga, $importacion, $user);
        $this->registrarHistorialSolicitud($clienteSolicitud, $user);

        $response = $this->actingAs($user)->getJson(route('admin.clientes.auditoria.datos'));

        $response->assertOk();
        $origenes = collect($response->json('auditoriaMontos.data'))->pluck('origen')->all();

        $this->assertCount(1, $origenes);
        $this->assertSame(RegistrarHistorialMontoClienteService::ORIGEN_SOLICITUD_APROBACION, $origenes[0]);
        $this->assertNotContains(RegistrarHistorialMontoClienteService::ORIGEN_CARGA_MASIVA, $origenes);
    }

    public function test_endpoint_auditoria_importacion_devuelve_cambios_y_errores_de_la_carga(): void
    {
        $this->sincronizarListas();
        $user = $this->usuarioConPermisoClientes();
        $cliente = $this->crearCliente('800');
        $otroCliente = $this->crearCliente('801');

        $importacion = ImportacionCliente::create([
            'usuario_id' => $user->id,
            'nombre_archivo_original' => 'audit.csv',
            'ruta_almacenamiento' => 'importaciones_clientes/audit.csv',
            'errores' => 1,
        ]);

        $otraImportacion = ImportacionCliente::create([
            'usuario_id' => $user->id,
            'nombre_archivo_original' => 'otra.csv',
            'ruta_almacenamiento' => 'importaciones_clientes/otra.csv',
        ]);

        $this->registrarHistorialCargaMasiva($cliente, $importacion, $user);
        $this->registrarHistorialCargaMasiva($otroCliente, $otraImportacion, $user);

        ErroresImportacionCliente::create([
            'importacion_cliente_id' => $importacion->id,
            'numero_fila' => 5,
            'numero_cliente' => '800',
            'mensaje' => 'Error de prueba',
        ]);

        $response = $this->actingAs($user)->getJson(
            route('admin.clientes.importaciones.auditoria', $importacion),
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'importacion',
            'cambiosMontos',
            'cambiosLista',
            'errores',
            'erroresDetalleDisponible',
            'cambiosListaDisponible',
        ]);

        $this->assertTrue($response->json('erroresDetalleDisponible'));
        $this->assertCount(1, $response->json('cambiosMontos.data'));
        $this->assertCount(1, $response->json('errores.data'));
        $this->assertSame('Error de prueba', $response->json('errores.data.0.mensaje'));
    }

    public function test_auditoria_importacion_sin_detalle_errores_para_cargas_historicas(): void
    {
        $user = $this->usuarioConPermisoClientes();

        $importacion = ImportacionCliente::create([
            'usuario_id' => $user->id,
            'nombre_archivo_original' => 'historica.csv',
            'ruta_almacenamiento' => 'importaciones_clientes/historica.csv',
            'errores' => 3,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('admin.clientes.importaciones.auditoria', $importacion),
        );

        $response->assertOk();
        $this->assertFalse($response->json('erroresDetalleDisponible'));
        $this->assertSame(0, $response->json('errores.total'));
    }

    public function test_importacion_persiste_errores_en_base_de_datos(): void
    {
        Storage::fake('local');
        $this->sincronizarListas();

        $user = User::factory()->create();
        $rutaAlmacenamiento = 'importaciones_clientes/errores.csv';

        Storage::disk('local')->put(
            $rutaAlmacenamiento,
            "numero_cliente,nombre,monto_venta_actual\n999,Cliente Error,100\n",
        );

        $importacion = ImportacionCliente::create([
            'usuario_id' => $user->id,
            'nombre_archivo_original' => 'errores.csv',
            'ruta_almacenamiento' => $rutaAlmacenamiento,
        ]);

        $archivo = new UploadedFile(
            Storage::disk('local')->path($rutaAlmacenamiento),
            'errores.csv',
            'text/csv',
            null,
            true,
        );

        $mock = Mockery::mock(ProcesarFilaClienteAction::class);
        $mock->shouldReceive('ejecutar')
            ->once()
            ->andThrow(new Exception('Fallo simulado en fila'));
        $this->app->instance(ProcesarFilaClienteAction::class, $mock);

        app(ImportarClientesWizerpService::class)->ejecutar($archivo, $importacion);

        $this->assertDatabaseHas('errores_importacion_clientes', [
            'importacion_cliente_id' => $importacion->id,
            'numero_cliente' => '999',
            'mensaje' => 'Fallo simulado en fila',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_importacion_persiste_cambios_lista_con_tipo(): void
    {
        $this->sincronizarListas();
        $user = User::factory()->create();

        CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO BRONCE',
            'monto_requerido' => 0.01,
            'activo' => true,
        ]);

        $listaPg = CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->first();
        $listaBronce = CatalogoListaDescuento::where('nombre', 'MAYOREO BRONCE')->first();

        $cliente = Cliente::create([
            'numero_cliente' => '9100',
            'nombre' => 'Cliente Lista',
            'lista_actual_id' => $listaPg->id,
            'monto_venta_actual' => 100,
        ]);

        $importacion = ImportacionCliente::create([
            'usuario_id' => $user->id,
            'nombre_archivo_original' => 'listas.csv',
            'ruta_almacenamiento' => 'importaciones_clientes/listas.csv',
        ]);

        Storage::fake('local');
        Storage::disk('local')->put(
            'importaciones_clientes/listas.csv',
            "numero_cliente,nombre,codigo_lista,monto_venta_actual\n9100,Cliente Lista,2,500\n",
        );

        $archivo = new UploadedFile(
            Storage::disk('local')->path('importaciones_clientes/listas.csv'),
            'listas.csv',
            'text/csv',
            null,
            true,
        );

        app(ImportarClientesWizerpService::class)->ejecutar($archivo, $importacion);

        $this->assertDatabaseHas('cambios_lista_importacion_clientes', [
            'importacion_cliente_id' => $importacion->id,
            'numero_cliente' => '9100',
            'lista_anterior' => 'PUBLICO GENERAL',
            'lista_nueva' => 'MAYOREO BRONCE',
            'tipo_cambio' => CambioListaImportacionCliente::TIPO_ASCENSO,
            'codigo_lista' => '2',
        ]);

        $cliente->refresh();
        $this->assertSame($listaBronce->id, $cliente->lista_actual_id);
    }
}

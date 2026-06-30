<?php

namespace Tests\Feature\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ImportacionCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportacionClienteArchivoTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConPermisos(array $permisos): User
    {
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $role = Role::findOrCreate('admin_import', 'web');
        $role->givePermissionTo($permisos);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function sincronizarListas(): void
    {
        CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
        ]);
    }

    public function test_importacion_guarda_archivo_y_registro(): void
    {
        Storage::fake('local');
        $this->sincronizarListas();

        $user = $this->usuarioConPermisos(['clientes.ver', 'clientes.carga_masiva']);

        $csv = "numero_cliente,nombre,monto_venta_actual\n900,Cliente Nuevo,150\n";
        $archivo = UploadedFile::fake()->createWithContent('clientes_wizerp.csv', $csv);

        $response = $this->actingAs($user)->post(route('admin.clientes.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $importacion = ImportacionCliente::first();
        $this->assertNotNull($importacion);
        $this->assertSame('clientes_wizerp.csv', $importacion->nombre_archivo_original);
        $this->assertSame($user->id, $importacion->usuario_id);
        Storage::disk('local')->assertExists($importacion->ruta_almacenamiento);

        $this->assertDatabaseHas('clientes', [
            'numero_cliente' => '900',
            'nombre' => 'Cliente Nuevo',
        ]);
    }

    public function test_descarga_archivo_importacion(): void
    {
        Storage::fake('local');
        $user = $this->usuarioConPermisos(['clientes.ver', 'clientes.carga_masiva']);

        $ruta = 'importaciones_clientes/2026/06/test.csv';
        Storage::disk('local')->put($ruta, "numero_cliente,nombre\n1,Test\n");

        $importacion = ImportacionCliente::create([
            'usuario_id' => $user->id,
            'nombre_archivo_original' => 'evidencia.csv',
            'ruta_almacenamiento' => $ruta,
        ]);

        $response = $this->actingAs($user)->get(
            route('admin.clientes.importaciones.archivo', $importacion)
        );

        $response->assertOk();
        $response->assertDownload('evidencia.csv');
    }

    public function test_descarga_requiere_permiso_carga_masiva(): void
    {
        Storage::fake('local');
        $user = $this->usuarioConPermisos(['clientes.ver']);

        $ruta = 'importaciones_clientes/2026/06/test.csv';
        Storage::disk('local')->put($ruta, 'contenido');

        $importacion = ImportacionCliente::create([
            'usuario_id' => $user->id,
            'nombre_archivo_original' => 'evidencia.csv',
            'ruta_almacenamiento' => $ruta,
        ]);

        $this->actingAs($user)->get(
            route('admin.clientes.importaciones.archivo', $importacion)
        )->assertForbidden();
    }
}

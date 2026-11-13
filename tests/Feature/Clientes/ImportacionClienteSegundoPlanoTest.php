<?php

namespace Tests\Feature\Clientes;

use App\Jobs\Clientes\AnalizarImportacionClientesJob;
use App\Jobs\Clientes\ProcesarImportacionClientesJob;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\FilaRevisionImportacionCliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\User;
use App\Services\Clientes\ValidarArchivoClientesWizerp;
use App\Support\Colas\DisponibilidadCola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportacionClienteSegundoPlanoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach (['PUBLICO GENERAL' => 0, 'MAYOREO BRONCE' => 0.01, 'MAYOREO PLATA' => 5104] as $nombre => $monto) {
            CatalogoListaDescuento::create(['nombre' => $nombre, 'monto_requerido' => $monto, 'activo' => true]);
        }
        Permission::findOrCreate('clientes.ver', 'web');
        Permission::findOrCreate('clientes.carga_masiva', 'web');
        $rol = Role::findOrCreate('admin_import', 'web');
        $rol->givePermissionTo(['clientes.ver', 'clientes.carga_masiva']);
        $this->usuario = User::factory()->create();
        $this->usuario->assignRole($rol);
    }

    public function test_la_recepcion_del_archivo_no_cuenta_como_importacion_terminada(): void
    {
        Queue::fake();
        $respuesta = $this->actingAs($this->usuario)->postJson(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo("numero_cliente,nombre,monto_venta_actual\n1,Cliente ficticio,10\n"),
        ]);

        $respuesta->assertOk();
        $respuesta->assertJsonPath('fase', 'analizando');
        $this->assertNotSame('completado', $respuesta->json('fase'));
        Queue::assertPushed(AnalizarImportacionClientesJob::class);
        $this->assertNull(Cliente::where('numero_cliente', '1')->first());
        $this->assertSame('analizando', ImportacionCliente::first()->estado);
    }

    public function test_la_revision_se_pagina_y_no_se_corta_en_25(): void
    {
        $lineas = ['numero_cliente,nombre,monto_venta_actual'];
        for ($i = 1; $i <= 30; $i++) {
            $lineas[] = $i.',Cliente ficticio '.$i.',10';
        }
        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo(implode("\n", $lineas)."\n"),
        ])->assertRedirect();

        $importacion = ImportacionCliente::first();
        $this->assertSame(30, FilaRevisionImportacionCliente::where('importacion_cliente_id', $importacion->id)->count());
        $this->actingAs($this->usuario)
            ->getJson(route('admin.clientes.importaciones.revision', $importacion).'?page=2')
            ->assertOk()
            ->assertJsonPath('filas.total', 30)
            ->assertJsonPath('filas.per_page', 25)
            ->assertJsonCount(5, 'filas.data');
    }

    public function test_la_confirmacion_se_encola_y_el_reintento_no_duplica(): void
    {
        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo("numero_cliente,nombre,codigo_lista,monto_venta_actual\n700,Cliente ficticio,2,20\n"),
        ])->assertRedirect();
        $importacion = ImportacionCliente::first();
        $token = $importacion->resumen['revision']['token'];

        Queue::fake();
        $this->actingAs($this->usuario)->postJson(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $importacion->id,
            'token' => $token,
        ])->assertOk()->assertJsonPath('fase', 'procesando');
        Queue::assertPushed(ProcesarImportacionClientesJob::class);
        $this->assertSame('en_cola', $importacion->fresh()->estado);
        $this->assertNull(Cliente::where('numero_cliente', '700')->first());

        (new ProcesarImportacionClientesJob($importacion->id, $token))->handle(
            app(\App\Services\Clientes\RevisionImportacionClientes::class),
            app(\App\Services\Clientes\ImportarClientesWizerpService::class),
            app(\App\Services\Clientes\RegistrarImportacionClienteService::class),
        );
        $this->assertSame('20.00', Cliente::where('numero_cliente', '700')->value('monto_venta_actual'));
        $historial = HistorialMontoCliente::count();

        (new ProcesarImportacionClientesJob($importacion->id, $token))->handle(
            app(\App\Services\Clientes\RevisionImportacionClientes::class),
            app(\App\Services\Clientes\ImportarClientesWizerpService::class),
            app(\App\Services\Clientes\RegistrarImportacionClienteService::class),
        );
        $this->assertSame($historial, HistorialMontoCliente::count());
        $this->assertSame('completo', $importacion->fresh()->estado);
    }

    public function test_un_archivo_cambiado_despues_de_revisar_no_escribe(): void
    {
        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo("numero_cliente,monto_venta_actual\n710,10\n"),
        ])->assertRedirect();
        $importacion = ImportacionCliente::first();
        Storage::disk('local')->put($importacion->ruta_almacenamiento, "numero_cliente,monto_venta_actual\n710,90\n");

        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $importacion->id,
            'token' => $importacion->resumen['revision']['token'],
        ])->assertSessionHasErrors('archivo');

        $this->assertNull(Cliente::where('numero_cliente', '710')->first());
        $this->assertFalse((bool) ($importacion->fresh()->resumen['revision']['aplicada'] ?? false));
        $this->actingAs($this->usuario)
            ->getJson(route('admin.clientes.importaciones.progreso', $importacion))
            ->assertJsonPath('fase', 'error');
    }

    public function test_el_fallo_del_trabajador_queda_visible(): void
    {
        $importacion = ImportacionCliente::create([
            'usuario_id' => $this->usuario->id,
            'nombre_archivo_original' => 'ficticio.csv',
            'ruta_almacenamiento' => 'importaciones_clientes/ficticio.csv',
            'estado' => 'analizando',
        ]);
        (new AnalizarImportacionClientesJob($importacion->id))->failed(new \RuntimeException('Fallo ficticio del trabajador'));

        $this->actingAs($this->usuario)
            ->getJson(route('admin.clientes.importaciones.progreso', $importacion))
            ->assertOk()
            ->assertJsonPath('fase', 'error')
            ->assertJsonPath('mensaje', 'Fallo ficticio del trabajador');
    }

    public function test_sin_trabajador_no_libera_la_importacion(): void
    {
        $this->mock(DisponibilidadCola::class, function ($mock) {
            $mock->shouldReceive('puedeEncolar')->andReturn(false);
        });

        $this->actingAs($this->usuario)->postJson(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo("numero_cliente,nombre\n720,Cliente ficticio\n"),
        ])->assertStatus(503);

        $importacion = ImportacionCliente::first();
        $this->assertSame('recibido', $importacion->estado);
        $this->assertNull(Cliente::where('numero_cliente', '720')->first());
    }

    public function test_el_segundo_lote_no_vuelve_a_leer_el_csv(): void
    {
        $lineas = ['numero_cliente,nombre,codigo_lista,monto_venta_actual'];
        for ($i = 1; $i <= 41; $i++) {
            $lineas[] = $i.',Cliente ficticio '.$i.',pg,10';
        }
        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo(implode("\n", $lineas)."\n"),
        ])->assertRedirect();
        $importacion = ImportacionCliente::first();
        $this->assertNotNull($importacion->resumen['tiempos']['analisis_ms'] ?? null);
        $this->assertNotNull(FilaRevisionImportacionCliente::query()->where('importacion_cliente_id', $importacion->id)->value('datos'));

        ValidarArchivoClientesWizerp::$lecturas = 0;
        (new ProcesarImportacionClientesJob($importacion->id, $importacion->resumen['revision']['token']))->handle(
            app(\App\Services\Clientes\RevisionImportacionClientes::class),
            app(\App\Services\Clientes\ImportarClientesWizerpService::class),
            app(\App\Services\Clientes\RegistrarImportacionClienteService::class),
        );

        $this->assertSame(1, ValidarArchivoClientesWizerp::$lecturas);
        $this->assertSame(41, Cliente::query()->count());
        $this->assertSame('completo', $importacion->fresh()->estado);
        $this->assertArrayHasKey('lote_ms', $importacion->fresh()->resumen['tiempos']);
    }

    public function test_el_progreso_de_imagenes_de_tiendanube_sigue_expuesto(): void
    {
        $this->assertTrue(Route::has('tiendanube.imagenes.importar.progreso'));
    }

    public function test_cancelar_una_revision_la_saca_de_la_cola(): void
    {
        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo("numero_cliente,nombre,monto_venta_actual\n1,Cliente ficticio,10\n"),
        ])->assertRedirect();
        $importacion = ImportacionCliente::first();
        $this->assertSame('previsualizada', $importacion->estado);

        $this->actingAs($this->usuario)
            ->deleteJson(route('admin.clientes.importaciones.cancelar', $importacion))
            ->assertOk()
            ->assertJsonPath('message', 'La carga salió de la cola.');
        $this->assertSame('cancelada', $importacion->fresh()->estado);
        $this->assertNull(Cliente::where('numero_cliente', '1')->first());
        (new ProcesarImportacionClientesJob($importacion->id, 'token-viejo'))->handle(
            app(\App\Services\Clientes\RevisionImportacionClientes::class),
            app(\App\Services\Clientes\ImportarClientesWizerpService::class),
            app(\App\Services\Clientes\RegistrarImportacionClienteService::class),
        );
        (new AnalizarImportacionClientesJob($importacion->id))->handle(
            app(\App\Services\Clientes\RevisionImportacionClientes::class),
        );
        $this->assertSame('cancelada', $importacion->fresh()->estado);
        $this->actingAs($this->usuario)
            ->getJson(route('admin.clientes.importaciones.activas'))
            ->assertOk()
            ->assertJsonPath('ids', []);

        $ajena = User::factory()->create();
        $ajena->assignRole(Role::findByName('admin_import', 'web'));
        $this->actingAs($ajena)
            ->deleteJson(route('admin.clientes.importaciones.cancelar', $importacion))
            ->assertNotFound();

        $importacion->update(['estado' => 'en_curso', 'filas_procesadas' => 1]);
        $this->actingAs($this->usuario)
            ->deleteJson(route('admin.clientes.importaciones.cancelar', $importacion))
            ->assertStatus(422);
        $this->assertSame('en_curso', $importacion->fresh()->estado);
        $this->assertSame(1, $importacion->fresh()->filas_procesadas);
    }

    public function test_otra_cuenta_no_consulta_la_revision(): void
    {
        $this->actingAs($this->usuario)->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => $this->archivo("numero_cliente,nombre\n730,Cliente ficticio\n"),
        ])->assertRedirect();
        $importacion = ImportacionCliente::first();
        $ajena = User::factory()->create();
        $ajena->assignRole(Role::findByName('admin_import', 'web'));

        $this->actingAs($ajena)
            ->getJson(route('admin.clientes.importaciones.progreso', $importacion))
            ->assertNotFound();
    }

    private function archivo(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('revision.csv', $csv);
    }
}

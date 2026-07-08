<?php

namespace Tests\Feature\Almacenes;

use App\Jobs\Almacenes\ImportarAlmacenCatalogoJob;
use App\Models\Almacen;
use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ImportacionAlmacenAsyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'almacenes.productos.gestionar',
            'almacenes.inventarios.importar',
            'almacenes.costos.importar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'almacenes.productos.gestionar',
            'almacenes.inventarios.importar',
            'almacenes.costos.importar',
        ]);
    }

    public function test_iniciar_importacion_productos_despacha_job_y_devuelve_log_id(): void
    {
        Queue::fake();

        $tempPath = $this->crearArchivoTemporalProductos();

        $response = $this->actingAs($this->usuario)->postJson(route('almacenes.productos.import_iniciar'), [
            'file_path' => $tempPath,
            'mapping' => [
                'sku' => 'sku',
                'descripcion' => 'descripcion',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['log_id']);

        $this->assertDatabaseHas('importaciones_almacen_logs', [
            'id' => $response->json('log_id'),
            'tipo' => 'productos',
            'estado' => 'pendiente',
        ]);

        Queue::assertPushed(ImportarAlmacenCatalogoJob::class, function ($job) use ($response) {
            return $job->logId === $response->json('log_id');
        });
    }

    public function test_job_procesa_lote_de_productos_y_completa_log(): void
    {
        $tempPath = $this->crearArchivoTemporalProductos();
        Storage::makeDirectory('importaciones_almacenes/1');
        $persistente = 'importaciones_almacenes/1/source.csv';
        Storage::move($tempPath, $persistente);

        $log = ImportacionAlmacenLog::create([
            'user_id' => $this->usuario->id,
            'tipo' => 'productos',
            'archivo_ruta' => $persistente,
            'mapping' => ['sku' => 'sku', 'descripcion' => 'descripcion'],
            'estado' => 'pendiente',
        ]);

        $job = new ImportarAlmacenCatalogoJob($log->id, 0);
        $job->handle(
            app(\App\Services\Almacenes\LeerFilasImportacionAlmacenService::class),
            app(\App\Services\Almacenes\ProcesarFilaProductoImportacionService::class),
            app(\App\Services\Almacenes\ProcesarFilaInventarioImportacionService::class),
            app(\App\Services\Almacenes\ProcesarFilaCostoImportacionService::class),
            app(\App\Services\Almacenes\FinalizarImportacionAlmacenAsyncService::class),
        );

        $log = $log->fresh();
        $this->assertSame('completado', $log->estado);
        $this->assertSame(2, $log->total_filas);
        $this->assertSame(2, $log->procesados);
        $this->assertSame(2, $log->importados);
        $this->assertDatabaseHas('productos', ['sku' => 'IMP001']);
        $this->assertDatabaseHas('productos', ['sku' => 'IMP002']);
    }

    public function test_importacion_costos_actualiza_costo_sin_tocar_inventario(): void
    {
        $almacen = $this->crearAlmacen();
        $producto = $this->crearProducto(['sku' => 'COST01', 'descripcion' => 'Producto costos']);

        Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'existencia' => 25,
            'apartado' => 0,
        ]);

        $csv = "sku,costo,costo_reposicion,precio_venta\nCOST01,10.50,11.00,19.99\n";
        $persistente = 'importaciones_almacenes/2/source.csv';
        Storage::put($persistente, $csv);

        $log = ImportacionAlmacenLog::create([
            'user_id' => $this->usuario->id,
            'tipo' => 'costos',
            'almacen_id' => $almacen->id,
            'archivo_ruta' => $persistente,
            'mapping' => [
                'sku' => 'sku',
                'costo' => 'costo',
                'costo_reposicion' => 'costo_reposicion',
                'precio_venta' => 'precio_venta',
            ],
            'estado' => 'pendiente',
        ]);

        $job = new ImportarAlmacenCatalogoJob($log->id, 0);
        $job->handle(
            app(\App\Services\Almacenes\LeerFilasImportacionAlmacenService::class),
            app(\App\Services\Almacenes\ProcesarFilaProductoImportacionService::class),
            app(\App\Services\Almacenes\ProcesarFilaInventarioImportacionService::class),
            app(\App\Services\Almacenes\ProcesarFilaCostoImportacionService::class),
            app(\App\Services\Almacenes\FinalizarImportacionAlmacenAsyncService::class),
        );

        $this->assertDatabaseHas('producto_costos', [
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'costo' => '10.50',
            'precio_venta' => '19.99',
        ]);

        $this->assertDatabaseHas('inventarios', [
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'existencia' => '25.000',
        ]);
    }

    public function test_importacion_costos_omite_producto_inexistente(): void
    {
        $almacen = $this->crearAlmacen();
        $csv = "sku,costo\nNOEXISTE,15.00\n";
        $persistente = 'importaciones_almacenes/3/source.csv';
        Storage::put($persistente, $csv);

        $log = ImportacionAlmacenLog::create([
            'user_id' => $this->usuario->id,
            'tipo' => 'costos',
            'almacen_id' => $almacen->id,
            'archivo_ruta' => $persistente,
            'mapping' => ['sku' => 'sku', 'costo' => 'costo'],
            'estado' => 'pendiente',
        ]);

        $job = new ImportarAlmacenCatalogoJob($log->id, 0);
        $job->handle(
            app(\App\Services\Almacenes\LeerFilasImportacionAlmacenService::class),
            app(\App\Services\Almacenes\ProcesarFilaProductoImportacionService::class),
            app(\App\Services\Almacenes\ProcesarFilaInventarioImportacionService::class),
            app(\App\Services\Almacenes\ProcesarFilaCostoImportacionService::class),
            app(\App\Services\Almacenes\FinalizarImportacionAlmacenAsyncService::class),
        );

        $log = $log->fresh();
        $this->assertSame(1, $log->omitidos);
        $this->assertDatabaseHas('importaciones_almacen_errores', [
            'importacion_almacen_log_id' => $log->id,
            'fila' => 2,
        ]);
    }

    public function test_endpoint_progreso_devuelve_estado(): void
    {
        $log = ImportacionAlmacenLog::create([
            'user_id' => $this->usuario->id,
            'tipo' => 'productos',
            'archivo_ruta' => 'importaciones_almacenes/x/source.csv',
            'mapping' => ['sku' => 'sku', 'descripcion' => 'descripcion'],
            'total_filas' => 100,
            'procesados' => 40,
            'estado' => 'en_proceso',
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('almacenes.importaciones.progreso', $log->id))
            ->assertOk()
            ->assertJsonPath('procesados', 40)
            ->assertJsonPath('total_filas', 100)
            ->assertJsonPath('estado', 'en_proceso');
    }

    public function test_cancelar_importacion_activa(): void
    {
        $log = ImportacionAlmacenLog::create([
            'user_id' => $this->usuario->id,
            'tipo' => 'productos',
            'archivo_ruta' => 'importaciones_almacenes/x/source.csv',
            'mapping' => ['sku' => 'sku', 'descripcion' => 'descripcion'],
            'estado' => 'en_proceso',
        ]);

        $this->actingAs($this->usuario)
            ->deleteJson(route('almacenes.importaciones.cancelar', $log->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('importaciones_almacen_logs', [
            'id' => $log->id,
            'estado' => 'cancelado',
        ]);
    }

    private function crearArchivoTemporalProductos(): string
    {
        $contenido = "sku,descripcion\nIMP001,Producto Uno\nIMP002,Producto Dos\n";
        $path = 'temp/import_test_' . Str::uuid() . '.csv';
        Storage::put($path, $contenido);

        return $path;
    }

    private function crearProducto(array $attrs = []): Producto
    {
        static $folio = 600000;
        $folio++;

        return Producto::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU' . $folio,
            'descripcion' => 'Producto test ' . $folio,
            'activo' => true,
        ], $attrs));
    }

    private function crearAlmacen(): Almacen
    {
        static $n = 0;
        $n++;

        return Almacen::create([
            'codigo' => 'ALM' . $n,
            'nombre' => 'Almacén Test ' . $n,
            'activo' => true,
        ]);
    }
}

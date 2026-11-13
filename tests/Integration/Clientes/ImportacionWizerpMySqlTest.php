<?php

namespace Tests\Integration\Clientes;

use App\Jobs\NotificarMobileSyncJob;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\HistorialMontoCliente;
use App\Models\MobileSyncPublication;
use App\Models\User;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Mobile\MobileSyncPublicationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Ejecutar únicamente con el MySQL efímero definido en scripts/qa/wizerp-mysql.sh. */
class ImportacionWizerpMySqlTest extends TestCase
{
    use DatabaseTruncation;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'mysql'
            || $app['config']->get('database.connections.mysql.database') !== 'gelia_wizerp_test'
            || $app['config']->get('database.connections.mysql.host') !== 'gelia-wizerp-mysql-test') {
            throw new \RuntimeException('Prueba MySQL bloqueada fuera del contenedor y base exclusivos.');
        }

        return $app;
    }

    private function importar(): array
    {
        return app(ImportarClientesWizerpService::class)->ejecutar(UploadedFile::fake()->createWithContent('test.csv', "numero_cliente,monto_venta_actual\n990077,7000\n"));
    }

    public function test_importaciones_excluyentes_con_conexiones_independientes(): void
    {
        config(['database.connections.competidor' => config('database.connections.mysql')]);
        $competidor = DB::connection('competidor');
        $this->assertSame(1, (int) $competidor->selectOne("SELECT GET_LOCK('gelia.importacion.clientes.wizerp', 0) AS adquirido")->adquirido);
        try {
            for ($i = 0; $i < 2; $i++) {
                try {
                    $this->importar();
                    $this->fail('Importación concurrente debió rechazarse');
                } catch (ValidationException $e) {
                    $this->assertStringContainsString('en curso', $e->getMessage());
                }
            }
            $this->assertSame(0, Cliente::count());
        } finally {
            $competidor->selectOne("SELECT RELEASE_LOCK('gelia.importacion.clientes.wizerp')");
            DB::disconnect('competidor');
        }
    }

    public function test_importacion_espera_bloqueo_y_lee_ultimo_monto_confirmado(): void
    {
        $lista = CatalogoListaDescuento::create(['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0, 'activo' => true]);
        Cliente::create(['numero_cliente' => '990077', 'nombre' => 'Ficticio', 'lista_actual_id' => $lista->id, 'monto_venta_actual' => '1000.00']);
        $senal = tempnam('/tmp', 'wizerp-lock-');
        unlink($senal);
        $codigo = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'gelia_wizerp_test' || config('database.connections.mysql.host') !== 'gelia-wizerp-mysql-test') { exit(9); }
Illuminate\Support\Facades\DB::transaction(function () use ($argv) {
    Illuminate\Support\Facades\DB::table('clientes')->where('numero_cliente', '990077')->lockForUpdate()->first();
    Illuminate\Support\Facades\DB::table('clientes')->where('numero_cliente', '990077')->update(['monto_venta_actual' => '900.00']);
    file_put_contents($argv[1], 'locked');
    usleep(800000);
});
CODE;
        $proceso = new Process([PHP_BINARY, '-r', $codigo, $senal], base_path(), [
            'APP_CONFIG_CACHE' => '/tmp/gelia-mysql-test-config.php', 'APP_ROUTES_CACHE' => '/tmp/gelia-mysql-test-routes.php',
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => 'gelia-wizerp-mysql-test', 'DB_PORT' => '3306', 'DB_DATABASE' => 'gelia_wizerp_test',
            'DB_USERNAME' => 'root', 'DB_PASSWORD' => 'gelia-isolated-test', 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array',
        ]);
        $proceso->start();
        try {
            $limite = microtime(true) + 10;
            while (! is_file($senal) && $proceso->isRunning() && microtime(true) < $limite) {
                usleep(10000);
            }
            $this->assertFileExists($senal, $proceso->getErrorOutput());
            $r = $this->importar();
            $proceso->wait();
            $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput());
            $this->assertSame(1, $r['stats']['actualizados']);
            $this->assertSame('900.00', HistorialMontoCliente::first()->monto_anterior);
            $this->assertSame('7000.00', Cliente::first()->monto_venta_actual);
        } finally {
            $proceso->stop();
            @unlink($senal);
        }
    }

    public function test_publicacion_y_aviso_solo_sobreviven_al_commit(): void
    {
        $user = User::factory()->create();
        $servicio = app(MobileSyncPublicationService::class);
        $lista = CatalogoListaDescuento::create(['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0, 'activo' => true]);
        $c = Cliente::create(['numero_cliente' => '990088', 'nombre' => 'Móvil ficticio', 'lista_actual_id' => $lista->id]);
        Queue::fake();
        $antes = MobileSyncPublication::count();
        DB::beginTransaction();
        $servicio->publicarCliente($c, null);
        $servicio->notificar([$user->id], 123, false);
        Queue::assertNothingPushed();
        DB::rollBack();
        $this->assertSame($antes, MobileSyncPublication::count());
        Queue::assertNothingPushed();
        DB::beginTransaction();
        $servicio->publicarCliente($c, null);
        $servicio->notificar([$user->id], 124, false);
        Queue::assertNothingPushed();
        DB::commit();
        $this->assertSame($antes + 1, MobileSyncPublication::count());
        Queue::assertPushed(NotificarMobileSyncJob::class);
    }
}

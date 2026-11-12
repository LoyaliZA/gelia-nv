<?php

namespace Tests\Integration\Solicitudes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\SolicitudBeneficio;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Solo contra la base exclusiva gelia_wizerp_test. No usa la base operativa. */
class ReversionBeneficiosMySqlTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $mysql = $app['config']->get('database.connections.mysql');
        $host = (string) ($mysql['host'] ?? '');
        $base = (string) ($mysql['database'] ?? '');
        if ($app['config']->get('database.default') !== 'mysql'
            || $base !== 'gelia_wizerp_test'
            || $base === 'gelia'
            || ! in_array($host, ['gelia-wizerp-mysql-test', '127.0.0.1'], true)) {
            throw new RuntimeException('Prueba MySQL bloqueada fuera de la base exclusiva gelia_wizerp_test.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (Schema::getTables() as $tabla) {
            $nombre = $tabla['name'];
            if (in_array($nombre, ['migrations'], true)) {
                continue;
            }
            DB::table($nombre)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_aprobacion_y_cancelacion_concurrentes_respetan_la_vigente(): void
    {
        [$cliente, $primera, $segunda, $plata] = $this->escenario();
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($primera);
        $senal = tempnam(sys_get_temp_dir(), 'fase2b-lock-');
        unlink($senal);
        $cancelar = $this->proceso($this->codigoRevertir(), [$primera->id, $senal]);
        $aprobar = $this->proceso($this->codigoAplicar(), [$segunda->id]);
        $cancelar->start();
        try {
            $limite = microtime(true) + 10;
            while (! is_file($senal) && $cancelar->isRunning() && microtime(true) < $limite) {
                usleep(10000);
            }
            $this->assertFileExists($senal, $cancelar->getErrorOutput());
            $aprobar->start();
            $aprobar->wait();
            $cancelar->wait();
            $this->assertTrue($cancelar->isSuccessful(), $cancelar->getErrorOutput());
            $this->assertTrue($aprobar->isSuccessful(), $aprobar->getErrorOutput());
            $this->assertSame($plata, $cliente->fresh()->lista_actual_id);
            $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
            $this->assertSame(
                1,
                SolicitudBeneficio::query()->where('solicitud_tag_id', $segunda->id)->where('estado', SolicitudBeneficio::ESTADO_VIGENTE)->count()
            );
        } finally {
            $cancelar->stop(0);
            $aprobar->stop(0);
        }
    }

    public function test_dos_concesiones_concurrentes_no_dejan_montos_duplicados(): void
    {
        [$cliente, $primera, $segunda, $plata] = $this->escenario();
        $una = $this->proceso($this->codigoAplicar(), [$primera->id]);
        $otra = $this->proceso($this->codigoAplicar(), [$segunda->id]);
        $una->start();
        $otra->start();
        $una->wait();
        $otra->wait();

        $this->assertTrue($una->isSuccessful(), $una->getErrorOutput());
        $this->assertTrue($otra->isSuccessful(), $otra->getErrorOutput());
        $this->assertSame($plata, $cliente->fresh()->lista_actual_id);
        $this->assertEquals(1000.0, (float) $cliente->fresh()->monto_venta_actual);
        $vigentes = SolicitudBeneficio::query()->where('estado', SolicitudBeneficio::ESTADO_VIGENTE)->get();
        $this->assertCount(2, $vigentes);
        $this->assertSame([(string) $plata], $vigentes->pluck('valor_aplicado')->unique()->values()->all());
    }

    /**
     * @return array{0: Cliente, 1: SolicitudTag, 2: SolicitudTag, 3: int}
     */
    private function escenario(): array
    {
        $bronce = CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01, 'activo' => true]);
        $plata = CatalogoListaDescuento::create(['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5104, 'activo' => true]);
        $vendedor = User::factory()->create();
        $cliente = Cliente::create([
            'numero_cliente' => '880020',
            'nombre' => 'Ficticio',
            'lista_actual_id' => $bronce->id,
            'monto_venta_actual' => '1000.00',
            'vendedor_id' => $vendedor->id,
        ]);
        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta', 'Cancelada'] as $estado) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $estado], ['activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $proceso = CatalogoProceso::create([
            'nombre' => 'CAMBIO DE LISTA',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        $comun = [
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Respondida'),
            'catalogo_lista_descuento_id' => $plata->id,
            'monto_cotizado' => 6000,
            'pago_confirmado' => false,
        ];

        return [$cliente, SolicitudTag::create($comun), SolicitudTag::create($comun), $plata->id];
    }

    private function codigoAplicar(): string
    {
        return <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.mysql.database');
$host = (string) config('database.connections.mysql.host');
if ($base !== 'gelia_wizerp_test' || $base === 'gelia' || ! in_array($host, ['gelia-wizerp-mysql-test', '127.0.0.1'], true)) { exit(9); }
$solicitud = App\Models\SolicitudTag::query()->findOrFail($argv[1]);
app(App\Services\Solicitudes\AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud);
CODE;
    }

    private function codigoRevertir(): string
    {
        return <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.mysql.database');
$host = (string) config('database.connections.mysql.host');
if ($base !== 'gelia_wizerp_test' || $base === 'gelia' || ! in_array($host, ['gelia-wizerp-mysql-test', '127.0.0.1'], true)) { exit(9); }
Illuminate\Support\Facades\DB::transaction(function () use ($argv) {
    $solicitud = App\Models\SolicitudTag::query()->findOrFail($argv[1]);
    app(App\Services\Solicitudes\AjustarMontoPorSolicitudService::class)->prepararBloqueo((int) $solicitud->cliente_id);
    file_put_contents($argv[2], 'locked');
    usleep(800000);
    app(App\Services\Solicitudes\AjustarMontoPorSolicitudService::class)->revertirBeneficios($solicitud);
});
CODE;
    }

    /**
     * @param  array<int, int|string>  $argumentos
     */
    private function proceso(string $codigo, array $argumentos): Process
    {
        return new Process(
            array_merge([PHP_BINARY, '-r', $codigo], array_map('strval', $argumentos)),
            base_path(),
            $this->entorno(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function entorno(): array
    {
        return [
            'APP_CONFIG_CACHE' => '/tmp/gelia-fase2b-mysql-config.php',
            'APP_ROUTES_CACHE' => '/tmp/gelia-fase2b-mysql-routes.php',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => 'gelia_wizerp_test',
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
        ];
    }
}

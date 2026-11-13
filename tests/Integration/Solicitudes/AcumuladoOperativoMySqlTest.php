<?php

namespace Tests\Integration\Solicitudes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\ImporteProvisionalSolicitud;
use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Solo contra la base exclusiva gelia_wizerp_test. No usa la base operativa. */
class AcumuladoOperativoMySqlTest extends TestCase
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

    public function test_dos_pagos_concurrentes_dejan_un_solo_vigente(): void
    {
        $bronce = CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01, 'activo' => true]);
        $vendedor = User::factory()->create();
        $cliente = Cliente::create([
            'numero_cliente' => '880030',
            'nombre' => 'Ficticio',
            'lista_actual_id' => $bronce->id,
            'monto_venta_actual' => '5000.00',
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
        $solicitud = SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Respondida'),
            'monto_cotizado' => 30000,
            'pago_confirmado' => false,
        ]);

        $una = $this->proceso($solicitud->id);
        $otra = $this->proceso($solicitud->id);
        $una->start();
        $otra->start();
        $una->wait();
        $otra->wait();

        $this->assertTrue($una->isSuccessful(), $una->getErrorOutput());
        $this->assertTrue($otra->isSuccessful(), $otra->getErrorOutput());
        $this->assertSame('5000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame(1, ImporteProvisionalSolicitud::query()->where('estado', 'vigente')->count());
    }

    private function proceso(int $solicitudId): Process
    {
        $codigo = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.mysql.database');
$host = (string) config('database.connections.mysql.host');
if ($base !== 'gelia_wizerp_test' || $base === 'gelia' || ! in_array($host, ['gelia-wizerp-mysql-test', '127.0.0.1'], true)) { exit(9); }
$solicitud = App\Models\SolicitudTag::query()->findOrFail($argv[1]);
app(App\Services\Solicitudes\AjustarMontoPorSolicitudService::class)->aplicarPagoConfirmado($solicitud, 30000, null);
CODE;

        return new Process([PHP_BINARY, '-r', $codigo, (string) $solicitudId], base_path(), [
            'APP_CONFIG_CACHE' => '/tmp/gelia-operativo-mysql-config.php',
            'APP_ROUTES_CACHE' => '/tmp/gelia-operativo-mysql-routes.php',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => 'gelia_wizerp_test',
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
        ]);
    }
}

<?php

namespace Tests\Integration\Solicitudes;

use App\Models\AuditoriaSolicitud;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Escalonamiento\ConciliacionSolicitudEscalonamiento;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Ejecutar únicamente con el MySQL efímero definido en scripts/qa/wizerp-mysql.sh. */
class ConciliacionDocumentalMySqlTest extends TestCase
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

    public function test_la_reversion_mysql_deshace_la_conciliacion(): void
    {
        [$cliente, $solicitud] = $this->escenario('40.00');

        try {
            DB::transaction(function () use ($solicitud) {
                app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud);
                throw new RuntimeException('forzar reversa');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('forzar reversa', $e->getMessage());
        }

        $this->assertSame(0, EscalonamientoConciliacionSolicitud::count());
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_dos_conciliaciones_concurrentes_no_exceden_el_documento(): void
    {
        [, $primera, $segunda, $documento] = $this->dosSolicitudes();
        $uno = $this->procesoConciliar($primera->id);
        $dos = $this->procesoConciliar($segunda->id);
        $uno->start();
        $dos->start();
        $uno->wait();
        $dos->wait();

        $this->assertTrue($uno->isSuccessful(), $uno->getErrorOutput().$uno->getOutput());
        $this->assertTrue($dos->isSuccessful(), $dos->getErrorOutput().$dos->getOutput());
        $this->assertSame('100.00', $this->sumaAsignada($documento));
        $this->assertSame(1, EscalonamientoConciliacionSolicitud::query()->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)->count());
        $this->assertSame(1, EscalonamientoConciliacionSolicitud::query()->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_SIN_DISPONIBILIDAD)->count());
    }

    public function test_la_conciliacion_espera_el_pago_bloqueado(): void
    {
        [$cliente, $solicitud, $documento] = $this->escenario('100.00');
        $solicitud->update(['pago_confirmado' => false, 'monto_aplicado_al_cliente' => 0, 'monto_cotizado' => '100.00']);
        $senal = tempnam('/tmp', 'conciliacion-lock-');
        unlink($senal);
        $codigo = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.mysql.database');
$host = (string) config('database.connections.mysql.host');
if ($base !== 'gelia_wizerp_test' || $base === 'gelia' || ! in_array($host, ['gelia-wizerp-mysql-test', '127.0.0.1'], true)) { exit(9); }
Illuminate\Support\Facades\DB::transaction(function () use ($argv) {
    $solicitud = App\Models\SolicitudTag::query()->lockForUpdate()->findOrFail($argv[1]);
    $solicitud->pago_confirmado = true;
    $solicitud->monto_aplicado_al_cliente = '40.00';
    $solicitud->save();
    file_put_contents($argv[2], 'locked');
    usleep(800000);
});
CODE;
        $proceso = new Process([PHP_BINARY, '-r', $codigo, (string) $solicitud->id, $senal], base_path(), $this->entorno());
        $proceso->start();
        try {
            $limite = microtime(true) + 10;
            while (! is_file($senal) && $proceso->isRunning() && microtime(true) < $limite) {
                usleep(10000);
            }
            $this->assertFileExists($senal, $proceso->getErrorOutput());
            $fila = app(ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud->fresh());
            $proceso->wait();
            $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput());
            $this->assertSame('40.00', (string) $fila->fresh()->importe_asignado);
            $this->assertSame(EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA, $fila->fresh()->estado);
            $this->assertSame('40.00', $this->sumaAsignada($documento));
            $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
        } finally {
            $proceso->stop(0);
        }
    }

    public function test_conciliar_y_asignar_la_misma_solicitud_no_duplican(): void
    {
        [$cliente, $solicitud, $documento] = $this->escenario('100.00');
        $conciliar = $this->procesoConciliar($solicitud->id);
        $asignar = $this->procesoAsignar($solicitud->id, $documento->id, (int) $documento->escalonamiento_periodo_id, '100.00');
        $conciliar->start();
        $asignar->start();
        $conciliar->wait();
        $asignar->wait();

        $this->assertTrue($conciliar->isSuccessful(), $conciliar->getErrorOutput());
        $this->assertTrue($asignar->isSuccessful(), $asignar->getErrorOutput());
        $this->assertSame('100.00', $this->sumaAsignada($documento));
        $this->assertSame(1, EscalonamientoConciliacionSolicitud::query()->where('solicitud_tag_id', $solicitud->id)->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)->count());
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_cancelar_mientras_se_asigna_no_reactiva_cobertura(): void
    {
        [$cliente, $solicitud, $documento] = $this->escenario('100.00');
        $cancelada = CatalogoEstadoSolicitud::query()->firstOrCreate(['nombre' => 'Cancelada'], ['activo' => true]);
        $senal = tempnam(sys_get_temp_dir(), 'fase2a-cancel-');
        unlink($senal);
        $resultado = tempnam(sys_get_temp_dir(), 'fase2a-asig-');
        $codigo = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.mysql.database');
$host = (string) config('database.connections.mysql.host');
if ($base !== 'gelia_wizerp_test' || $base === 'gelia' || ! in_array($host, ['gelia-wizerp-mysql-test', '127.0.0.1'], true)) { exit(9); }
Illuminate\Support\Facades\DB::transaction(function () use ($argv) {
    $solicitud = App\Models\SolicitudTag::query()->lockForUpdate()->findOrFail($argv[1]);
    $solicitud->catalogo_estado_solicitud_id = (int) $argv[2];
    $solicitud->save();
    file_put_contents($argv[3], 'locked');
    usleep(800000);
    app(App\Services\Escalonamiento\ConciliacionSolicitudEscalonamiento::class)->liberar($solicitud);
});
CODE;
        $bloqueo = new Process([
            PHP_BINARY, '-r', $codigo, (string) $solicitud->id, (string) $cancelada->id, $senal,
        ], base_path(), $this->entorno());
        $asignar = $this->procesoAsignar($solicitud->id, $documento->id, (int) $documento->escalonamiento_periodo_id, '100.00', $resultado);
        $bloqueo->start();
        try {
            $limite = microtime(true) + 10;
            while (! is_file($senal) && $bloqueo->isRunning() && microtime(true) < $limite) {
                usleep(10000);
            }
            $this->assertFileExists($senal, $bloqueo->getErrorOutput());
            $asignar->start();
            $asignar->wait();
            $bloqueo->wait();
            $this->assertTrue($bloqueo->isSuccessful(), $bloqueo->getErrorOutput());
            $this->assertTrue($asignar->isSuccessful(), $asignar->getErrorOutput());
            $this->assertSame('rejected', trim((string) file_get_contents($resultado)));
            $this->assertSame(0, EscalonamientoConciliacionSolicitud::query()->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)->count());
            $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
        } finally {
            $bloqueo->stop(0);
            $asignar->stop(0);
        }
    }

    public function test_dos_confirmaciones_http_concurrentes_conservan_un_solo_monto(): void
    {
        [$cliente, $solicitud] = $this->escenario('100.00');
        $solicitud->update(['pago_confirmado' => false, 'monto_aplicado_al_cliente' => 0]);
        Permission::findOrCreate('solicitudes.confirmar_pago', 'web');
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Administrador', 'web');
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('solicitudes.confirmar_pago');
        $solicitud->update(['vendedor_id' => $usuario->id]);

        $primero = tempnam(sys_get_temp_dir(), 'fase2a-http-a-');
        $segundo = tempnam(sys_get_temp_dir(), 'fase2a-http-b-');
        $a = $this->procesoConfirmarHttp($usuario->id, $solicitud->id, '30.00', $primero);
        $b = $this->procesoConfirmarHttp($usuario->id, $solicitud->id, '90.00', $segundo);
        $a->start();
        $b->start();
        $a->wait();
        $b->wait();

        $solicitud->refresh();
        $this->assertTrue($a->isSuccessful(), $a->getErrorOutput().file_get_contents($primero));
        $this->assertTrue($b->isSuccessful(), $b->getErrorOutput().file_get_contents($segundo));
        $this->assertContains((string) $solicitud->monto_aplicado_al_cliente, ['30.00', '90.00']);
        $this->assertTrue((bool) $solicitud->pago_confirmado);
        $this->assertSame(1, AuditoriaSolicitud::query()->where('solicitud_id', $solicitud->id)->count());
        $this->assertSame('1000.00', $cliente->fresh()->monto_venta_actual);
        $this->assertSame($cliente->lista_actual_id, $cliente->fresh()->lista_actual_id);
    }

    /**
     * @return array{0: Cliente, 1: SolicitudTag, 2: DocumentoVenta}
     */
    private function escenario(string $total): array
    {
        $lista = CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01, 'activo' => true]);
        $cliente = Cliente::create(['numero_cliente' => '880010', 'nombre' => 'Ficticio', 'lista_actual_id' => $lista->id, 'monto_venta_actual' => '1000.00']);
        $periodo = EscalonamientoPeriodo::create(['anio' => 2026, 'mes' => 11, 'zona_horaria' => 'America/Mexico_City', 'estado' => 'abierto']);
        $documento = DocumentoVenta::create([
            'escalonamiento_periodo_id' => $periodo->id, 'cliente_id' => $cliente->id, 'tipo' => 'remision', 'folio' => 'R-MYSQL',
            'moneda' => 'MXN', 'total' => $total, 'estado' => 'activo', 'fecha_emision' => '2026-11-01', 'origen' => 'prueba',
            'clave_documento' => 'REMISION|||R-MYSQL|'.$total,
        ]);
        $estado = CatalogoEstadoSolicitud::create(['nombre' => 'Respondida', 'activo' => true]);
        $proceso = CatalogoProceso::create(['nombre' => 'CAMBIO DE LISTA', 'categoria_flujo' => 'financiero', 'activo' => true]);
        $vendedor = User::factory()->create();
        $solicitud = SolicitudTag::create([
            'cliente_id' => $cliente->id, 'vendedor_id' => $vendedor->id, 'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => $estado->id, 'monto_cotizado' => $total, 'monto_aplicado_al_cliente' => $total,
            'pago_confirmado' => true, 'numero_remision' => 'R-MYSQL',
        ]);

        return [$cliente, $solicitud, $documento];
    }

    /**
     * @return array{0: Cliente, 1: SolicitudTag, 2: SolicitudTag, 3: DocumentoVenta}
     */
    private function dosSolicitudes(): array
    {
        [$cliente, $primera, $documento] = $this->escenario('100.00');
        $segunda = SolicitudTag::create([
            'cliente_id' => $cliente->id, 'vendedor_id' => $primera->vendedor_id, 'catalogo_proceso_id' => $primera->catalogo_proceso_id,
            'catalogo_estado_solicitud_id' => $primera->catalogo_estado_solicitud_id, 'monto_cotizado' => '100.00',
            'monto_aplicado_al_cliente' => '100.00', 'pago_confirmado' => true, 'numero_remision' => 'R-MYSQL',
        ]);

        return [$cliente, $primera, $segunda, $documento];
    }

    private function procesoConciliar(int $solicitudId): Process
    {
        $codigo = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.mysql.database');
$host = (string) config('database.connections.mysql.host');
if ($base !== 'gelia_wizerp_test' || $base === 'gelia' || ! in_array($host, ['gelia-wizerp-mysql-test', '127.0.0.1'], true)) { exit(9); }
$solicitud = App\Models\SolicitudTag::query()->findOrFail($argv[1]);
app(App\Services\Escalonamiento\ConciliacionSolicitudEscalonamiento::class)->conciliar($solicitud);
CODE;

        return new Process([PHP_BINARY, '-r', $codigo, (string) $solicitudId], base_path(), $this->entorno());
    }

    private function procesoAsignar(int $solicitudId, int $documentoId, int $periodoId, string $importe, ?string $resultado = null): Process
    {
        $codigo = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.mysql.database');
$host = (string) config('database.connections.mysql.host');
if ($base !== 'gelia_wizerp_test' || $base === 'gelia' || ! in_array($host, ['gelia-wizerp-mysql-test', '127.0.0.1'], true)) { exit(9); }
try {
    $solicitud = App\Models\SolicitudTag::query()->findOrFail($argv[1]);
    $documento = App\Models\Escalonamiento\DocumentoVenta::query()->findOrFail($argv[2]);
    $periodo = App\Models\Escalonamiento\EscalonamientoPeriodo::query()->findOrFail($argv[3]);
    app(App\Services\Escalonamiento\ConciliacionSolicitudEscalonamiento::class)->asignar($periodo, $solicitud, $documento, $argv[4], 'cotejo concurrente', null);
    if (! empty($argv[5])) { file_put_contents($argv[5], 'assigned'); }
} catch (InvalidArgumentException $e) {
    if (! empty($argv[5])) { file_put_contents($argv[5], 'rejected'); }
}
CODE;
        $args = [PHP_BINARY, '-r', $codigo, (string) $solicitudId, (string) $documentoId, (string) $periodoId, $importe];
        if ($resultado !== null) {
            $args[] = $resultado;
        }

        return new Process($args, base_path(), $this->entorno());
    }

    private function procesoConfirmarHttp(int $usuarioId, int $solicitudId, string $monto, string $salida): Process
    {
        $codigo = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$base = (string) config('database.connections.mysql.database');
$host = (string) config('database.connections.mysql.host');
if ($base !== 'gelia_wizerp_test' || $base === 'gelia' || ! in_array($host, ['gelia-wizerp-mysql-test', '127.0.0.1'], true)) { exit(9); }
$usuario = App\Models\User::query()->findOrFail($argv[1]);
$sesion = $app->make('session')->driver();
$sesion->start();
$sesion->put('_token', 'fase2a-http');
Illuminate\Support\Facades\Auth::guard('web')->login($usuario);
$sesion->save();
Illuminate\Cookie\Middleware\EncryptCookies::except(config('session.cookie'));
$peticion = Illuminate\Http\Request::create('/solicitudes/'.$argv[2].'/confirmar-pago', 'PUT', [
    'monto_final_pagado' => $argv[3], '_token' => 'fase2a-http', 'modo' => 'pago',
]);
$peticion->cookies->set(config('session.cookie'), $sesion->getId());
$peticion->headers->set('X-CSRF-TOKEN', 'fase2a-http');
$respuesta = $kernel->handle($peticion);
file_put_contents($argv[4], $respuesta->getStatusCode()."\n".$respuesta->getContent());
$kernel->terminate($peticion, $respuesta);
exit($respuesta->isRedirection() || $respuesta->isSuccessful() ? 0 : 1);
CODE;

        return new Process(
            [PHP_BINARY, '-r', $codigo, (string) $usuarioId, (string) $solicitudId, $monto, $salida],
            base_path(),
            $this->entorno() + ['SESSION_DRIVER' => 'file'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function entorno(): array
    {
        return [
            'APP_CONFIG_CACHE' => '/tmp/gelia-fase2a-mysql-config.php',
            'APP_ROUTES_CACHE' => '/tmp/gelia-fase2a-mysql-routes.php',
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

    private function sumaAsignada(DocumentoVenta $documento): string
    {
        $total = '0.00';
        foreach (EscalonamientoConciliacionSolicitud::query()->where('documento_venta_id', $documento->id)->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)->get() as $fila) {
            $total = bcadd($total, (string) $fila->importe_asignado, 2);
        }

        return $total;
    }
}

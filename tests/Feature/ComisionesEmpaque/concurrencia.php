<?php

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\AsignacionComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteUnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\PagoComisionEmpaque;
use App\Models\ComisionesEmpaque\ParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\RepartoComisionEmpaque;
use App\Models\ComisionesEmpaque\RepartoParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\TarifaComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadLineaComisionEmpaque;
use App\Models\User;
use App\Services\ComisionesEmpaque\ConfirmarCorteComisionEmpaqueService;
use App\Services\ComisionesEmpaque\GestionarUnidadComisionEmpaqueService;
use App\Services\ComisionesEmpaque\RegistrarPagoComisionEmpaqueService;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.mysql.database' => 'gelia_ce_test']);
DB::purge('mysql');
DB::reconnect('mysql');
if (DB::connection()->getDatabaseName() !== 'gelia_ce_test') {
    fwrite(STDERR, "No se conectó a la base aislada. Abortado.\n");
    exit(2);
}

$caso = $argv[1] ?? '';
$worker = $argv[2] ?? 'padre';
$marca = $argv[3] ?? '';

if ($worker !== 'padre') {
    exit(trabajador($caso, $marca));
}

$marca = 'ce'.bin2hex(random_bytes(4));
preparar($caso, $marca);
file_put_contents(sys_get_temp_dir().'/'.$marca.'-go', 'wait');

$procesos = [];
foreach (['1', '2'] as $n) {
    $cmd = [PHP_BINARY, __FILE__, $caso, $n, $marca];
    $procesos[$n] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $procesos[$n.'_out'] = $pipes;
}
usleep(2000000);
file_put_contents(sys_get_temp_dir().'/'.$marca.'-go', 'go');

$fallos = 0;
foreach (['1', '2'] as $n) {
    $salida = stream_get_contents($procesos[$n.'_out'][1]);
    $error = stream_get_contents($procesos[$n.'_out'][2]);
    $codigo = proc_close($procesos[$n]);
    if ($codigo !== 0) {
        $fallos++;
        fwrite(STDERR, "worker {$n} {$codigo}: {$salida} {$error}\n");
    }
}

$verificado = verificar($caso, $marca);
fwrite(STDOUT, $verificado ? "VERIFICADO\n" : "FALLO_VERIFICACION\n");
try {
    limpiar($marca);
} catch (Throwable) {
}
exit(($fallos > 0 || ! $verificado) ? 1 : 0);

function preparar(string $caso, string $marca): void
{
    $user = User::factory()->create(['name' => $marca]);
    $tienda = AreaComisionEmpaque::query()->create([
        'codigo' => $marca, 'nombre' => $marca, 'activo' => true, 'sistema' => false,
    ]);
    TarifaComisionEmpaque::query()->create([
        'area_id' => $tienda->id, 'importe_centavos' => 600, 'vigente_desde' => '2026-01-01 00:00:00', 'creado_por_id' => $user->id,
    ]);
    $participante = ParticipanteComisionEmpaque::query()->create([
        'nombre' => $marca, 'user_id' => $user->id, 'activo' => true,
    ]);
    $reparto = RepartoComisionEmpaque::query()->create([
        'area_id' => $tienda->id,
        'vigente_desde' => '2026-01-01 00:00:00',
        'creado_por_id' => $user->id,
    ]);
    RepartoParticipanteComisionEmpaque::query()->create([
        'reparto_id' => $reparto->id,
        'participante_id' => $participante->id,
        'puntos_base' => 10000,
    ]);
    if ($caso === 'capturar') {
        file_put_contents(sys_get_temp_dir().'/'.$marca, $user->id.'|'.$tienda->id);

        return;
    }

    $unidad = UnidadComisionEmpaque::query()->create([
        'clave_origen' => 'manual:'.$marca,
        'area_id' => $tienda->id,
        'procedencia' => 'manual',
        'origen_registro' => 'manual',
        'origen_tipo' => 'manual',
        'empacado_at' => '2026-10-11 12:00:00',
        'incorporado_at' => now(),
        'empacador_user_id' => $user->id,
        'capturado_por_id' => $user->id,
        'empacador_nombre' => $user->name,
        'cliente_nombre' => $marca,
        'folio_pedido' => $marca,
        'cantidad_origen' => 1,
        'cantidad_comisionable' => 1,
        'importe_centavos' => 600,
        'situacion' => UnidadComisionEmpaque::ELEGIBLE,
        'revision_estado' => 'aceptada',
        'huella' => hash('sha256', $marca),
        'notas' => $marca,
    ]);
    UnidadLineaComisionEmpaque::query()->create([
        'unidad_id' => $unidad->id, 'cantidad' => 1, 'es_bolsa' => false,
    ]);

    if ($caso === 'pagar') {
        $servicio = app(ConfirmarCorteComisionEmpaqueService::class);
        $zona = 'America/Mexico_City';
        $desde = DineroComisionEmpaque::inicioDia('2026-10-01', $zona);
        $hasta = DineroComisionEmpaque::finDia('2026-10-31', $zona);
        $calculo = $servicio->calcular($tienda->id, $desde, $hasta, false);
        $servicio->confirmar($tienda->id, $desde, $hasta, false, $calculo['hash'], $user);
    }

    file_put_contents(sys_get_temp_dir().'/'.$marca, $user->id.'|'.$tienda->id);
}

function trabajador(string $caso, string $marca): int
{
    while (file_get_contents(sys_get_temp_dir().'/'.$marca.'-go') !== 'go') {
        usleep(20000);
    }
    $partes = explode('|', (string) file_get_contents(sys_get_temp_dir().'/'.$marca));
    $user = User::query()->findOrFail((int) $partes[0]);
    $tienda = AreaComisionEmpaque::query()->findOrFail((int) ($partes[1] ?? 0));
    try {
        if ($caso === 'capturar') {
            app(GestionarUnidadComisionEmpaqueService::class)->registrarManual([
                'clave_idempotencia' => $marca,
                'empacado_at' => '2026-10-11 12:00:00',
                'cantidad' => 1,
                'cliente_nombre' => $marca,
                'folio_pedido' => $marca,
            ], $user);

            return 0;
        }
        if ($caso === 'pagar') {
            $asignacion = AsignacionComisionEmpaque::query()->whereHas('participante', fn ($q) => $q->where('nombre', $marca))->firstOrFail();
            app(RegistrarPagoComisionEmpaqueService::class)->pagar(
                (int) $asignacion->participante_id, '2026-10-18', '6.00',
                [['asignacion_id' => $asignacion->id, 'importe' => '6.00']],
                $marca, 'transferencia', null, $marca, $user
            );

            return 0;
        }

        $servicio = app(ConfirmarCorteComisionEmpaqueService::class);
        $zona = 'America/Mexico_City';
        $desde = DineroComisionEmpaque::inicioDia('2026-10-01', $zona);
        $hasta = DineroComisionEmpaque::finDia('2026-10-31', $zona);
        if ($caso === 'confirmar-clasificar' && getenv('CE_WORKER_N') === null && $GLOBALS['argv'][2] === '2') {
            $unidad = UnidadComisionEmpaque::query()->where('notas', $marca)->firstOrFail();
            app(GestionarUnidadComisionEmpaqueService::class)->clasificar($unidad, [
                ['catalogo_tipo_caja_id' => null, 'cantidad' => 1, 'es_bolsa' => true],
            ], $user);

            return 0;
        }
        $calculo = $servicio->calcular($tienda->id, $desde, $hasta, false);
        $servicio->confirmar($tienda->id, $desde, $hasta, false, $calculo['hash'], $user);

        return 0;
    } catch (ValidationException) {
        return 0;
    } catch (QueryException $e) {
        return str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062') ? 0 : 1;
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage());

        return 1;
    }
}

function verificar(string $caso, string $marca): bool
{
    if ($caso === 'capturar') {
        return UnidadComisionEmpaque::query()->where('clave_idempotencia', $marca)->count() === 1;
    }
    if ($caso === 'pagar') {
        $pagos = PagoComisionEmpaque::query()->where('clave_idempotencia', $marca)->get();
        $asignacion = AsignacionComisionEmpaque::query()->whereHas('participante', fn ($q) => $q->where('nombre', $marca))->first();

        return $pagos->count() === 1 && (int) $asignacion->pagado_centavos === 600;
    }

    $unidad = UnidadComisionEmpaque::query()->where('notas', $marca)->first();
    $inclusiones = CorteUnidadComisionEmpaque::query()->where('unidad_id', $unidad->id)->count();
    if ($inclusiones > 1) {
        return false;
    }
    if ($caso === 'confirmar') {
        $corte = CorteComisionEmpaque::query()->where('folio', '!=', null)->whereHas('unidades', fn ($q) => $q->where('unidad_id', $unidad->id))->first();

        return $inclusiones === 1
            && (int) $corte->total_centavos === 600
            && (int) $corte->asignaciones()->sum('importe_centavos') === 600;
    }

    return $inclusiones <= 1 && ($unidad->corte_id === null || $inclusiones === 1);
}

function limpiar(string $marca): void
{
    $unidadIds = UnidadComisionEmpaque::query()->where('notas', $marca)->orWhere('clave_idempotencia', $marca)->pluck('id');
    CorteUnidadComisionEmpaque::query()->whereIn('unidad_id', $unidadIds)->delete();
    UnidadComisionEmpaque::query()->whereIn('id', $unidadIds)->delete();
    $participante = ParticipanteComisionEmpaque::query()->where('nombre', $marca)->first();
    if ($participante) {
        PagoComisionEmpaque::query()->where('participante_id', $participante->id)->delete();
        AsignacionComisionEmpaque::query()->where('participante_id', $participante->id)->delete();
        $participante->delete();
    }
    User::query()->where('name', $marca)->delete();
    @unlink(sys_get_temp_dir().'/'.$marca);
}

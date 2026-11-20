<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\TurnoAsignado;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use App\Services\PuntoVenta\Operacion\OperacionPdvConfig;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresión: la ventana del día operativo debe calcularse en la zona de la sucursal.
 * Con una zona al este de la zona de la aplicación (p. ej. America/Cancun) la ventana
 * quedaba en la hora previa al día y nadie resultaba disponible para recibir turnos.
 */
class DisponibilidadVentanaDiaOperativoPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Zona']);
        config(['cache.default' => 'array']);
        Cache::purge('array');
    }

    public function test_fin_del_dia_operativo_cubre_el_dia_completo_de_la_sucursal(): void
    {
        foreach (['America/Mexico_City', 'America/Cancun', 'America/Tijuana'] as $zona) {
            $this->configurarZona($zona);
            $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));

            $config = app(OperacionPdvConfig::class);
            $inicio = $config->inicioDiaOperativo($this->sucursal->id);
            $fin = $config->finDiaOperativo($this->sucursal->id);

            $this->assertSame(
                86400,
                (int) $inicio->diffInSeconds($fin, true) + 1,
                "El día operativo en {$zona} debe durar 24 h (inicio {$inicio}, fin {$fin})."
            );
        }
    }

    public function test_zona_horaria_guardada_distinta_se_ignora(): void
    {
        $this->configurarZona('America/Cancun');

        $this->assertSame(
            config('app.timezone'),
            app(OperacionPdvConfig::class)->zonaHorariaOperativa($this->sucursal->id)
        );
    }

    public function test_control_asigna_turno_en_zona_de_la_aplicacion(): void
    {
        $this->asignaTurnoEnZona('America/Mexico_City');
    }

    public function test_asigna_turno_en_sucursal_con_zona_al_este_de_la_aplicacion(): void
    {
        $this->asignaTurnoEnZona('America/Cancun');
    }

    public function test_asigna_turno_en_sucursal_con_zona_al_oeste_de_la_aplicacion(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
        $this->asignaTurnoEnZona('America/Tijuana');
    }

    private function asignaTurnoEnZona(string $zona): void
    {
        Event::fake([TurnoAsignado::class]);
        Queue::fake();
        $this->configurarZona($zona);
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));

        SucursalDiaOperacionPdvFactoryHelper::abrir($this->sucursal->id, app(OperacionPdvConfig::class)->fechaOperativa($this->sucursal->id));
        $vendedor = $this->crearVendedor();
        app(AbrirJornadaPdvService::class)->ejecutar($vendedor, now());

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'fecha_operativa' => app(OperacionPdvConfig::class)->fechaOperativa($this->sucursal->id),
            'alta_at' => now(),
            'atencion_actual_id' => null,
        ]);

        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'test');

        $this->assertSame(TurnoPdv::ESTADO_ASIGNADO, $turno->fresh()->estado);
    }

    private function configurarZona(string $zona): void
    {
        $horario = app(HorarioCierreOperacionPdvConfig::class);
        $horario->persistir(array_merge($horario->configuracionInicialPlaneada(), [
            'zona_horaria' => $zona,
            'hora_cierre' => '23:30',
            'dias_operacion' => [0, 1, 2, 3, 4, 5, 6],
        ]));
        Cache::forget(HorarioCierreOperacionPdvConfig::CACHE_KEY);
    }

    private function crearVendedor(): User
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
        ]);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        return $usuario;
    }
}

final class SucursalDiaOperacionPdvFactoryHelper
{
    public static function abrir(int $sucursalId, string $fecha): void
    {
        SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $sucursalId,
            'fecha_operativa' => $fecha,
            'acepta_altas' => true,
        ]);
    }
}

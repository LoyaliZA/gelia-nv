<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\FinalizarPausaPdvService;
use App\Services\PuntoVenta\Operacion\IniciarPausaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use Carbon\Carbon;
use App\Services\PuntoVenta\Turnos\TransferirTurnoPdvService;
use Tests\Support\RefreshDatabaseSafe;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriaOperacionPdvTest extends TestCase
{
    use RefreshDatabaseSafe;

    private Sucursal $sucursal;

    private User $vendedor;

    private User $superAdmin;

    private User $analista;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-29 17:19:23', 'America/Mexico_City'));

        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        Role::findOrCreate('Super Admin', 'web')->syncPermissions(PuntoVentaModulo::permisosIniciales());
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1'],
        );
        $plazos = app(PlazosTurnosPdvConfig::class);
        $plazos->persistir(array_merge($plazos->configuracionInicialAprobada(), ['cooldown_segundos' => 20]));

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Auditoría']);
        SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'fecha_operativa' => now()->toDateString(),
            'acepta_altas' => true,
        ]);

        $this->vendedor = $this->persona('Lorenzo Auditoría');
        $this->superAdmin = User::factory()->create(['name' => 'Super Admin Auditoría']);
        $this->superAdmin->assignRole('Super Admin');
        $this->superAdmin->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        $this->analista = User::factory()->create(['name' => 'Analista Reportes']);
        $this->analista->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ]);
        $this->analista->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_super_admin_ve_pausa_espera_y_asignacion_en_la_linea(): void
    {
        app(AbrirJornadaPdvService::class)->ejecutar($this->vendedor, now()->subHours(3));
        app(IniciarPausaPdvService::class)->ejecutar($this->vendedor, now()->subMinutes(30));
        TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'alta_at' => now()->subMinutes(2),
            'folio' => 'V-0071',
        ]);
        app(FinalizarPausaPdvService::class)->ejecutar($this->vendedor, now());
        $this->travel(20)->seconds();
        app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'cooldown');

        $this->actingAs($this->superAdmin)
            ->get(route('punto_venta.reportes.auditoria_operacion', [
                'sucursal_id' => $this->sucursal->id,
                'user_id' => $this->vendedor->id,
                'desde' => '2026-10-29',
                'hasta' => '2026-10-29',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PuntoVenta/Reportes/AuditoriaOperacion', false)
                ->where('consulta_completa', true)
                ->where('vistas_disponibles.auditoria_operacion', true)
                ->where('linea', function ($linea): bool {
                    $filas = collect($linea);
                    $asignacion = $filas->firstWhere('tipo', 'turno.asignado');
                    $espera = $filas->firstWhere('tipo', 'espera.aplicada');

                    return $filas->contains(fn ($fila) => $fila['tipo'] === 'pausa.finalizada')
                        && str_contains((string) ($espera['detalle'] ?? ''), 'fin de pausa')
                        && str_contains((string) ($espera['detalle'] ?? ''), '20 segundos')
                        && str_contains((string) ($asignacion['detalle'] ?? ''), 'espera entre turnos')
                        && str_contains((string) ($asignacion['titulo'] ?? ''), 'V-0071');
                }));
    }

    public function test_linea_reciente_oculta_asignacion_ajena_y_muestra_la_transferencia(): void
    {
        $diego = $this->persona('Diego Pausa');
        $eduardo = $this->persona('Eduardo Fila');
        app(AlcancePdv::class)->establecerSucursalActiva($this->superAdmin, $this->sucursal->id);

        app(AbrirJornadaPdvService::class)->ejecutar($diego, now()->subHour());
        app(AbrirJornadaPdvService::class)->ejecutar($eduardo, now()->subHour());
        app(IniciarPausaPdvService::class)->ejecutar($diego, now()->subMinutes(20));

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'alta_at' => now()->subMinutes(5),
            'folio' => 'V-0025',
        ]);
        $this->assertSame(1, app(MatchmakerTurnosPdvService::class)->ejecutar($this->sucursal->id, 'jornada.abierta'));

        $turno = $turno->fresh();
        app(TransferirTurnoPdvService::class)->ejecutar(
            $turno,
            $this->superAdmin,
            (int) $turno->version,
            'pdv:auditoria:transferencia-durante-pausa',
            $diego->id,
            now(),
        );

        $this->actingAs($this->superAdmin)
            ->get(route('punto_venta.reportes.auditoria_operacion', [
                'sucursal_id' => $this->sucursal->id,
                'user_id' => $diego->id,
                'desde' => '2026-10-29',
                'hasta' => '2026-10-29',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('linea', function ($linea): bool {
                    $filas = collect($linea);
                    $transferencia = $filas->firstWhere('tipo', 'turno.transferido');
                    $horas = $filas->pluck('ocurrido_at')->all();
                    $descendente = $horas;
                    rsort($descendente);

                    return $filas->isNotEmpty()
                        && $horas === $descendente
                        && $filas->first()['tipo'] === 'turno.transferido'
                        && ! $filas->contains(fn ($fila) => $fila['tipo'] === 'turno.asignado')
                        && str_contains((string) ($transferencia['detalle'] ?? ''), 'Entrega: Eduardo Fila')
                        && str_contains((string) ($transferencia['detalle'] ?? ''), 'Recibe: Diego Pausa')
                        && str_contains((string) ($transferencia['titulo'] ?? ''), 'V-0025');
                }));
    }

    public function test_quien_no_es_super_admin_no_consulta_la_auditoria_ni_ve_la_pestana(): void
    {
        $this->actingAs($this->analista)
            ->get(route('punto_venta.reportes.auditoria_operacion'))
            ->assertForbidden();

        $turnos = $this->actingAs($this->analista)
            ->get(route('punto_venta.reportes.turnos_operacion'));
        $this->assertSame(200, $turnos->status(), (string) $turnos->exception?->getMessage());
        $turnos->assertInertia(fn (Assert $page) => $page
            ->where('vistas_disponibles.auditoria_operacion', false));

        $this->actingAs($this->superAdmin)
            ->get(route('punto_venta.reportes.turnos_operacion'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vistas_disponibles.auditoria_operacion', true));
    }

    public function test_rechaza_un_rango_mayor_a_31_dias(): void
    {
        $this->actingAs($this->superAdmin)
            ->from(route('punto_venta.reportes.auditoria_operacion'))
            ->get(route('punto_venta.reportes.auditoria_operacion', [
                'sucursal_id' => $this->sucursal->id,
                'user_id' => $this->vendedor->id,
                'desde' => '2026-10-01',
                'hasta' => '2026-11-15',
            ]))
            ->assertRedirect(route('punto_venta.reportes.auditoria_operacion'))
            ->assertSessionHasErrors('hasta');
    }

    private function persona(string $nombre): User
    {
        $persona = User::factory()->create(['name' => $nombre]);
        $persona->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($persona, $this->sucursal->id);
        $persona->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR,
            PuntoVentaModulo::PERMISO_OPERACION_PAUSA,
        ]);

        return $persona;
    }
}

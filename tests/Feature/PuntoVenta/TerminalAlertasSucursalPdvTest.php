<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\PdvTerminalAlertasSucursal;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TerminalAlertasSucursalPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $autorizado;

    private User $sinPermiso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        $this->activarModulo();
        $this->seedPermisos();
        $this->seedPlazos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Terminal']);
        $this->autorizado = $this->crearUsuarioConPermiso(true);
        $this->sinPermiso = $this->crearUsuarioConPermiso(false);
    }

    public function test_usuario_autorizado_activa_terminal(): void
    {
        $terminalId = (string) Str::uuid();

        $response = $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.terminal_alertas.activar'),
            [
                'sucursal_id' => $this->sucursal->id,
                'terminal_id' => $terminalId,
            ],
        );

        $response->assertOk()
            ->assertJsonPath('estado', 'terminal_activa')
            ->assertJsonPath('designacion.terminal_id', $terminalId);

        $this->assertDatabaseHas('pdv_terminal_alertas_sucursal', [
            'terminal_id' => $terminalId,
            'sucursal_id' => $this->sucursal->id,
            'estado' => PdvTerminalAlertasSucursal::ESTADO_ACTIVA,
        ]);
    }

    public function test_usuario_sin_permiso_es_rechazado(): void
    {
        $this->actingAs($this->sinPermiso)->postJson(
            route('punto_venta.terminal_alertas.activar'),
            [
                'sucursal_id' => $this->sucursal->id,
                'terminal_id' => (string) Str::uuid(),
            ],
        )->assertForbidden();
    }

    public function test_segunda_terminal_no_desplaza_a_la_activa(): void
    {
        $primera = (string) Str::uuid();
        $segunda = (string) Str::uuid();

        $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.terminal_alertas.activar'),
            ['sucursal_id' => $this->sucursal->id, 'terminal_id' => $primera],
        )->assertOk();

        $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.terminal_alertas.activar'),
            ['sucursal_id' => $this->sucursal->id, 'terminal_id' => $segunda],
        )->assertStatus(422)
            ->assertJsonValidationErrors(['terminal']);

        $this->assertSame(1, PdvTerminalAlertasSucursal::query()
            ->where('estado', PdvTerminalAlertasSucursal::ESTADO_ACTIVA)
            ->count());
    }

    public function test_terminal_vencida_puede_ser_reclamada(): void
    {
        $terminalId = (string) Str::uuid();
        $otra = (string) Str::uuid();

        PdvTerminalAlertasSucursal::query()->create([
            'terminal_id' => $terminalId,
            'sucursal_id' => $this->sucursal->id,
            'user_id' => $this->autorizado->id,
            'estado' => PdvTerminalAlertasSucursal::ESTADO_VENCIDA,
            'activada_at' => now()->subHour(),
            'ultima_senal_at' => now()->subHour(),
            'liberada_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.terminal_alertas.activar'),
            ['sucursal_id' => $this->sucursal->id, 'terminal_id' => $otra],
        )->assertOk()
            ->assertJsonPath('estado', 'terminal_activa');
    }

    public function test_liberar_permite_nueva_activacion(): void
    {
        $terminalId = (string) Str::uuid();

        $this->actingAs($this->autorizado)->postJson(
            route('punto_venta.terminal_alertas.activar'),
            ['sucursal_id' => $this->sucursal->id, 'terminal_id' => $terminalId],
        )->assertOk();

        $this->actingAs($this->autorizado)->deleteJson(
            route('punto_venta.terminal_alertas.liberar'),
            ['sucursal_id' => $this->sucursal->id, 'terminal_id' => $terminalId],
        )->assertOk()
            ->assertJsonPath('estado', 'disponible');
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    private function seedPlazos(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PlazosTurnosPdvConfig::CLAVE],
            ['valor' => json_encode((new PlazosTurnosPdvConfig)->configuracionInicialAprobada())],
        );
    }

    private function crearUsuarioConPermiso(bool $conAlertas): User
    {
        $user = User::factory()->create();
        $permisos = [
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ];
        if ($conAlertas) {
            $permisos[] = PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL;
        }
        $user->givePermissionTo($permisos);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }
}

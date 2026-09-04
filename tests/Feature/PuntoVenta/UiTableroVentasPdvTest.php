<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UiTableroVentasPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();
        $this->seedPlazos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal UI Ventas']);
        $this->vendedor = User::factory()->create();
        $this->vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
        ]);
        $this->vendedor->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($this->vendedor, $this->sucursal->id);
    }

    public function test_ventas_renderiza_inertia_con_tablero_y_permisos(): void
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
        ]);

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->vendedor->id,
            'inicio_at' => now(),
            'fin_at' => null,
        ]);

        $turno->update(['atencion_actual_id' => $atencion->id]);

        $this->actingAs($this->vendedor)
            ->get(route('punto_venta.turnos.ventas'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Turnos/Ventas', false)
                ->where('permisos.ver', true)
                ->where('permisos.cerrar_atencion', true)
                ->where('sucursal_activa.id', $this->sucursal->id)
                ->has('tablero.turno_asignado')
                ->has('catalogos.motivos_cierre'));
    }

    public function test_ventas_sin_permiso_ver(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(PuntoVentaModulo::PERMISO_ACCEDER);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        $this->actingAs($usuario)
            ->get(route('punto_venta.turnos.ventas'))
            ->assertForbidden();
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
        $config = new PlazosTurnosPdvConfig;
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PlazosTurnosPdvConfig::CLAVE],
            [
                'valor' => json_encode($config->configuracionInicialAprobada(), JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
            ]
        );
    }
}

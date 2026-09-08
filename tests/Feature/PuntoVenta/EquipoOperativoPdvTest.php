<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\ConsultaEquipoOperativoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipoOperativoPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Equipo']);
    }

    public function test_usuario_con_solo_ver_no_aparece_en_equipo(): void
    {
        $recepcion = User::factory()->create(['name' => 'Recepción']);
        $recepcion->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ]);
        $recepcion->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $equipo = app(ConsultaEquipoOperativoPdvService::class)->listar($this->sucursal->id);

        $this->assertSame([], $equipo);
    }

    public function test_usuario_con_atender_sin_sucursal_no_aparece_en_equipo(): void
    {
        $vendedor = User::factory()->create(['name' => 'Sin sucursal']);
        $vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);

        $equipo = app(ConsultaEquipoOperativoPdvService::class)->listar($this->sucursal->id);

        $this->assertSame([], $equipo);
    }

    public function test_usuario_con_atender_sin_acceder_no_aparece_en_equipo(): void
    {
        $vendedor = User::factory()->create(['name' => 'Sin acceder']);
        $vendedor->givePermissionTo(PuntoVentaModulo::PERMISO_TURNOS_ATENDER);
        $vendedor->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $equipo = app(ConsultaEquipoOperativoPdvService::class)->listar($this->sucursal->id);

        $this->assertSame([], $equipo);
    }

    public function test_usuario_con_asignacion_inactiva_no_aparece_en_equipo(): void
    {
        $vendedor = User::factory()->create(['name' => 'Asignación inactiva']);
        $vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $vendedor->concederAccesoSucursal($this->sucursal, esPrincipal: true, activo: false);

        $equipo = app(ConsultaEquipoOperativoPdvService::class)->listar($this->sucursal->id);

        $this->assertSame([], $equipo);
    }

    public function test_usuario_de_otra_sucursal_no_aparece_en_equipo(): void
    {
        $otra = Sucursal::factory()->create(['nombre' => 'Otra sucursal']);
        $vendedor = User::factory()->create(['name' => 'Otra sucursal']);
        $vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $vendedor->concederAccesoSucursal($otra, esPrincipal: true);

        $equipo = app(ConsultaEquipoOperativoPdvService::class)->listar($this->sucursal->id);

        $this->assertSame([], $equipo);
    }

    public function test_usuario_eliminado_no_aparece_en_equipo(): void
    {
        $vendedor = User::factory()->create(['name' => 'Eliminado']);
        $vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $vendedor->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        $vendedor->delete();

        $equipo = app(ConsultaEquipoOperativoPdvService::class)->listar($this->sucursal->id);

        $this->assertSame([], $equipo);
    }

    public function test_permisos_fase_01_se_registran_idempotentemente(): void
    {
        $nuevos = [
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
            PuntoVentaModulo::PERMISO_TURNOS_REATENCION_ASIGNAR,
            PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL,
            PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR,
        ];

        foreach ($nuevos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            Permission::findOrCreate($permiso, 'web');
        }

        foreach ($nuevos as $permiso) {
            $this->assertSame(1, Permission::query()->where('name', $permiso)->count());
        }
    }

    public function test_usuario_con_atender_y_sucursal_aparece_en_equipo(): void
    {
        $vendedor = User::factory()->create(['name' => 'Vendedor Equipo']);
        $vendedor->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $vendedor->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($vendedor, $this->sucursal->id);

        $equipo = app(ConsultaEquipoOperativoPdvService::class)->listar($this->sucursal->id);

        $this->assertCount(1, $equipo);
        $this->assertSame($vendedor->id, $equipo[0]['id']);
        $this->assertSame('Vendedor Equipo', $equipo[0]['nombre']);
        $this->assertArrayHasKey('estado_vendedor', $equipo[0]);
        $this->assertSame('no_activado', $equipo[0]['estado_vendedor']);
        $this->assertArrayHasKey('acciones', $equipo[0]);
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
}

<?php

namespace Tests\Unit\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Policies\PuntoVenta\AutorizaAlcancePdv;
use App\Services\PuntoVenta\AlcancePdv;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutorizacionAlcancePdvTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISO_PISO = 'pdv.resguardos.ver';

    private ResuelveAlcancePdv $alcance;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate(self::PERMISO_PISO, 'web');
        Permission::findOrCreate(AlcancePdv::PERMISO_ALCANCE_GLOBAL, 'web');
        Role::findOrCreate('Super Admin', 'web');

        $this->alcance = app(ResuelveAlcancePdv::class);
    }

    public function test_permiso_sin_sucursal_se_deniega(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(self::PERMISO_PISO);

        $this->assertFalse($this->alcance->permiteConsultaPiso($usuario, self::PERMISO_PISO));
        $this->assertFalse($this->alcance->permiteMutacionPiso($usuario, self::PERMISO_PISO));

        $this->expectException(AuthorizationException::class);
        $this->alcance->aplicarConsultaPiso(Sucursal::query(), $usuario, self::PERMISO_PISO, 'id');
    }

    public function test_sucursal_sin_permiso_se_deniega(): void
    {
        $usuario = User::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $usuario->concederAccesoSucursal($sucursal, esPrincipal: true);

        $this->assertFalse($this->alcance->permiteConsultaPiso($usuario, self::PERMISO_PISO));
        $this->assertFalse($this->alcance->permiteMutacionPiso($usuario, self::PERMISO_PISO, $sucursal->id));
    }

    public function test_permiso_y_sucursal_activa_se_permite(): void
    {
        [$usuario, $sucursal] = $this->usuarioConPiso();

        $this->assertTrue($this->alcance->permiteConsultaPiso($usuario, self::PERMISO_PISO));
        $this->assertTrue($this->alcance->permiteMutacionPiso($usuario, self::PERMISO_PISO, $sucursal->id));
        $this->assertSame($sucursal->id, $this->alcance->sucursalActivaId($usuario));

        $ids = $this->alcance
            ->aplicarConsultaPiso(Sucursal::query(), $usuario, self::PERMISO_PISO, 'id')
            ->pluck('id')
            ->all();

        $this->assertSame([$sucursal->id], $ids);
    }

    public function test_acceso_cruzado_por_id_se_deniega(): void
    {
        [$usuario, $activa] = $this->usuarioConPiso();
        $otraAsignada = Sucursal::factory()->create();
        $ajena = Sucursal::factory()->create();
        $usuario->concederAccesoSucursal($otraAsignada);

        $this->assertFalse($this->alcance->permiteMutacionPiso($usuario, self::PERMISO_PISO, $otraAsignada->id));
        $this->assertFalse($this->alcance->permiteMutacionPiso($usuario, self::PERMISO_PISO, $ajena->id));
        $this->assertNull($this->alcance->sucursalIdReclamadaSiOperable($usuario, $ajena->id));
        $this->assertSame($otraAsignada->id, $this->alcance->sucursalIdReclamadaSiOperable($usuario, $otraAsignada->id));

        $this->expectException(AuthorizationException::class);
        $this->alcance->sucursalParaMutacion($usuario, $otraAsignada->id, self::PERMISO_PISO);
    }

    public function test_sucursal_inexistente_es_404(): void
    {
        [$usuario] = $this->usuarioConPiso();

        $this->expectException(ModelNotFoundException::class);
        $this->alcance->sucursalParaMutacion($usuario, 9_999_999, self::PERMISO_PISO);
    }

    public function test_alcance_global_consulta_sin_sucursal_y_no_muta(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(AlcancePdv::PERMISO_ALCANCE_GLOBAL);
        $elegible = Sucursal::factory()->create();
        $inactiva = Sucursal::factory()->inactiva()->create();

        $this->assertTrue($this->alcance->tieneAlcanceGlobal($usuario));
        $this->assertFalse($this->alcance->permiteMutacionPiso($usuario, self::PERMISO_PISO, $elegible->id));
        $this->assertFalse($this->alcance->permiteConsultaPiso($usuario, self::PERMISO_PISO));

        $ids = $this->alcance
            ->aplicarConsultaGlobal(Sucursal::query(), $usuario, 'id')
            ->pluck('id')
            ->all();

        $this->assertContains($elegible->id, $ids);
        $this->assertNotContains($inactiva->id, $ids);

        $this->expectException(AuthorizationException::class);
        $this->alcance->asegurarMutacionPiso($usuario, self::PERMISO_PISO, $elegible->id);
    }

    public function test_super_admin_sin_permiso_directo_no_tiene_alcance_pdv(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $sucursal = Sucursal::factory()->create();
        $admin->concederAccesoSucursal($sucursal, esPrincipal: true);

        $this->assertTrue($admin->can(self::PERMISO_PISO));
        $this->assertFalse($this->alcance->tienePermisoPdv($admin, self::PERMISO_PISO));
        $this->assertFalse($this->alcance->tieneAlcanceGlobal($admin));
        $this->assertFalse($this->alcance->permiteConsultaPiso($admin, self::PERMISO_PISO));
    }

    public function test_consulta_global_sin_permiso_lanza_403_no_lista_vacia(): void
    {
        $usuario = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->alcance->aplicarConsultaGlobal(Sucursal::query(), $usuario, 'id');
    }

    public function test_sucursal_inactiva_o_no_asignada_no_opera(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(self::PERMISO_PISO);
        $inactiva = Sucursal::factory()->inactiva()->create();
        $usuario->concederAccesoSucursal($inactiva, esPrincipal: true);

        $this->assertNull($this->alcance->sucursalActivaId($usuario));
        $this->assertFalse($this->alcance->permiteConsultaPiso($usuario, self::PERMISO_PISO));

        $this->expectException(AuthorizationException::class);
        $this->alcance->establecerSucursalActiva($usuario, $inactiva->id);
    }

    public function test_no_usa_sucursal_id_de_sesion_si_deja_de_ser_operable(): void
    {
        [$usuario, $sucursal] = $this->usuarioConPiso();
        $this->alcance->establecerSucursalActiva($usuario, $sucursal->id);

        $sucursal->update(['activo' => false]);
        $usuario->unsetRelation('sucursales');
        $usuario->unsetRelation('sucursalesOperables');

        $this->assertNull($this->alcance->sucursalActivaId($usuario->fresh()));
    }

    public function test_policy_trait_reusa_doble_validacion(): void
    {
        [$usuario, $sucursal] = $this->usuarioConPiso();
        $policy = new class
        {
            use AutorizaAlcancePdv;

            public function ver(User $user, string $permiso): bool
            {
                return $this->permiteConsultaPisoPdv($user, $permiso);
            }

            public function mutar(User $user, string $permiso, int $sucursalId): bool
            {
                return $this->permiteMutacionPisoPdv($user, $permiso, $sucursalId);
            }
        };

        $this->assertTrue($policy->ver($usuario, self::PERMISO_PISO));
        $this->assertTrue($policy->mutar($usuario, self::PERMISO_PISO, $sucursal->id));
        $this->assertFalse($policy->mutar($usuario, self::PERMISO_PISO, Sucursal::factory()->create()->id));
    }

    /**
     * @return array{0: User, 1: Sucursal}
     */
    private function usuarioConPiso(): array
    {
        $usuario = User::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $usuario->givePermissionTo(self::PERMISO_PISO);
        $usuario->concederAccesoSucursal($sucursal, esPrincipal: true);

        return [$usuario, $sucursal];
    }
}

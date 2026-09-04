<?php

namespace Tests\Unit\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesoUsuarioSucursalTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_tener_varias_sucursales_activas(): void
    {
        $usuario = User::factory()->create();
        $sucursalA = Sucursal::factory()->create();
        $sucursalB = Sucursal::factory()->create();

        $usuario->concederAccesoSucursal($sucursalA, esPrincipal: true);
        $usuario->concederAccesoSucursal($sucursalB);

        $ids = $usuario->idsSucursalesOperables()->sort()->values()->all();

        $this->assertSame(
            collect([$sucursalA->id, $sucursalB->id])->sort()->values()->all(),
            $ids
        );
        $this->assertTrue($sucursalA->users()->where('users.id', $usuario->id)->exists());
        $this->assertTrue($sucursalB->users()->where('users.id', $usuario->id)->exists());
    }

    public function test_no_duplica_la_misma_relacion(): void
    {
        $usuario = User::factory()->create();
        $sucursal = Sucursal::factory()->create();

        $usuario->sucursales()->attach($sucursal->id, [
            'es_principal' => true,
            'activo' => true,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        $usuario->sucursales()->attach($sucursal->id, [
            'es_principal' => false,
            'activo' => true,
        ]);
    }

    public function test_resuelve_principal_segun_contrato_0b(): void
    {
        $unaSola = User::factory()->create();
        $unica = Sucursal::factory()->create();
        $unaSola->concederAccesoSucursal($unica);

        $this->assertTrue($unica->is($unaSola->sucursalPrincipal()));

        $varias = User::factory()->create();
        $principal = Sucursal::factory()->create();
        $otra = Sucursal::factory()->create();
        $varias->concederAccesoSucursal($principal, esPrincipal: true);
        $varias->concederAccesoSucursal($otra);

        $this->assertTrue($principal->is($varias->sucursalPrincipal()));
        $this->assertSame(1, $varias->sucursales()->wherePivot('es_principal', true)->count());

        $varias->concederAccesoSucursal($otra, esPrincipal: true);

        $this->assertTrue($otra->is($varias->fresh()->sucursalPrincipal()));
        $this->assertSame(1, $varias->sucursales()->wherePivot('es_principal', true)->count());
    }

    public function test_principal_opcional_si_hay_varias_sin_marcar(): void
    {
        $usuario = User::factory()->create();
        $usuario->concederAccesoSucursal(Sucursal::factory()->create());
        $usuario->concederAccesoSucursal(Sucursal::factory()->create());

        $this->assertNull($usuario->sucursalPrincipal());
    }

    public function test_excluye_sucursal_inactiva_pivot_inactivo_y_usuario_eliminado(): void
    {
        $usuario = User::factory()->create();
        $operable = Sucursal::factory()->create();
        $catalogoInactivo = Sucursal::factory()->inactiva()->create();
        $asignacionInactiva = Sucursal::factory()->create();

        $usuario->concederAccesoSucursal($operable, esPrincipal: true);
        $usuario->concederAccesoSucursal($catalogoInactivo);
        $usuario->concederAccesoSucursal($asignacionInactiva, activo: false);

        $this->assertSame([$operable->id], $usuario->idsSucursalesOperables()->all());
        $this->assertTrue($operable->is($usuario->sucursalPrincipal()));
        $this->assertFalse($usuario->sucursalesOperables()->whereKey($catalogoInactivo->id)->exists());
        $this->assertFalse($usuario->sucursalesOperables()->whereKey($asignacionInactiva->id)->exists());

        $usuario->delete();

        $this->assertFalse($operable->users()->where('users.id', $usuario->id)->exists());
        $this->assertTrue($operable->users()->withTrashed()->where('users.id', $usuario->id)->exists());
    }

    public function test_sincronizar_sucursales_asignadas_reemplaza_y_marca_principal(): void
    {
        $usuario = User::factory()->create();
        $norte = Sucursal::factory()->create(['nombre' => 'Norte']);
        $sur = Sucursal::factory()->create(['nombre' => 'Sur']);
        $este = Sucursal::factory()->create(['nombre' => 'Este']);

        $usuario->sincronizarSucursalesAsignadas([$norte->id, $sur->id], $sur->id);

        $this->assertSame(
            [$norte->id, $sur->id],
            $usuario->fresh()->idsSucursalesOperables()->sort()->values()->all()
        );
        $this->assertTrue($sur->is($usuario->fresh()->sucursalPrincipal()));

        $usuario->sincronizarSucursalesAsignadas([$este->id], null);

        $this->assertSame([$este->id], $usuario->fresh()->idsSucursalesOperables()->all());
        $this->assertTrue($este->is($usuario->fresh()->sucursalPrincipal()));
    }

    public function test_sincronizar_vacio_elimina_asignaciones(): void
    {
        $usuario = User::factory()->create();
        $usuario->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: true);

        $usuario->sincronizarSucursalesAsignadas([], null);

        $this->assertTrue($usuario->fresh()->idsSucursalesOperables()->isEmpty());
    }
}

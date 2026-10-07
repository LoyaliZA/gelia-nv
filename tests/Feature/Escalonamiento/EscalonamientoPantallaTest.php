<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EscalonamientoPantallaTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_el_periodo_vacio_y_abre_sin_copiar_montos(): void
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['escalonamiento.ver', 'escalonamiento.operar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            $user->givePermissionTo($permiso);
        }

        $this->actingAs($user)
            ->get(route('escalonamiento.index'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Escalonamiento/Index', false)
                ->where('periodo', null));

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($user)
            ->post(route('escalonamiento.periodos.abrir'), ['anio' => 2026, 'mes' => 10])
            ->assertRedirect(route('escalonamiento.index'));

        $periodo = EscalonamientoPeriodo::first();
        $this->assertNotNull($periodo);
        $this->assertSame(2026, $periodo->anio);
        $this->assertSame(10, $periodo->mes);
        $this->assertSame('abierto', $periodo->estado);
        $this->assertSame(0, $periodo->resumenes()->count());
    }

    public function test_sin_permiso_no_entra_al_modulo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('escalonamiento.index'))
            ->assertForbidden();
    }
}

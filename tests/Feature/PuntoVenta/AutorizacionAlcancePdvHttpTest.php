<?php

namespace Tests\Feature\PuntoVenta;

use App\Http\Requests\PuntoVenta\PdvConsultaGlobalRequest;
use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AutorizacionAlcancePdvHttpTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISO_PISO = 'pdv.resguardos.recibir';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        Permission::findOrCreate(self::PERMISO_PISO, 'web');
        Permission::findOrCreate(AlcancePdv::PERMISO_ALCANCE_GLOBAL, 'web');

        Route::middleware(['web', 'auth', 'pdv.piso'])->get('/__pdv/piso', fn () => response('ok'));
        Route::middleware(['web', 'auth'])->post('/__pdv/mutar', function (PdvOperacionPisoRequestStub $request) {
            return response('ok');
        });
        Route::middleware(['web', 'auth'])->get('/__pdv/global', function (PdvConsultaGlobalRequest $request) {
            return response('ok');
        });
    }

    public function test_middleware_piso_exige_sucursal_activa(): void
    {
        $sinSucursal = User::factory()->create();
        $sinSucursal->givePermissionTo(self::PERMISO_PISO);

        $this->actingAs($sinSucursal)->get('/__pdv/piso')->assertForbidden();

        $conSucursal = User::factory()->create();
        $conSucursal->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: true);

        $this->actingAs($conSucursal)->get('/__pdv/piso')->assertOk();
    }

    public function test_request_piso_exige_permiso_y_sucursal(): void
    {
        $soloPermiso = User::factory()->create();
        $soloPermiso->givePermissionTo(self::PERMISO_PISO);
        $this->actingAs($soloPermiso)->post('/__pdv/mutar')->assertForbidden();

        $soloSucursal = User::factory()->create();
        $soloSucursal->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: true);
        $this->actingAs($soloSucursal)->post('/__pdv/mutar')->assertForbidden();

        $ambos = User::factory()->create();
        $ambos->givePermissionTo(self::PERMISO_PISO);
        $ambos->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: true);
        $this->actingAs($ambos)
            ->post('/__pdv/mutar', ['sucursal_id' => Sucursal::factory()->create()->id])
            ->assertOk();
    }

    public function test_request_global_solo_con_permiso_0b(): void
    {
        $sinGlobal = User::factory()->create();
        $sinGlobal->givePermissionTo(self::PERMISO_PISO);
        $sinGlobal->concederAccesoSucursal(Sucursal::factory()->create(), esPrincipal: true);
        $this->actingAs($sinGlobal)->get('/__pdv/global')->assertForbidden();

        $conGlobal = User::factory()->create();
        $conGlobal->givePermissionTo(AlcancePdv::PERMISO_ALCANCE_GLOBAL);
        $this->actingAs($conGlobal)->get('/__pdv/global')->assertOk();
    }
}

class PdvOperacionPisoRequestStub extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return 'pdv.resguardos.recibir';
    }
}

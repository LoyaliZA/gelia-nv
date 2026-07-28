<?php

namespace Tests\Feature\Tiendanube;

use App\Http\Controllers\TiendanubeController;
use App\Jobs\Tiendanube\SyncTiendanubeCatalogoJob;
use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeCatalogoWipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TiendanubeCatalogoWipeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['tiendanube.ver', 'tiendanube.configurar', 'tiendanube.sincronizar'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['tiendanube.ver', 'tiendanube.configurar', 'tiendanube.sincronizar']);
        $this->actingAs($this->user);
        Gate::before(fn () => true);

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 111,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-secreto'),
        ])->save();

        TiendanubeProducto::query()->create([
            'id' => 9001,
            'name' => ['es' => 'Viejo'],
            'published' => true,
        ]);
        TiendanubeCategoria::query()->create([
            'id' => 50,
            'name' => ['es' => 'Cat'],
        ]);
    }

    public function test_wipe_service_no_toca_credenciales(): void
    {
        $counts = app(TiendanubeCatalogoWipeService::class)->wipe();

        $this->assertSame(1, $counts['tiendanube_productos']);
        $this->assertSame(1, $counts['tiendanube_categorias']);
        $this->assertSame(0, TiendanubeProducto::count());
        $this->assertSame(0, TiendanubeCategoria::count());

        $config = TiendanubeConfiguracion::obtener();
        $this->assertSame(111, (int) $config->store_id);
        $this->assertSame('token-secreto', $config->accessTokenDecrypted());
    }

    public function test_limpiar_catalogo_inicia_sync(): void
    {
        Queue::fake();

        $request = Request::create('/tiendanube/catalogo/limpiar', 'POST', ['iniciar_sync' => true]);
        $request->setUserResolver(fn () => $this->user);

        $response = app(TiendanubeController::class)->limpiarCatalogo(
            $request,
            app(TiendanubeCatalogoWipeService::class)
        );

        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame(0, TiendanubeProducto::count());
        $this->assertSame('token-secreto', TiendanubeConfiguracion::obtener()->accessTokenDecrypted());
        Queue::assertPushed(SyncTiendanubeCatalogoJob::class);
    }

    public function test_cambiar_store_sin_confirmacion_exige_wipe(): void
    {
        $request = Request::create('/tiendanube/configuracion', 'PUT', ['store_id' => 222]);
        $request->setUserResolver(fn () => $this->user);

        $response = app(TiendanubeController::class)->guardarConfiguracion(
            $request,
            app(TiendanubeCatalogoWipeService::class)
        );

        $this->assertSame(409, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['requires_wipe_confirmation']);
        $this->assertSame(1, TiendanubeProducto::count());
        $this->assertSame(111, (int) TiendanubeConfiguracion::obtener()->store_id);
    }

    public function test_cambiar_store_con_wipe_limpia_y_guarda(): void
    {
        Queue::fake();

        $request = Request::create('/tiendanube/configuracion', 'PUT', [
            'store_id' => 222,
            'limpiar_catalogo' => true,
            'iniciar_sync' => true,
        ]);
        $request->setUserResolver(fn () => $this->user);

        $response = app(TiendanubeController::class)->guardarConfiguracion(
            $request,
            app(TiendanubeCatalogoWipeService::class)
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, TiendanubeProducto::count());
        $this->assertSame(222, (int) TiendanubeConfiguracion::obtener()->store_id);
        $this->assertSame('token-secreto', TiendanubeConfiguracion::obtener()->accessTokenDecrypted());
        Queue::assertPushed(SyncTiendanubeCatalogoJob::class);
    }
}

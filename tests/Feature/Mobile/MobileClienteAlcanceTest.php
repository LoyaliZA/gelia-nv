<?php

namespace Tests\Feature\Mobile;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Mobile\MobileClienteAlcanceService;
use App\Services\Mobile\MobileScopeVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileClienteAlcanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mis_clientes_solo_ve_asignados(): void
    {
        $vendedor = User::factory()->create();
        $otro = User::factory()->create();
        Permission::findOrCreate('mis_clientes.gestionar', 'web');
        $vendedor->givePermissionTo('mis_clientes.gestionar');

        $propio = $this->cliente('1', $vendedor->id);
        $ajeno = $this->cliente('2', $otro->id);

        $alcance = app(MobileClienteAlcanceService::class);

        $this->assertTrue($alcance->puedeAcceder($vendedor, $propio));
        $this->assertFalse($alcance->puedeAcceder($vendedor, $ajeno));
        $this->assertSame([$propio->id], $alcance->queryPara($vendedor)->pluck('id')->all());
    }

    public function test_clientes_ver_ve_todos(): void
    {
        $admin = User::factory()->create();
        $vendedor = User::factory()->create();
        Permission::findOrCreate('clientes.ver', 'web');
        $admin->givePermissionTo('clientes.ver');

        $this->cliente('1', $vendedor->id);
        $this->cliente('2', $admin->id);

        $ids = app(MobileClienteAlcanceService::class)->queryPara($admin)->pluck('id')->all();
        $this->assertCount(2, $ids);
    }

    public function test_cambio_de_permiso_cambia_scope_version(): void
    {
        $pMis = Permission::query()->firstOrCreate(
            ['name' => 'mis_clientes.gestionar', 'guard_name' => 'web']
        );
        $pVer = Permission::query()->firstOrCreate(
            ['name' => 'clientes.ver', 'guard_name' => 'web']
        );

        $soloMisClientes = User::factory()->create();
        $conClientesVer = User::factory()->create();

        DB::table('model_has_permissions')->insert([
            [
                'permission_id' => $pMis->id,
                'model_type' => $soloMisClientes->getMorphClass(),
                'model_id' => $soloMisClientes->id,
            ],
            [
                'permission_id' => $pVer->id,
                'model_type' => $conClientesVer->getMorphClass(),
                'model_id' => $conClientesVer->id,
            ],
        ]);

        $this->assertNotSame(
            app(MobileScopeVersionService::class)->compute($soloMisClientes),
            app(MobileScopeVersionService::class)->compute($conClientesVer)
        );
    }

    private function cliente(string $numero, int $vendedorId): Cliente
    {
        $lista = CatalogoListaDescuento::first() ?? CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente '.$numero,
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $vendedorId,
            'vendedor_original_id' => $vendedorId,
        ]);
    }
}

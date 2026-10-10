<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Departamento;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlPedidosCedisVisibilidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_permiso_ver_todos_existe_y_no_se_hereda_de_cedis(): void
    {
        $this->assertTrue(
            Permission::query()
                ->where('name', 'control_pedidos.cedis.ver_todos')
                ->where('guard_name', 'web')
                ->exists()
        );

        Permission::findOrCreate('control_pedidos.cedis', 'web');
        $rol = Role::findOrCreate('CedisEmpaqueVis', 'web');
        $rol->givePermissionTo('control_pedidos.cedis');

        $this->assertFalse($rol->hasPermissionTo('control_pedidos.cedis.ver_todos'));
    }

    public function test_cedis_solo_ve_pedidos_de_sus_departamentos(): void
    {
        [$aromas, $bellaroma] = $this->departamentos();
        $vendeAromas = User::factory()->create(['departamento_id' => $aromas->id]);
        $vendeBellaroma = User::factory()->create(['departamento_id' => $bellaroma->id]);
        $vendeSinDepto = User::factory()->create(['departamento_id' => null]);
        $cedisAromas = $this->usuarioCedis($aromas->id);
        $cedisAmbos = $this->usuarioCedis($aromas->id, [$bellaroma->id]);
        $cedisVacio = $this->usuarioCedis(null);
        $gerente = $this->usuarioCedis($aromas->id);
        $gerente->assignRole(Role::findOrCreate('Gerente', 'web'));
        $admin = $this->usuarioCedis($aromas->id);
        $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
        $verTodos = $this->usuarioCedis($aromas->id);
        $verTodos->givePermissionTo(
            Permission::findOrCreate('control_pedidos.cedis.ver_todos', 'web')
        );

        $pedidoAromas = $this->pedidoEnCedis($vendeAromas, 'CEDIS-AROMAS');
        $pedidoBellaroma = $this->pedidoEnCedis($vendeBellaroma, 'CEDIS-BELLA');
        $pedidoSinDepto = $this->pedidoEnCedis($vendeSinDepto, 'CEDIS-SIN');

        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($cedisAromas, $pedidoAromas));
        $this->assertFalse(VisibilidadPedidoBma::puedeVerEnCedis($cedisAromas, $pedidoBellaroma));
        $this->assertFalse(VisibilidadPedidoBma::puedeVerEnCedis($cedisAromas, $pedidoSinDepto));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($cedisAmbos, $pedidoAromas));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($cedisAmbos, $pedidoBellaroma));
        $this->assertFalse(VisibilidadPedidoBma::puedeVerEnCedis($cedisVacio, $pedidoAromas));
        $this->assertFalse(VisibilidadPedidoBma::puedeVerEnCedis($gerente, $pedidoBellaroma));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($admin, $pedidoBellaroma));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($verTodos, $pedidoBellaroma));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($verTodos, $pedidoSinDepto));

        $this->assertTrue(VisibilidadPedidoBma::puedeConsultar($cedisAromas, $pedidoAromas));
        $this->assertFalse(VisibilidadPedidoBma::puedeConsultar($cedisAromas, $pedidoBellaroma));
    }

    /** @return array{0: Departamento, 1: Departamento} */
    private function departamentos(): array
    {
        return [
            Departamento::create(['nombre' => 'Aromas '.uniqid(), 'activo' => true]),
            Departamento::create(['nombre' => 'Bellaroma '.uniqid(), 'activo' => true]),
        ];
    }

    private function usuarioCedis(?int $principal, array $adicionales = []): User
    {
        Permission::findOrCreate('control_pedidos.cedis', 'web');
        $usuario = User::factory()->create(['departamento_id' => $principal]);
        $ids = array_values(array_filter(array_merge(
            $principal ? [$principal] : [],
            $adicionales
        )));
        if ($ids !== []) {
            $usuario->departamentos()->sync($ids);
        }
        $usuario->givePermissionTo('control_pedidos.cedis');

        return $usuario;
    }

    private function pedidoEnCedis(User $vendedor, string $folio): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()
            ->where('fase_ciclo', CatalogoEstatusPedido::FASE_EN_CEDIS)
            ->first()
            ?? CatalogoEstatusPedido::create([
                'codigo_interno' => 'EN_CEDIS_VIS_'.uniqid(),
                'nombre_visual' => 'En CEDIS',
                'color_hex' => '#EAB308',
                'fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS,
                'orden' => 3,
                'activo' => true,
            ]);

        return PedidoBma::create([
            'folio' => $folio.'-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedor->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
        ]);
    }
}

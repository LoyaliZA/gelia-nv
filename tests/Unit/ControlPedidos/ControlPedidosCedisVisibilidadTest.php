<?php

namespace Tests\Unit\ControlPedidos;

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
}

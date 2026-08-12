<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $nuevos = [
            'gestion_interna.productos.ver',
            'gestion_interna.productos.gestionar',
            'gestion_interna.productos.importar',
            'reportes.ventas.ver',
            'reportes.ventas.importar',
            'gestion_interna.catalogos_producto.gestionar',
        ];

        foreach ($nuevos as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $map = [
            'almacenes.productos.ver' => ['gestion_interna.productos.ver'],
            'almacenes.productos.gestionar' => [
                'gestion_interna.productos.gestionar',
                'gestion_interna.productos.importar',
                'gestion_interna.catalogos_producto.gestionar',
            ],
        ];

        foreach ($map as $viejo => $nuevosPerms) {
            $roles = Role::whereHas('permissions', fn ($q) => $q->where('name', $viejo))->get();
            foreach ($roles as $role) {
                $role->givePermissionTo($nuevosPerms);
            }
        }

        foreach (['Super Admin', 'Administrador'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo([
                    'gestion_interna.productos.ver',
                    'gestion_interna.productos.gestionar',
                    'gestion_interna.productos.importar',
                    'gestion_interna.catalogos_producto.gestionar',
                    'reportes.ventas.ver',
                    'reportes.ventas.importar',
                ]);
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'gestion_interna.productos.ver',
            'gestion_interna.productos.gestionar',
            'gestion_interna.productos.importar',
            'reportes.ventas.ver',
            'reportes.ventas.importar',
            'gestion_interna.catalogos_producto.gestionar',
        ])->delete();
    }
};

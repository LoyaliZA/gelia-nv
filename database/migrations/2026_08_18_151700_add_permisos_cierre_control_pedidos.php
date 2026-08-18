<?php

use App\Models\User;
use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const EXTRAS = [
        'control_pedidos.auditar.aprobar',
        'control_pedidos.liberar_resguardo',
        'control_pedidos.cedis.enviar',
        'control_pedidos.delegado.importar',
    ];

    /** @var array<string, list<string>> */
    private const PADRE_A_EXTRAS = [
        'control_pedidos.auditar' => [
            'control_pedidos.auditar.aprobar',
            'control_pedidos.liberar_resguardo',
        ],
        'control_pedidos.cedis' => [
            'control_pedidos.cedis.enviar',
        ],
        'control_pedidos.delegado' => [
            'control_pedidos.delegado.importar',
        ],
    ];

    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(self::EXTRAS);

        foreach (self::PADRE_A_EXTRAS as $padre => $extras) {
            Role::query()
                ->where('guard_name', 'web')
                ->whereHas('permissions', fn ($q) => $q->where('name', $padre))
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($extras));

            User::withoutGlobalScopes()
                ->whereHas('permissions', fn ($q) => $q->where('name', $padre))
                ->get()
                ->each(fn (User $user) => $user->givePermissionTo($extras));
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::EXTRAS)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

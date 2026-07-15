<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolePermissionsMap = DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->select('roles.name as role_name', 'permissions.name as permission_name', 'permissions.id as permission_id')
            ->get()
            ->groupBy('role_name')
            ->map(fn ($rows) => $rows->keyBy('permission_name'));

        // without SoftDeletes: esta migración corre antes de users.deleted_at
        User::withoutGlobalScopes()
            ->with(['roles', 'permissions', 'gerentes'])
            ->chunkById(100, function ($users) use ($rolePermissionsMap) {
            foreach ($users as $user) {
                $effective = $user->getAllPermissions()->pluck('name')->unique()->values()->all();
                $directBefore = $user->permissions->pluck('name')->all();
                $assignedRoles = $user->roles->pluck('name')->all();
                $asignadoPorId = $user->gerentes->first()?->id;

                $user->syncPermissions($effective);

                $now = now();
                $procedenciaRows = [];

                foreach ($effective as $permisoName) {
                    $permission = Permission::where('name', $permisoName)->first();
                    if (!$permission) {
                        continue;
                    }

                    $plantillaOrigen = null;

                    if (in_array($permisoName, $directBefore, true)) {
                        $plantillaOrigen = null;
                    } else {
                        foreach ($assignedRoles as $roleName) {
                            if (str_contains($roleName, 'Grupo:') && isset($rolePermissionsMap[$roleName][$permisoName])) {
                                $plantillaOrigen = $roleName;
                                break;
                            }
                        }

                        if ($plantillaOrigen === null) {
                            foreach ($assignedRoles as $roleName) {
                                if (!str_contains($roleName, 'Grupo:') && isset($rolePermissionsMap[$roleName][$permisoName])) {
                                    $plantillaOrigen = $roleName;
                                    break;
                                }
                            }
                        }
                    }

                    $procedenciaRows[] = [
                        'user_id' => $user->id,
                        'permission_id' => $permission->id,
                        'asignado_por_id' => $asignadoPorId,
                        'plantilla_origen' => $plantillaOrigen,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($procedenciaRows)) {
                    DB::table('usuario_permiso_procedencia')->upsert(
                        $procedenciaRows,
                        ['user_id', 'permission_id'],
                        ['asignado_por_id', 'plantilla_origen', 'updated_at']
                    );
                }

                $user->load('permissions');
                $afterDirect = $user->permissions->pluck('name')->sort()->values()->all();
                $expected = collect($effective)->sort()->values()->all();

                if ($afterDirect !== $expected) {
                    Log::warning('Materialización de permisos: diff detectado', [
                        'user_id' => $user->id,
                        'expected' => $expected,
                        'actual' => $afterDirect,
                    ]);
                }
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('usuario_permiso_procedencia')->truncate();
    }
};

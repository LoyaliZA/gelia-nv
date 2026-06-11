<?php

namespace App\Services\Permisos;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class RepararProcedenciaPermisosService
{
    public const ORIGEN_MIGRACION = 'sistema:migracion';

    /**
     * Crea filas de procedencia para permisos directos que no tienen registro.
     * No atribuye a ningún usuario humano.
     *
     * @return array{insertados: int}
     */
    public static function repararHuerfanos(): array
    {
        $huerfanos = DB::table('model_has_permissions as mhp')
            ->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
            ->leftJoin('usuario_permiso_procedencia as upp', function ($join) {
                $join->on('upp.user_id', '=', 'mhp.model_id')
                    ->on('upp.permission_id', '=', 'p.id');
            })
            ->where('mhp.model_type', 'like', '%User')
            ->whereNull('upp.id')
            ->select('mhp.model_id as user_id', 'p.id as permission_id')
            ->get();

        if ($huerfanos->isEmpty()) {
            return ['insertados' => 0];
        }

        $now = now();
        $rows = $huerfanos->map(fn ($row) => [
            'user_id' => $row->user_id,
            'permission_id' => $row->permission_id,
            'asignado_por_id' => null,
            'plantilla_origen' => self::ORIGEN_MIGRACION,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('usuario_permiso_procedencia')->insert($rows);

        return ['insertados' => count($rows)];
    }

    /**
     * Otorga un permiso durante una migración y registra procedencia de sistema.
     */
    public static function darPermisoMigracion(int $userId, string $permisoName): void
    {
        $permission = Permission::findOrCreate($permisoName, 'web');

        $yaTiene = DB::table('model_has_permissions')
            ->where('model_id', $userId)
            ->where('model_type', 'App\Models\User')
            ->where('permission_id', $permission->id)
            ->exists();

        if (!$yaTiene) {
            DB::table('model_has_permissions')->insert([
                'permission_id' => $permission->id,
                'model_type' => 'App\Models\User',
                'model_id' => $userId,
            ]);
        }

        DB::table('usuario_permiso_procedencia')->updateOrInsert(
            [
                'user_id' => $userId,
                'permission_id' => $permission->id,
            ],
            [
                'asignado_por_id' => null,
                'plantilla_origen' => self::ORIGEN_MIGRACION,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}

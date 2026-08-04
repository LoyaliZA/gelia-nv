<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /** @return list<array{clave: string, valor: string, tipo: string, grupo: string, descripcion: string}> */
    private function filas(): array
    {
        return [
            [
                'clave' => 'deepseek.api_token',
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'DeepSeek',
                'descripcion' => 'API token DeepSeek (asistente GELIA)',
            ],
            [
                'clave' => 'deepseek.base_url',
                'valor' => 'https://api.deepseek.com',
                'tipo' => 'string',
                'grupo' => 'DeepSeek',
                'descripcion' => 'Base URL API DeepSeek',
            ],
            [
                'clave' => 'gelia_ai.acceso_modo',
                'valor' => 'super_admin',
                'tipo' => 'string',
                'grupo' => 'Gelia AI',
                'descripcion' => 'Quién puede usar el chat: general | usuarios | super_admin',
            ],
            [
                'clave' => 'gelia_ai.acceso_user_ids',
                'valor' => '[]',
                'tipo' => 'json',
                'grupo' => 'Gelia AI',
                'descripcion' => 'IDs de usuarios con acceso (solo si acceso_modo=usuarios)',
            ],
            [
                'clave' => 'gelia_ai.model',
                'valor' => 'deepseek-chat',
                'tipo' => 'string',
                'grupo' => 'Gelia AI',
                'descripcion' => 'Modelo DeepSeek para el asistente',
            ],
        ];
    }

    public function up(): void
    {
        Permission::findOrCreate('gelia_ai.gestionar_acceso', 'web');

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo('gelia_ai.gestionar_acceso');
        }

        if (! Schema::hasTable('configuraciones_sistema')) {
            return;
        }

        $now = now();
        foreach ($this->filas() as $fila) {
            if (DB::table('configuraciones_sistema')->where('clave', $fila['clave'])->exists()) {
                continue;
            }

            DB::table('configuraciones_sistema')->insert([
                ...$fila,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('configuraciones_sistema_globales');
    }

    public function down(): void
    {
        if (Schema::hasTable('configuraciones_sistema')) {
            DB::table('configuraciones_sistema')->whereIn('clave', array_column($this->filas(), 'clave'))->delete();
            Cache::forget('configuraciones_sistema_globales');
        }

        Permission::where('name', 'gelia_ai.gestionar_acceso')->where('guard_name', 'web')->delete();
    }
};

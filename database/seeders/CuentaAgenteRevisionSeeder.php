<?php

namespace Database\Seeders;

use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CuentaAgenteRevisionSeeder extends Seeder
{
    public const EMAIL = 'agente.revision@gelia.local';

    public const USERNAME = 'agente.revision';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('CuentaAgenteRevisionSeeder no se ejecuta en production.');
        }

        $contrasena = config('entorno_prueba.agente_password');
        if (! is_string($contrasena) || trim($contrasena) === '') {
            throw new RuntimeException('Defina AGENTE_REVISION_PASSWORD para crear la cuenta de revisión.');
        }

        $rol = Role::findOrCreate('Super Admin', 'web');

        $usuario = User::withTrashed()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Agente de revisión',
                'username' => self::USERNAME,
                'password' => $contrasena,
                'telefono' => '0000000000',
                'fecha_nacimiento' => '1990-01-01',
            ]
        );

        if ($usuario->trashed()) {
            $usuario->restore();
        }

        $usuario->syncRoles([$rol]);
        $usuario->syncPermissions(Permission::all());

        $sucursales = Sucursal::query()
            ->where('activo', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($sucursales !== []) {
            $usuario->sincronizarSucursalesAsignadas($sucursales, $sucursales[0]);
        }

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => 'gelia_ai.acceso_modo'],
            [
                'valor' => 'super_admin',
                'tipo' => 'string',
                'grupo' => 'Gelia AI',
                'descripcion' => 'Quién puede usar el chat: general | usuarios | super_admin',
            ]
        );

        Cache::forget('configuraciones_sistema_globales');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermisosActualesSeeder extends Seeder
{
    public const CATALOGO = 'seeders/data/permisos_actuales.php';

    public static function rutaCatalogo(): string
    {
        return database_path(self::CATALOGO);
    }

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('PermisosActualesSeeder no se ejecuta en production.');
        }

        $nombres = self::nombres();

        foreach ($nombres as $nombre) {
            Permission::findOrCreate($nombre, 'web');
        }

        $rol = Role::findOrCreate('Super Admin', 'web');
        $rol->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    public static function nombres(): array
    {
        $ruta = self::rutaCatalogo();
        if (! is_file($ruta)) {
            throw new RuntimeException('No existe el catálogo de permisos. Ejecute db:volcado-entorno-prueba.');
        }

        $nombres = require $ruta;
        if (! is_array($nombres)) {
            throw new RuntimeException('El catálogo de permisos no es una lista.');
        }

        $limpios = [];
        foreach ($nombres as $nombre) {
            $nombre = trim((string) $nombre);
            if ($nombre !== '') {
                $limpios[] = $nombre;
            }
        }

        return $limpios;
    }
}

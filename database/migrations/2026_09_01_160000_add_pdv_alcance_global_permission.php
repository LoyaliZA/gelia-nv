<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISO = 'pdv.alcance.global';

    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(self::PERMISO);
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::where('name', self::PERMISO)->delete();
    }
};

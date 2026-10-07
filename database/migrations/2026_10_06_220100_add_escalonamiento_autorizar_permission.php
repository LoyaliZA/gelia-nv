<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(['escalonamiento.autorizar']);
    }

    public function down(): void
    {
        Permission::where('name', 'escalonamiento.autorizar')->delete();
    }
};

<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar('control_pedidos.cedis.ver_todos');
    }

    public function down(): void
    {
        Permission::query()->where('name', 'control_pedidos.cedis.ver_todos')->delete();
    }
};

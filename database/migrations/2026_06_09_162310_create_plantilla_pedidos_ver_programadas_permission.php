<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar('plantilla_pedidos.ver_programadas');
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::where('name', 'plantilla_pedidos.ver_programadas')->delete();
    }
};

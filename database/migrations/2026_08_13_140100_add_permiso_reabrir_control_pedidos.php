<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar('control_pedidos.reabrir');
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::where('name', 'control_pedidos.reabrir')->delete();
    }
};

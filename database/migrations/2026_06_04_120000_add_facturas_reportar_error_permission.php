<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar('facturas.reportar_error');
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::where('name', 'facturas.reportar_error')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('solicitudes.cancelar');
    }

    public function down(): void
    {
        Permission::where('name', 'solicitudes.cancelar')->delete();
    }
};

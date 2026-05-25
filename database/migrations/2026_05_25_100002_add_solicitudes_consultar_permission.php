<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('solicitudes.consultar');
    }

    public function down(): void
    {
        Permission::where('name', 'solicitudes.consultar')->delete();
    }
};

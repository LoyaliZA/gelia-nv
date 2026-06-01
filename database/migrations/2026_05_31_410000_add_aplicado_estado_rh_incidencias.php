<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE rh_incidencias MODIFY estado_deduccion ENUM('pendiente', 'programado', 'aplicado') NOT NULL DEFAULT 'pendiente'");
        }

        Permission::findOrCreate('rh.incidencias.aplicar', 'web');
    }

    public function down(): void
    {
        DB::table('rh_incidencias')->where('estado_deduccion', 'aplicado')->update(['estado_deduccion' => 'programado']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE rh_incidencias MODIFY estado_deduccion ENUM('pendiente', 'programado') NOT NULL DEFAULT 'pendiente'");
        }
    }
};
